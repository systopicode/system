<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use DateTimeImmutable;
use DomainException;
use Systopic\Db\Scope;
use Systopic\Db\Type\Text;
use Systopic\System\Queries\Passkeys\Passkeys as Keys;
use Systopic\System\Queries\AuthFailures\AuthFailures;
use Systopic\System\Tables\AuthFailures\Operator as Failures;
use Systopic\System\Tables\Passkeys\Model as Key;
use Systopic\System\Tables\Users\Model as User;
use lbuchs\WebAuthn\WebAuthn;

/** WebAuthn verification plus the existing ORM, password and session services. */
final class Passkeys
{
    /** Use the framework's resolved request URL, never an Origin/Forwarded header as configuration. */
    public static function config(): array
    {
        $config = AuthConfig::block()['passkeys'] ?? [];
        return self::resolveConfig(is_array($config) ? $config : [], (string) (\http::$hostUrl ?? ''));
    }

    /** Explicit origin remains an optional deployment override. */
    public static function resolveConfig(array $config, string $requestOrigin): array
    {
        if (trim((string) ($config['origin'] ?? '')) === '') $config['origin'] = $requestOrigin;
        return self::validateConfig($config);
    }

    public static function validateConfig(array $config): array
    {
        $origin = rtrim((string) ($config['origin'] ?? ''), '/');
        $url = parse_url($origin);
        $host = strtolower((string) ($url['host'] ?? ''));
        $rpId = strtolower((string) ($config['rpId'] ?? $host));
        if ($host === '' || $rpId === '' || isset($url['user']) || isset($url['pass'])
            || isset($url['query']) || isset($url['fragment']) || !empty($url['path'])
            || !(($url['scheme'] ?? '') === 'https' || (($url['scheme'] ?? '') === 'http' && $host === 'localhost'))
            || ($host !== $rpId && !str_ends_with($host, '.' . $rpId))
            || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            throw new DomainException('Passkeys need HTTPS and a domain name. Sign in with your password when using an IP address.');
        }
        return ['origin' => $origin, 'rpId' => $rpId];
    }

    /** WebAuthn scopes domains; our credential rows additionally scope the project path. */
    public static function project(): string
    {
        return hash('sha256', self::config()['origin'] . '/' . trim((string) \http::$root, '/'));
    }

    public static function label(): string
    {
        $settings = AuthConfig::block()['passkeys'] ?? [];
        $path = trim((string) \http::$root, '/');
        $parts = explode('/', $path);
        if (end($parts) === 'public') array_pop($parts);
        $name = trim((string) ($settings['name'] ?? end($parts)));
        $host = (string) parse_url(self::config()['origin'], PHP_URL_HOST);
        $stage = \Systopic\System\Config\Config::current()?->instance->stage ?? '';
        return implode(' · ', array_filter([$name ?: 'Fluxo CMS', $stage, $host]));
    }

