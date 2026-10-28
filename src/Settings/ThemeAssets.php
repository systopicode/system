<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

use Systopic\System\Panels\PanelNode;
use Systopic\System\Sys\Packages;

/**
 * The images a project replaces by putting a file of the same name in
 * overrides/img/. The theme page shows the one that is actually used.
 */
final class ThemeAssets
{
    /** @var array<string, array{file: string, label: string, hint: string, accept: string, kind: string}> */
    public const SLOTS = [
        'logo' => [
            'file' => 'img/logo.svg',
            'label' => 'Logo',
            'hint' => 'SVG only. On the login card it is at most 2.75rem tall. A wide mark, about 4:1, fits the line.',
            'accept' => '.svg,image/svg+xml',
            'kind' => 'logo',
        ],
        'signet' => [
            'file' => 'img/signet.svg',
            'label' => 'Signet',
            'hint' => 'SVG only. The square mark in the menu, shown at 2.5rem.',
            'accept' => '.svg,image/svg+xml',
            'kind' => 'signet',
        ],
        'wordDark' => [
            'file' => 'img/logo-horizontal-dark.png',
            'label' => 'Word mark, dark bar',
            'hint' => 'PNG, on the dashboard menu. A wide mark, about 5:1.',
            'accept' => '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp',
            'kind' => 'dark',
        ],
        'wordLight' => [
            'file' => 'img/logo-horizontal-light.png',
            'label' => 'Word mark, light',
            'hint' => 'PNG. The login card uses this when there is no logo SVG.',
            'accept' => '.png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp',
            'kind' => 'light',
        ],
        'favicon' => [
            'file' => 'img/icon.svg',
            'label' => 'Favicon',
            'hint' => 'SVG, or a PNG. A PNG is also used for the home-screen icon.',
            'accept' => '.svg,.png,image/svg+xml,image/png',
            'kind' => 'icon',
        ],
        'share' => [
            'file' => 'img/share_default.jpg',
            'label' => 'Share image',
            'hint' => 'JPG or PNG, about 1.9:1. Shown when a CMS page is shared.',
            'accept' => '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp',
            'kind' => 'share',
        ],
    ];

