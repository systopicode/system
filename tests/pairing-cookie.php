<?php
declare(strict_types=1);
namespace Systopic\System\Auth {
    class SignInCodes {
        public const SPENT = 'spent';
        public static string $error = '';
        public static array $polled = [];
        public static function available(): bool { return true; }
        public static function pollDeviceCode(string $poll): mixed { self::$polled[] = $poll; self::$error = 'invalid'; return null; }
        public static function cancelDeviceCode(string $poll): void {}
        public static function startDeviceCode(): array { return ['code'=>'fixture', 'poll'=>str_repeat((string) count(self::$polled), 64)]; }
    }
}
namespace {
    require __DIR__ . '/bootstrap.php';
    class http {
        public static string $root = '/';
        public static function projectCookieKey(): ?string { return self::$root === '/' ? null : substr(md5(self::$root), 0, 10); }
        public static function sessionStartOnce(): void {}
    }
    use Systopic\System\Auth\DeviceCode;
    use Systopic\System\Auth\SignInCodes;
    ob_start(); // cookies remain writable while checks print
    $_COOKIE = ['sys_device_code'=>'legacy-root-secret']; $_SESSION = [];
    http::$root = '/project-a/'; $a = DeviceCode::cookieName(); $_COOKIE[$a] = 'secret-a';
    http::$root = '/project-b/'; $b = DeviceCode::cookieName(); $_COOKIE[$b] = 'secret-b';
    check('projects have different pairing cookie names', $a !== $b);
    http::$root = '/project-a/'; DeviceCode::begin();
    check('project A reads only its own polling secret', SignInCodes::$polled === ['secret-a']);
    check('project A writes without replacing project B or root cookie', $_COOKIE[$b] === 'secret-b' && $_COOKIE['sys_device_code'] === 'legacy-root-secret' && $_COOKIE[$a] !== 'secret-a');
    http::$root = '/project-b/'; DeviceCode::begin();
    check('project B reads only its own polling secret', SignInCodes::$polled === ['secret-a', 'secret-b']);
    $clear = new ReflectionMethod(DeviceCode::class, 'cookie'); $clear->invoke(null, '', time()-3600);
    check('clearing project B leaves project A intact', $_COOKIE[$a] !== '' && $_COOKIE[$b] === '');
    http::$root = '/project-c/'; DeviceCode::begin();
    check('new project never borrows ambiguous legacy cookie', SignInCodes::$polled === ['secret-a', 'secret-b']);
    http::$root = '/'; check('root installations keep existing cookie name', DeviceCode::cookieName() === 'sys_device_code');
    ob_end_flush();
}
