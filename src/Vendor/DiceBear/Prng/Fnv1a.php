<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear\Prng;


class Fnv1a
{

    public static function hash(string $input): int
    {
        $hash = 0x811C9DC5;

        // FNV prime is small enough that hash * prime fits in 64-bit.
        foreach (self::utf16CodeUnits($input) as $code) {
            $hash = (($hash ^ $code) * 0x01000193) & 0xFFFFFFFF;
        }

        return $hash;
    }


    public static function hex(string $input): string
    {
        return str_pad(dechex(self::hash($input)), 8, '0', STR_PAD_LEFT);
    }


    private static function utf16CodeUnits(string $input): array
    {
        if ($input === '') {
            return [];
        }

        return array_values(unpack('v*', mb_convert_encoding($input, 'UTF-16LE', 'UTF-8')));
    }
}
