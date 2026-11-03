<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear\Prng;


class Mulberry32
{
    private const UINT32_MAX_PLUS_1 = 2 ** 32;

    private int $state;

    public function __construct(int $seed = 0)
    {
        $this->state = $seed & 0xFFFFFFFF;
    }


    public function next(): int
    {
        $z = ($this->state + 0x6D2B79F5) & 0xFFFFFFFF;
        $this->state = $z;

        $z = self::mul($z ^ ($z >> 15), $z | 1);
        $z ^= self::add($z, self::mul($z ^ ($z >> 7), $z | 61));

        return ($z ^ ($z >> 14)) & 0xFFFFFFFF;
    }


    public function nextFloat(): float
    {
        return $this->next() / self::UINT32_MAX_PLUS_1;
    }


    public function state(): int
    {
        return $this->state >= 0x80000000
            ? (int) ($this->state - self::UINT32_MAX_PLUS_1)
            : (int) $this->state;
    }


    private static function mul(int $a, int $b): int
    {
        $al = $a & 0xFFFF;
        $ah = ($a >> 16) & 0xFFFF;

        return ($al * $b + ((($ah * $b) & 0xFFFF) << 16)) & 0xFFFFFFFF;
    }


    private static function add(int $a, int $b): int
    {
        return ($a + $b) & 0xFFFFFFFF;
    }
}