    public static function suggestedName(): string
    {
        return mb_substr(self::label() . ' · ' . DeviceName::from((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 100);
    }

    public static function available(): bool
    {
        try {
            self::config();
            return class_exists(WebAuthn::class);
        } catch (DomainException) {
            return false;
        }
    }

    public static function unavailableReason(): string
    {
        try { self::config(); }
        catch (DomainException $error) {
            $origin = (string) (\http::$hostUrl ?? '');
            $host = trim((string) parse_url($origin, PHP_URL_HOST), '[]');
            if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                return 'Passkeys do not work with IP addresses, even over HTTPS. Open the CMS using a domain name with a trusted HTTPS certificate. You can still sign in with your password here.';
            }
            if (parse_url($origin, PHP_URL_SCHEME) !== 'https' && $host !== 'localhost') {
                return 'Passkeys require a secure HTTPS connection. Open the CMS using HTTPS with a trusted certificate. You can still sign in with your password here.';
            }
            return $error->getMessage();
        }
        return 'Passkey support is not installed on this installation.';
    }

    /** Display/offer check only; credential requests still require the exact Origin header. */
    public static function onConfiguredOrigin(?string $origin = null): bool
    {
        return rtrim($origin ?? (PHP_SAPI === 'cli' ? self::config()['origin'] : (string) \http::$hostUrl), '/') === self::config()['origin'];
    }

    public static function needsSetup(User $user): bool
    {
        return self::available() && (bool) $user->isActive && $user->type !== User::SCREEN
            && Keys::ofUser(Scope::default(), (int) $user->id, self::project()) === [];
    }

    /** Password login has just verified this account; no password is stored. */
    public static function offerSetup(User $user): bool
    {
        if (!self::needsSetup($user) || !self::onConfiguredOrigin()) { unset($_SESSION['systopic_passkey_offer']); return false; }
        $_SESSION['systopic_passkey_offer'] = ['user' => (int) $user->id, 'project' => self::project(), 'expires' => time() + 300];
        return true;
    }

    public static function setupPending(): bool
    {
        $offer = $_SESSION['systopic_passkey_offer'] ?? null;
        return is_array($offer) && Session::active() && $offer['user'] === Session::id()
            && $offer['expires'] > time() && self::available() && $offer['project'] === self::project();
    }

    public static function completeSetup(): string
    {
        unset($_SESSION['systopic_passkey_offer']);
        return Session::takeReturn() ?? \http::$root . 'cms/';
    }

    public static function render(User $user): string
    {
        \http::sessionStartOnce();
        $passkeyUser = $user;
        ob_start();
        include __DIR__ . '/passkeys.tpl.php';
        return (string) ob_get_clean();
    }

    /** Raw JSON actions use the existing auth/profile routes, never a second login service. */
    public static function respond(string $action, bool $login = false): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        try {
            \http::sessionStartOnce();
            $config = self::config();
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                throw new DomainException('This passkey request was redirected or expired. Reload the sign-in page and try again.');
            }
            if (($_SERVER['HTTP_ORIGIN'] ?? '') !== $config['origin']) {
                throw new DomainException('Passkeys require ' . $config['origin'] . '. Open the CMS using that address, or sign in with your password.');
            }
            if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
                throw new DomainException('This passkey request has an invalid format. Reload the sign-in page and try again.');
            }
            PasskeyChallenge::checkToken((string) ($_SERVER['HTTP_X_FLUXO_PASSKEY'] ?? ''));
            if (!self::available()) {
                throw new DomainException('Passkeys are not configured on this installation.');
            }
            $raw = file_get_contents('php://input', false, null, 0, 131073);
            if ($raw === false || strlen($raw) > 131072) {
                throw new DomainException('Invalid passkey response.');
            }
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new DomainException('Invalid passkey response.');
            }
            $scope = Scope::default();
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            $failures = AuthFailures::of($scope, $ip);
            if (Failures::blocked($failures, new DateTimeImmutable())) {
                throw new DomainException('Too many attempts. Please try again later.');
            }
            try {
                $result = self::execute($action, $data, $login);
            } catch (\Throwable $e) {
                $scope->save(Failures::failed($failures, $ip, new DateTimeImmutable()));
                throw $e;
            }
            echo json_encode($result, JSON_THROW_ON_ERROR | JSON_HEX_TAG);
        } catch (DomainException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \logroute('Passkey request failed', $e->getMessage());
            error_log('[auth] ' . $action . ' failed: ' . get_class($e) . ' (code ' . $e->getCode() . ')');
            http_response_code(400);
            echo json_encode(['error' => 'The passkey could not be verified. Please try again.']);
        }
        exit;
    }

    public static function execute(string $action, array $data, bool $login): array
    {
        $scope = Scope::default();
        $user = Session::current()->user;
        if ($login) {
            if (Session::known() || !in_array($action, ['passkeyBeginLogin', 'passkeyFinishLogin', 'passkeyCancel'], true)) {
                throw new DomainException('Invalid sign-in request.');
            }
        } elseif (!in_array($action, ['passkeyBeginCreate', 'passkeyFinishCreate', 'passkeyRename', 'passkeyRemove', 'passkeyCancel'], true)) {
            throw new DomainException('Invalid passkey action.');
        } elseif ($user === null || !$user->isActive || $user->type === User::SCREEN) {
            throw new DomainException('Sign in to your own account first.');
        }
        if ($action === 'passkeyCancel') {
            if (!empty($data['ceremony'])) PasskeyChallenge::cancel((string) $data['ceremony']);
            return ['ok' => true];
        }
        $config = self::config();
        $web = new WebAuthn('Fluxo CMS', $config['rpId'], ['none'], true);
        if ($action === 'passkeyBeginLogin') {
            $name = trim((string) ($data['username'] ?? ''));
            $discovery = $name === '';
            if (strlen($name) > 254) {
                throw new DomainException('Enter your email first.');
            }
            $account = $name === '' ? null : \Systopic\System\Queries\UserLookup\UserLookup::byName($scope, $name);
            $keys = $account?->isActive && $account->type !== User::SCREEN
                ? Keys::ofUser($scope, (int) $account->id, self::project()) : [];
            // An unknown account gets an indistinguishable random allow-list entry.
            $ids = array_map(static fn(Key $key): string => self::decode($key->credentialId->value), $keys);
            $options = $web->getGetArgs($discovery ? [] : ($ids ?: [random_bytes(32)]), 120, true, true, true, true, true, 'required');
            $ceremony = PasskeyChallenge::issue('login', null, $web->getChallenge()->getBinaryString(),
                ['user' => $account?->id, 'discovery' => $discovery, 'project' => self::project()]);
            return ['options' => $options->publicKey, 'ceremony' => $ceremony];
        }
        if ($action === 'passkeyFinishLogin') {
            $challenge = PasskeyChallenge::take('login', null, self::ceremony($data));
            $id = self::decode((string) ($data['id'] ?? ''));
            $lock = 'passkey-' . substr(hash('sha256', (string) $scope->connection->database . ':' . $id), 0, 48);
            if ((int) $scope->connection->value('SELECT GET_LOCK(?, 5)', [$lock]) !== 1) {
                throw new DomainException('This passkey is busy. Please try again.');
            }
            try {
                // Read and update the signature counter under the same connection lock.
                $key = Keys::byCredential($scope, $id);
                if ($key === null || $key->rpId !== $config['rpId'] || $key->project !== self::project()
                    || $challenge['data']['project'] !== self::project()
                    || (!($challenge['data']['discovery'] ?? false) && $key->relationId('user') !== $challenge['data']['user'])
                    || (($challenge['data']['discovery'] ?? false) && empty($data['userHandle'])) || !$key->user?->isActive
                    || $key->user->type === User::SCREEN
                    || !hash_equals($key->credentialId->value, self::encode($id))
                    || (!empty($data['userHandle']) && !hash_equals(hex2bin($key->userHandle), self::decode((string) $data['userHandle'])))) {
                    throw new DomainException('The passkey could not be verified.');
                }
                self::verifyAssertion($web, $key, $data, $challenge['challenge']);
                $key->signCount = $web->getSignatureCounter() ?? 0;
                $key->lastUsedAt = new DateTimeImmutable();
                $scope->save($key);
            } finally {
                $scope->connection->value('SELECT RELEASE_LOCK(?)', [$lock]);
            }
            Session::login($key->user);
            LoginLog::write((string) $key->user->name, 'logged in', LoginLog::PASSKEY);
            return ['redirect' => Session::takeReturn() ?? \http::$root . 'cms/'];
        }
        if ($user === null || $login) {
            throw new DomainException('Invalid passkey request.');
        }
        $target = $user;
        $targetId = (int) ($data['userId'] ?? $user->id);
        if ($targetId !== (int) $user->id) {
            if ($action !== 'passkeyRemove' || !Session::isSuperuser()) {
                throw new DomainException('Only the account owner can add or change passkeys.');
            }
            $target = \Systopic\System\Queries\UserLookup\UserLookup::byId($scope, $targetId);
            if ($target === null) {
                throw new DomainException('This account no longer exists.');
            }
        }
        if ($action === 'passkeyBeginCreate') {
            $onboarding = self::setupPending();
            if (!$onboarding) self::confirmPassword($user, $data);
            else $_SESSION['systopic_passkey_offer']['expires'] = max($_SESSION['systopic_passkey_offer']['expires'], time() + 120);
            $keys = Keys::ofUser($scope, (int) $user->id, self::project());
            if (count($keys) >= 20) {
                throw new DomainException('Remove an old passkey before adding another.');
            }
            $handle = $keys[0]->userHandle ?? bin2hex(random_bytes(32));
            $options = $web->getCreateArgs(hex2bin($handle), (string) ($user->email ?: $user->name) . ' · ' . self::label(),
                self::label() . ' · ' . (string) ($user->fullname ?: $user->name), 120, true, 'required', null,
                array_map(static fn(Key $k): string => self::decode($k->credentialId->value), $keys));
            $ceremony = PasskeyChallenge::issue('create', (int) $user->id, $web->getChallenge()->getBinaryString(),
                ['handle' => $handle, 'onboarding' => $onboarding, 'project' => self::project(), 'name' => self::name($data['name'] ?? 'Passkey')]);
            return ['options' => $options->publicKey, 'ceremony' => $ceremony];
        }
        if ($action === 'passkeyFinishCreate') {
            $challenge = PasskeyChallenge::take('create', (int) $user->id, self::ceremony($data));
            if ($challenge['data']['project'] !== self::project()) {
                throw new DomainException('This passkey request belongs to another project.');
            }
            $client = self::clientData($data);
            $verified = $web->processCreate($client, self::decode((string) ($data['attestationObject'] ?? '')),
                $challenge['challenge'], true, true);
            if (Keys::byCredential($scope, $verified->credentialId) !== null) {
                throw new DomainException('This passkey is already registered.');
            }
            $key = new Key();
            $key->user = $user;
            $key->project = self::project();
            $key->credentialHash = hash('sha256', $verified->credentialId);
            $key->credentialId = new Text(self::encode($verified->credentialId));
            $key->publicKey = new Text($verified->credentialPublicKey);
            $key->rpId = $config['rpId'];
            $key->userHandle = $challenge['data']['handle'];
            $key->name = $challenge['data']['name'];
            $key->signCount = $verified->signatureCounter ?? 0;
            $key->createdAt = new DateTimeImmutable();
            $scope->save($key);
            if ($challenge['data']['onboarding'] ?? false) return ['redirect' => self::completeSetup()];
            return ['html' => self::render($user)];
        }
        if (in_array($action, ['passkeyRename', 'passkeyRemove'], true)) {
            self::confirmPassword($user, $data);
            foreach (Keys::ofUser($scope, (int) $target->id, self::project()) as $key) {
                if ((int) $key->id !== (int) ($data['keyId'] ?? 0)) {
                    continue;
                }
                $signal = null;
                if ($action === 'passkeyRemove') {
                    $signal = ['rpId' => $key->rpId, 'credentialId' => $key->credentialId->value];
                    $scope->remove($key);
                } else {
                    $key->name = self::name($data['name'] ?? '');
                    $scope->save($key);
                }
                return ['html' => self::render($target), 'removedCredential' => $signal];
            }
            throw new DomainException('This passkey no longer exists.');
        }
        throw new DomainException('Unknown passkey action.');
    }

    private static function ceremony(array $data): string
    {
        $ticket = (string) ($data['ceremony'] ?? '');
        if (preg_match('~^[a-f0-9]{32}$~D', $ticket) !== 1) {
            throw new DomainException('This passkey request expired. Please try again.');
        }
        return $ticket;
    }

    private static function confirmPassword(User $user, array $data): void
    {
        if (!Password::verify($user->passwordHash, (string) ($data['password'] ?? ''))) {
            throw new DomainException('Your current password is incorrect.');
        }
    }

    private static function name(mixed $name): string
    {
        $name = trim((string) $name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new DomainException('Use a passkey name of 1 to 100 characters.');
        }
        return $name;
    }

    /** Require the exact configured origin in addition to the library's RP check. */
    public static function clientData(array $data): string
    {
        $raw = self::decode((string) ($data['clientDataJSON'] ?? ''));
        $client = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($client) || ($client['origin'] ?? '') !== self::config()['origin']
            || ($client['crossOrigin'] ?? false) !== false || isset($client['topOrigin'])) {
            throw new DomainException('Invalid passkey origin.');
        }
        return $raw;
    }

    private static function verifyAssertion(WebAuthn $web, Key $key, array $data, string $challenge): void
    {
        $web->processGet(self::clientData($data), self::decode((string) ($data['authenticatorData'] ?? '')),
            self::decode((string) ($data['signature'] ?? '')), $key->publicKey->value,
            $challenge, $key->signCount, true, true);
    }

    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function decode(string $value): string
    {
        if ($value === '' || strlen($value) > 120000 || preg_match('~^[A-Za-z0-9_-]+$~D', $value) !== 1) {
            throw new DomainException('Invalid passkey response.');
        }
        $bytes = base64_decode(strtr($value, '-_', '+/'), true);
        if ($bytes === false || self::encode($bytes) !== $value) {
            throw new DomainException('Invalid passkey response.');
        }
        return $bytes;
    }
}
