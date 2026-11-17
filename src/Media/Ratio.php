<?php

declare(strict_types=1);

namespace Systopic\System\Media;

/**
 * An aspect ratio as a reduced integer fraction - `16:9`, `1:1`, `753:1364`.
 *
 * The name is the identity: a crop is found by it (`media_crop_format`,
 * `media_crop_ratio`) and a file is named by it (`--16by9`). Two ratios are
 * the same when their names are; nothing here compares floats, so `16:9`
 * read from the settings and `16:9` read from a row always meet.
 *
 * A master that was dragged freely gets the fraction of its pixel size,
 * which may be an odd one (`753:1364`). That is fine: it is exact.
 */
final class Ratio
{
    /** Longest fraction part that is read: `1.9125:1` yes, more digits no. */
    private const DECIMALS = 4;

    private function __construct(
        public readonly int $w,
        public readonly int $h,
    ) {}

    /** `16:9` - what the row and the settings hold. */
    public string $name { get => $this->w . ':' . $this->h; }

    /** `16by9` - the part of a file name; a colon cannot be in a path. */
    public string $suffix { get => $this->w . 'by' . $this->h; }

    /** Width over height, for arithmetic only - never to tell two ratios apart. */
    public float $value { get => $this->w / $this->h; }

    /**
     * Reads `16:9`, `16/9`, `16by9`, `1.91:1` and a bare `1.5`. Null for
     * everything else - zero, negative, three parts, text.
     *
     * Decimals are read as text and scaled, so `1.91:1` is exactly `191:100`.
     */
    public static function of(string $value): ?self
    {
        $number = '(\d{1,6})(?:\.(\d{1,' . self::DECIMALS . '}))?';
        if (!preg_match('~^\s*' . $number . '\s*(?:(?::|/|by)\s*' . $number . ')?\s*$~i', $value, $m)) {
            return null;
        }
        $fractions = [$m[2] ?? '', $m[4] ?? ''];
        $digits = max(strlen($fractions[0]), strlen($fractions[1]));
        $w = (int) ($m[1] . str_pad($fractions[0], $digits, '0'));
        $h = isset($m[3]) && $m[3] !== ''
            ? (int) ($m[3] . str_pad($fractions[1], $digits, '0'))
            : 10 ** $digits;
        return $w > 0 && $h > 0 ? self::reduced($w, $h) : null;
    }

    /** The ratio of a pixel size - how a freely dragged master gets its name. */
    public static function ofPixels(int $width, int $height): self
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException("Ratio::ofPixels(): $width x $height is not a size");
        }
        return self::reduced($width, $height);
    }

    public function equals(self $other): bool
    {
        return $this->w === $other->w && $this->h === $other->h;
    }

    /** -1, 0, 1 by width over height - exact, by cross multiplication. */
    public static function compare(self $a, self $b): int
    {
        return $a->w * $b->h <=> $b->w * $a->h;
    }

    /**
     * Where this ratio sits between two others, 0 at `$from` and 1 at `$to`,
     * on a logarithmic scale: `2:1` is as far from `1:1` as `1:2` is. The
     * crop interpolation and the positions on the editor's slider both use it.
     */
    public function position(self $from, self $to): float
    {
        $span = log($to->value) - log($from->value);
        return $span == 0.0 ? 0.0 : (log($this->value) - log($from->value)) / $span;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    private static function reduced(int $w, int $h): self
    {
        [$a, $b] = [$w, $h];
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }
        return new self(intdiv($w, $a), intdiv($h, $a));
    }
}
