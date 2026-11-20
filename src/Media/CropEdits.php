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
            $row->ratio = $row->alignX = $row->alignY = $row->scale = $row->basis = null;
        }
        $neutral = Operator::look(null);
        $look = ['r' => $look['r'] ?? 0, 'f' => $look['f'] ?? 0] + $look + $neutral;
        if ($row === null && $crop === null && self::sameLook($look, $neutral)) {
            return ['save' => [], 'remove' => [], 'clipped' => []];   // nothing set, nothing to set
        }
        $row ??= Operator::put(null, $media, new CropBox(Ratio::ofPixels($width, $height)), master: true);
        if ($crop === null) {
            $row->ratio = $row->alignX = $row->alignY = $row->scale = $row->basis = null;
        }
        $row = Operator::withLook($row, $look);

        $save = [$row];
        $quarters = (int) round(((float) $look['r'] - (float) $was['r']) / 90);
        $mirrored = (bool) $look['f'] !== (bool) $was['f'];
        if ($quarters % 4 !== 0 || $mirrored) {
            foreach ($set->rows as $format => $other) {
                $box = $format === Variants::MASTER ? null : Operator::box($other);
                if ($box !== null) {
                    // a fixed format's row keeps its name
                    $save[] = Operator::put($other, $media, self::reoriented($box, (float) $was['r'], (bool) $was['f'], (float) $look['r'], (bool) $look['f']), format: (string) $format);
                }
            }
        }
        return ['save' => $save, 'remove' => [], 'clipped' => []];
    }

    /**
     * A crop's alignment after the picture under it was turned by quarters
     * or mirrored: the point it is aligned to goes where that piece of the
     * picture goes. The order is the renderer's - mirror, then turn. The
     * same in either basis: in `fit` the point of the crop and the point of
     * the picture it lies on (1 - a) go the same way.
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
        return new CropBox($box->ratio, $x, $y, $box->scale, $box->source, $box->basis);
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
     * Several crops of ratios at once - what the editor applies with its
     * tick: each `{ratio, ax, ay, scale, basis}` set by hand, or
     * `{ratio, auto: true}` back to automatic. Null when one of them is not a
     * crop of a declared ratio; then nothing is stored.
     *
     * @param array<mixed> $crops
     * @return array{save: list<object>, remove: list<object>, clipped: list<string>}|null
     */
    public static function crops(CropSet $set, array $crops): ?array
    {
        $edit = ['save' => [], 'remove' => [], 'clipped' => []];
        $seen = [];
        foreach (array_values($crops) as $crop) {
            $ratio = is_array($crop) && is_string($crop['ratio'] ?? null) ? Variants::ratio($crop['ratio']) : null;
            if ($ratio === null || isset($seen[$ratio->name])) {
                return null;
            }
            $seen[$ratio->name] = true;
            if (($crop['auto'] ?? false) === true) {
                $row = $set->row($ratio->name);
                if ($row !== null && Operator::box($row) !== null) {
                    $edit['remove'][] = $row;
                }
                continue;
            }
            $box = self::boxFrom($ratio->name, $crop['ax'] ?? null, $crop['ay'] ?? null, $crop['scale'] ?? null, $crop['basis'] ?? null);
            if ($box === null) {
                return null;
            }
            $edit['save'][] = Operator::put($set->row($ratio->name), $set->media, $box);
        }
        return $edit;
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
                'basis' => $crop?->basis,
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
                'basis' => $master->crop->basis,
                'angle' => $look['r'],
                'flip' => (bool) $look['f'],
                'look' => ['b' => $look['b'], 'c' => $look['c'], 's' => $look['s']],
                'width' => $master->width,
                'height' => $master->height,
                'fitScale' => $master->fitScale(),
                'sources' => array_map(static fn(array $s): array => ['url' => $s['url'], 'width' => $s['width'], 'height' => $s['height']], $picture->sources()),
            ],
            'ratios' => $list,
            'formats' => $master === null ? [] : self::formatsState($set, $master->width, $master->height, $narrowest, $widest),
            'pois' => $set->pois(),
            'sizes' => Variants::sizes(),
        ];
    }

    /**
     * The fixed formats for the editor's tab Formats: each with its place on
     * the slider (as a ratio would have, within 0-1), its crop - set by hand
     * or computed - and the computed one it goes back to (`auto`).
     *
     * @return list<array<string, mixed>>
     */
    private static function formatsState(CropSet $set, int $width, int $height, ?Ratio $narrowest, ?Ratio $widest): array
    {
        $list = [];
        foreach (Variants::formats() as $name => $format) {
            $crop = $set->formatCrop($name, $width, $height);
            $auto = $set->computedFormatCrop($format['ratio'], $width, $height);
            $position = $widest === null || $narrowest === null || $widest->equals($narrowest) ? 0.5 : 1 - $format['ratio']->position($narrowest, $widest);
            $list[] = [
                'name' => $name,
                'label' => $format['label'],
                'w' => $format['ratio']->w,
                'h' => $format['ratio']->h,
                'width' => $format['width'],
                'height' => $format['height'],
                'type' => $format['type'],
                'position' => round(max(0.0, min(1.0, $position)), 6),
                'ax' => $crop->alignX,
                'ay' => $crop->alignY,
                'scale' => $crop->scale,
                'basis' => $crop->basis,
                'source' => $crop->source,
                'auto' => ['ax' => $auto->alignX, 'ay' => $auto->alignY, 'scale' => $auto->scale, 'basis' => $auto->basis, 'source' => $auto->source],
            ];
        }
        return $list;
    }

    /**
     * The crops of fixed formats at once - the editor's tab Formats applies
     * them so: each `{format, ax, ay, scale, basis}` set by hand (its ratio
     * is the format's), or `{format, auto: true}` back to computed. Null when
     * one of them is none (`formatsProblem()` says why); then nothing is
     * stored. A format's crop is no anchor of the ratios: nothing else changes.
     *
     * @param array<mixed> $crops
     * @return array{save: list<object>, remove: list<object>, clipped: list<string>}|null
     */
    public static function formats(CropSet $set, array $crops): ?array
    {
        if (self::formatsProblem($crops) !== null) {
            return null;
        }
        $edit = ['save' => [], 'remove' => [], 'clipped' => []];
        foreach (array_values($crops) as $crop) {
            $format = Variants::format($crop['format']);
            $row = $set->row($format['name']);
            if (($crop['auto'] ?? false) === true) {
                if ($row !== null && Operator::box($row) !== null) {
                    if ($row->left === null && $row->top === null) {
                        $edit['remove'][] = $row;
                    } else {
                        // A row of the old editor under the same name (shareImage): its old crop stays.
                        $row->ratio = $row->alignX = $row->alignY = $row->scale = $row->basis = null;
                        $row->version = (int) $row->version + 1;
                        $edit['save'][] = $row;
                    }
                }
                continue;
            }
            $box = self::boxFrom($format['ratio']->name, $crop['ax'], $crop['ay'], $crop['scale'], $crop['basis'] ?? null);
            $edit['save'][] = Operator::put($row, $set->media, $box, format: $format['name']);
        }
        return $edit;
    }

    /** Why a list of fixed formats' crops is not taken (`formats()` gives null) - null when it is. */
    public static function formatsProblem(array $crops): ?string
    {
        $seen = [];
        foreach (array_values($crops) as $crop) {
            $name = is_array($crop) && is_string($crop['format'] ?? null) ? $crop['format'] : null;
            if ($name === null) {
                return 'Every crop names its format.';
            }
            $format = Variants::format($name);
            if ($format === null) {
                return "There is no fixed format $name.";
            }
            if (isset($seen[$name])) {
                return "The format $name comes twice.";
            }
            $seen[$name] = true;
            if (($crop['auto'] ?? false) === true) {
                continue;
            }
            $problem = self::boxProblem($format['ratio']->name, $crop['ax'] ?? null, $crop['ay'] ?? null, $crop['scale'] ?? null, $crop['basis'] ?? null);
            if ($problem !== null) {
                return $name . ': ' . $problem;
            }
        }
        return null;
    }

    /**
     * A crop from what a request sends - null when it is not one, or not
     * one its basis allows (`boxProblem()` tells why): an alignment within
     * 0-1, in `fill` a scale of at least 1 (what the editor sends; rounding
     * is brought in). No basis is `fill`.
     */
    public static function boxFrom(mixed $ratio, mixed $ax, mixed $ay, mixed $scale, mixed $basis = null): ?CropBox
    {
        if (self::boxProblem($ratio, $ax, $ay, $scale, $basis) !== null) {
            return null;
        }
        return (new CropBox(Ratio::of($ratio), (float) $ax, (float) $ay, (float) $scale, CropBox::MANUAL, $basis ?? CropBox::FILL))->clamped();
    }

    /** Why what a request sends is no crop (`boxFrom()`) - null when it is one. For the editor to show. */
    public static function boxProblem(mixed $ratio, mixed $ax, mixed $ay, mixed $scale, mixed $basis = null): ?string
    {
        if (!is_string($ratio) || Ratio::of($ratio) === null) {
            return 'Not a ratio: ' . json_encode($ratio) . '.';
        }
        foreach (['alignment x' => $ax, 'alignment y' => $ay, 'scale' => $scale] as $name => $value) {
            if (!is_numeric($value) || !is_finite((float) $value)) {
                return "The $name is no number: " . json_encode($value) . '.';
            }
        }
        $basis ??= CropBox::FILL;
        if (!in_array($basis, [CropBox::FILL, CropBox::FIT], true)) {
            return 'The basis is fill or fit, not ' . json_encode($basis) . '.';
        }
        // Rounding is brought in (clamped()): the editor stops a hair beyond an edge.
        foreach (['x' => $ax, 'y' => $ay] as $axis => $align) {
            if ((float) $align < -1e-4 || (float) $align > 1 + 1e-4) {
                return "The alignment $axis lies outside 0-1: $align.";
            }
        }
        if ($basis === CropBox::FILL && (float) $scale < 1 - 1e-4) {
            return "In fill the scale is at least 1 - the edge of the picture stays outside; it is $scale.";
        }
        // A scale near nothing shows no picture at all.
        if ((float) $scale < 0.05 || (float) $scale > 100) {
            return "The scale lies between 0.05 and 100; it is $scale.";
        }
        return null;
    }

    /** Why a list of crops is not taken (`crops()` gives null) - null when it is. For the editor to show. */
    public static function cropsProblem(array $crops): ?string
    {
        $seen = [];
        foreach (array_values($crops) as $crop) {
            $name = is_array($crop) && is_string($crop['ratio'] ?? null) ? $crop['ratio'] : null;
            if ($name === null) {
                return 'Every crop names its ratio.';
            }
            $ratio = Variants::ratio($name);
            if ($ratio === null) {
                return "The ratio $name is not declared.";
            }
            if (isset($seen[$ratio->name])) {
                return "The ratio {$ratio->name} comes twice.";
            }
            $seen[$ratio->name] = true;
            if (($crop['auto'] ?? false) === true) {
                continue;
            }
            $problem = self::boxProblem($ratio->name, $crop['ax'] ?? null, $crop['ay'] ?? null, $crop['scale'] ?? null, $crop['basis'] ?? null);
            if ($problem !== null) {
                return $ratio->name . ': ' . $problem;
            }
        }
        return null;
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
