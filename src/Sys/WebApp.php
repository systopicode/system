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
        return \http::$root . self::FILE;
    }

    /** The product, after the site's name when it has one. */
    public static function name(): string
    {
        $site = self::site();
        return $site !== '' ? $site . ' // fluxo cms' : 'fluxo cms';
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
        $site = self::site();
        return $site !== '' && mb_strlen($site) <= 12 ? $site : 'fluxo';
    }

    private static function site(): string
    {
        return defined('PUBLIC_PAGE_NAME') ? trim((string) PUBLIC_PAGE_NAME) : '';
    }

    /** @return array<string, mixed> */
    private static function manifest(): array
    {
        $start = \http::$root . 'cms/';
        $icons = [];
        foreach ([['img/favicons/android-chrome-192x192.png', 192, 'any'], ['img/favicons/android-chrome-512x512.png', 512, 'any'],
                  ['img/favicons/maskable-192x192.png', 192, 'maskable'], ['img/favicons/maskable-512x512.png', 512, 'maskable']] as [$file, $size, $purpose]) {
            $src = Packages::panelAssetHref($file);
            if ($src !== '') {
                $icons[] = ['src' => $src, 'sizes' => $size . 'x' . $size, 'type' => 'image/png', 'purpose' => $purpose];
            }
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
}
