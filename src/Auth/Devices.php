<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use DateTimeImmutable;
use Systopic\Db\Scope;
use Systopic\System\Queries\Devices\Devices as DeviceQuery;
use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\Devices\Model as Device;
use Systopic\System\Tables\Devices\Operator;
use Systopic\System\Tables\Users\Model as User;

/**
 * The devices a user is logged in on (`Tables\Devices`), and what keeps them
 * logged in: a cookie `<device id>.<secret>`, good for long, next to the
 * PHP session, which is short. When the session is gone, `restore()` logs
 * the device's user in again, silently.
 *
 * While a session lives, `check()` looks at its device now and then: a
 * device revoked elsewhere (another device, a user admin) ends the session
 * within `CHECK_EVERY` seconds.
 */
final class Devices
{
    public const CHECK_EVERY = 60;
    public const TOUCH_EVERY = 300;
    public const COOKIE_DAYS = 400;   // the browsers' cap; the row decides

    private const SESSION_KEY = 'systopic_device';

    private static ?Device $current = null;

    /** `__Host-` binds the cookie to this host, https and path / - not possible over http (DEV without TLS). */
    public static function cookieName(): string
    {
        // __Host- needs the path /, so projects under paths of one host
        // tell their cookies apart by name (see http::projectCookieKey())
        $key = \http::projectCookieKey();
        return (self::https() ? '__Host-sys_device' : 'sys_device') . ($key === null ? '' : '_' . $key);
    }

