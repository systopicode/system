<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
class http { public static string $root='/project/'; public static string $assetsMode='direct'; public static function sessionStartOnce(): void {} }
use Systopic\System\Auth\Branding;
foreach ([['/project/about/',true],['/project/admin/pages/',false],['/project/cms',false],['/other/',false],[null,false]] as [$target,$expected]) {
    $_SESSION['systopic_return_to']=$target;
    check('frontend branding destination '.($target??'(none)'),Branding::frontend()===$expected);
}

// Real lookup in disposable frontend roots, without a project bootstrap.
class fs {
    public static string $siteRoot;
    public static string $projectRoot;
    public static function toNative(string $path): string { return $path; }
    public static function toInternal(string $path): string { return $path; }
}
class httpHelper {
    public static function filepathToHref(string $path): string { return '/project/' . substr($path, strlen(fs::$siteRoot)); }
}
$dir = sys_get_temp_dir() . '/fluxo-branding-' . bin2hex(random_bytes(6));
mkdir($dir . '/img/favicon', 0700, true);
fs::$siteRoot = $dir . '/'; fs::$projectRoot = $dir;
$_SESSION['systopic_return_to'] = '/project/about/';
try {
    file_put_contents($dir . '/img/favicon/favicon.svg', '<svg/>');
    file_put_contents($dir . '/img/favicon/favicon-96x96.png', 'fixture');
    check('singular frontend favicon directory', str_starts_with(Branding::href('img/icon.svg') ?? '', '/project/img/favicon/favicon.svg?t='));
    check('frontend PNG size fallback', str_starts_with(Branding::href('img/favicons/favicon-32x32.png') ?? '', '/project/img/favicon/favicon-96x96.png?t='));
    check('missing optional icon stays absent', Branding::href('img/favicons/favicon.ico') === null);
    $_SESSION['systopic_return_to'] = '/project/admin/pages/';
    check('CMS destination never takes frontend icons', Branding::href('img/icon.svg') === null);
} finally {
    unlink($dir . '/img/favicon/favicon.svg'); unlink($dir . '/img/favicon/favicon-96x96.png');
    rmdir($dir . '/img/favicon'); rmdir($dir . '/img'); rmdir($dir);
}
