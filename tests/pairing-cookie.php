<?php
declare(strict_types=1);
namespace Systopic\System\Auth {
    class LoginCodes {
        public const SPENT = 'spent';
        public static string $error = '';
        public static array $polled = [];
        public static function available(): bool { return true; }
        public static function pollScreen(string $poll): mixed { self::$polled[] = $poll; self::$error = 'invalid'; return null; }
        public static function cancelScreenPairing(string $poll): void {}
        public static function startWaiting(string $purpose): array { return ['code'=>'fixture', 'poll'=>str_repeat((string) count(self::$polled), 64)]; }
    }
}
namespace {
    require __DIR__ . '/bootstrap.php';
    class http {
        public static string $root = '/';
        public static function projectCookieKey(): ?string { return self::$root === '/' ? null : substr(md5(self::$root), 0, 10); }
        public static function sessionStartOnce(): void {}
    }
    use Systopic\System\Auth\ScreenPair;
    use Systopic\System\Auth\LoginCodes;
    ob_start(); // cookies remain writable while checks print
    $_COOKIE = ['sys_pair'=>'legacy-root-secret']; $_SESSION = [];
    http::$root = '/project-a/'; $a = ScreenPair::cookieName(); $_COOKIE[$a] = 'secret-a';
    http::$root = '/project-b/'; $b = ScreenPair::cookieName(); $_COOKIE[$b] = 'secret-b';
    check('projects have different pairing cookie names', $a !== $b);
    http::$root = '/project-a/'; ScreenPair::begin('/project-a/');
    check('project A reads only its own polling secret', LoginCodes::$polled === ['secret-a']);
    check('project A writes without replacing project B or root cookie', $_COOKIE[$b] === 'secret-b' && $_COOKIE['sys_pair'] === 'legacy-root-secret' && $_COOKIE[$a] !== 'secret-a');
    http::$root = '/project-b/'; ScreenPair::begin('/project-b/');
    check('project B reads only its own polling secret', LoginCodes::$polled === ['secret-a', 'secret-b']);
    $clear = new ReflectionMethod(ScreenPair::class, 'cookie'); $clear->invoke(null, '', time()-3600);
    check('clearing project B leaves project A intact', $_COOKIE[$a] !== '' && $_COOKIE[$b] === '');
    http::$root = '/project-c/'; ScreenPair::begin('/project-c/');
    check('new project never borrows ambiguous legacy cookie', LoginCodes::$polled === ['secret-a', 'secret-b']);
    http::$root = '/'; check('root installations keep existing cookie name', ScreenPair::cookieName() === 'sys_pair');
    ob_end_flush();
}
