<?php

declare(strict_types=1);

namespace Systopic\System\Mail;

use Systopic\System\Sys\Theme;

/**
 * The shell of a system mail: the project logo, like the login, and the
 * theme colours, including whatever the project overrides.
 */
final class Letter
{
    /** @var array{main: string, highlight: string, bg: string, text: string, onHighlight: string, darkBg: string, darkCard: string}|null */
    private static ?array $colors = null;

    public static function render(string $title, string $body): string
    {
        $c = self::colors();
        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
        $logo = self::logo();
        $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif";
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark">'
            . '<style>:root{color-scheme:light dark;supported-color-schemes:light dark;}'
            . '@media(prefers-color-scheme:dark){.mailCanvas{background:' . $c['darkBg'] . '!important;}.mailCard{background:' . $c['darkCard'] . '!important;}.mailContent,.mailNote{color:#edf2f4!important;}.mailTitle{color:#ffffff!important;}.mailLogoLight{display:none!important;}.mailLogoDark{display:block!important;}.mailContent a:not(.mailButton){color:#c6e4ef!important;}}'
            . '[data-ogsc] .mailCanvas{background:' . $c['darkBg'] . '!important;}[data-ogsc] .mailCard{background:' . $c['darkCard'] . '!important;}[data-ogsc] .mailContent,[data-ogsc] .mailNote{color:#edf2f4!important;}[data-ogsc] .mailTitle{color:#ffffff!important;}[data-ogsc] .mailLogoLight{display:none!important;}[data-ogsc] .mailLogoDark{display:block!important;}'
            . '</style><title>' . $e($title) . '</title></head>'
            . '<body class="mailCanvas" style="margin:0;padding:0;background:' . $c['bg'] . ';">'
            . '<table class="mailCanvas" role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $c['bg'] . ';"><tr>'
            . '<td align="center" style="padding:32px 16px;">'
            . '<table class="mailCard" role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;">'
            . '<tr><td style="height:6px;background:' . $c['highlight'] . ';border-radius:12px 12px 0 0;font-size:0;line-height:0;">&nbsp;</td></tr>'
            . ($logo === '' ? '' : '<tr><td align="center" style="padding:28px 32px 20px;">' . $logo . '</td></tr>')
            . '<tr><td class="mailContent" style="padding:8px 32px 32px;font-family:' . $font . ';color:' . $c['text'] . ';font-size:16px;line-height:1.5;">'
            . '<h1 class="mailTitle" style="margin:0 0 16px;font-size:26px;line-height:1.25;font-weight:700;color:' . $c['main'] . ';">' . $e($title) . '</h1>'
            . $body
            . '</td></tr></table></td></tr></table></body></html>';
    }

    public static function button(string $href, string $label): string
    {
        $c = self::colors();
        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
        return '<p style="margin:24px 0;text-align:center;"><a class="mailButton" href="' . $e($href) . '" style="display:inline-block;padding:12px 22px;background:' . $c['highlight'] . ';color:' . $c['onHighlight'] . ';text-decoration:none;border-radius:6px;font-weight:700;">' . $e($label) . '</a></p>';
    }

    public static function note(string $text): string
    {
        $c = self::colors();
        return '<p class="mailNote" style="margin:16px 0 0;text-align:center;font-size:14px;line-height:1.45;color:' . $c['text'] . ';">' . $text . '</p>';
    }

    public static function accessWarning(): string
    {
        return self::note('Do not forward this email or share its code or link. They can be used to access your account.');
    }

    private static function logo(): string
    {
        // Default to the dark lettering on light mail surfaces. Clients supporting
        // dark-mode CSS switch the existing transparent word marks, without a plate.
        return '<div class="mailLogoLight">' . self::logoImage('wordLight') . '</div>'
            . '<div class="mailLogoDark" style="display:none;mso-hide:all;">' . self::logoImage('wordDark') . '</div>';
    }

    private static function logoImage(string $slot): string
    {
        $href = \Systopic\System\Settings\ThemeAssets::href($slot);
        if ($href === '') return '';
        $src = \http::$hostUrl . $href;
        return '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="fluxo" width="160" style="display:block;margin:0 auto;border:0;height:auto;">';
    }

    private static function darken(string $hex, float $factor): string
    {
        return '#' . implode('', array_map(static fn($v) => str_pad(dechex((int) round(hexdec($v) * $factor)), 2, '0', STR_PAD_LEFT), str_split(ltrim($hex, '#'), 2)));
    }

    /** @return array{main: string, highlight: string, bg: string, text: string, onHighlight: string, darkBg: string, darkCard: string} */
    private static function colors(): array
    {
        $hex = static fn(string $name): string => '#' . Theme::color($name);
        return self::$colors ??= [
            'main' => $hex('cmsMainColor'),
            'highlight' => $hex('cmsHighlightColor'),
            'bg' => $hex('cmsBgColor'),
            'text' => $hex('cmsTextColor'),
            'onHighlight' => '#' . Theme::textOn('cmsHighlightColor'),
            'darkBg' => self::darken($hex('cmsMainColor'), 0.30),
            'darkCard' => self::darken($hex('cmsMainColor'), 0.50),
        ];
    }
}
