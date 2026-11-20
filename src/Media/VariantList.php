<?php

declare(strict_types=1);

namespace Systopic\System\Media;

use Systopic\System\Queries\MediaCrops\CropSet;
use Systopic\System\Tables\Medias\Files;
use Systopic\System\Tables\Medias\Media;
use Systopic\System\Tables\Medias\Picture;

/**
 * Every file a picture has or can have, for the file editor's overview
 * (cms: editor/file/image/files/): which are made, which are made on their
 * first request.
 *
 *   grid     across the sizes (and its own pixels), down the master and
 *            then each declared ratio: one variant per cell (2mp4by3); a
 *            size the picture has not the pixels for is made in its own
 *            pixels (`note`)
 *   fixed    the fixed formats (shareImage), exact pixels
 *   names    the format names templates ask for (setFormat('fullWidth'),
 *            the system's mmlThumb, portrait …) and the variant each one is
 *   original the file as it was uploaded (var/original/) - no variant
 *   others   files in the folder none of these is - left from before
 *
 * Every address opens the file; one that is not made yet is made then.
 */
final class VariantList
{
    /**
     * @return array{
     *   sizes: list<float>,
     *   grid: list<array{title: string, cells: array<string, ?array>}>,
     *   fixed: list<array>, names: list<array>, others: list<array>,
     *   made: int, all: int
     * } a cell / an entry: name, file, url, width, height, made, bytes, note;
     *   the cells by size (`0.5`, `2` …) and `own`
     */
    public static function of(CropSet $set): array
    {
        $media = $set->media;
        $id = (int) $media->id;
        $filename = (string) $media->filename;
        $files = Files::variants($id);
        $dir = Files::variantDir($id);
        $listed = [];
        $counts = ['made' => 0, 'all' => 0];
        $entry = static function (string $name, string $file, string $url, int $width = 0, int $height = 0, string $note = '') use ($files, $dir, &$listed, &$counts): array {
            $listed[$file] = true;
            $made = isset($files[$file]);
            $counts['all']++;
            $counts['made'] += $made ? 1 : 0;
            return ['name' => $name, 'file' => $file, 'url' => $url, 'width' => $width, 'height' => $height,
                'made' => $made, 'bytes' => $made ? (int) @filesize($dir . $file) : 0, 'note' => $note];
        };
        $master = new Picture($set);
        $sizes = Variants::sizes();

        // One row: its sizes as the page lists them (sources()), its own pixels always.
        $row = static function (string $title, Picture $picture) use ($entry, $filename, $sizes): array {
            $cells = array_fill_keys(array_map('strval', $sizes), null);
            foreach ($picture->sources() as $source) {
                if ($source['megapixels'] !== null) {
                    $suffix = Variants::suffix($picture->ratio, $source['megapixels']);
                    $cells[(string) $source['megapixels']] = $entry($suffix, Files::variantName($filename, $suffix, 'webp'), $source['url'], $source['width'], $source['height']);
                }
            }
            // A size the picture has not the pixels for is an address too: made on request, in its own pixels - never blown up.
            foreach ($cells as $size => $cell) {
                if ($cell === null) {
                    $suffix = Variants::suffix($picture->ratio, (float) $size);
                    $cells[$size] = $entry($suffix, Files::variantName($filename, $suffix, 'webp'), $picture->variantUrl($suffix), $picture->width, $picture->height, 'its own pixels: no more there');
                }
            }
            $suffix = Variants::suffix($picture->ratio, null);
            $cells['own'] = $entry($suffix, Files::variantName($filename, $suffix, 'webp'), $picture->variantUrl($suffix), $picture->width, $picture->height);
            return ['title' => $title, 'cells' => $cells];
        };
        $grid = [$row('Master', $master)];
        foreach (Variants::ratios() as $ratio) {
            $grid[] = $row($ratio->name, new Picture($set, $ratio));
        }

        $fixed = [];
        foreach (Variants::formats() as $name => $format) {
            $fixed[] = $entry($name, Files::variantName($filename, $name, $format['type']), $master->variantUrl($name, $format['type']), $format['width'], $format['height'], $format['label']);
        }

        // The editor's copy of the original (--original.webp) is no format: not listed, and no "other file" either.
        $listed[Files::variantName($filename, 'original', 'webp')] = true;
        $names = [];
        $file = new Media($media);
        foreach (class_exists('mediaFormats') ? \mediaFormats::get() : [] as $name => $config) {
            if (!is_string($name) || $name === Variants::MASTER || Variants::format($name) !== null) {
                continue;
            }
            $named = $file->setFormat($name);
            $path = (string) $named->path;
            $made = is_file($path);
            $counts['all']++;
            $counts['made'] += $made ? 1 : 0;
            $base = basename(explode('?', (string) $named->url, 2)[0]);
            // the variant the name is: the part of the file name after the stem (5mp1by1)
            $variant = str_contains($base, '--') ? (string) preg_replace('~^.*--|\.[a-z]+$~', '', $base) : 'the original';
            $names[] = ['name' => $name, 'file' => $base, 'url' => (string) $named->src, 'width' => 0, 'height' => 0,
                'made' => $made, 'bytes' => $made ? (int) filesize($path) : 0,
                'note' => (in_array($name, \mediaFormats::SYSTEM, true) ? 'system' : 'format name') . ': ' . $variant];
        }

        $others = [];
        foreach ($files as $name => $time) {
            if (!isset($listed[$name])) {
                $others[] = ['name' => $name, 'file' => $name, 'url' => \http::$root . 'var/formats/' . Files::idPath($id) . $name, 'width' => 0, 'height' => 0,
                    'made' => true, 'bytes' => (int) @filesize($dir . $name), 'note' => 'none of the above'];
            }
        }
        $original = Files::originalPath($media);
        return ['original' => ['name' => $filename, 'url' => \http::$root . 'var/original/' . Files::idPath($id) . $filename,
                'width' => (int) $media->width, 'height' => (int) $media->height, 'bytes' => is_file($original) ? (int) filesize($original) : 0],
            'sizes' => $sizes, 'grid' => $grid, 'fixed' => $fixed, 'names' => $names, 'others' => $others] + $counts;
    }
}
