<?php

declare(strict_types=1);
namespace Systopic\System\Settings;

final class Contrast
{
    public static function ratio(string $a, string $b): float
    {
        $luminance = static function (string $hex): float {
            $hex = ltrim($hex, '#');
            if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) throw new \InvalidArgumentException('Expected a six-digit RGB colour.');
            $rgb = array_map(static function ($part): float {
                $v = hexdec($part) / 255;
                return $v <= .04045 ? $v / 12.92 : (($v + .055) / 1.055) ** 2.4;
            }, str_split($hex, 2));
            return .2126 * $rgb[0] + .7152 * $rgb[1] + .0722 * $rgb[2];
        };
        $a = $luminance($a); $b = $luminance($b);
        return (max($a, $b) + .05) / (min($a, $b) + .05);
    }

    public static function render(): void
    {
        $color = static fn($name) => \Systopic\System\Sys\Theme::color($name);
        echo '<details class="themeContrast"><summary>Colour contrast <i class="cmsIcon cmsIcon-chevron-down"></i></summary><p class="tagsHint">Checks the chosen base colours for normal text (4.5:1). Appearance modes and custom CSS can change the final colours; verify those in the interface too.</p><div class="tableScroll"><table class="scalars"><tbody>';
        foreach ([['Text / background','cmsTextColor','cmsBgColor'],['Main / highlight','cmsMainColor','cmsHighlightColor']] as [$label,$a,$b]) {
            $ratio = self::ratio($color($a),$color($b));
            echo '<tr><td>' . $label . '</td><td><i class="cmsIcon cmsIcon-' . ($ratio >= 4.5 ? 'circle-check' : 'triangle-exclamation') . '"></i> ' . number_format($ratio,2) . ':1 — ' . ($ratio >= 4.5 ? 'Sufficient for normal text' : 'Low contrast: adjust these colours') . '</td></tr>';
        }
        echo '<tr data-contrast-live><td>Current appearance: text / surface</td><td data-contrast-result></td></tr></tbody></table></div></details>';
    }
}
