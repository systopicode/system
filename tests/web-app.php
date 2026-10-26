<?php
/** Standalone manifest regression: no project bootstrap, database or session. */
declare(strict_types=1);
namespace {
    class fs {
        public static string $siteRoot;
        public static string $sysRoot;
        public static function toNative(string $path): string { return $path; }
        public static function toInternal(string $path): string { return $path; }
    }
    class http { public static string $root = '/project/public/'; }
    class httpHelper {
        public static function filepathToHref(string $path): string {
            return str_starts_with($path, fs::$siteRoot)
                ? http::$root . substr($path, strlen(fs::$siteRoot))
                : '/assets/sys/' . substr($path, strlen(fs::$sysRoot));
        }
    }
}
namespace Systopic\System\Http {
    class Assets {
        public static function href(string $href, string $path): string { return $href . '?t=' . filemtime($path); }
    }
}
namespace {
    require dirname(__DIR__) . '/src/Sys/Packages.php';
    require dirname(__DIR__) . '/src/Sys/Theme.php';
    require dirname(__DIR__) . '/src/Sys/WebApp.php';
    use Systopic\System\Sys\WebApp;
    function expect(bool $ok, string $message): void {
        if (!$ok) throw new RuntimeException($message);
        echo "ok: $message\n";
    }
    fs::$sysRoot = dirname(__DIR__) . '/';
    fs::$siteRoot = sys_get_temp_dir() . '/fluxo-pwa-' . bin2hex(random_bytes(5)) . '/';
    mkdir(fs::$siteRoot . 'overrides/css', 0777, true);
    mkdir(fs::$siteRoot . 'overrides/img/favicons', 0777, true);
    try {
        define('PUBLIC_APP_NAME', 'SP'); // Public website branding must not name the CMS install.
        expect(WebApp::name() === 'fluxo cms', 'CMS install defaults to fluxo, independent of the public website');
        define('CMS_PAGE_TITLE_OVERRIDE', 'Studio CMS');
        expect(WebApp::name() === 'Studio CMS' && WebApp::shortName() === 'Studio CMS', 'configured CMS name is used by both install labels');
        file_put_contents(fs::$siteRoot . 'overrides/css/theme.css', ':root { --cmsMainColor: #123456; }');
        $icon = fs::$siteRoot . 'overrides/img/favicons/android-chrome-192x192.png';
        copy(fs::$sysRoot . 'public/img/favicons/android-chrome-192x192.png', $icon);
        $manifest = (new ReflectionMethod(WebApp::class, 'manifest'))->invoke(null);
        expect($manifest['theme_color'] === '#123456', 'manifest respects theme overrides');
        expect(str_starts_with($manifest['icons'][0]['src'], '/project/public/cms/icon-'), 'manifest icons stay inside the app scope');
        expect(!str_contains($manifest['icons'][0]['src'], '?'), 'icon addresses have no query, or iOS drops them');
        expect(count($manifest['icons']) === 4, 'standard and maskable icons remain available');
        expect($manifest['start_url'] === '/project/public/cms/' && $manifest['scope'] === '/project/public/cms/', 'installed app opens within the CMS scope');
        expect(str_contains(WebApp::href(), '?v='), 'manifest discovery invalidates old metadata');
    } finally {
        foreach (glob(fs::$siteRoot . 'overrides/css/*') ?: [] as $file) unlink($file);
        foreach (glob(fs::$siteRoot . 'overrides/img/favicons/*') ?: [] as $file) unlink($file);
        rmdir(fs::$siteRoot . 'overrides/img/favicons'); rmdir(fs::$siteRoot . 'overrides/img');
        rmdir(fs::$siteRoot . 'overrides/css'); rmdir(fs::$siteRoot . 'overrides'); rmdir(fs::$siteRoot);
    }
}
