<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Medias;

use Systopic\System\Media\Ratio;
use Systopic\System\Media\VariantRenderer;
use Systopic\System\Media\Variants;
use Systopic\System\Queries\MediaCrops\CropSet;

/**
 * A medium in one ratio, as a page shows it - what `$media->getCrop('16:9')`
 * hands out. The master when no ratio is given.
 *
 *   <?= $media->getCrop('16:9')->toImgHtml() ?>
 *   <?= $media->getCrop('16:9')->toPictureHtml(['(max-width: 600px)' => '1:1']) ?>
 *   <?= $media->getCrop('16:9')->getUrl(2) ?>
 *
 * The page names the ratio, **the browser picks the size**: the tag lists
 * every size the picture has pixels for (`srcset` with widths), and the
 * browser takes the one that fits the box and the screen's real pixels. A
 * file that is not there yet is made on its first request.
 *
 * **A picture whose files are still to be made shows a preview**: the small
 * master every medium has from the start, cut to the crop by CSS. The page's
 * loader (`public/js/core/media.js`) then asks for the real files - the
 * visible pictures first, a few at a time - and puts them in.
 *
 * Every address carries the medium's version (`?v=`): finished files are
 * cached by browsers for a year, and a new crop has to be a new address.
 *
 * `width` and `height` are known without any file: the master's size is in
 * the row, the crop follows from the rules - so the page does not jump when
 * the picture arrives.
 *
 * A medium nothing can be cut from (an svg, a document) is its original.
 */
final class Picture
{
    /** @var array{0: int, 1: int}|null the crop's own pixels; null = size unknown */
    private ?array $own = null;
    /** @var array{0: int, 1: int}|null the master's pixels */
    private ?array $master = null;
    private bool $measured = false;
    /** @var array<int, array<string, int>> media id => file name => time, one look per folder and request */
    private static array $files = [];

    public function __construct(
        private readonly CropSet $set,
        public readonly ?Ratio $ratio = null,
    ) {}

    /** The crop in its own pixels - 0 when the size of the picture is not known yet. */
    public int $width { get => $this->own()[0] ?? 0; }
    public int $height { get => $this->own()[1] ?? 0; }

    /** Can variants be made of this medium at all? */
    public bool $cut { get => VariantRenderer::supports($this->set->media->mimetype); }

    /**
     * One fixed address: this ratio at a size - megapixels null: the size a
     * page without `srcset` would show (about one megapixel).
     */
    public function getUrl(?float $megapixels = null, string $type = 'webp'): string
    {
        if (!$this->cut) {
            return $this->originalUrl();
        }
        if ($megapixels === null) {
            return $this->fallback($this->sources($type))['url'];
        }
        return $this->address(Variants::suffix($this->ratio, Variants::sizeFor($megapixels)), $type);
    }

    /**
     * Every size this picture has pixels for, small to large.
     *
     * @return list<array{url: string, width: int, height: int, megapixels: ?float, made: bool}>
     *         megapixels null = the crop's own pixels; made = the file is there
     */
    public function sources(string $type = 'webp'): array
    {
        $own = $this->own();
        if (!$this->cut) {
            return [];
        }
        if ($own === null) {
            // Size unknown (never rendered, no size in the row): the preview size, made on request.
            $suffix = Variants::suffix($this->ratio, Variants::smallest());
            return [['url' => $this->address($suffix, $type), 'width' => 0, 'height' => 0, 'megapixels' => Variants::smallest(), 'made' => $this->made($suffix, $type)]];
        }
        [$width, $height] = $own;
        $sources = [];
        foreach (Variants::sizesWithin($width, $height) as $size) {
            // The same arithmetic as the renderer's: cut() for a ratio, scaled() for the master.
            if ($this->ratio !== null) {
                [$w, $h] = Variants::pixels($this->ratio, $size);
                if ($w >= $width) {
                    [$w, $h] = [$width, $height];
                }
            } else {
                $factor = min(1.0, sqrt($size * 1e6 / ($width * $height)));
                [$w, $h] = [max(1, (int) round($width * $factor)), max(1, (int) round($height * $factor))];
            }
            $suffix = Variants::suffix($this->ratio, $size);
            $sources[] = [
                'url' => $this->address($suffix, $type),
                'width' => $w,
                'height' => $h,
                'megapixels' => $size,
                'made' => $this->made($suffix, $type),
            ];
        }
        // The picture's own pixels on top, where they are clearly more than the largest size.
        if ($width > end($sources)['width'] * 1.15) {
            $suffix = Variants::suffix($this->ratio, null);
            $sources[] = ['url' => $this->address($suffix, $type), 'width' => $width, 'height' => $height, 'megapixels' => null, 'made' => $this->made($suffix, $type)];
        }
        return $sources;
    }

