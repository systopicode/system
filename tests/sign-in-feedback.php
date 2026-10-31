<?php
declare(strict_types=1);
namespace Systopic\System\Auth {
    class Session {
        public static bool $known = false;
        public static function known(): bool { return self::$known; }
        public static function current(): object { return (object) ['fullname'=>'Test <User>', 'name'=>'fixture', 'user'=>(object) ['id'=>42,'email'=>'fixture@example.test','type'=>'person']]; }
    }
    class Portrait { public static function src(int $id, ?string $email, bool $screen): string { return '/fixture-portrait.png'; } }
}
namespace {
    require __DIR__ . '/bootstrap.php';
    class http { public static string $root = '/'; }
    use Systopic\System\Auth\Session;
    use Systopic\System\Auth\SignInFeedback;
    check('anonymous response contains no profile', SignInFeedback::profile() === []);
    Session::$known = true;
    check('authenticated profile uses account name and shared portrait', SignInFeedback::profile() === ['screen'=>false, 'name'=>'Test <User>', 'portrait'=>'/fixture-portrait.png']);
    ob_start(); include dirname(__DIR__) . '/src/Auth/signed-in.tpl.php'; $html = ob_get_clean();
    check('success escapes name and shows portrait with 2 seconds', str_contains($html, 'Test &lt;User&gt;') && str_contains($html, 'src="/fixture-portrait.png"') && str_contains($html, 'Redirecting in 2 seconds') && str_contains($html, 'data-success-continue') && str_contains($html, 'target="_self"'));
    $authSuccessHidden = true;
    ob_start(); include dirname(__DIR__) . '/src/Auth/signed-in.tpl.php'; $html = ob_get_clean();
    check('hidden pre-login state contains no account data', !str_contains($html, 'Test &lt;User&gt;') && !str_contains($html, '/fixture-portrait.png'));
}
