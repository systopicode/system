<?php
/** The standalone project path must work even without the CMS package autoloader. */
require dirname(__DIR__) . '/src/Sys/ThirdParty.php';
class fs {
    public static string $siteRoot = '';
    public static string $projectRoot = '';
    public static function toNative(string $value): string { return $value; }
}
$check = static function (bool $ok, string $name): void { if (!$ok) throw new RuntimeException($name); echo "ok: $name\n"; };
$names = static fn() => array_column(\Systopic\System\Sys\ThirdParty::entries(), 'name');
$check(!str_contains(implode(' ', $names()), 'OpenSKP'), 'no false viewer detection');
$dir = sys_get_temp_dir() . '/fluxo-viewer-notices-' . bin2hex(random_bytes(6));
mkdir($dir . '/public/js', 0755, true);
try {
    file_put_contents($dir . '/public/js/skp-viewer.mjs', '// fixture only');
    fs::$projectRoot = $dir;
    $all = implode(' ', $names());
    foreach (['Three.js 0.185.0', 'OpenSKP 1.0.0', 'Earcut 3.2.3', 'fflate 0.8.3'] as $component) $check(str_contains($all, $component), 'standalone project credit: ' . $component);
    $check(str_contains($all, 'FFmpeg / FFprobe'), 'project-local media tools covered');
} finally {
    unlink($dir . '/public/js/skp-viewer.mjs');rmdir($dir . '/public/js');rmdir($dir . '/public');rmdir($dir);
}
