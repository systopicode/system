<?php

declare(strict_types=1);

namespace Systopic\System\Media;

/**
 * The crop of a medium for one ratio - from what was set by hand, or
 * computed. Reads nothing and writes nothing: the caller hands in the
 * master's size, the crops set by hand and the points of interest.
 *
 * In this order, crops set by hand before points of interest:
 *
 *   1. a crop set by hand for this ratio            - taken as it is
 *   2. between two crops set by hand                - interpolated
 *   3. points of interest                           - the largest crop,
 *                                                     moved over them
 *   4. a crop set by hand on one side only          - towards the whole
 *                                                     master; beyond the last
 *                                                     crop towards automatic
 *                                                     (centred, scale 1) at
 *                                                     the outermost ratio
 *   5. nothing                                      - the largest crop, centred
 *
 * The basis (`CropBox::$basis`): what is automatic - nothing known, the
 * outermost ratios - has the master's (`$basis`: a master "fit in" has
 * its crops "fit in" by default); with it `fit`, points of interest are
 * not needed, the whole master shows. Between two crops of one basis that
 * basis; of `fill` and `fit` both are read as `fit` - the crops in between
 * run into the one showing background without a jump. The editor's
 * preview runs the same rules in the browser; `tests/media-crop-resolver.cases.json` holds
 * the cases both must pass.
 */
final class CropResolver
{
    /**
     * @param list<CropBox> $manual the crops set by hand, relative to the master
     * @param list<array{x: float|int, y: float|int, w?: float|int, h?: float|int, weight?: float|int}> $pois
     *        points of interest in fractions of the master: `x`, `y` the
     *        centre, `w`, `h` the size (a point has none)
     * @param list<Ratio> $declared the declared ratios: the outermost of them
     *        on either side is automatic (centred, scale 1) unless set by
     *        hand - beyond the last crop known the crops run towards it.
     *        Without them, automatic at once.
     * @param string $basis the basis of what is automatic: the master's
     */
    public static function resolve(Ratio $ratio, int $width, int $height, array $manual, array $pois = [], array $declared = [], string $basis = CropBox::FILL): CropBox
    {
        $byName = [];
        foreach ($manual as $crop) {
            $byName[$crop->ratio->name] = $crop->as(CropBox::MANUAL);
        }
        if (isset($byName[$ratio->name])) {
            return $byName[$ratio->name];
        }

        [$below, $above] = self::neighbours($ratio, $byName);
        if ($below !== null && $above !== null) {
            return self::between($ratio, $below, $above, $width, $height);
        }
        if ($pois !== [] && $basis === CropBox::FILL) {
            return self::overPois($ratio, $width, $height, $pois);
        }
        if ($byName === []) {
            return new CropBox($ratio, basis: $basis);
        }

        // One side only. The whole master stands in for the missing side, at
        // its own ratio - so the crop opens up towards the full picture. It
        // takes the basis of the nearest crop: centred at scale 1 it is the
        // same frame in either.
        $master = Ratio::ofPixels($width, $height);
        if ($master->equals($ratio)) {
            return new CropBox($ratio, basis: $basis);
        }
        $anchors = $byName + [$master->name => new CropBox($master, basis: ($below ?? $above)->basis)];
        [$below, $above] = self::neighbours($ratio, $anchors);
        if ($below !== null && $above !== null) {
            return self::between($ratio, $below, $above, $width, $height);
        }
        // Outside everything known: the outermost declared ratio of that side
        // is automatic - centred at scale 1, in the master's basis ("fill
        // in", or "fit in" - all of the picture). In between, the crops run
        // from the nearest towards it; it does not inherit the nearest one's
        // alignment or basis.
        $nearest = $below ?? $above;
        $wider = $above === null;
        $end = self::outermost($declared, $wider);
        if ($end !== null && Ratio::compare($end, $ratio) === ($wider ? 1 : -1)) {
            $edge = new CropBox($end, basis: $basis);
            return self::between($ratio, $wider ? $nearest : $edge, $wider ? $edge : $nearest, $width, $height);
        }
        return new CropBox($ratio, basis: $basis);
    }

    /**
     * The widest or the narrowest of a list of ratios.
     *
     * @param list<Ratio> $ratios
     */
    private static function outermost(array $ratios, bool $widest): ?Ratio
    {
        $end = null;
        foreach ($ratios as $ratio) {
            if ($end === null || Ratio::compare($ratio, $end) === ($widest ? 1 : -1)) {
                $end = $ratio;
            }
        }
        return $end;
    }

