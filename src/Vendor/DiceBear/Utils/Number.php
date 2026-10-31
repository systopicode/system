<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear\Utils;


class Number
{
    public static function format(int|float $value): string
    {
        if (is_nan((float) $value)) {
            return 'NaN';
        }

        if (is_infinite((float) $value)) {
            return $value > 0 ? 'Infinity' : '-Infinity';
        }

        $scaled = self::roundHalfUp($value * 100000);
        $sign = $scaled < 0 ? '-' : '';
        $scaled = abs($scaled);

        $integerPart = intdiv($scaled, 100000);
        $fraction = rtrim(str_pad((string) ($scaled % 100000), 5, '0', STR_PAD_LEFT), '0');

        return $sign . $integerPart . ($fraction !== '' ? '.' . $fraction : '');
    }


    public static function roundHalfUp(float $value): int
    {
        $floor = floor($value);

        return (int) ($value - $floor < 0.5 ? $floor : $floor + 1);
    }
}
