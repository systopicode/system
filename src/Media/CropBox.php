<?php

declare(strict_types=1);

namespace Systopic\System\Media;

/**
 * One crop: a ratio, an alignment, a scale and the basis both are read on.
 * A value - every change is a copy.
 *
 * It is always read against a reference image: the master for the crop of a
 * ratio, the turned original for the master crop itself.
 *
 *   basis   `fill` - the edge of the picture stays outside the crop:
 *             scale 1 is "fill in", the largest crop of this ratio inside
 *             the reference; the point at fraction a of the crop lies on
 *             the point at fraction a of the reference (as with
 *             `background-position` in per cent). 0 = flush left (top),
 *             1 = flush right (bottom), 0.5 = centred.
 *           `fit` - background may show: scale 1 is "fit in", the whole
 *             reference inside the crop; the point at fraction a of the
 *             crop lies on the point at fraction 1 - a of the reference.
 *             0 = the picture lies left of (above) the crop, touching it,
 *             1 = right of (below) it, 0.5 = centred.
 *   align   `alignX`, `alignY`, 0-1 in either basis: across that range the
 *           crop goes from one end to the other.
 *   scale   magnification over scale 1 of the basis: 2 has half the edge
 *           length.
 *
 * Why an alignment and not the middle: a `fill` crop with 0 <= align <= 1
 * and scale >= 1 lies inside the reference, whatever the sizes. When the
 * reference changes - a new master under the crops of the ratios - a crop
 * keeps its alignment: flush left stays flush left, "fit in" stays "fit in".
 *
 * Why two bases: in `fill`, where the crop is exactly as long as the
 * reference (at scale 1 always one axis), the alignment of that axis does
 * not move it and is kept for when the reference changes - there is nothing
 * to move either. In `fit` the crop moves along every axis at any size,
 * also where it is exactly as long as the picture.
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
    /** Outside all crops set by hand: the nearest one's alignment, towards scale 1. */
    public const ANCHOR = 'anchor';
    /** Nothing known: the largest crop, centred. */
    public const CENTERED = 'centered';

    /** The edge of the picture stays outside; scale 1 = "fill in". */
    public const FILL = 'fill';
    /** Background may show; scale 1 = "fit in". */
    public const FIT = 'fit';

    public function __construct(
        public readonly Ratio $ratio,
        public readonly float $alignX = 0.5,
        public readonly float $alignY = 0.5,
        public readonly float $scale = 1.0,
        public readonly string $source = self::CENTERED,
        public readonly string $basis = self::FILL,
    ) {
        if (!is_finite($alignX) || !is_finite($alignY) || !is_finite($scale) || $scale <= 0) {
            throw new \InvalidArgumentException('CropBox: align ' . var_export($alignX, true) . ' / ' . var_export($alignY, true) . ', scale ' . var_export($scale, true));
        }
        if ($basis !== self::FILL && $basis !== self::FIT) {
            throw new \InvalidArgumentException("CropBox: basis $basis");
        }
    }

    /** The same crop under another origin - a stored one is `MANUAL`. */
    public function as(string $source): self
    {
        return new self($this->ratio, $this->alignX, $this->alignY, $this->scale, $source, $this->basis);
    }

    /**
     * The alignment along one axis that puts the middle of a crop of that
     * length (fractions) at a place - within 0-1. In `fill`, where the
     * length is the whole axis, `$keep`: any alignment is the same there.
     */
    public static function alignOf(float $centre, float $length, float $keep = 0.5, string $basis = self::FILL): float
    {
        if ($basis === self::FIT) {
            $align = (1 + $length / 2 - $centre) / (1 + $length);
        } else {
            $room = 1 - $length;
            if (abs($room) < 1e-9) {
                return $keep;
            }
            $align = ($centre - $length / 2) / $room;
        }
        return max(0.0, min(1.0, $align));
    }

    /** The middle of a crop of that length (fractions) under an alignment. */
    public static function centreOf(float $align, float $length, string $basis = self::FILL): float
    {
        return $basis === self::FIT
            ? 1 + $length / 2 - $align * (1 + $length)
            : $align + $length * (0.5 - $align);
    }

    /**
     * The crop whose middle lies at a place of the reference - in `fill` with
     * the alignment of `$keep` where the alignment of an axis does not matter.
     */
    public static function at(Ratio $ratio, float $cx, float $cy, float $scale, float $width, float $height, ?self $keep = null, string $source = self::MANUAL, string $basis = self::FILL): self
    {
        [$w, $h] = (new self($ratio, scale: $scale, basis: $basis))->size($width, $height);
        return new self($ratio, self::alignOf($cx, $w, $keep?->alignX ?? 0.5, $basis), self::alignOf($cy, $h, $keep?->alignY ?? 0.5, $basis), $scale, $source, $basis);
    }

    /**
     * The middle of the crop as a fraction of a reference of the given size.
     *
     * @return array{0: float, 1: float}
     */
    public function centre(float $width, float $height): array
    {
        [$w, $h] = $this->size($width, $height);
        return [self::centreOf($this->alignX, $w, $this->basis), self::centreOf($this->alignY, $h, $this->basis)];
    }

    /** The scale over "fill in": in `fill` the scale itself, in `fit` that times the scale of "fit in". */
    public function fillScale(float $width, float $height): float
    {
        return $this->basis === self::FIT ? $this->scale * self::fitScale($this->ratio, $width, $height) : $this->scale;
    }

    /**
     * Width and height of the crop as fractions of a reference of the given
     * size. At scale 1 one of them is 1: the crop fills that axis (`fill`),
     * or the picture fills that axis of the crop (`fit`).
     *
     * @return array{0: float, 1: float}
     */
    public function size(float $width, float $height): array
    {
        $r = $this->ratio->value;
        $scale = $this->fillScale($width, $height);
        return $r >= $width / $height
            ? [1 / $scale, $width / $r / $height / $scale]
            : [$height * $r / $width / $scale, 1 / $scale];
    }

    /**
     * The same frame in the basis `fill` - where crops of two bases are
     * interpolated. The alignment within 0-1: a frame reaching past the
     * edge comes in.
     */
    public function asFill(float $width, float $height): self
    {
        if ($this->basis === self::FILL) {
            return $this;
        }
        [$cx, $cy] = $this->centre($width, $height);
        $scale = $this->fillScale($width, $height);
        [$w, $h] = (new self($this->ratio, scale: $scale))->size($width, $height);
        return new self($this->ratio, self::alignOf($cx, $w), self::alignOf($cy, $h), $scale, $this->source);
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
        [$cx, $cy] = [self::centreOf($this->alignX, $w, $this->basis), self::centreOf($this->alignY, $h, $this->basis)];
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
     * The scale of "fit in" over "fill in": the whole reference lies inside
     * the crop, with background on two sides. Never above 1; exactly 1 when
     * the ratios match.
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
     * The nearest crop its basis allows: the alignment within 0-1, and in
     * `fill` the scale at least 1 - then it lies inside the reference. In
     * `fit` the scale stays; the picture at least touches the crop.
     */
    public function clamped(float $width = 0, float $height = 0): self
    {
        return new self(
            $this->ratio,
            min(max($this->alignX, 0.0), 1.0),
            min(max($this->alignY, 0.0), 1.0),
            $this->basis === self::FILL ? max(1.0, $this->scale) : $this->scale,
            $this->source,
            $this->basis,
        );
    }
}
