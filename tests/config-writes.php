<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use Systopic\System\Config\FileWriter;
use Systopic\System\Settings\ProjectFile;
use Systopic\System\Settings\ProjectJson;

class fs { public static string $siteRoot; }
class fsUploader {
    public static ?array $fixture = null;
    public static function postedFile(string $name): ?array { return self::$fixture; }
}
if (($argv[1] ?? '') === 'theme-worker') {
    fs::$siteRoot = $argv[2];
    exit(\Systopic\System\Settings\ThemeFile::save($argv[3], $argv[4]) ? 0 : 1);
}

// Each child uses the real settings API in a separate PHP process.
if (($argv[1] ?? '') === 'worker') {
    class_exists(ProjectFile::class); // Group/Leaf value objects share this source file.
    exit(ProjectJson::setField($argv[2], 'call:mediaFormats::add', $argv[3], 'label', 'saved-' . $argv[3]) ? 0 : 1);
}
$root = sys_get_temp_dir() . '/fluxo-config-write-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
try {
    $path = $root . '/media.settings.json';
    $rows = [];
    for ($i = 0; $i < 16; $i++) $rows['format' . $i] = ['width' => 800, 'height' => 600, 'label' => 'before'];
    check('initial JSON written atomically', ProjectJson::put($path, ['formats' => $rows, 'untouched' => ['secret' => 'fixture']]));
    chmod($path, 0600);
    $children = [];
    for ($i = 0; $i < 16; $i++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $path, 'format' . $i], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not launch worker');
        fclose($pipes[0]);
        $children[] = [$process, $pipes];
    }
    foreach ($children as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('Worker failed: ' . $output);
    }
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    foreach ($rows as $key => $row) check('concurrent save retained: ' . $key, $data['formats'][$key]['label'] === 'saved-' . $key);
    check('unknown data preserved', $data['untouched']['secret'] === 'fixture');
    clearstatcache(true, $path);
    check('file permissions preserved', (fileperms($path) & 0777) === 0600);
    file_put_contents($path, '{broken');
    check('invalid JSON retained on edit', ProjectJson::setField($path, 'call:mediaFormats::add', 'format0', 'label', 'oops') === null && file_get_contents($path) === '{broken');
    fs::$siteRoot = $root;
    mkdir($root . '/overrides/css', 0700, true);
    $theme = \Systopic\System\Settings\ThemeFile::path();
    $legacyTheme = dirname($theme) . '/zz_cms_theme.css';
    file_put_contents($legacyTheme, ':root{--cmsMainColor:#004b93;}');
    check('legacy theme is loaded before migration', \Systopic\System\Settings\ThemeFile::stylesheet() === $legacyTheme);
    $fields = array_keys(\Systopic\System\Settings\ThemeFile::FIELDS);
    file_put_contents($theme, ':root {' . implode('', array_map(fn($key) => '--'.$key.': #112233;', $fields)) . '}');
    check('new theme takes precedence over legacy', \Systopic\System\Settings\ThemeFile::stylesheet() === $theme);
    $children = [];
    foreach ($fields as $i => $key) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, 'theme-worker', $root, $key, 'abcdef'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        fclose($pipes[0]); $children[] = [$process, $pipes];
    }
    foreach ($children as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException($output);
    }
    foreach ($fields as $key) check('concurrent theme colour retained: '.$key, str_contains(file_get_contents($theme), '--'.$key.': #abcdef;'));
    check('theme rejects invalid colour', !\Systopic\System\Settings\ThemeFile::save('cmsMainColor', 'invalid'));
    check('theme reset succeeds', \Systopic\System\Settings\ThemeFile::resetColors());
    $php = $root . '/page.settings.php';
    file_put_contents($php, "<?php\ndefine('PUBLIC_PAGE_NAME', 'Original');\n// Keep this comment\n");
    $payload = "Quote ' and \\; <?php exit;";
    check('legacy PHP safely quotes values', ProjectFile::replace($php, 'define:PUBLIC_PAGE_NAME', $payload) !== null);
    token_get_all(file_get_contents($php), TOKEN_PARSE);
    check('legacy comments preserved', str_contains(file_get_contents($php), '// Keep this comment'));
    if (extension_loaded('gd')) {
        $image = imagecreatetruecolor(8, 4);
        $source = $root . '/source.png'; imagepng($image, $source);
        $raster = new ReflectionMethod(\Systopic\System\Settings\ThemeAssets::class, 'writeRaster');
        check('theme raster published as PNG', $raster->invoke(null, $source, $root.'/asset.png', false) && getimagesize($root.'/asset.png')[2] === IMAGETYPE_PNG);
        check('theme raster published as JPEG', $raster->invoke(null, $source, $root.'/asset.jpg', true) && getimagesize($root.'/asset.jpg')[2] === IMAGETYPE_JPEG);
        if (function_exists('imagewebp') && function_exists('imagecreatefromwebp')) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
            $webp = $root . '/transparent.webp';
            imagewebp($image, $webp, IMG_WEBP_LOSSLESS);
            $mime = new ReflectionMethod(\Systopic\System\Settings\ThemeAssets::class, 'mime');
            check('theme detects WebP', $mime->invoke(null, $webp) === 'image/webp');
            check('theme converts WebP through existing raster pipeline', $raster->invoke(null, $webp, $root.'/webp.png', false));
            $decoded = imagecreatefrompng($root.'/webp.png');
            check('theme WebP transparency retained', imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0))['alpha'] === 127);
            imagedestroy($decoded);
            fsUploader::$fixture = ['tmp_name' => $webp, 'uploader' => true];
            foreach (['wordDark', 'wordLight', 'signet', 'favicon'] as $slot) {
                check('public theme upload accepts WebP: '.$slot, \Systopic\System\Settings\ThemeAssets::store($slot) === null);
                check('theme picker advertises WebP: '.$slot, str_contains(\Systopic\System\Settings\ThemeAssets::SLOTS[$slot]['accept'], 'image/webp'));
            }
            fsUploader::$fixture = null;

        }
        $square = new ReflectionMethod(\Systopic\System\Settings\ThemeAssets::class, 'pngSquare');
        check('favicon generated atomically', $square->invoke(null, $image, 32, $root.'/favicon.png') && getimagesize($root.'/favicon.png')[0] === 32);
        imagedestroy($image);
    }
    symlink($path, $root . '/link.json');
    check('atomic writer refuses symlink destinations', !FileWriter::write($root . '/link.json', '{}'));
    mkdir($root . '/directory');
    check('failed publication leaves no temporary file', !FileWriter::write($root . '/directory', 'test') && glob($root . '/.fluxo-config-*') === []);
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) { if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname()); else unlink($entry->getPathname()); }
    rmdir($root);
}