    /**
     * `<img>` with every size in `srcset`.
     *
     * @param array<string, string|null> $attributes more attributes, or other values for
     *        `alt`, `class`, `sizes`, `loading` (null leaves one out). `sizes` is where a
     *        template that knows its layout says so (`(min-width: 900px) 50vw, 100vw`);
     *        without it the browser measures the box itself (`auto`).
     */
    public function toImgHtml(array $attributes = []): string
    {
        return self::tag('img', $this->imgAttributes($attributes));
    }

    /**
     * `<picture>`: this ratio by default, another one where a media query
     * matches - a square on a phone, the wide crop on a desk.
     *
     * @param array<string, string> $byMedia media query => ratio, first match wins
     * @param array<string, string|null> $attributes as in `toImgHtml()`
     */
    public function toPictureHtml(array $byMedia = [], array $attributes = []): string
    {
        $html = '<picture>';
        foreach ($byMedia as $query => $ratio) {
            $other = new self($this->set, Variants::ratio((string) $ratio) ?? (Ratio::of((string) $ratio) === null ? null : Variants::nearestRatio(Ratio::of((string) $ratio))));
            $sources = $other->sources();
            if ($sources === []) {
                continue;
            }
            $html .= self::tag('source', [
                'media' => (string) $query,
                'srcset' => self::srcset($sources),
                'sizes' => $attributes['sizes'] ?? 'auto, 100vw',
                'width' => $other->width > 0 ? (string) $other->width : null,
                'height' => $other->height > 0 ? (string) $other->height : null,
            ]);
        }
        return $html . self::tag('img', $this->imgAttributes($attributes)) . '</picture>';
    }

    /**
     * `<img>` without a ratio of its own: **the box decides.** The template
     * gives the element a size by CSS; the page's loader measures it, takes
     * the declared ratio nearest to the box and the size the screen needs.
     * Until then, and without a script, it shows the small master, filling
     * the box (`object-fit: cover`).
     *
     * The picture starts only after the script ran - for the main picture of
     * a page, name the ratio (`toImgHtml()`).
     *
     * @param array<string, string|null> $attributes `alt`, `class`, `style` ...
     */
    public function toAutoHtml(array $attributes = []): string
    {
        $media = $this->set->media;
        $master = new self($this->set);
        if (!$this->cut || $master->own() === null) {
            return $master->toImgHtml($attributes);
        }
        $ratios = [];
        foreach (Variants::ratios() as $ratio) {
            $ratios[$ratio->suffix] = [$ratio->w, $ratio->h, (new self($this->set, $ratio))->width];
        }
        $id = (int) $media->id;
        $data = [
            'base' => \http::$root . 'var/formats/' . Files::idPath($id) . Files::variantStem((string) $media->filename) . '--',
            'type' => 'webp',
            'v' => $this->set->version(),
            'sizes' => Variants::sizes(),
            'ratios' => $ratios,
        ];
        return self::tag('img', array_replace([
            'src' => $master->address(Variants::suffix(null, Variants::smallest()), 'webp'),
            'data-media-auto' => (string) json_encode($data, JSON_UNESCAPED_SLASHES),
            'alt' => (string) $media->label,
        ], $attributes, [
            'class' => trim('media-auto ' . ($attributes['class'] ?? '')),
            'style' => trim('object-fit:cover;' . ($attributes['style'] ?? '')),
        ]));
    }

    /**
     * The address of any variant of this medium by its file name part
     * (`original`, `master`, `2mp16by9`) - for the editor, which shows the
     * untouched original and the master themselves.
     */
    public function variantUrl(string $suffix, string $type = 'webp'): string
    {
        return $this->address($suffix, $type);
    }

    /** Forget what was seen in the folders - after pictures were made or removed in the same request. */
    public static function forgetFiles(): void
    {
        self::$files = [];
    }

    // ---------------------------------------------------------------- private

