<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
define('STAGE', 'DEV');
class http { public static string $root = '/'; public static function get($k,$default=''){return $default;} public static function post($k,$default=''){return $default;} }
class message { public static function flush(...$args): string { return ''; } }
use Systopic\System\Tables\Users\Model;
$render = function (): string {
    ob_start(); include dirname(__DIR__) . '/panels/admin/auth/setPassword.tpl.php'; return ob_get_clean();
};
foreach (['missing', 'expired', 'consumed', 'valid'] as $state) {
    $user = null;
    if ($state !== 'missing') {
        $user = new Model();
        $user->activationKey = $state === 'consumed' ? null : 'fixture';
        $user->activationKeyExpire = new DateTimeImmutable($state === 'expired' ? '-1 minute' : '+1 hour');
    }
    $context = new class($user) { public function __construct(public ?Model $keyUser) {} };
    $html = $render->call($context);
    check("$state: password fields only with a valid key", str_contains($html, 'name="password"') === ($state === 'valid'));
    check("$state: invalid key offers resend", str_contains($html, 'Resend key') === ($state !== 'valid'));
}
