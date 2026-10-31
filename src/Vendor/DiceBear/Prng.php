<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear;

use Systopic\System\Vendor\DiceBear\Prng\Fnv1a;
use Systopic\System\Vendor\DiceBear\Prng\Mulberry32;
use Systopic\System\Vendor\DiceBear\Utils\Number;


class Prng
{
    private string $seed;

    public function __construct(string $seed)
    {
        $this->seed = $seed;
    }


    public function pick(string $key, array $items): mixed
    {
        if (count($items) === 0) {
            return null;
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $unique = self::uniqueByCodePoint($items);

        if (count($unique) === 1) {
            return $unique[0];
        }

        $sorted = $unique;
        usort($sorted, self::compareByCodePoint(...));
        $index = (int) floor($this->getValue($key) * count($sorted));

        return $sorted[$index];
    }


    public function weightedPick(string $key, array $weights): ?string
    {
        if (count($weights) === 0) {
            return null;
        }

        $keys = array_keys($weights);

        if (count($keys) === 1) {
            return (string) $keys[0];
        }

        usort($keys, self::compareByCodePoint(...));

        // Sum in sorted-key order to match JS reduce-over-sorted parity —
        // float addition is non-associative, so iterating $weights directly
        // (insertion order) would diverge from JS for fractional weights.
        $totalWeight = 0.0;
        foreach ($keys as $k) {
            $totalWeight += $weights[$k];
        }

        if ($totalWeight === 0.0) {
            return $this->pick($key, array_map('strval', $keys));
        }

        $threshold = $this->getValue($key) * $totalWeight;
        $cumulative = 0;

        foreach ($keys as $k) {
            $cumulative += $weights[$k];

            if ($threshold < $cumulative) {
                return (string) $k;
            }
        }

        return (string) $keys[count($keys) - 1];
    }


    public function bool(string $key, float $likelihood = 50): bool
    {
        return $this->getValue($key) * 100 < $likelihood;
    }


    public function float(string $key, array $range): float
    {
        $min = min($range['min'], $range['max']);
        $max = max($range['min'], $range['max']);
        $step = $range['step'] ?? 0;

        if ($step > 0) {
            $buckets = (int) floor(($max - $min) / $step) + 1;
            $i = (int) floor($this->getValue($key) * $buckets);
            $value = $min + $i * $step;
        } else {
            $value = $min + $this->getValue($key) * ($max - $min);
        }

        // Number::roundHalfUp matches JS Math.round (half toward +Infinity);
        // PHP's native round() rounds half away from zero, diverging from the
        // reference for negative values landing exactly on a .5 boundary.
        return Number::roundHalfUp($value * 10000) / 10000;
    }


    public function integer(string $key, array $range): int
    {
        $min = (int) min($range['min'], $range['max']);
        $max = (int) max($range['min'], $range['max']);

        return (int) floor($this->getValue($key) * ($max - $min + 1)) + $min;
    }


    public function shuffle(string $key, array $items): array
    {
        if (count($items) <= 1) {
            return $items;
        }

        $result = self::uniqueByCodePoint($items);
        usort($result, self::compareByCodePoint(...));
        $prng = new Mulberry32(Fnv1a::hash($this->seed . ':' . $key));

        for ($i = count($result) - 1; $i > 0; $i--) {
            $j = (int) floor($prng->nextFloat() * ($i + 1));
            $temp = $result[$i];
            $result[$i] = $result[$j];
            $result[$j] = $temp;
        }

        return $result;
    }


    public function getValue(string $key): float
    {
        return (new Mulberry32(Fnv1a::hash($this->seed . ':' . $key)))->nextFloat();
    }


    private static function compareByCodePoint(mixed $a, mixed $b): int
    {
        $au = (string) mb_convert_encoding((string) $a, 'UTF-16BE', 'UTF-8');
        $bu = (string) mb_convert_encoding((string) $b, 'UTF-16BE', 'UTF-8');

        return strcmp($au, $bu);
    }


    private static function uniqueByCodePoint(array $items, ?callable $keyFn = null): array
    {
        $seen = [];
        $result = [];

        foreach ($items as $item) {
            $repr = $keyFn !== null ? $keyFn($item) : (string) $item;

            if (!isset($seen[$repr])) {
                $seen[$repr] = true;
                $result[] = $item;
            }
        }

        return $result;
    }
}
