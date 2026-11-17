<?php

declare(strict_types=1);

namespace Systopic\System\Media;

/**
 * One crop: a ratio, an alignment and a scale. A value - every change is a copy.
 *
 * It is always read against a reference image: the master for the crop of a
 * ratio, the turned original for the master crop itself.
 *
 *   align   `alignX`, `alignY` - how the crop is aligned in the reference,
 *           as with `background-position` in per cent: the point at that
 *           fraction of the crop lies on the point at that fraction of the
 *           reference. 0 = flush left (top), 1 = flush right (bottom),
 *           0.5 = centred. Outside 0-1 the crop reaches past the edge.
 *   scale   magnification over "fill in": 1 is the largest crop of this
 *           ratio that fits into the reference without a border, 2 has half
 *           the edge length. Below 1 the crop is larger than the image and
 *           background shows.
 *
 * Why an alignment and not the middle: inside the reference means simply
 * 0 <= align <= 1 and scale >= 1, whatever the sizes. When the reference
 * changes - a new master under the crops of the ratios - a crop keeps its
 * alignment and stays inside; flush left stays flush left.
 *
 * Where the crop is exactly as long as the reference (at scale 1 always
 * one axis), the alignment of that axis does not move it - it is kept for
 * when the reference changes.
 *
 * One scale instead of a width and a height: two values could disagree with
 * the ratio and stretch the picture, one cannot.
 */
final class CropBox
{
    /** Set by hand in the editor - stored, and taken as it is. */
    public const MANUAL = 'manual';
    /** Between two crops set by hand. */
    public const INTERPOLATED = 'interpolated';
    /** Moved so that the points of interest are inside. */
    public const POI = 'poi';
    /** Outside all crops set by hand: the nearest one's alignment, towards "fill in". */
    public const ANCHOR = 'anchor';
    /** Nothing known: the largest crop, centred. */
    public const CENTERED = 'centered';

    public function __construct(
        public readonly Ratio $ratio,
        public readonly float $alignX = 0.5,
        public readonly float $alignY = 0.5,
        public readonly float $scale = 1.0,
        public readonly string $source = self::CENTERED,
    ) {
        if (!is_finite($alignX) || !is_finite($alignY) || !is_finite($scale) || $scale <= 0) {
            throw new \InvalidArgumentException("CropBox: align $alignX / $alignY, scale $scale");
        }
    }

    /** The same crop under another origin - a stored one is `MANUAL`. */
    public function as(string $source): self
    {
        return new self($this->ratio, $this->alignX, $this->alignY, $this->scale, $source);
    }

    /** The alignment along one axis that puts the middle of a crop of that length (fractions) at a place. */
    public static function alignOf(float $centre, float $length, float $keep = 0.5): float
    {
        $room = 1 - $length;
        if (abs($room) < 1e-9) {
            return $keep;       // as long as the reference: any alignment is the same here
        }
        return max(-10.0, min(11.0, ($centre - $length / 2) / $room));
    }

    /** The middle of a crop of that length (fractions) under an alignment. */
    public static function centreOf(float $align, float $length): float
    {
        return $align + $length * (0.5 - $align);
    }

    /**
     * The crop whose middle lies at a place of the reference - the same
     * crop as before where the alignment of an axis does not matter.
     */
    public static function at(Ratio $ratio, float $cx, float $cy, float $scale, float $width, float $height, ?self $keep = null, string $source = self::MANUAL): self
    {
        [$w, $h] = (new self($ratio, scale: $scale))->size($width, $height);
        return new self($ratio, self::alignOf($cx, $w, $keep?->alignX ?? 0.5), self::alignOf($cy, $h, $keep?->alignY ?? 0.5), $scale, $source);
    }

    /**
     * The middle of the crop as a fraction of a reference of the given size.
     *
     * @return array{0: float, 1: float}
     */
    public function centre(float $width, float $height): array
    {
        [$w, $h] = $this->size($width, $height);
        return [self::centreOf($this->alignX, $w), self::centreOf($this->alignY, $h)];
    }

    /**
     * Width and height of the crop as fractions of a reference of the given
     * size. At scale 1 one of them is 1: the crop fills that axis.
     *
     * @return array{0: float, 1: float}
     */
    public function size(float $width, float $height): array
    {
        $r = $this->ratio->value;
        return $r >= $width / $height
            ? [1 / $this->scale, $width / $r / $height / $this->scale]
            : [$height * $r / $width / $this->scale, 1 / $this->scale];
    }

    /**
     * The crop as a rectangle in fractions of the reference: `x`, `y` its
     * top left corner. Not limited to 0-1 - a crop may reach past the image.
     *
     * @return array{x: float, y: float, w: float, h: float}
     */
    public function rect(float $width, float $height): array
    {
        [$w, $h] = $this->size($width, $height);
        [$cx, $cy] = [self::centreOf($this->alignX, $w), self::centreOf($this->alignY, $h)];
        return ['x' => $cx - $w / 2, 'y' => $cy - $h / 2, 'w' => $w, 'h' => $h];
    }

    /**
     * The same rectangle in whole pixels of the reference.
     *
     * @return array{x: int, y: int, w: int, h: int}
     */
    public function pixels(int $width, int $height): array
    {
        $rect = $this->rect($width, $height);
        return [
            'x' => (int) round($rect['x'] * $width),
            'y' => (int) round($rect['y'] * $height),
            'w' => max(1, (int) round($rect['w'] * $width)),
            'h' => max(1, (int) round($rect['h'] * $height)),
        ];
    }

    /**
     * The scale of "fit in": the whole reference lies inside the crop, with
     * background on two sides. Never above 1; exactly 1 when the ratios match.
     */
    public static function fitScale(Ratio $ratio, float $width, float $height): float
    {
        $r = $ratio->value;
        $reference = $width / $height;
        return min($r / $reference, $reference / $r);
    }

    /** Does the crop lie inside the reference - no background anywhere? */
    public function inside(float $width, float $height, float $tolerance = 1e-9): bool
    {
        $rect = $this->rect($width, $height);
        return $rect['x'] >= -$tolerance && $rect['y'] >= -$tolerance
            && $rect['x'] + $rect['w'] <= 1 + $tolerance && $rect['y'] + $rect['h'] <= 1 + $tolerance;
    }

    /**
     * The nearest crop that lies inside the reference: the scale at least 1,
     * the alignment within 0-1. For computed crops - one set by hand may show
     * background and is left alone.
     */
    public function clamped(float $width, float $height): self
    {
        return new self(
            $this->ratio,
            min(max($this->alignX, 0.0), 1.0),
            min(max($this->alignY, 0.0), 1.0),
            max(1.0, $this->scale),
            $this->source,
        );
    }
}