    /**
     * Every ratio of a list at once, by name - what the editor's slider and
     * the output of a page ask for.
     *
     * @param list<Ratio> $ratios
     * @param list<CropBox> $manual
     * @return array<string, CropBox>
     */
    public static function resolveAll(array $ratios, int $width, int $height, array $manual, array $pois = [], string $basis = CropBox::FILL): array
    {
        $crops = [];
        foreach ($ratios as $ratio) {
            $crops[$ratio->name] = self::resolve($ratio, $width, $height, $manual, $pois, $ratios, $basis);
        }
        return $crops;
    }

    /**
     * The nearest crop on either side of a ratio.
     *
     * @param array<string, CropBox> $crops
     * @return array{0: ?CropBox, 1: ?CropBox} narrower, wider
     */
    private static function neighbours(Ratio $ratio, array $crops): array
    {
        $below = $above = null;
        foreach ($crops as $crop) {
            $side = Ratio::compare($crop->ratio, $ratio);
            if ($side < 0 && ($below === null || Ratio::compare($crop->ratio, $below->ratio) > 0)) {
                $below = $crop;
            } elseif ($side > 0 && ($above === null || Ratio::compare($crop->ratio, $above->ratio) < 0)) {
                $above = $crop;
            }
        }
        return [$below, $above];
    }

    /**
     * The alignment moves in a straight line, the scale in equal factors -
     * by how far the ratio lies between the two, logarithmically. At either
     * end the neighbour itself comes out.
     *
     * Two neighbours of one basis give a crop of that basis. Of two bases,
     * both are read as `fit` first (`CropBox::asFit()`): read as `fill`, a
     * frame showing background would be pulled in up to the `fit` end and
     * jump there.
     */
    private static function between(Ratio $ratio, CropBox $below, CropBox $above, int $width, int $height): CropBox
    {
        if ($below->basis !== $above->basis) {
            $below = $below->asFit($width, $height);
            $above = $above->asFit($width, $height);
        }
        $t = $ratio->position($below->ratio, $above->ratio);
        return (new CropBox(
            $ratio,
            $below->alignX + ($above->alignX - $below->alignX) * $t,
            $below->alignY + ($above->alignY - $below->alignY) * $t,
            $below->scale ** (1 - $t) * $above->scale ** $t,
            CropBox::INTERPOLATED,
            $below->basis,
        ))->clamped();
    }

    /**
     * The largest crop of the ratio, moved along its one free axis so that
     * the points of interest are inside.
     */
    private static function overPois(Ratio $ratio, int $width, int $height, array $pois): CropBox
    {
        [$w, $h] = (new CropBox($ratio))->size($width, $height);
        return new CropBox(
            $ratio,
            CropBox::alignOf(self::slide($w, $pois, 'x', 'w'), $w),
            CropBox::alignOf(self::slide($h, $pois, 'y', 'h'), $h),
            1.0,
            CropBox::POI,
        );
    }

    /**
     * The centre of a window of the given length on 0-1.
     *
     * All points fit: the window moves from the middle as little as needed.
     * They do not: the window holding the most weight wins, and among equals
     * the one nearest to the weighted centre of all points.
     */
    private static function slide(float $length, array $pois, string $position, string $size): float
    {
        if ($length >= 1 - 1e-12) {
            return 0.5;
        }
        $spans = [];
        foreach ($pois as $poi) {
            $weight = (float) ($poi['weight'] ?? 1);
            if ($weight <= 0 || !isset($poi[$position])) {
                continue;
            }
            $half = max(0.0, (float) ($poi[$size] ?? 0)) / 2;
            $spans[] = [max(0.0, $poi[$position] - $half), min(1.0, $poi[$position] + $half), $weight];
        }
        if ($spans === []) {
            return 0.5;
        }
        $room = 1 - $length;
        $centred = $room / 2;
        $first = min(array_column($spans, 0));
        $last = max(array_column($spans, 1));
        if ($last - $first <= $length + 1e-12) {
            return min(max($centred, $last - $length), $first) + $length / 2;
        }

        $total = array_sum(array_column($spans, 2));
        $centre = array_sum(array_map(static fn(array $s): float => ($s[0] + $s[1]) / 2 * $s[2], $spans)) / $total;
        $starts = [$centred];
        foreach ($spans as [$from, $to]) {
            $starts[] = $from;          // the window begins with this point
            $starts[] = $to - $length;  // or ends with it
        }
        $best = null;
        foreach ($starts as $start) {
            $start = min(max($start, 0.0), $room);
            $held = 0.0;
            foreach ($spans as [$from, $to, $weight]) {
                if ($from >= $start - 1e-12 && $to <= $start + $length + 1e-12) {
                    $held += $weight;
                }
            }
            $distance = abs($start + $length / 2 - $centre);
            if ($best === null || $held > $best[1] + 1e-12
                || (abs($held - $best[1]) <= 1e-12 && $distance < $best[2] - 1e-12)) {
                $best = [$start, $held, $distance];
            }
        }
        return $best[0] + $length / 2;
    }
}
