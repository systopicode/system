<?php

declare(strict_types=1);

namespace Systopic\System\Media;

use Systopic\Db\Scope;
use Systopic\Db\Type\Text;
use Systopic\System\Queries\MediaCrops\CropSet;
use Systopic\System\Queries\MediaCrops\MediaCrops;
use Systopic\System\Tables\MediaCrops\Operator;
use Systopic\System\Tables\Medias\Files;
use Systopic\System\Tables\Medias\Picture;

/**
 * What the image editor does to a medium: set the master, set the crop of a
 * ratio, put one back to automatic, set the points of interest - and what
 * the editor is told about a medium (`state()`).
 *
 * Each edit is worked out first (`master()`, `crop()`, `reset()`, `pois()`:
 * which rows to save, which to remove - nothing is written), then applied
 * (`apply()`): written, everything made of the medium removed, its preview
 * made again.
 *
 * A new master moves every crop set by hand: they are fractions of the
 * master, so each is carried over to the same piece of the picture.
 */
final class CropEdits
{
    /** More points than this are a mistake of the sender. */
    private const MAX_POIS = 50;

    // ------------------------------------------------------------ the edits

    /**
     * The master: its crop (null = automatic, the largest frame inside the
     * turned picture) and its look.
     *
     * The crops of the ratios keep their alignment and scale in the master
     * (`CropBox`): flush left stays flush left, "fill in" stays "fill in",
     * one inside the old master is inside the new one. Turned by quarters
     * or mirrored, the alignment turns and mirrors with the picture - a crop
     * on the left of it is on top after a quarter turn. `clipped` stays empty;
     * nothing is cut any more.
     *
     * @return array{save: list<object>, remove: list<object>, clipped: list<string>}
     */
    public static function master(CropSet $set, ?CropBox $crop, array $look): array
    {
        $media = $set->media;
        $width = (int) $media->width;
        $height = (int) $media->height;
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('CropEdits::master(): the size of the original is not known');
        }
        $was = $set->look();

        $row = $set->row(Variants::MASTER);
        if ($crop !== null) {
            $row = Operator::put($row, $media, $crop, master: true);
        } elseif ($row !== null) {
            $row->ratio = $row->alignX = $row->alignY = $row->scale = null;
        }
        $neutral = Operator::look(null);
        $look = ['r' => $look['r'] ?? 0, 'f' => $look['f'] ?? 0] + $look + $neutral;
        if ($row === null && $crop === null && self::sameLook($look, $neutral)) {
            return ['save' => [], 'remove' => [], 'clipped' => []];   // nothing set, nothing to set
        }
        $row ??= Operator::put(null, $media, new CropBox(Ratio::ofPixels($width, $height)), master: true);
        if ($crop === null) {
            $row->ratio = $row->alignX = $row->alignY = $row->scale = null;
        }
        $row = Operator::withLook($row, $look);

