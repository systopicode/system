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
    /** @var array<string, array{file: string, label: string, hint: string, accept: string, kind: string, png?: string, svg?: string}> */
    public const SLOTS = [
        'logo' => [
            'file' => 'img/logo.svg',
            'png' => 'img/logo.png',
            'label' => 'Logo',
            'hint' => 'SVG, PNG or WebP.',
            'accept' => '.svg,.png,.webp,image/svg+xml,image/png,image/webp',
            'kind' => 'logo',
        ],
        'wordDark' => [
            'file' => 'img/logo-horizontal-dark.png',
            'svg' => 'img/logo-horizontal-dark.svg',
            'label' => 'Logo on dark',
            'hint' => 'SVG, PNG or WebP.',
            'accept' => '.svg,.png,.webp,image/svg+xml,image/png,image/webp',
            'kind' => 'dark',
        ],
        'wordLight' => [
            'file' => 'img/logo-horizontal-light.png',
            'svg' => 'img/logo-horizontal-light.svg',
            'label' => 'Logo on bright',
            'hint' => 'SVG, PNG or WebP.',
            'accept' => '.svg,.png,.webp,image/svg+xml,image/png,image/webp',
            'kind' => 'light',
        ],
        'signet' => [
            'file' => 'img/signet.svg',
            'png' => 'img/signet.png',
            'label' => 'Signet',
            'hint' => 'SVG, PNG or WebP.',
            'accept' => '.svg,.png,.webp,image/svg+xml,image/png,image/webp',
            'kind' => 'signet',
        ],
        'favicon' => [
            'file' => 'img/icon.svg',
            'label' => 'Favicon',
            'hint' => 'SVG, PNG or WebP.',
            'accept' => '.svg,.png,.webp,image/svg+xml,image/png,image/webp',
            'kind' => 'icon',
        ],
        'share' => [
            'file' => 'img/share_default.jpg',
            'label' => 'Share image',
            'hint' => 'JPG.',
            'accept' => '.jpg,.jpeg,image/jpeg',
            'kind' => 'share',
        ],
    ];

    public static function render(PanelNode $panel): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $href = (string) $panel->href;
        echo '<div class="widgets themeAssets">';
        foreach (self::SLOTS as $slot => $spec) {
            if ($slot === 'logo') continue; // Legacy sign-in fallback; no separate editable slot.
            $path = self::path($slot);
            $own = self::isOverride($path);
            $note = $own ? '' : 'System default';
            echo '<article class="widget themeAsset ' . $e($spec['kind']) . '">';
            echo '<header><h2>' . $e($spec['label']) . '</h2></header>';
            echo '<p class="tagsHint">' . $e($spec['hint']) . '</p>';
            echo '<main><form class="ajax" data-upload="onsubmit" method="post" enctype="multipart/form-data" action="' . $e($href . 'upload?slot=' . $slot) . '">';
            echo '<label class="themePreview" data-slot="' . $e($slot) . '" data-drop-files>';
            echo self::preview($slot, $path);
            echo '<input type="file" name="file" accept="' . $e($spec['accept']) . '" aria-label="Upload ' . $e($spec['label']) . '" onchange="this.form.requestSubmit()">';
            echo '</label></form></main>';
            echo '<footer><span class="left">' . $e($note) . '</span>';
            if ($own) {
                echo '<button type="button" class="button" data-on_click="clear" data-slot="' . $e($slot) . '"><i class="cmsIcon cmsIcon-arrow-rotate-left"></i>Use the system image</button>';
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
        $mime = self::mime($tmp);
        if ($slot === 'favicon') {
            if (in_array($mime, ['image/png', 'image/webp'], true)) {
                if (!self::storeFaviconPng($tmp)) return 'That image could not be saved.';
                self::remove(self::overridePath('img/icon.svg'));
                return null;
            }
            if ($mime === 'image/svg+xml') {
                $svg = self::cleanSvg((string) file_get_contents($tmp));
                if ($svg === null) {
                    return 'Use an SVG without scripts.';
                }
                foreach (['img/favicons/favicon-32x32.png', 'img/favicons/favicon-16x16.png', 'img/favicons/apple-touch-icon.png'] as $name) {
                    self::remove(self::overridePath($name));
                }
                return self::write(self::overridePath('img/icon.svg'), $svg) ? null : 'That file could not be saved.';
            }
            return 'Use an SVG, PNG or WebP.';
        }
        if ($slot === 'share') {
            $info = @getimagesize($tmp);
            if (!is_array($info) || ($info[2] ?? 0) !== IMAGETYPE_JPEG) {
                return 'Use a JPG.';
            }
            return self::writeRaster($tmp, self::overridePath($spec['file']), true) ? null : 'That image could not be saved.';
        }
        if ($mime === 'image/svg+xml') {
            $svg = self::cleanSvg((string) file_get_contents($tmp));
            if ($svg === null) {
                return 'Use an SVG without scripts.';
            }
            $png = $spec['png'] ?? (str_ends_with($spec['file'], '.png') ? $spec['file'] : null);
            if ($png !== null) {
                self::remove(self::overridePath($png));
            }
            $svgFile = $spec['svg'] ?? $spec['file'];
            return self::write(self::overridePath($svgFile), $svg) ? null : 'That file could not be saved.';
        }
        if (in_array($mime, ['image/png', 'image/webp'], true)) {
            $svgFile = str_ends_with($spec['file'], '.svg') ? $spec['file'] : ($spec['svg'] ?? null);
            $png = $spec['png'] ?? $spec['file'];
            if (!self::writeRaster($tmp, self::overridePath($png), false)) return 'That image could not be saved.';
            if ($svgFile !== null) self::remove(self::overridePath($svgFile));
            return null;
        }
        return 'Use an SVG, PNG or WebP.';
    }

    public static function clear(string $slot): bool
    {
        $spec = self::SLOTS[$slot] ?? null;
        if ($spec === null) {
            return false;
        }
        $gone = false;
        foreach (self::ownedFiles($slot) as $file) {
            if (is_file($file)) {
                $gone = unlink($file) || $gone;
            }
        }
        return $gone;
    }

    /** The file that is shown: a project file of either accepted type, otherwise the system file. */
    public static function href(string $slot): string
    {
        $file = self::served($slot);
        return $file === null ? '' : Packages::panelAssetHref($file);
    }

    public static function path(string $slot): ?string
    {
        $file = self::served($slot);
        return $file === null ? null : Packages::panelAsset($file);
    }

    /** The project's replacement, or null while the slot still uses a shipped file. */
    public static function projectHref(string $slot): ?string
    {
        $file = self::served($slot);
        if ($file === null || !self::isOverride(Packages::panelAsset($file))) {
            return null;
        }
        $href = Packages::panelAssetHref($file);
        return $href === '' ? null : $href;
    }

    /** @return list<string> */
    private static function ownedFiles(string $slot): array
    {
        $spec = self::SLOTS[$slot];
        $files = [];
        foreach (self::names($slot, $spec) as $name) {
            $files[] = self::overridePath($name);
        }
        if ($slot === 'favicon') {
            foreach (['img/favicons/favicon-16x16.png', 'img/favicons/apple-touch-icon.png'] as $name) {
                $files[] = self::overridePath($name);
            }
        }
        return $files;
    }

    /** @param array{file: string, png?: string, svg?: string} $spec */
    private static function names(string $slot, array $spec): array
    {
        $names = [$spec['file']];
        foreach (['png', 'svg'] as $key) {
            if (isset($spec[$key]) && !in_array($spec[$key], $names, true)) {
                $names[] = $spec[$key];
            }
        }
        if ($slot === 'favicon') {
            $names[] = 'img/favicons/favicon-32x32.png';
        }
        return $names;
    }

    private static function served(string $slot): ?string
    {
        $spec = self::SLOTS[$slot] ?? null;
        if ($spec === null) {
            return null;
        }
        $fallback = null;
        $override = null;
        $overrideTime = -1;
        foreach (self::names($slot, $spec) as $name) {
            $path = Packages::panelAsset($name);
            if ($path === null) {
                continue;
            }
            if (!self::isOverride($path)) {
                $fallback ??= $name;
                continue;
            }
            $time = (int) filemtime(\fs::toNative($path));
            if ($time >= $overrideTime) {
                $overrideTime = $time;
                $override = $name;
            }
        }
        return $override ?? $fallback;
    }

    private static function storeFaviconPng(string $tmp): bool
    {
        $src = @imagecreatefromstring((string) file_get_contents($tmp));
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

    private static function preview(string $slot, ?string $path): string
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
        return '<img src="' . htmlspecialchars(self::href($slot), ENT_QUOTES) . '" alt="">';
    }

    private static function remove(string $file): void
    {
        if (is_file($file)) {
            unlink($file);
        }
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

    private static function write(string $dest, string $bytes): bool
    {
        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        return \Systopic\System\Config\FileWriter::write($dest, $bytes);
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
            ob_start();
            $ok = imagejpeg($flat, null, 90);
            $bytes = (string) ob_get_clean();
            $ok = $ok && self::write($dest, $bytes);
            imagedestroy($flat);
        } else {
            imagesavealpha($img, true);
            ob_start();
            $ok = imagepng($img);
            $bytes = (string) ob_get_clean();
            $ok = $ok && self::write($dest, $bytes);
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
        ob_start();
        $ok = imagepng($out);
        $bytes = (string) ob_get_clean();
        $ok = $ok && self::write($dest, $bytes);
        imagedestroy($out);
        return $ok;
    }

    private static function mime(string $tmp): string
    {
        $info = @getimagesize($tmp);
        if (is_array($info) && in_array($info['mime'] ?? '', ['image/png', 'image/webp'], true)) {
            return $info['mime'];
        }
        $head = (string) file_get_contents($tmp, false, null, 0, 200);
        return preg_match('/<svg\b/i', $head) === 1 ? 'image/svg+xml' : '';
    }
}