    public static function render(PanelNode $panel): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $href = (string) $panel->href;
        echo '<div class="widgets themeAssets">';
        foreach (self::SLOTS as $slot => $spec) {
            $path = Packages::panelAsset($spec['file']);
            $own = self::isOverride($path);
            if ($slot === 'favicon' && !$own) {
                $png = Packages::panelAsset('img/favicons/favicon-32x32.png');
                $own = self::isOverride($png);
                if ($own) {
                    $path = $png;
                }
            }
            $note = $own ? 'This project' : 'System default';
            if ($spec['kind'] === 'logo' && $path !== null && self::isSvg($path)) {
                $ratio = self::aspect((string) file_get_contents(\fs::toNative($path)));
                if ($ratio !== null) {
                    $note .= ' · ' . $ratio;
                }
            }
            echo '<article class="widget themeAsset ' . $e($spec['kind']) . '">';
            echo '<header><h2>' . $e($spec['label']) . '</h2></header>';
            echo '<main><form method="post" enctype="multipart/form-data" action="' . $e($href . 'upload?slot=' . $slot) . '">';
            echo '<label class="themePreview" data-drop-files>';
            echo self::preview($spec, $path);
            echo '<input type="file" name="file" accept="' . $e($spec['accept']) . '" hidden onchange="this.form.requestSubmit()">';
            echo '</label></form></main>';
            echo '<footer><span class="left">' . $e($note) . '</span>';
            if ($own) {
                echo '<button type="button" class="button" data-on_click="clear" data-slot="' . $e($slot) . '">Use the system image</button>';
            }
            echo '</footer></article>';
        }
        echo '</div>';
    }

    public static function store(string $slot): ?string
    {
        $spec = self::SLOTS[$slot] ?? null;
        $upload = \fsUploader::postedFile('file');
        if ($spec === null || $upload === null) {
            return 'That file was not uploaded.';
        }
        $tmp = (string) ($upload['tmp_name'] ?? '');
        $staged = ($upload['uploader'] ?? null) !== null;
        if ($tmp === '' || !is_file($tmp) || (!$staged && !is_uploaded_file($tmp))) {
            return 'That file was not uploaded.';
        }
        if (filesize($tmp) > 1500000) {
            return 'That file is too large.';
        }
        if ($slot === 'favicon' && self::mime($tmp) === 'image/png') {
            return self::storeFaviconPng($tmp) ? null : 'That image could not be saved.';
        }
        if ($spec['kind'] === 'logo' || $spec['kind'] === 'signet' || ($slot === 'favicon' && self::mime($tmp) === 'image/svg+xml')) {
            $svg = self::cleanSvg((string) file_get_contents($tmp));
            if ($svg === null) {
                return 'Use an SVG without scripts.';
            }
            return self::write(self::overridePath($spec['file']), $svg) ? null : 'That file could not be saved.';
        }
        $dest = self::overridePath($spec['file']);
        $jpg = str_ends_with($spec['file'], '.jpg');
        return self::writeRaster($tmp, $dest, $jpg) ? null : 'That image could not be saved.';
    }

    public static function clear(string $slot): bool
    {
        $spec = self::SLOTS[$slot] ?? null;
        if ($spec === null) {
            return false;
        }
        $gone = false;
        foreach (self::ownedFiles($slot, $spec['file']) as $file) {
            if (is_file($file)) {
                $gone = unlink($file) || $gone;
            }
        }
        return $gone;
    }

    /** @return list<string> */
    private static function ownedFiles(string $slot, string $file): array
    {
        $files = [self::overridePath($file)];
        if ($slot === 'favicon') {
            foreach (['img/favicons/favicon-32x32.png', 'img/favicons/favicon-16x16.png', 'img/favicons/apple-touch-icon.png'] as $name) {
                $files[] = self::overridePath($name);
            }
        }
        return $files;
    }

    private static function storeFaviconPng(string $tmp): bool
    {
        $src = @imagecreatefrompng($tmp);
        if ($src === false) {
            return false;
        }
        $ok = true;
        foreach (['img/favicons/favicon-32x32.png' => 32, 'img/favicons/favicon-16x16.png' => 16, 'img/favicons/apple-touch-icon.png' => 180] as $name => $size) {
            $ok = self::pngSquare($src, $size, self::overridePath($name)) && $ok;
        }
        imagedestroy($src);
        return $ok;
    }

    private static function preview(array $spec, ?string $path): string
    {
        if ($path === null) {
            return '<span class="themeEmpty">None</span>';
        }
        if (self::isSvg($path)) {
            $svg = self::cleanSvg((string) file_get_contents(\fs::toNative($path)));
            if ($svg !== null && preg_match('/<svg\b.*<\/svg>/is', $svg, $drawn) === 1) {
                return $drawn[0];
            }
            return '<span class="themeEmpty">None</span>';
        }
        $href = Packages::panelAssetHref($spec['file']);
        if ($spec['kind'] === 'icon') {
            $png = Packages::panelAsset('img/favicons/favicon-32x32.png');
            if ($png !== null && self::isOverride($png)) {
                $href = Packages::panelAssetHref('img/favicons/favicon-32x32.png');
            }
        }
        return '<img src="' . htmlspecialchars($href, ENT_QUOTES) . '" alt="">';
    }

    private static function isOverride(?string $path): bool
    {
        if ($path === null) {
            return false;
        }
        $root = \fs::toNative(rtrim((string) \fs::$siteRoot, '/\\') . '/overrides/');
        return str_starts_with(\fs::toNative($path), $root);
    }

    private static function overridePath(string $file): string
    {
        return rtrim((string) \fs::$siteRoot, '/\\') . '/overrides/' . $file;
    }

    private static function isSvg(string $path): bool
    {
        return str_ends_with(strtolower($path), '.svg');
    }

    private static function cleanSvg(string $raw): ?string
    {
        if ($raw === '' || strlen($raw) > 200000 || !preg_match('/<svg\b/i', $raw)) {
            return null;
        }
        if (preg_match('/<\s*script\b|<\s*foreignObject\b|javascript\s*:|\son[a-z]+\s*=/i', $raw) === 1) {
            return null;
        }
        return $raw;
    }

    private static function aspect(string $svg): ?string
    {
        if (preg_match('/viewBox\s*=\s*"\s*[\d.+-]+\s+[\d.+-]+\s+([\d.]+)\s+([\d.]+)/i', $svg, $box) !== 1
            && preg_match('/\bwidth\s*=\s*"([\d.]+)[a-z%]*".*?\bheight\s*=\s*"([\d.]+)[a-z%]*"/is', $svg, $box) !== 1) {
            return null;
        }
        $w = (float) $box[1];
        $h = (float) $box[2];
        if ($w <= 0 || $h <= 0) {
            return null;
        }
        return rtrim(rtrim(number_format($w / $h, 1, '.', ''), '0'), '.') . ':1';
    }

    private static function write(string $dest, string $bytes): bool
    {
        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        return file_put_contents($dest, $bytes) !== false;
    }

    private static function writeRaster(string $tmp, string $dest, bool $jpg): bool
    {
        $info = @getimagesize($tmp);
        if ($info === false) {
            return false;
        }
        $img = match ($info[2]) {
            IMAGETYPE_PNG => @imagecreatefrompng($tmp),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
            IMAGETYPE_GIF => @imagecreatefromgif($tmp),
            default => false,
        };
        if ($img === false) {
            return false;
        }
        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            imagedestroy($img);
            return false;
        }
        if ($jpg) {
            $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
            $white = imagecolorallocate($flat, 255, 255, 255);
            imagefilledrectangle($flat, 0, 0, imagesx($img), imagesy($img), $white);
            imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
            $ok = imagejpeg($flat, $dest, 90);
            imagedestroy($flat);
        } else {
            imagesavealpha($img, true);
            $ok = imagepng($img, $dest);
        }
        imagedestroy($img);
        return $ok;
    }

    private static function pngSquare(\GdImage $src, int $size, string $dest): bool
    {
        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $clear = imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefilledrectangle($out, 0, 0, $size, $size, $clear);
        imagealphablending($out, true);
        imagecopyresampled($out, $src, 0, 0, 0, 0, $size, $size, imagesx($src), imagesy($src));
        $ok = imagepng($out, $dest);
        imagedestroy($out);
        return $ok;
    }

    private static function mime(string $tmp): string
    {
        $info = @getimagesize($tmp);
        if (is_array($info) && ($info['mime'] ?? '') === 'image/png') {
            return 'image/png';
        }
        $head = (string) file_get_contents($tmp, false, null, 0, 200);
        return preg_match('/<svg\b/i', $head) === 1 ? 'image/svg+xml' : '';
    }
}
