<?php

declare(strict_types=1);

namespace Systopic\System\Mail;

/**
 * The shell of a system mail: the project logo, like the login, and the
 * theme colours, including whatever the project overrides.
 */
final class Letter
{
    /** @var array{main: string, highlight: string, bg: string, text: string}|null */
    private static ?array $colors = null;

    public static function render(string $title, string $body): string
    {
        $c = self::colors();
        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
        $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif";
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $e($title) . '</title></head>'
            . '<body style="margin:0;padding:0;background:' . $c['bg'] . ';">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $c['bg'] . ';"><tr>'
            . '<td align="center" style="padding:32px 16px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;">'
            . '<tr><td style="height:6px;background:' . $c['main'] . ';border-radius:12px 12px 0 0;font-size:0;line-height:0;">&nbsp;</td></tr>'
            . '<tr><td align="center" style="padding:28px 32px 4px;">' . self::logo() . '</td></tr>'
            . '<tr><td style="padding:8px 32px 32px;font-family:' . $font . ';color:' . $c['text'] . ';font-size:16px;line-height:1.5;">'
            . '<h1 style="margin:0 0 16px;font-size:26px;line-height:1.25;font-weight:700;color:' . $c['main'] . ';">' . $e($title) . '</h1>'
            . $body
            . '</td></tr></table></td></tr></table></body></html>';
    }

    public static function button(string $href, string $label): string
    {
        $c = self::colors();
        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
        return '<p style="margin:24px 0;text-align:center;"><a href="' . $e($href) . '" style="display:inline-block;padding:12px 22px;background:' . $c['highlight'] . ';color:' . $c['main'] . ';text-decoration:none;border-radius:6px;font-weight:700;">' . $e($label) . '</a></p>';
    }

    public static function note(string $text): string
    {
        $c = self::colors();
        return '<p style="margin:16px 0 0;text-align:center;font-size:14px;line-height:1.45;color:' . $c['text'] . ';">' . $text . '</p>';
    }

    private static function logo(): string
    {
        $file = (string) \fs::$siteRoot . 'overrides/img/logo.svg';
        if (\fs::isFile($file)) {
            $svg = preg_replace('/<\?xml[^>]*\?>/', '', (string) \fs::file_get_contents($file)) ?? '';
            return '<div style="max-width:180px;margin:0 auto;">' . $svg . '</div>';
        }
        $href = \Systopic\System\Sys\Packages::panelAssetHref('img/logo-horizontal-dark.png');
        if ($href === '') {
            return '';
        }
        $src = \http::$hostUrl . $href;
        return '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="fluxo" width="160" style="display:block;margin:0 auto;border:0;height:auto;">';
    }

    /** @return array{main: string, highlight: string, bg: string, text: string} */
    private static function colors(): array
    {
        if (self::$colors !== null) {
            return self::$colors;
        }
        $vars = [];
        $files = [];
        $theme = \Systopic\System\Sys\Packages::panelAsset('css/theme.css');
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
        foreach ($files as $file) {
            $text = (string) @file_get_contents($file);
            if (preg_match_all('/--(cmsMainColor|cmsHighlightColor|cmsBgColor|cmsTextColor)\s*:\s*#([0-9a-fA-F]{3,8})\b/', $text, $found, PREG_SET_ORDER)) {
                foreach ($found as $hit) {
                    $hex = strtolower($hit[2]);
                    if (strlen($hex) === 3) {
                        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
                    }
                    $vars[$hit[1]] = '#' . substr($hex, 0, 6);
                }
            }
        }
        return self::$colors = [
            'main' => $vars['cmsMainColor'] ?? '#1f3a45',
            'highlight' => $vars['cmsHighlightColor'] ?? '#fcb60c',
            'bg' => $vars['cmsBgColor'] ?? '#f0f3f4',
            'text' => $vars['cmsTextColor'] ?? '#161c1f',
        ];
    }
}
