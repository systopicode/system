<?php

declare(strict_types=1);

namespace Systopic\System\Sys;

/**
 * The CMS theme colours outside a stylesheet (portraits, system mails, the
 * web app manifest, the edit bar, theme-color): 00_theme.css, then whatever the project overrides
 * (css/system_overrides.css, or css/cms_overrides.css while that name is still
 * the one on disk, then overrides/css/*.css) - the last value wins.
 */
final class Theme
{
    private const DEFAULTS = [
        'cmsMainColor' => '1f3a45',
        'cmsHighlightColor' => 'fcb60c',
        'cmsBgColor' => 'f0f3f4',
        'cmsTextColor' => '161c1f',
    ];

    /** @var array<string, string>|null */
    private static ?array $colors = null;

    /** `cmsMainColor`, `cmsHighlightColor`, `cmsBgColor` or `cmsTextColor` - six hex digits, no `#`. */
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
            $text = (string) @\fs::file_get_contents($file);
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
        $named = self::projectStylesheet();
        if ($named !== null) {
            $files[] = $named;
        }
        $root = (string) \fs::$siteRoot;
        $overrides = \fs::glob($root . 'overrides/css/*.css') ?: [];
        sort($overrides, SORT_STRING);
        foreach ($overrides as $file) {
            $files[] = $file;
        }
        return $files;
    }

    /**
     * The project's GUI stylesheet. `system_overrides.css` is the name;
     * `cms_overrides.css` is read while a project still has only that file.
     */
    public static function projectStylesheet(): ?string
    {
        $root = (string) \fs::$siteRoot;
        foreach (['css/system_overrides.css', 'css/cms_overrides.css'] as $name) {
            if (\fs::isFile($root . $name)) {
                return $root . $name;
            }
        }
        return null;
    }

    /** Drop the cached read so a theme just written is what the next render uses. */
    public static function flush(): void
    {
        self::$colors = null;
    }

    /** Text colour readable on a theme colour: dark on light colours, white on dark ones. */
    public static function textOn(string $name): string
    {
        [$r, $g, $b] = array_map(static fn(string $c): float => hexdec($c) / 255, str_split(self::color($name), 2));
        $linear = static fn(float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $luminance = 0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b);
        return $luminance > 0.4 ? '1b1b1b' : 'ffffff';
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
