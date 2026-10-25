<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require getenv('FLUXO_WEBAUTHN_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';

use Systopic\System\Auth\Passkeys;
use Systopic\System\Auth\PasskeyChallenge as Challenge;
use Systopic\System\Auth\Session;
use Systopic\System\Auth\Password;
use Systopic\System\Config\Config;
use Systopic\System\Queries\Passkeys\Passkeys as Keys;
use Systopic\System\Tables\Users\Model as User;
use Systopic\Db\Connection;
use Systopic\Db\Scope;

// Minimal web boundary for standalone service tests, no project bootstrap.
class http { public static string $root = '/'; public static string $hostUrl = 'https://cms.example.test'; public static function redirect(string $target): never { throw new TestRedirect($target); } public static function sessionStartOnce(): void {} public static function put($key, $default = '') { return $default; } }
class TestRedirect extends RuntimeException {}
class fs { public static string $root; }
class message { public static function flush($targets = [], bool $keepEmpty = false): string { return $keepEmpty ? '<div class="cms message" data-system-messages role="status" aria-live="polite"></div>' : ''; } }
$_SESSION = [];

check('new installations derive origin and RP domain without passkey configuration', Passkeys::resolveConfig([], 'https://new.example.test') === ['origin'=>'https://new.example.test','rpId'=>'new.example.test']);
check('alternate HTTPS hosts each derive their own RP domain', Passkeys::resolveConfig([], 'https://staging.example.test')['rpId'] === 'staging.example.test');
check('optional fixed-origin override stays supported', Passkeys::resolveConfig(['origin'=>'https://fixed.example.test'], 'https://other.example.test')['origin'] === 'https://fixed.example.test');
rejects('automatic configuration rejects IPv6-based passkeys', fn()=>Passkeys::resolveConfig([], 'https://[::1]'));
rejects('automatic configuration rejects IP-based passkeys', fn()=>Passkeys::resolveConfig([], 'https://192.168.1.2'));
check('valid HTTPS config', Passkeys::validateConfig(['origin' => 'https://cms.example.test'])['rpId'] === 'cms.example.test');
foreach (['http://example.test', 'https://example.test/path', 'https://badexample.test', 'https://127.0.0.1'] as $origin) {
    rejects('reject invalid origin ' . $origin, fn() => Passkeys::validateConfig(['origin' => $origin, 'rpId' => 'example.test']));
}
check('local development origin', Passkeys::validateConfig(['origin' => 'http://localhost:8123'])['rpId'] === 'localhost');
$token = Challenge::token();
Challenge::checkToken($token);
rejects('CSRF mismatch', fn() => Challenge::checkToken('wrong'));
foreach (["\0\xff", random_bytes(64), 'hello'] as $bytes) check('base64url round trip', Passkeys::decode(Passkeys::encode($bytes)) === $bytes);
foreach (['', 'a', 'AA=', 'A!'] as $bad) rejects('reject malformed base64url', fn() => Passkeys::decode($bad));
Challenge::issue('create', 2, 'secret');
rejects('challenge belongs to one user', fn() => Challenge::take('create', 3));
rejects('failed challenge cannot be replayed', fn() => Challenge::take('create', 2));
Challenge::issue('login', null, 'secret');
check('correct challenge', Challenge::take('login', null)['challenge'] === 'secret');
rejects('challenge is single use', fn() => Challenge::take('login', null));
Challenge::issue('login', null, 'secret');
$_SESSION['systopic_passkey_challenge'][array_key_last($_SESSION['systopic_passkey_challenge'])]['expires'] = time() - 1;
rejects('expired challenge', fn() => Challenge::take('login', null));
Challenge::issue('login', null, 'secret');
rejects('challenge purpose mismatch', fn() => Challenge::take('create', null));

$tabA=Challenge::issue('login',null,'A'); $tabB=Challenge::issue('login',null,'B');
check('two tabs keep independent autofill ceremonies',Challenge::take('login',null,$tabA)['challenge']==='A' && Challenge::take('login',null,$tabB)['challenge']==='B');
$tabA=Challenge::issue('login',null,'A'); $tabB=Challenge::issue('login',null,'B'); Challenge::cancel($tabA);
check('cancelling one tab preserves another tab challenge',Challenge::take('login',null,$tabB)['challenge']==='B');

$dsn = getenv('FLUXO_TEST_DSN') ?: throw new RuntimeException('Set FLUXO_TEST_DSN to an isolated MySQL database named fluxo_test_…');
$connection = Connection::create($dsn, getenv('FLUXO_TEST_USER') ?: 'root', getenv('FLUXO_TEST_PASSWORD') ?: '');
check('isolated test database', str_starts_with((string) $connection->value('SELECT DATABASE()'), 'fluxo_test_'));
$connection->database = (string) $connection->value('SELECT DATABASE()');
Connection::setDefault($connection);
$scope = new Scope($connection);
Scope::setDefault($scope);
$work = sys_get_temp_dir() . '/fluxo-passkey-test-' . bin2hex(random_bytes(6));
mkdir($work);
fs::$root = $work;
file_put_contents($work . '/config.json', json_encode(['instances' => ['DEV' => ['auth' => ['passkeys' => ['origin' => 'https://cms.example.test']]]]]));
Config::boot($work, [], $work, 'DEV');
check('passkeys available with library and explicit origin', Passkeys::available());
check('configured host is eligible for setup', Passkeys::onConfiguredOrigin('https://cms.example.test'));
check('IP access is not eligible for domain-bound passkeys', !Passkeys::onConfiguredOrigin('https://192.168.1.2'));
check('alternate domain and plain HTTP cannot offer setup', !Passkeys::onConfiguredOrigin('https://other.example.test') && !Passkeys::onConfiguredOrigin('http://cms.example.test'));

// Real OpenSSL signatures and CBOR fixtures exercise the production verifier.
$private = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
$details = openssl_pkey_get_details($private);
$id = random_bytes(32);
$cose = hex2bin('a5010203262001215820') . $details['ec']['x'] . hex2bin('225820') . $details['ec']['y'];
$bytes = static fn(string $s): string => (strlen($s) < 24 ? chr(0x40 + strlen($s)) : (strlen($s) <= 255 ? "\x58" . chr(strlen($s)) : "\x59" . pack('n', strlen($s)))) . $s;
$client = static fn(string $challenge, string $type, string $origin = 'https://cms.example.test', bool $crossOrigin = false): string => Passkeys::encode(json_encode(['type' => $type, 'origin' => $origin, 'challenge' => $challenge, 'crossOrigin' => $crossOrigin]));
$createResponse = static function (array $options, int $flags = 0x45, string $rp = 'cms.example.test') use ($id, $cose, $bytes, $client): array {
    $auth = hash('sha256', $rp, true) . chr($flags) . pack('N', 0) . str_repeat("\0", 16) . pack('n', strlen($id)) . $id . $cose;
    $attestation = "\xa3\x63fmt\x64none\x67attStmt\xa0\x68authData" . $bytes($auth);
    return ['ceremony' => $options['_ceremony'], 'id' => Passkeys::encode($id), 'clientDataJSON' => $client((string) $options['challenge'], 'webauthn.create'), 'attestationObject' => Passkeys::encode($attestation)];
};
$assertResponse = static function (array $options, string $handle, int $counter, int $flags = 0x05, string $rp = 'cms.example.test') use ($id, $private, $client): array {
    $json = $client((string) $options['challenge'], 'webauthn.get');
    $auth = hash('sha256', $rp, true) . chr($flags) . pack('N', $counter);
    openssl_sign($auth . hash('sha256', Passkeys::decode($json), true), $signature, $private, OPENSSL_ALGO_SHA256);
    return ['ceremony' => $options['_ceremony'], 'id' => Passkeys::encode($id), 'userHandle' => Passkeys::encode(hex2bin($handle)),
        'clientDataJSON' => $json, 'authenticatorData' => Passkeys::encode($auth), 'signature' => Passkeys::encode($signature)];
};
$optionsArray = static fn(array $result): array => json_decode(json_encode($result['options']), true) + ['_ceremony' => $result['ceremony']];
\Systopic\System\Queries\UserLookup\UserLookup::byId($scope, 2);
$existing = Keys::ofUser($scope, 2); // empty installations create usm_passkeys through the existing provisioner
check('ORM provisions credentials table', is_array($existing));
$user = new User();
$user->name = 'passkey-test-' . bin2hex(random_bytes(4));
$user->isActive = true;
$user->passwordHash = Password::hash('Example!234');
$scope->save($user);
if ((int) $user->id === 1) {
    $reserved = $user;
    $user = new User(); $user->name = 'passkey-person'; $user->isActive = true; $user->passwordHash = Password::hash('Example!234');
    $scope->save($user);
}
$other = new User(); $other->name = 'passkey-other'; $other->isActive = true; $other->passwordHash = Password::hash('Other!234'); $scope->save($other);
$as = static function (?User $row): void { if ($row) $_SESSION['systopic_user_id'] = (int) $row->id; else unset($_SESSION['systopic_user_id']); Session::reset(); };
$as($user);
rejects('registration requires current password', fn() => Passkeys::execute('passkeyBeginCreate', ['password' => 'wrong'], false));
$beginCreate = static fn(): array => $optionsArray(Passkeys::execute('passkeyBeginCreate', ['password' => 'Example!234', 'name' => 'My laptop'], false));
$options = $beginCreate();
check('registration requires resident key and user verification', $options['authenticatorSelection']['residentKey'] === 'required' && $options['authenticatorSelection']['userVerification'] === 'required');
rejects('registration rejects missing UV flag', fn() => Passkeys::execute('passkeyFinishCreate', $createResponse($options, 0x41), false));
$options = $beginCreate();
rejects('registration rejects wrong RP hash', fn() => Passkeys::execute('passkeyFinishCreate', $createResponse($options, 0x45, 'other.test'), false));
$options = $beginCreate();
$badOrigin = $createResponse($options); $badOrigin['clientDataJSON'] = $client($options['challenge'], 'webauthn.create', 'https://other.example.test');
rejects('registration requires exact origin', fn() => Passkeys::execute('passkeyFinishCreate', $badOrigin, false));
$options = $beginCreate();
$created = Passkeys::execute('passkeyFinishCreate', $createResponse($options), false);
if ($fixture = getenv('FLUXO_TEST_HTML')) file_put_contents($fixture, $created['html']);
check('verified credential persisted and shared component rendered', str_contains($created['html'], 'My laptop'));
$key = Keys::byCredential($scope, $id);
check('credential is scoped to this project', $key->project === Passkeys::project());
check('password-manager display name prioritizes the project', str_starts_with($options['user']['displayName'], Passkeys::label()));
check('password-manager label includes project and stage', str_contains($options['user']['name'], Passkeys::label()));
check('stored credential belongs to registering user', $key->relationId('user') === (int) $user->id);
rejects('registration cannot be replayed', fn() => Passkeys::execute('passkeyFinishCreate', $createResponse($options), false));
$options = $beginCreate();
rejects('credential cannot be registered twice', fn() => Passkeys::execute('passkeyFinishCreate', $createResponse($options), false));
$as(null);
$scopedOptions = $optionsArray(Passkeys::execute('passkeyBeginLogin', ['username' => $user->name], true));
http::$root = '/other-project/';
rejects('same-host project cannot finish another project ceremony', fn() => Passkeys::execute('passkeyFinishLogin', $assertResponse($scopedOptions, $key->userHandle, 1), true));
$otherProjectOptions = $optionsArray(Passkeys::execute('passkeyBeginLogin', ['username' => $user->name], true));
check('same-host project does not offer another project credential', $otherProjectOptions['allowCredentials'][0]['id'] !== Passkeys::encode($id));
rejects('same-host project cannot use a copied credential', fn() => Passkeys::execute('passkeyFinishLogin', $assertResponse($otherProjectOptions, $key->userHandle, 1), true));
http::$root = '/';
$as($other);
rejects('other user cannot remove credential', fn() => Passkeys::execute('passkeyRemove', ['password' => 'Other!234', 'keyId' => $key->id], false));
check('foreign account has no registration controls', !str_contains(Passkeys::render($user), 'data-passkey-mode="create"'));
$other->isSuperuser = true; $scope->save($other); $as($other);
$adminKey = new \Systopic\System\Tables\Passkeys\Model();
foreach (['project','credentialId','publicKey','rpId','userHandle','name','createdAt'] as $property) $adminKey->$property = $key->$property;
$adminId = random_bytes(32); $adminKey->credentialHash = hash('sha256', $adminId); $adminKey->credentialId = new \Systopic\Db\Type\Text(Passkeys::encode($adminId)); $adminKey->user = $user;
$scope->save($adminKey);
check('superuser sees remove controls for another account', str_contains(Passkeys::render($user), 'data-passkey-mode="remove"'));
rejects('superuser cannot rename foreign passkeys', fn() => Passkeys::execute('passkeyRename', ['userId'=>$user->id,'keyId'=>$key->id,'password'=>'Other!234','name'=>'Foreign'], false));
rejects('superuser cannot register a passkey for another account', fn() => Passkeys::execute('passkeyBeginCreate', ['userId'=>$user->id,'password'=>'Other!234','name'=>'Foreign'], false));
rejects('admin deletion rejects the target account password', fn() => Passkeys::execute('passkeyRemove', ['userId'=>$user->id,'keyId'=>$adminKey->id,'password'=>'Example!234'], false));
$removed = Passkeys::execute('passkeyRemove', ['userId'=>$user->id,'keyId'=>$adminKey->id,'password'=>'Other!234'], false);
check('revocation returns a manager signal only after successful removal', $removed['removedCredential'] === ['rpId'=>$adminKey->rpId,'credentialId'=>Passkeys::encode($adminId)]);
check('superuser revokes foreign passkey using own password', Keys::byCredential($scope,$adminId) === null && str_contains($removed['html'], 'data-passkey-user-id="'.$user->id.'"'));
$other->isSuperuser = false; $scope->save($other);
$as($user);
$renamed = Passkeys::execute('passkeyRename', ['password' => 'Example!234', 'keyId' => $key->id, 'name' => '<Laptop>'], false);
check('names are escaped', str_contains($renamed['html'], '&lt;Laptop&gt;'));
$as(null);
$beginLogin = static fn(): array => $optionsArray(Passkeys::execute('passkeyBeginLogin', ['username' => $user->name], true));
$options = $beginLogin();
$badHandle = $assertResponse($options, bin2hex(random_bytes(32)), 1);
rejects('assertion requires matching user handle', fn() => Passkeys::execute('passkeyFinishLogin', $badHandle, true));
$options = $beginLogin();
$badSig = $assertResponse($options, $key->userHandle, 1); $badSig['signature'] = Passkeys::encode(random_bytes(64));
rejects('invalid signature rejected', fn() => Passkeys::execute('passkeyFinishLogin', $badSig, true));
$options = $beginLogin();
rejects('authentication requires user verification', fn() => Passkeys::execute('passkeyFinishLogin', $assertResponse($options, $key->userHandle, 1, 1), true));
$options = $beginLogin();
$good = $assertResponse($options, $key->userHandle, 1);
$result = Passkeys::execute('passkeyFinishLogin', $good, true);
check('verified assertion logs into existing session', Session::id() === (int) $user->id && $result['redirect'] === '/cms/');
check('signature counter and last use persisted', $key->signCount === 1 && $key->lastUsedAt !== null);
$as(null);
$options = $beginLogin();
rejects('signature counter rollback rejected', fn() => Passkeys::execute('passkeyFinishLogin', $assertResponse($options, $key->userHandle, 1), true));
$explicitDiscovery = $optionsArray(Passkeys::execute('passkeyBeginLogin', [], true));
check('explicit passkey button works without an email', empty($explicitDiscovery['allowCredentials']));
$discovery = $optionsArray(Passkeys::execute('passkeyBeginLogin', ['conditional'=>true], true));
check('autofill starts without an email or modal prompt', empty($discovery['allowCredentials']));
$missingHandle=$assertResponse($discovery,$key->userHandle,2); $missingHandle['userHandle']='';
rejects('discoverable login requires a user handle', fn()=>Passkeys::execute('passkeyFinishLogin',$missingHandle,true));
$discovery=$optionsArray(Passkeys::execute('passkeyBeginLogin',['conditional'=>true],true));
http::$root='/another-project/';
rejects('autofill cannot authenticate a different project credential', fn()=>Passkeys::execute('passkeyFinishLogin',$assertResponse($discovery,$key->userHandle,2),true));
http::$root='/';
$discovery=$optionsArray(Passkeys::execute('passkeyBeginLogin',['conditional'=>true],true));
Passkeys::execute('passkeyFinishLogin',$assertResponse($discovery,$key->userHandle,2),true);
check('autofill authenticates the credential owner in this project', Session::id()===(int)$user->id);
$as(null);
if($loginFixture=getenv('FLUXO_TEST_LOGIN_HTML')) { ob_start(); include dirname(__DIR__).'/panels/cms/auth/login.tpl.php'; file_put_contents($loginFixture,ob_get_clean()); }
$options = $beginLogin();
$user->isActive = false; $scope->save($user);
rejects('inactive user cannot log in', fn() => Passkeys::execute('passkeyFinishLogin', $assertResponse($options, $key->userHandle, 2), true));
$user->isActive = true; $scope->save($user); $as($user);
Passkeys::execute('passkeyRemove', ['password' => 'Example!234', 'keyId' => $key->id], false);
check('owner can remove credential', Keys::byCredential($scope, $id) === null);
check('password login offers setup to an account without keys', Passkeys::offerSetup($user) && Passkeys::setupPending());
if ($setupFixture = getenv('FLUXO_TEST_ONBOARDING_HTML')) { ob_start(); include dirname(__DIR__).'/panels/cms/auth/passkeySetup.tpl.php'; file_put_contents($setupFixture, ob_get_clean()); }
http::$root = '/different/';
check('fresh login proof is project bound', !Passkeys::setupPending());
http::$root = '/';
$_SESSION['systopic_passkey_offer']['expires'] = time() - 1;
check('login proof expires', !Passkeys::setupPending());
rejects('expired login proof requires password again', fn() => Passkeys::execute('passkeyBeginCreate', ['name'=>'Expired'], false));
Passkeys::offerSetup($user);
// Ordinary continuation is safe even after proof expiry, from an IP, and without WebAuthn headers.
Passkeys::offerSetup($user);
$_SESSION['systopic_passkey_offer']['expires'] = time() - 1;
http::$hostUrl = 'https://192.168.1.2';
$_SERVER['HTTP_ORIGIN'] = http::$hostUrl;
$_SERVER['REQUEST_METHOD'] = 'GET';
Session::rememberReturn('/cms/pages/?id=43');
$continuation = new class { public string $action='continueWithoutPasskey'; public string $form=''; public function updateView($view): void {} };
$continue = function (): void { include dirname(__DIR__).'/panels/cms/auth/actions.php'; };
try { $continue->call($continuation); check('continuation redirects', false); }
catch (TestRedirect $redirect) { check('IP access with expired setup proof can continue through ordinary navigation', $redirect->getMessage()==='/cms/pages/?id=43' && !Passkeys::setupPending()); }
$as(null); $continue->call($continuation);
check('expired session returns to normal login without a passkey error', $continuation->form==='login');
$as($user); http::$hostUrl='https://cms.example.test';
unset($_SERVER['HTTP_ORIGIN'],$_SERVER['REQUEST_METHOD']);

Passkeys::offerSetup($user);
$options = $optionsArray(Passkeys::execute('passkeyBeginCreate', ['name'=>'Fresh login'], false));
$created = Passkeys::execute('passkeyFinishCreate', $createResponse($options), false);
check('fresh password login allows setup without entering password twice', $created['redirect'] === '/cms/' && !Passkeys::setupPending());
check('accounts with a key are not prompted again', !Passkeys::offerSetup($user));
$scope->remove(...Keys::ofUser($scope, (int)$user->id));
$scope->remove($other, $user, ...(isset($reserved) ? [$reserved] : []));
echo "All passkey checks passed.\n";