    /**
     * afterConnect, every request: a session with a device is checked now and
     * then; without a session, the device cookie logs its user in again.
     */
    public static function restore(): void
    {
        if (PHP_SAPI === 'cli' || Session::isRecovery()) {
            return;
        }
        // a page's heartbeat (header X-Session-Check): checked now, answered
        // with nothing but whether it is still logged in - a kiosk nobody
        // touches learns of its revocation within a minute
        $probe = isset($_SERVER['HTTP_X_SESSION_CHECK']);
        try {
            if (Session::known()) {
                self::check($probe);
            } else {
                self::restoreFromCookie();
            }
        } catch (\Throwable $e) {
            error_log('[devices] restore: ' . $e->getMessage());
        }
        if ($probe) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode(['loggedIn' => Session::known()]);
            exit;
        }
    }

    /** No session: the device cookie logs its user in again. */
    private static function restoreFromCookie(): void
    {
        [$id, $secret] = self::cookie() ?? [0, ''];
        if ($id === 0) {
            return;
        }
        $scope = Scope::default();
        $now = new DateTimeImmutable();
        $device = DeviceQuery::byId($scope, $id);
        $verdict = $device === null ? Operator::WRONG : Operator::verdict($device, $secret, $now);
        if ($verdict === Operator::REUSED) {
            // a secret replaced more than a minute ago came back: the
            // cookie was copied - nobody gets in with it any more
            $scope->save(Operator::revoked($device, 'reuse', $now));
            error_log("[devices] device $id revoked: a rotated secret came back");
        }
        if ($verdict !== Operator::OK && $verdict !== Operator::GRACED) {
            if ($verdict === Operator::EXPIRED && ($user = UserLookup::byId($scope, (int) $device->relationId('user'))) !== null) {
                LoginLog::write((string) $user->name, 'logged out', LoginLog::EXPIRED);
            }
            self::clearCookie();
            return;
        }
        Session::login((int) $device->relationId('user'));
        Session::clearPasskeyGrant();
        if (!Session::known()) {
            return;
        }
        LoginLog::write((string) Session::current()->name, 'logged in', LoginLog::DEVICE);
        if ($verdict === Operator::OK && Operator::dueForRotation($device, $now)) {
            $secret = bin2hex(random_bytes(32));
            Operator::rotated($device, $secret, $now);
            self::setCookie($device, $secret);
        }
        self::remember($device, $now);
        $scope->save(self::touch($device, $now));
    }

    /**
     * After a login: the browser's own device, if its cookie still belongs to
     * this user. A cookie we cannot trust is a new row. The QR cannot say
     * which device will scan it, so the cookie is the only reliable sign.
     */
    public static function issue(User $user, ?string $client = null): Device
    {
        $client ??= self::displayed() ?? 'browser';
        $scope = Scope::default();
        $now = new DateTimeImmutable();
        $cookie = self::cookie();
        if ($cookie !== null) {
            [$oldId, $oldSecret] = $cookie;
            $old = DeviceQuery::byId($scope, $oldId);
            if ($old !== null) {
                $verdict = Operator::verdict($old, $oldSecret, $now);
                $sameUser = (int) $old->relationId('user') === (int) $user->id;
                if ($sameUser && ($verdict === Operator::OK || $verdict === Operator::GRACED)) {
                    return self::reconnect($old, $oldSecret, $verdict, $user, $client, $now, $scope);
                }
                // A revoked row stays revoked: whoever still holds that cookie
                // must not be let back in through it. A wrong secret proves
                // nothing, so that row is left alone.
                if ($verdict === Operator::REUSED) {
                    $scope->save(Operator::revoked($old, 'reuse', $now));
                } elseif ($verdict !== Operator::WRONG && $verdict !== Operator::REVOKED) {
                    $scope->save(Operator::revoked($old, 'replaced', $now));
                }
            }
        }
        // a read first: writing provisions nothing, and the very first device
        // of an installation needs usm_devices to exist
        DeviceQuery::ofUser($scope, (int) $user->id);
        $secret = bin2hex(random_bytes(32));
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $device = Operator::issued(
            $user, $secret, DeviceName::from($userAgent), $client, $userAgent, self::ip(),
            self::expiresFor($user, $now), $now,
        );
        $scope->save($device);
        self::setCookie($device, $secret);
        self::remember($device, $now);
        // The next page is a fresh sign-in. It does not advertise the install
        // banner; a later visit of the CMS still can.
        self::markPlainLanding();
        return $device;
    }

    /** The same browser signed in again. One row, the name the user gave it stays. */
    private static function reconnect(
        Device $device,
        string $secret,
        string $verdict,
        User $user,
        string $client,
        DateTimeImmutable $now,
        Scope $scope,
    ): Device {
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $generated = DeviceName::from((string) $device->userAgent);
        $device->userAgent = mb_substr($userAgent, 0, 255);
        if ($device->name === null || trim($device->name) === '' || $device->name === $generated) {
            $device->name = mb_substr(DeviceName::from($userAgent), 0, 100);
        }
        $device->client = $client;
        Operator::touched($device, self::ip(), self::expiresFor($user, $now), $now);
        // The grace secret is the previous one. It cannot be written back
        // (only its hash is stored), so it is replaced.
        if ($verdict === Operator::GRACED || Operator::dueForRotation($device, $now)) {
            $secret = bin2hex(random_bytes(32));
            Operator::rotated($device, $secret, $now);
        }
        $scope->save($device);
        self::setCookie($device, $secret);
        self::remember($device, $now);
        return $device;
    }

    /** The device of this request's session, if it has one. */
    public static function current(): ?Device
    {
        if (self::$current !== null) {
            return self::$current;
        }
        $id = (int) (self::session()['id'] ?? 0);
        return self::$current = $id > 0 ? DeviceQuery::byId(Scope::default(), $id) : null;
    }

    /** Log this device out: revoked, cookie gone. The session is Session's. */
    public static function logoutHere(): void
    {
        $device = self::current();
        if ($device !== null && $device->revokedAt === null) {
            Scope::default()->save(Operator::revoked($device, 'logout', new DateTimeImmutable()));
        }
        self::forget();
        self::clearCookie();
    }

    /** Revoke one device - from its own list or by a user admin. */
    public static function revoke(Device $device, string $reason): void
    {
        if ($device->revokedAt === null) {
            Scope::default()->save(Operator::revoked($device, $reason, new DateTimeImmutable()));
        }
        if ($device->id === self::current()?->id) {
            self::forget();
            self::clearCookie();
        }
        self::signalLogout((int) $device->id);
    }

    /**
     * With systopic/relay: the revoked device hears "logout" in its channel,
     * and its open pages go to the login now - not at their next request
     * (a CMS page has no heartbeat, the app's is once a minute). Without
     * relay this does nothing.
     */
    private static function signalLogout(int ...$deviceIds): void
    {
        if ($deviceIds === [] || !class_exists(\Systopic\Relay\Relay::class) || !\Systopic\Relay\Relay::enabled()) {
            return;
        }
        foreach ($deviceIds as $id) {
            \Systopic\Relay\Relay::tell(\Systopic\Relay\Relay::deviceTopic($id), ['op' => 'logout']);
        }
    }

    /** Every device of a user but `$exceptId` ("log out everywhere else"). */
    public static function revokeAllOf(int $userId, ?int $exceptId, string $reason): int
    {
        $scope = Scope::default();
        $now = new DateTimeImmutable();
        $revoked = [];
        foreach (DeviceQuery::ofUser($scope, $userId) as $device) {
            if ($device->id !== $exceptId) {
                $revoked[] = Operator::revoked($device, $reason, $now);
            }
        }
        if ($revoked !== []) {
            $scope->save(...$revoked);
            self::signalLogout(...array_map(static fn(Device $device) => (int) $device->id, $revoked));
        }
        return count($revoked);
    }

    // ------------------------------------------------------------ internal

    /** A live session: is its device still good? Ends the session when not. */
    private static function check(bool $force = false): void
    {
        $state = self::session();
        $id = (int) ($state['id'] ?? 0);
        if ($id === 0) {
            return;   // logged in without a device (CMS, other projects)
        }
        $now = new DateTimeImmutable();
        if (!$force && $now->getTimestamp() - (int) ($state['checked'] ?? 0) < self::CHECK_EVERY) {
            return;
        }
        $scope = Scope::default();
        $device = DeviceQuery::byId($scope, $id);
        if ($device === null || $device->revokedAt !== null || ($device->expiresAt !== null && $device->expiresAt < $now)) {
            $expired = $device !== null && $device->revokedAt === null;
            LoginLog::write((string) Session::current()->name, 'logged out', $expired ? LoginLog::EXPIRED : LoginLog::REVOKED);
            self::forget();
            self::clearCookie();
            Session::logout(false);
            return;
        }
        self::$current = $device;
        $touch = $now->getTimestamp() - (int) ($state['touched'] ?? 0) >= self::TOUCH_EVERY;
        self::remember($device, $now, $touch ? null : (int) ($state['touched'] ?? 0));
        if ($touch) {
            $scope->save(self::touch($device, $now));
        }
    }

    private static function touch(Device $device, DateTimeImmutable $now): Device
    {
        $user = Session::current()->user;
        if (($displayed = self::displayed()) !== null && $displayed !== $device->client) {
            Operator::reported($device, $displayed === 'app');
        }
        return Operator::touched($device, self::ip(), $user !== null ? self::expiresFor($user, $now) : $device->expiresAt, $now);
    }

    /**
     * How the page runs, as its script reports it in the cookie `sys_display`
     * (display-mode standalone = an installed web app): 'app', 'browser', or
     * null when no script said so.
     */
    private static function displayed(): ?string
    {
        $display = $_COOKIE['sys_display'] ?? null;
        return in_array($display, ['app', 'browser'], true) ? $display : null;
    }

    private static function expiresFor(User $user, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return Operator::expiresAt($user->type, AuthConfig::deviceDays(), AuthConfig::screenDays(), $now);
    }

    /** @return array{0: int, 1: string}|null the cookie's id and secret */
    private static function cookie(): ?array
    {
        $raw = (string) ($_COOKIE[self::cookieName()] ?? '');
        if (!preg_match('~^(\d+)\.([0-9a-f]{64})$~', $raw, $m)) {
            return null;
        }
        return [(int) $m[1], $m[2]];
    }

    private static function setCookie(Device $device, string $secret): void
    {
        self::sendCookie($device->id . '.' . $secret, time() + self::COOKIE_DAYS * 86_400);
    }

    private static function clearCookie(): void
    {
        if (isset($_COOKIE[self::cookieName()])) {
            self::sendCookie('', time() - 3600);
        }
    }

    private static function sendCookie(string $value, int $expires): void
    {
        if (!headers_sent()) {
            setcookie(self::cookieName(), $value, [
                'expires'  => $expires,
                'path'     => '/',
                'secure'   => self::https(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        if ($value === '') {
            unset($_COOKIE[self::cookieName()]);
        } else {
            $_COOKIE[self::cookieName()] = $value;
        }
    }

    /** @return array{id?: int, checked?: int, touched?: int} */
    private static function session(): array
    {
        \http::sessionStartOnce();
        $state = $_SESSION[self::SESSION_KEY] ?? [];
        return is_array($state) ? $state : [];
    }

    private static function remember(Device $device, DateTimeImmutable $now, ?int $touched = null): void
    {
        \http::sessionStartOnce();
        $_SESSION[self::SESSION_KEY] = ['id' => (int) $device->id, 'checked' => $now->getTimestamp(), 'touched' => $touched ?? $now->getTimestamp()];
        self::$current = $device;
    }

    private static function forget(): void
    {
        \http::sessionStartOnce();
        unset($_SESSION[self::SESSION_KEY]);
        self::$current = null;
    }

    private static function ip(): ?string
    {
        return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;
    }

    /** One response without the web-app manifest, so a QR sign-in does not ask to install the CMS. */
    private static function markPlainLanding(): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie('sys_plain', '1', [
            'expires' => time() + 600,
            'path' => '/',
            'secure' => self::https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function https(): bool
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}
