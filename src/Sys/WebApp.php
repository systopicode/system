<?php

declare(strict_types=1);

namespace Systopic\System\Sys;

/**
 * The CMS as an installable web app: `cms/app.webmanifest`, answered before
 * the panel tree (a manifest is fetched without the session cookie, the
 * login redirect must not catch it). Colours follow the theme and its
 * overrides, icons the panel assets - a project replaces them in overrides/img/.
 */
final class WebApp
{
    public const FILE = 'cms/app.webmanifest';

    /** afterConnect, every request - only the manifest's URL answers here. */
    public static function serve(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (preg_match('#^' . preg_quote(\http::$root, '#') . 'cms/icon-(\d+)(-mask)?\.png$#', $path, $icon)) {
            self::serveIcon((int) $icon[1], isset($icon[2]));
        }
        if ($path !== \http::$root . self::FILE) {
            return;
        }
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: no-cache');
        echo json_encode(self::manifest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function href(): string
    {
        return \http::$root . self::FILE . '?v=' . filemtime(__FILE__);
    }

    /** CMS branding is independent of PUBLIC_APP_NAME, which names the public website. */
    public static function name(): string
    {
        $name = defined('CMS_PAGE_TITLE_OVERRIDE') ? trim(strip_tags((string) CMS_PAGE_TITLE_OVERRIDE)) : '';
        return $name !== '' ? $name : 'fluxo cms';
    }

    /** The public site's name, so several CMS tabs can be told apart. */
    public static function publicName(): string
    {
        $value = \Systopic\System\Config\Config::current()?->site()?->meta('title');
        if (is_string($value) && trim($value) !== '') {
            return trim(strip_tags($value));
        }
        $constant = defined('PUBLIC_PAGE_NAME') ? trim(strip_tags((string) PUBLIC_PAGE_NAME)) : '';
        return $constant;
    }

    /** Browser tab. An installed app keeps `name()` — set from the page when standalone. */
    public static function documentTitle(): string
    {
        $public = self::publicName();
        if ($public === '' || strcasecmp($public, self::name()) === 0) {
            return self::stageMark() . self::name();
        }
        return self::stageMark() . $public . ' | ' . self::name();
    }

    /** Before a window title: the stage in the colour of the status bar badge, nothing on LIVE. */
    public static function stageMark(): string
    {
        return match (defined('STAGE') ? (string) STAGE : '') {
            'DEV' => '🟢 ',
            'STAGING' => '🟡 ',
            'RC' => '🟠 ',
            default => '',
        };
    }

    /** What a home screen has room for under the icon. */
    public static function shortName(): string
    {
        return self::name();
    }

    /** @return array<string, mixed> */
    private static function manifest(): array
    {
        $start = \http::$root . 'cms/';
        // Inside the app scope and without a query: iOS drops an apple-touch
        // icon whose address has one, and Android ignores icons outside the scope.
        $icons = [];
        foreach ([[192, 'any', ''], [512, 'any', ''], [192, 'maskable', '-mask'], [512, 'maskable', '-mask']] as [$size, $purpose, $mask]) {
            $icons[] = [
                'src' => $start . 'icon-' . $size . $mask . '.png',
                'sizes' => $size . 'x' . $size,
                'type' => 'image/png',
                'purpose' => $purpose,
            ];
        }
        return [
            'id' => $start,
            'name' => self::name(),
            'short_name' => self::shortName(),
            'description' => 'fluxo cms',
            'start_url' => $start,
            'scope' => $start,
            'display' => 'standalone',
            'orientation' => 'any',
            'lang' => 'en',
            'theme_color' => '#' . Theme::color('cmsMainColor'),
            'background_color' => '#' . Theme::color('cmsMainColor'),
            'icons' => $icons,
        ];
    }

    /** A home-screen icon, themed, or the project's own file when it replaced one. */
    private static function serveIcon(int $size, bool $mask): void
    {
        if (!in_array($size, [180, 192, 512], true)) {
            http_response_code(404);
            exit;
        }
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: image/png');
        header('Cache-Control: no-cache');
        $source = Packages::panelAsset('img/favicons/apple-touch-icon.png')
            ?? Packages::panelAsset('img/favicons/android-chrome-512x512.png');
        $own = $source !== null && str_starts_with(
            \fs::toNative($source),
            \fs::toNative((string) \fs::$siteRoot . 'overrides/'),
        );
        if ($source !== null && $own) {
            readfile(\fs::toNative($source));
            exit;
        }
        echo self::iconPng($source, $size, $mask);
        exit;
    }

    /** The system mark, recoloured from the theme (overrides included). */
    private static function iconPng(?string $source, int $size, bool $mask): string
    {
        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, true);
        $main = self::rgb(Theme::color('cmsMainColor'));
        $fill = imagecolorallocate($canvas, $main[0], $main[1], $main[2]);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $fill);
        $art = $source !== null ? @imagecreatefrompng(\fs::toNative($source)) : false;
        if ($art !== false) {
            $scale = $mask ? 0.8 : 1;
            $side = (int) round($size * $scale);
            $offset = (int) (($size - $side) / 2);
            $painted = self::recolor($art, Theme::color('cmsMainColor'), Theme::color('cmsHighlightColor'));
            imagecopyresampled($canvas, $painted, $offset, $offset, 0, 0, $side, $side, imagesx($painted), imagesy($painted));
            imagedestroy($painted);
            imagedestroy($art);
        }
        ob_start();
        imagepng($canvas);
        imagedestroy($canvas);
        return (string) ob_get_clean();
    }

    /** @return \GdImage */
    private static function recolor(\GdImage $art, string $mainHex, string $highlightHex): \GdImage
    {
        $w = imagesx($art);
        $h = imagesy($art);
        $out = imagecreatetruecolor($w, $h);
        $main = self::rgb($mainHex);
        $highlight = self::rgb($highlightHex);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $pixel = imagecolorat($art, $x, $y);
                $r = ($pixel >> 16) & 255;
                $g = ($pixel >> 8) & 255;
                $b = $pixel & 255;
                $gold = $r > 170 && $g > 100 && $b < 90;
                $light = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
                if ($gold) {
                    [$r, $g, $b] = $highlight;
                } elseif ($light < 0.35) {
                    [$r, $g, $b] = $main;
                } elseif ($light > 0.85) {
                    $r = $g = $b = 255;
                } else {
                    $t = ($light - 0.35) / 0.5;
                    $r = (int) round($main[0] + ($t * (255 - $main[0])));
                    $g = (int) round($main[1] + ($t * (255 - $main[1])));
                    $b = (int) round($main[2] + ($t * (255 - $main[2])));
                }
                imagesetpixel($out, $x, $y, ($r << 16) | ($g << 8) | $b);
            }
        }
        return $out;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb(string $hex): array
    {
        $hex = Theme::normalize($hex);
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
