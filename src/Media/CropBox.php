<?php

declare(strict_types=1);

namespace Systopic\System\Media;

/**
 * One crop: a ratio, a centre and a scale. A value - every change is a copy.
 *
 * It is always read against a reference image: the master for the crop of a
 * ratio, the turned original for the master crop itself.
 *
 *   centre  `cx`, `cy` - where the middle of the crop lies, as a fraction
 *           0-1 of the reference
 *   scale   magnification over "fill in": 1 is the largest crop of this
 *           ratio that fits into the reference without a border, 2 has half
 *           the edge length. Below 1 the crop is larger than the image and
 *           background shows.
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
    /** Outside all crops set by hand: the nearest one's centre and scale. */
    public const ANCHOR = 'anchor';
    /** Nothing known: the largest crop, centred. */
    public const CENTERED = 'centered';

    public function __construct(
        public readonly Ratio $ratio,
        public readonly float $cx = 0.5,
        public readonly float $cy = 0.5,
        public readonly float $scale = 1.0,
        public readonly string $source = self::CENTERED,
    ) {
        if (!is_finite($cx) || !is_finite($cy) || !is_finite($scale) || $scale <= 0) {
            throw new \InvalidArgumentException("CropBox: centre $cx / $cy, scale $scale");
        }
    }

    /** The same crop under another origin - a stored one is `MANUAL`. */
    public function as(string $source): self
    {
        return new self($this->ratio, $this->cx, $this->cy, $this->scale, $source);
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
        return ['x' => $this->cx - $w / 2, 'y' => $this->cy - $h / 2, 'w' => $w, 'h' => $h];
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
     * the centre moved as little as needed. For computed crops - one set by
     * hand may show background and is left alone.
     */
    public function clamped(float $width, float $height): self
    {
        $inside = new self($this->ratio, $this->cx, $this->cy, max(1.0, $this->scale), $this->source);
        [$w, $h] = $inside->size($width, $height);
        return new self(
            $this->ratio,
            min(max($this->cx, $w / 2), 1 - $w / 2),
            min(max($this->cy, $h / 2), 1 - $h / 2),
            $inside->scale,
            $this->source,
        );
    }
}
