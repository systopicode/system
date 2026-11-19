<?php

declare(strict_types=1);

namespace Systopic\System\Media;

/**
 * Where the master lies in the original - the one place that converts
 * between the two.
 *
 * The master is the original, mirrored, turned by any angle, and cut to the
 * master crop. With a free angle that is a turned rectangle in the original,
 * so this is a general affine mapping, not a table of the four right angles.
 *
 * The order is the renderer's: mirror, turn, crop.
 *
 * The master crop is read against the turned picture:
 *
 *   scale   `fill`: 1 is "fill in" as the picture stands - the largest frame
 *           of the ratio that lies inside the turned picture, so no
 *           background shows. At a right angle that is the plain fill in; at
 *           a tilt the frame's corners touch the picture's edges.
 *           `fit`: 1 is "fit in" - the box around the turned picture inside
 *           the frame.
 *   align   the alignment in the box around the turned picture (what
 *           Imagick's rotateImage() hands back), 0.5 / 0.5 being centred -
 *           read by the crop's basis (`CropBox`).
 *
 * `largest()` is the master of the largest area inside the turned picture,
 * its ratio free - what a turned picture without a master crop shows.
 *
 * Positive angles turn clockwise, as in CSS and Imagick. The original is the
 * one after EXIF orientation.
 */
final class MasterGeometry
{
    /** The master in whole pixels - one pixel of it is one of the original. */
    public readonly int $width;
    public readonly int $height;

    /** The box around the turned original: what the master crop is aligned in. */
    public readonly float $referenceWidth;
    public readonly float $referenceHeight;

    /** The middle of the frame, a fraction of the box around the turned picture - where the renderer cuts. */
    public readonly float $centreFractionX;
    public readonly float $centreFractionY;

    /** The frame, unrounded, in pixels of the original. */
    private readonly float $frameWidth;
    private readonly float $frameHeight;
    /** The middle of the frame in the original. */
    private readonly float $centreX;
    private readonly float $centreY;
    private readonly float $cos;
    private readonly float $sin;
    /** Height of the largest frame of the ratio inside the turned picture - scale 1. */
    private readonly float $fill;

    public function __construct(
        public readonly int $originalWidth,
        public readonly int $originalHeight,
        public readonly CropBox $crop,
        public readonly float $angle = 0.0,
        public readonly bool $flip = false,
    ) {
        if ($originalWidth <= 0 || $originalHeight <= 0 || !is_finite($angle)) {
            throw new \InvalidArgumentException("MasterGeometry: $originalWidth x $originalHeight, angle $angle");
        }
        // Quarter turns exactly, the rest by sine and cosine: cos(90°) is not 0 in floats.
        $quarters = (int) ceil($angle / 90 - 0.5);
        $tilt = deg2rad($angle - $quarters * 90);
        [$qc, $qs] = [[1, 0], [0, 1], [-1, 0], [0, -1]][(($quarters % 4) + 4) % 4];
        $this->cos = $qc * cos($tilt) - $qs * sin($tilt);
        $this->sin = $qs * cos($tilt) + $qc * sin($tilt);

        $c = abs($this->cos);
        $s = abs($this->sin);
        $this->referenceWidth = $originalWidth * $c + $originalHeight * $s;
        $this->referenceHeight = $originalWidth * $s + $originalHeight * $c;

        // A frame w x h inside the turned picture: w·c + h·s <= width, w·s + h·c <= height.
        $r = $crop->ratio->value;
        $this->fill = min($originalWidth / ($r * $c + $s), $originalHeight / ($r * $s + $c));
        $this->frameHeight = $this->fill / ($crop->basis === CropBox::FIT ? $crop->scale * $this->fitScale() : $crop->scale);
        $this->frameWidth = $this->frameHeight * $r;
        $this->width = max(1, (int) round($this->frameWidth));
        $this->height = max(1, (int) round($this->frameHeight));

        // The crop is aligned in the box around the turned picture; its middle, back through turn and mirror into the original.
        $this->centreFractionX = CropBox::centreOf($crop->alignX, $this->frameWidth / $this->referenceWidth, $crop->basis);
        $this->centreFractionY = CropBox::centreOf($crop->alignY, $this->frameHeight / $this->referenceHeight, $crop->basis);
        $x = ($this->centreFractionX - 0.5) * $this->referenceWidth;
        $y = ($this->centreFractionY - 0.5) * $this->referenceHeight;
        $dx = $x * $this->cos + $y * $this->sin;
        $dy = -$x * $this->sin + $y * $this->cos;
        $this->centreX = $originalWidth / 2 + ($flip ? -$dx : $dx);
        $this->centreY = $originalHeight / 2 + $dy;
    }

    /** The whole original, untouched - the master of a medium nobody edited. */
    public static function whole(int $width, int $height): self
    {
        return new self($width, $height, new CropBox(Ratio::ofPixels($width, $height)));
    }

    /**
     * A point of the original in the master, both in pixels.
     *
     * @return array{0: float, 1: float}
     */
    public function toMaster(float $x, float $y): array
    {
        $dx = $x - $this->centreX;
        $dy = $y - $this->centreY;
        if ($this->flip) {
            $dx = -$dx;
        }
        return [
            $dx * $this->cos - $dy * $this->sin + $this->frameWidth / 2,
            $dx * $this->sin + $dy * $this->cos + $this->frameHeight / 2,
        ];
    }