    /** @return array<string, string|null> */
    private function imgAttributes(array $attributes): array
    {
        $media = $this->set->media;
        $class = trim(($this->ratio === null ? 'master' : 'ratio-' . $this->ratio->suffix) . ' ' . ($attributes['class'] ?? ''));
        $sources = $this->sources();
        if ($sources === []) {
            return ['src' => $this->originalUrl(), 'alt' => (string) $media->label, 'class' => $class] + ['loading' => 'lazy'] + $attributes;
        }
        $fallback = $this->fallback($sources);
        $srcset = count($sources) > 1 || $sources[0]['width'] > 0 ? self::srcset($sources) : null;
        if (!$fallback['made'] && $this->own() !== null) {
            // Still to be made: the preview now, the real addresses for the loader.
            [$width, $height] = $this->own();
            return array_replace([
                'src' => "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 $width $height'/%3E",
                'data-media-src' => $fallback['url'],
                'data-media-srcset' => $srcset,
                'sizes' => 'auto, 100vw',
                'width' => (string) $width,
                'height' => (string) $height,
                'alt' => (string) $media->label,
            ], $attributes, ['class' => $class, 'style' => trim($this->previewStyle() . ($attributes['style'] ?? '')) ?: null]);
        }
        return array_replace([
            'src' => $fallback['url'],
            'srcset' => $srcset,
            'sizes' => 'auto, 100vw',
            'width' => $this->width > 0 ? (string) $this->width : null,
            'height' => $this->height > 0 ? (string) $this->height : null,
            'alt' => (string) $media->label,
            'loading' => 'lazy',
        ], $attributes, ['class' => $class]);
    }

    /** The size a browser without `srcset` gets: the largest up to about one megapixel. */
    private function fallback(array $sources): array
    {
        $pick = $sources[0];
        foreach ($sources as $source) {
            if ($source['megapixels'] !== null && $source['megapixels'] <= 1.0) {
                $pick = $source;
            }
        }
        return $pick;
    }

    /** `url 943w, url 1333w` - a source of unknown width has no descriptor. */
    private static function srcset(array $sources): string
    {
        return implode(', ', array_map(static fn(array $s): string => $s['url'] . ($s['width'] > 0 ? ' ' . $s['width'] . 'w' : ''), $sources));
    }

    /** @return array{0: int, 1: int}|null */
    private function own(): ?array
    {
        if ($this->measured) {
            return $this->own;
        }
        $this->measured = true;
        $media = $this->set->media;
        // The master as it was last made; before that, as it follows from the original's size.
        $width = (int) $media->masterWidth;
        $height = (int) $media->masterHeight;
        if ($width <= 0 || $height <= 0) {
            $master = $this->set->master();
            if ($master === null) {
                return null;
            }
            [$width, $height] = [$master->width, $master->height];
        }
        $this->master = [$width, $height];
        if ($this->ratio === null) {
            return $this->own = [$width, $height];
        }
        $pixels = $this->set->cropIn($this->ratio, $width, $height)->pixels($width, $height);
        return $this->own = [$pixels['w'], $pixels['h']];
    }

    /** The address of a variant, with the medium's version: a new crop is a new address. */
    private function address(string $suffix, string $type): string
    {
        $media = $this->set->media;
        $id = (int) $media->id;
        return \http::$root . 'var/formats/' . Files::idPath($id) . Files::variantName((string) $media->filename, $suffix, $type)
            . '?v=' . $this->set->version();
    }

    /** Is the file of a variant there? One look into the medium's folder per request. */
    private function made(string $suffix, string $type): bool
    {
        $media = $this->set->media;
        $id = (int) $media->id;
        self::$files[$id] ??= Files::variants($id);
        return isset(self::$files[$id][Files::variantName((string) $media->filename, $suffix, $type)]);
    }

    /**
     * The preview as a background: the small master, moved and sized so
     * that the box shows the crop. Nothing is rendered for it. Empty when
     * the small master is not there either - the loader asks for it first.
     */
    private function previewStyle(): string
    {
        $suffix = Variants::suffix(null, Variants::smallest());
        if (!$this->made($suffix, 'webp') || $this->master === null) {
            return '';
        }
        $rect = $this->ratio === null
            ? ['x' => 0.0, 'y' => 0.0, 'w' => 1.0, 'h' => 1.0]
            : $this->set->cropIn($this->ratio, $this->master[0], $this->master[1])->rect($this->master[0], $this->master[1]);
        // background-position in percent places the picture by its free room: x / (1 - w).
        $place = static fn(float $start, float $length): float => abs(1 - $length) < 1e-9 ? 50.0 : $start / (1 - $length) * 100;
        return sprintf(
            "background:url('%s') %s%% %s%% / %s%% %s%% no-repeat;",
            $this->address($suffix, 'webp'),
            self::number($place($rect['x'], $rect['w'])), self::number($place($rect['y'], $rect['h'])),
            self::number(100 / $rect['w']), self::number(100 / $rect['h']),
        );
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private function originalUrl(): string
    {
        $media = $this->set->media;
        return \http::$root . 'var/original/' . Files::idPath((int) $media->id) . $media->filename;
    }

    /** @param array<string, string|null> $attributes */
    private static function tag(string $name, array $attributes): string
    {
        $html = '<' . $name;
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== '' || $key === 'alt' && $value !== null) {
                $html .= ' ' . $key . '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
        }
        return $html . '>';
    }
}
