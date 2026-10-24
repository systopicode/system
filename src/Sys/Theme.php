<?php

declare(strict_types=1);

namespace Systopic\System\Sys;

/**
 * The CMS theme colours outside a stylesheet (portraits, the web app
 * manifest, theme-color): 00_theme.css, then whatever the project overrides
 * (css/cms_overrides.css, overrides/css/*.css) - the last value wins.
 */
final class Theme
{
    private const DEFAULTS = [
        'cmsMainColor' => '1f3a45',
        'cmsHighlightColor' => 'fcb60c',
        'cmsBgColor' => 'f0f3f4',
    ];

    /** @var array<string, string>|null */
    private static ?array $colors = null;

    /** `cmsMainColor`, `cmsHighlightColor` or `cmsBgColor` - six hex digits, no `#`. */
    public static function color(string $name): string
    {
        return self::colors()[$name] ?? self::DEFAULTS[$name] ?? '000000';
    }

    /** @return array<string, string> */
    private static function colors(): array
    {
        if (self::$colors !== null) {
            return self::$colors;
        }
        $vars = self::DEFAULTS;
        $names = implode('|', array_keys(self::DEFAULTS));
        foreach (self::stylesheets() as $file) {
            $text = (string) @file_get_contents($file);
            if (preg_match_all('/--(' . $names . ')\s*:\s*#([0-9a-fA-F]{3,8})\b/', $text, $found, PREG_SET_ORDER)) {
                foreach ($found as $hit) {
                    $vars[$hit[1]] = self::normalize($hit[2]);
                }
            }
        }
        return self::$colors = $vars;
    }

    /** @return list<string> */
    private static function stylesheets(): array
    {
        $files = [];
        $theme = Packages::panelAsset('css/00_theme.css');
        if ($theme !== null) {
            $files[] = $theme;
        }
        $root = (string) \fs::$siteRoot;
        $named = $root . 'css/cms_overrides.css';
        if (is_file($named)) {
            $files[] = $named;
        }
        foreach (glob($root . 'overrides/css/*.css') ?: [] as $file) {
            $files[] = $file;
        }
        return $files;
    }

    public static function normalize(string $hex): string
    {
        $hex = strtolower(ltrim($hex, '#'));
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return substr($hex, 0, 6);
    }
}