    /**
     * A point of the master in the original, both in pixels.
     *
     * @return array{0: float, 1: float}
     */
    public function toOriginal(float $x, float $y): array
    {
        $x -= $this->frameWidth / 2;
        $y -= $this->frameHeight / 2;
        $dx = $x * $this->cos + $y * $this->sin;
        $dy = -$x * $this->sin + $y * $this->cos;
        return [$this->centreX + ($this->flip ? -$dx : $dx), $this->centreY + $dy];
    }

    /**
     * The four corners of the master in the original, clockwise from the
     * master's top left.
     *
     * @return list<array{0: float, 1: float}>
     */
    public function corners(): array
    {
        return [
            $this->toOriginal(0, 0),
            $this->toOriginal($this->frameWidth, 0),
            $this->toOriginal($this->frameWidth, $this->frameHeight),
            $this->toOriginal(0, $this->frameHeight),
        ];
    }

    /**
     * Does the master lie inside the original - no background in it? The
     * editor's "the edge of the picture stays outside" asks exactly this.
     */
    public function insideOriginal(float $tolerance = 0.5): bool
    {
        foreach ($this->corners() as [$x, $y]) {
            if ($x < -$tolerance || $y < -$tolerance
                || $x > $this->originalWidth + $tolerance || $y > $this->originalHeight + $tolerance) {
                return false;
            }
        }
        return true;
    }

    /**
     * The scale of "fit in" over "fill in": the whole turned picture lies
     * inside the frame, background around it. Never above 1. A master crop
     * of the basis `fit` has its scale over this.
     */
    public function fitScale(): float
    {
        return min($this->fill * $this->crop->ratio->value / $this->referenceWidth, $this->fill / $this->referenceHeight);
    }

    /**
     * The master of the largest area that lies inside the turned picture,
     * no background - its ratio follows from the angle. What a turned
     * picture without a master crop shows.
     *
     * Two cases, which meet without a jump:
     *
     *   all four corners on an edge     near a right angle: the frame touches
     *                                   every edge of the turned picture
     *   two corners on the long edges   near the diagonal: there the frame
     *                                   would only get smaller by reaching the
     *                                   short edges, so it stops at the long
     *                                   ones - width and height follow from
     *                                   half the short side
     *
     * At a right angle it is the whole picture; in between the ratio goes
     * where the area is largest - wider than the picture first, through 1:1
     * at 45 degrees.
     */
    public static function largest(int $width, int $height, float $angle, bool $flip = false): self
    {
        $probe = new self($width, $height, new CropBox(Ratio::ofPixels($width, $height)), $angle, $flip);
        [$w, $h] = self::largestSize($width, $height, abs($probe->cos), abs($probe->sin));
        return new self($width, $height, new CropBox(Ratio::ofPixels(max(1, (int) round($w)), max(1, (int) round($h)))), $angle, $flip);
    }

    /**
     * Width and height of the largest frame inside a picture turned by an
     * angle of the given |cos| and |sin|.
     *
     * @return array{0: float, 1: float}
     */
    public static function largestSize(float $width, float $height, float $c, float $s): array
    {
        $long = max($width, $height);
        $short = min($width, $height);
        if ($short <= 2 * $s * $c * $long || abs($s - $c) < 1e-12) {
            $half = $short / 2;
            return $width >= $height ? [$half / $s, $half / $c] : [$half / $c, $half / $s];
        }
        $d = $c * $c - $s * $s;
        return [($width * $c - $height * $s) / $d, ($height * $c - $width * $s) / $d];
    }

    /**
     * Points of interest, stored in fractions of the original, as fractions
     * of the master - what `CropResolver` takes. A point whose middle lies
     * outside the master is left out; one that reaches over the edge is cut.
     *
     * @param list<array<string, mixed>> $pois `x`, `y` the centre, `w`, `h` the size
     * @return list<array<string, mixed>>
     */
    public function poisToMaster(array $pois): array
    {
        $inMaster = [];
        foreach ($pois as $poi) {
            if (!isset($poi['x'], $poi['y'])) {
                continue;
            }
            $halfW = (float) ($poi['w'] ?? 0) / 2;
            $halfH = (float) ($poi['h'] ?? 0) / 2;
            $xs = $ys = [];
            foreach ([[-1, -1], [1, -1], [1, 1], [-1, 1]] as [$sx, $sy]) {
                [$x, $y] = $this->toMaster(
                    ($poi['x'] + $sx * $halfW) * $this->originalWidth,
                    ($poi['y'] + $sy * $halfH) * $this->originalHeight,
                );
                $xs[] = $x / $this->frameWidth;
                $ys[] = $y / $this->frameHeight;
            }
            $middleX = (min($xs) + max($xs)) / 2;
            $middleY = (min($ys) + max($ys)) / 2;
            if ($middleX < 0 || $middleX > 1 || $middleY < 0 || $middleY > 1) {
                continue;
            }
            [$left, $right] = [max(0.0, min($xs)), min(1.0, max($xs))];
            [$top, $bottom] = [max(0.0, min($ys)), min(1.0, max($ys))];
            $inMaster[] = ['x' => ($left + $right) / 2, 'y' => ($top + $bottom) / 2,
                'w' => $right - $left, 'h' => $bottom - $top] + $poi;
        }
        return $inMaster;
    }
}