        $save = [$row];
        $quarters = (int) round(((float) $look['r'] - (float) $was['r']) / 90);
        $mirrored = (bool) $look['f'] !== (bool) $was['f'];
        if ($quarters % 4 !== 0 || $mirrored) {
            foreach ($set->rows as $format => $other) {
                $box = $format === Variants::MASTER ? null : Operator::box($other);
                if ($box !== null) {
                    $save[] = Operator::put($other, $media, self::reoriented($box, (float) $was['r'], (bool) $was['f'], (float) $look['r'], (bool) $look['f']));
                }
            }
        }
        return ['save' => $save, 'remove' => [], 'clipped' => []];
    }

    /**
     * A crop's alignment after the picture under it was turned by quarters
     * or mirrored: the point it is aligned to goes where that piece of the
     * picture goes. The order is the renderer's - mirror, then turn.
     */
    public static function reoriented(CropBox $box, float $fromAngle, bool $fromFlip, float $toAngle, bool $toFlip): CropBox
    {
        $quarter = static fn(float $angle): int => (((int) round($angle / 90)) % 4 + 4) % 4;
        // A place in the master (fractions) back to the unturned, unmirrored picture ...
        [$x, $y] = [$box->alignX, $box->alignY];
        for ($i = 0; $i < $quarter($fromAngle); $i++) {
            [$x, $y] = [$y, 1 - $x];          // a quarter back, anticlockwise
        }
        if ($fromFlip) {
            $x = 1 - $x;
        }
        // ... and forward into the new one.
        if ($toFlip) {
            $x = 1 - $x;
        }
        for ($i = 0; $i < $quarter($toAngle); $i++) {
            [$x, $y] = [1 - $y, $x];          // a quarter on, clockwise
        }
        return new CropBox($box->ratio, $x, $y, $box->scale, $box->source);
    }

    /**
     * The crop of a ratio, set by hand. Null when the ratio is not one of
     * the declared ones - only those exist.
     *
     * @return array{save: list<object>, remove: list<object>, clipped: list<string>}|null
     */
    public static function crop(CropSet $set, CropBox $crop): ?array
    {
        $ratio = Variants::ratio($crop->ratio->name);
        if ($ratio === null) {
            return null;
        }
        return ['save' => [Operator::put($set->row($ratio->name), $set->media, $crop)], 'remove' => [], 'clipped' => []];
    }

    /**
     * Back to automatic: the crop of a ratio is computed again, the master
     * (`Variants::MASTER`) is the whole picture again - its look stays.
     *
     * @return array{save: list<object>, remove: list<object>, clipped: list<string>}
     */
    public static function reset(CropSet $set, string $format): array
    {
        if ($format === Variants::MASTER) {
            return self::master($set, null, $set->look());
        }
        $row = $set->row($format);
        return ['save' => [], 'remove' => $row !== null && Operator::box($row) !== null ? [$row] : [], 'clipped' => []];
    }

    /**
     * The points of interest of the picture. `$dropCrops`: the crops set by
     * hand go, so the points decide everywhere (crops set by hand go before
     * points - the editor asks when the first point is set).
     *
     * @param array<mixed> $pois `x`, `y` the middle, `w`, `h` the size - fractions of the original
     * @return array{save: list<object>, remove: list<object>, clipped: list<string>}
     */
    public static function pois(CropSet $set, array $pois, bool $dropCrops = false): array
    {
        $clean = [];
        foreach (array_slice(array_values($pois), 0, self::MAX_POIS) as $poi) {
            if (!is_array($poi) || !is_numeric($poi['x'] ?? null) || !is_numeric($poi['y'] ?? null)) {
                continue;
            }
            $unit = static fn(mixed $value): float => round(max(0.0, min(1.0, is_numeric($value) ? (float) $value : 0.0)), 6);
            $entry = ['x' => $unit($poi['x']), 'y' => $unit($poi['y']), 'w' => $unit($poi['w'] ?? 0), 'h' => $unit($poi['h'] ?? 0)];
            $weight = is_numeric($poi['weight'] ?? null) ? max(0.0, min(100.0, (float) $poi['weight'])) : 1.0;
            if ($weight !== 1.0) {
                $entry['weight'] = $weight;
            }
            foreach (['type', 'source'] as $key) {
                if (is_string($poi[$key] ?? null) && preg_match('~^[a-z][a-z0-9_-]{0,31}$~', $poi[$key])) {
                    $entry[$key] = $poi[$key];
                }
            }
            $clean[] = $entry;
        }
        $media = $set->media;
        $media->pois = $clean === [] ? null : new Text((string) json_encode($clean));
        $remove = [];
        if ($dropCrops) {
            foreach ($set->rows as $format => $row) {
                if ($format !== Variants::MASTER && Operator::box($row) !== null) {
                    $remove[] = $row;
                }
            }
        }
        return ['save' => [$media], 'remove' => $remove, 'clipped' => []];
    }

    // --------------------------------------------------------------- apply

    /**
     * Writes an edit and brings the medium's files in line: everything made
     * of it goes (a crop of one ratio moves the computed ones beside it), the
     * preview is made again. Returns the medium as it is now.
     *
     * @param array{save: list<object>, remove: list<object>} $edit
     */
    public static function apply(Scope $scope, CropSet $set, array $edit): CropSet
    {
        $mediaId = (int) $set->media->id;
        if ($edit['save'] !== [] || $edit['remove'] !== []) {
            $scope->write($edit['save'], $edit['remove']);
            Files::removeVariants($mediaId);
            Picture::forgetFiles();
            MediaCrops::forget($mediaId);
            VariantServer::preview($scope, $mediaId);
            Picture::forgetFiles();
        }
        return MediaCrops::one($scope, $mediaId) ?? $set;
    }

    // --------------------------------------------------------------- state

    /**
     * Everything the editor's browser side works with, ready for JSON.
     *
     *   original  size, and the picture the master mode shows
     *   master    where it lies in the original, its look, its pixels
     *   ratios    every declared ratio: place on the slider (0 = widest,
     *             logarithmic), its crop in the master and where that came from
     *   pois      as stored, fractions of the original
     *
     * @return array<string, mixed>
     */
    public static function state(CropSet $set): array
    {
        $media = $set->media;
        $master = $set->master();
        $look = $set->look();
        $ratios = array_values(Variants::ratios());
        $widest = $ratios[0] ?? null;
        $narrowest = $ratios === [] ? null : $ratios[count($ratios) - 1];
        $crops = $master === null ? [] : $set->crops();
        $list = [];
        foreach ($ratios as $ratio) {
            $crop = $crops[$ratio->name] ?? null;
            $list[] = [
                'name' => $ratio->name,
                'suffix' => $ratio->suffix,
                'w' => $ratio->w,
                'h' => $ratio->h,
                'position' => $widest === null || $widest->equals($narrowest) ? 0.5 : round(1 - $ratio->position($narrowest, $widest), 6),
                'ax' => $crop?->alignX,
                'ay' => $crop?->alignY,
                'scale' => $crop?->scale,
                'source' => $crop?->source,
            ];
        }
        $picture = new Picture($set);
        $box = Operator::box($set->row(Variants::MASTER));
        return [
            'mediaId' => (int) $media->id,
            'version' => $set->version(),
            'cut' => $picture->cut,
            'original' => [
                'width' => (int) $media->width,
                'height' => (int) $media->height,
                'url' => $picture->cut ? $picture->variantUrl('original') : null,
            ],
            'master' => $master === null ? null : [
                'manual' => $box !== null,
                'ratio' => $master->crop->ratio->name,
                'ax' => $master->crop->alignX,
                'ay' => $master->crop->alignY,
                'scale' => $master->crop->scale,
                'angle' => $look['r'],
                'flip' => (bool) $look['f'],
                'look' => ['b' => $look['b'], 'c' => $look['c'], 's' => $look['s']],
                'width' => $master->width,
                'height' => $master->height,
                'fitScale' => $master->fitScale(),
                'sources' => array_map(static fn(array $s): array => ['url' => $s['url'], 'width' => $s['width'], 'height' => $s['height']], $picture->sources()),
            ],
            'ratios' => $list,
            'pois' => $set->pois(),
            'sizes' => Variants::sizes(),
        ];
    }

    /** A crop from what a request sends - null when it is not one. */
    public static function boxFrom(mixed $ratio, mixed $ax, mixed $ay, mixed $scale): ?CropBox
    {
        $read = is_string($ratio) ? Ratio::of($ratio) : null;
        foreach ([$ax, $ay, $scale] as $value) {
            if (!is_numeric($value) || !is_finite((float) $value)) {
                return null;
            }
        }
        // An alignment far past the picture or a scale near nothing shows no picture at all.
        if ($read === null || abs((float) $ax - 0.5) > 10.5 || abs((float) $ay - 0.5) > 10.5 || (float) $scale < 0.05 || (float) $scale > 100) {
            return null;
        }
        return new CropBox($read, (float) $ax, (float) $ay, (float) $scale, CropBox::MANUAL);
    }

    private static function sameLook(array $a, array $b): bool
    {
        foreach (['b', 'c', 's', 'r', 'f'] as $key) {
            if ((float) ($a[$key] ?? 0) !== (float) ($b[$key] ?? 0)) {
                return false;
            }
        }
        return true;
    }
}
