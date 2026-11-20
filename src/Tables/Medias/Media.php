<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Medias;

use Systopic\System\Tables\Medias\Model as MediaRow;

/**
 * One medium as a template reads it — a page's, an event's, a contact's —
 * successor of the legacy `media` object. Lives in the system (with the rows,
 * files and formats) so projects without the CMS package use it too.
 *
 *   $media->setFormat('fullWidth')->src     the address of that format
 *   $media->width, ->height, ->label, ->mimetype, ->type, ->filename
 *   <?= $media->setFormat('background') ?>  an <img> (a <video> for an mp4)
 *
 * **A value, not a state.** `setFormat()` and `addClass()` return a copy; the
 * legacy object changed itself, and the change stuck to the next reader of
 * the same medium. Every template in the projects calls them chained
 * (`->setFormat('x')->src`), so nothing notices — except that it is right now.
 *
 * **A named format is an alias now.** `setFormat('fullWidth')` no longer has a
 * crop or a folder of its own: it stands for a ratio at a size
 * (`Media\Variants::alias()`), and `src`, `url`, `path` and the tag give the
 * address of that picture (`var/formats/<id path><name>--5mp.webp?v=…`). The
 * crop is the one of the ratio, set in the image editor or computed. New
 * templates name the ratio: `getCrop('16:9')`.
 *
 * The addresses of the old layout remain for the original, and for a medium
 * nothing can be cut from (svg, pdf):
 *
 *   original   <root>var/original/<id path><filename>
 *   format     <root>var/formats/<format>/<id path><filename>.webp   (images)
 *                                                    <filename>.jpg    (pdf)
 *                                                    <filename>        (svg)
 *
 * A format file that does not exist yet is made on its first request
 * (system media.php). An svg has no formats: setFormat() keeps it 'original'.
 */
final class Media
{
    /** null = no format chosen: the original */
    public private(set) ?string $format = null;

    /** @var list<string> */
    private array $classNames = [];

    public function __construct(public readonly MediaRow $row)
    {
        // Its crops are read with those of every other medium of the page, on the first getCrop().
        \Systopic\System\Queries\MediaCrops\MediaCrops::announce((int) $row->id);
    }

    /**
     * The medium of a media node — a page's share image, say. Null when
     * there is none; the legacy `media::get()` handed out row 1 instead.
     */
    public static function byNodeId(\Systopic\Db\Scope $scope, int $nodeId): ?self
    {
        $row = \Systopic\System\Queries\MediaNodes\MediaNodes::byNodeIds($scope, [$nodeId])[$nodeId] ?? null;
        return $row === null ? null : new self($row);
    }

    // ------------------------------------------------------------ the row

    /** The media id (`cms_medias.media_id`) — it names the folders. */
    public int $id { get => (int) $this->row->id; }

    /** The media node — what a page's share image or a link points at. */
    public ?int $nodeId { get => $this->row->relationId('node'); }

    public string $label { get => (string) $this->row->label; }
    public ?string $group { get => $this->row->group; }
    public ?string $type { get => $this->row->type; }
    public ?string $mimetype { get => $this->row->mimetype; }
    public string $filename { get => (string) $this->row->filename; }
    public int $width { get => (int) $this->row->width; }
    public int $height { get => (int) $this->row->height; }
    public ?string $backgroundType { get => $this->row->backgroundType; }

    // -------------------------------------------------------------- crops

    /**
     * The medium in one aspect ratio (`16:9`), or - no ratio - its master:
     *
     *   <?= $media->getCrop('16:9')->toImgHtml() ?>     <img> with every size, the browser picks
     *   <?= $media->getCrop('16:9')->getUrl(2) ?>       one address, about 2 megapixels
     *
     * Only the ratios of the settings exist; any other is shown in the
     * nearest of them. The crop itself is the one set in the editor, or
     * computed from its neighbours and the points of interest.
     *
     * Successor of `setFormat()`: the medium has no "current format" any
     * more - a crop is a thing of its own.
     */
    public function getCrop(?string $ratio = null): Picture
    {
        $read = $ratio === null ? null : \Systopic\System\Media\Ratio::of($ratio);
        $set = \Systopic\System\Queries\MediaCrops\MediaCrops::lazy($this->id)
            ?? new \Systopic\System\Queries\MediaCrops\CropSet($this->row, []);
        return new Picture($set, $read === null ? null : \Systopic\System\Media\Variants::nearestRatio($read));
    }

    /**
     * `<img>` that fits whatever box the page gives it: the browser's side
     * picks the ratio nearest to the box and the size the screen needs.
     * The template sizes the element by CSS. See `Picture::toAutoHtml()`.
     *
     * @param array<string, string|null> $attributes `alt`, `class`, `style` ...
     */
    public function toAutoHtml(array $attributes = []): string
    {
        return $this->getCrop()->toAutoHtml($attributes);
    }

    // --------------------------------------------------------- variations

    /** A copy in another format ('original' or a name from settings.php). */
    public function setFormat(string $format): static
    {
        $copy = clone $this;
        $copy->format = $this->mimetype === 'image/svg+xml' ? 'original' : $format;
        return $copy;
    }

    /** A copy with one more css class on its tag. */
    public function addClass(string $className): static
    {
        $copy = clone $this;
        $copy->classNames[] = $className;
        return $copy;
    }

    // ---------------------------------------------------------- addresses

    /** '000/42/' for media id 42 — the legacy `media::id2folders()`. */
    public string $folders {
        get {
            $zerofill = str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
            return substr($zerofill, 0, 3) . '/' . substr($zerofill, 3, 5) . '/';
        }
    }

    /** The address of this format, bare - to build other names from or to store. */
    public string $url {
        get {
            $fixed = $this->fixed();
            if ($fixed !== null) {
                return explode('?', $this->getCrop()->variantUrl($fixed['name'], $fixed['type']), 2)[0];
            }
            $variant = $this->variant();
            if ($variant !== null) {
                return explode('?', $variant->variantUrl($this->suffix()), 2)[0];
            }
            $base = $this->isDerived()
                ? \http::$root . "var/formats/{$this->format}/"
                : \http::$root . 'var/original/';
            return $base . $this->folders . $this->filename . $this->appendix();
        }
    }

    /** The address to show: with the file time, files are cached as immutable and a new crop needs a new address. */
    public string $src {
        get {
            $fixed = $this->fixed();
            if ($fixed !== null) {
                return $this->getCrop()->variantUrl($fixed['name'], $fixed['type']);
            }
            $variant = $this->variant();
            if ($variant !== null) {
                return $variant->variantUrl($this->suffix());
            }
            return is_file($this->path) ? $this->url . '?v=' . filemtime($this->path) : $this->url;
        }
    }

    /** The legacy `link` — the same as src. */
    public string $link { get => $this->src; }

    /** The still of a video, beside its original (name.mp4 → name.webp) - '' when there is none. */
    public string $poster {
        get {
            if (!str_starts_with((string) $this->mimetype, 'video/')) {
                return '';
            }
            $name = (string) preg_replace('~\.[^.]+$~', '.webp', $this->filename);
            $path = \fs::$siteRoot . 'var/original/' . $this->folders . $name;
            return is_file($path) ? \http::$root . 'var/original/' . $this->folders . $name : '';
        }
    }

    /** The folder of this format in the file system. */
    public string $dir {
        get {
            if ($this->fixed() !== null || $this->variant() !== null) {
                return Files::variantDir($this->id);
            }
            $base = $this->isDerived()
                ? \fs::$siteRoot . "var/formats/{$this->format}/"
                : \fs::$siteRoot . 'var/original/';
            return $base . $this->folders;
        }
    }

    /** The file of this format in the file system. */
    public string $path {
        get {
            $fixed = $this->fixed();
            if ($fixed !== null) {
                return Files::variantPath($this->id, $this->filename, $fixed['name'], $fixed['type']);
            }
            return $this->variant() !== null
                ? Files::variantPath($this->id, $this->filename, $this->suffix(), 'webp')
                : $this->dir . $this->filename . $this->appendix();
        }
    }

    /**
     * The tag: `<img>`, for an mp4 a `<video>` of the original with its poster.
     * Unescaped label and classes, as the legacy wrote them.
     */
    public function __toString(): string
    {
        $classnames = $this->classNames !== [] ? ' ' . implode(' ', $this->classNames) : '';
        if ($this->mimetype === 'video/mp4') {
            $video = $this->setFormat('original');
            $poster = \http::$siteUrl . 'var/original/' . $this->folders . str_replace('mp4', 'webp', $this->filename);
            return "<video poster='$poster' controls class='original$classnames' src='{$video->src}'></video>";
        }
        return "<img alt='{$this->label}' class='{$this->format}$classnames' src='{$this->src}'/>";
    }

    private function isDerived(): bool
    {
        return $this->format !== null && $this->format !== 'original';
    }

    /**
     * The fixed format the name stands for (`Variants::formats()`: exact
     * pixels, a crop of its own - `bild--shareImage.jpg`), for a medium
     * pictures are made of. Null for any other name: an alias.
     *
     * eturn array{name: string, width: int, height: int, type: string}|null
     */
    private function fixed(): ?array
    {
        if (!$this->isDerived()) {
            return null;
        }
        $format = \Systopic\System\Media\Variants::format((string) $this->format);
        return $format !== null && \Systopic\System\Media\VariantRenderer::supports($this->mimetype) ? $format : null;
    }

    /**
     * What the named format stands for: a ratio (null: the master) at a
     * size (null: its own pixels). A name the config
     * (`packages.system.media.aliases`) does not know is the master at about
     * a megapixel.
     *
     * \return array{ratio: ?\Systopic\System\Media\Ratio, megapixels: ?float}
     */
    private function alias(): array
    {
        $formats = class_exists('mediaFormats') ? \mediaFormats::get() : [];
        return \Systopic\System\Media\Variants::alias(is_array($formats[$this->format] ?? null) ? $formats[$this->format] : ['megapixels' => 1]);
    }

    /** The file name part of that picture: `5mp`, `5mp1by1`. */
    private function suffix(): string
    {
        $alias = $this->alias();
        return \Systopic\System\Media\Variants::suffix($alias['ratio'], $alias['megapixels']);
    }

    /**
     * The picture a named format is shown as - null where the legacy
     * addresses apply: no format, the original, or a medium nothing can be
     * cut from.
     */
    private function variant(): ?Picture
    {
        if (!$this->isDerived()) {
            return null;
        }
        $picture = $this->getCrop($this->alias()['ratio']?->name);
        return $picture->cut ? $picture : null;
    }

    /** What the format file adds to the name: .webp for images, .jpg for a pdf, nothing for svg. */
    private function appendix(): string
    {
        if (!$this->isDerived()) {
            return '';
        }
        $mime = (string) $this->mimetype;
        return match (true) {
            $mime === 'application/pdf'                       => '.jpg',
            $mime === 'image/svg', $mime === 'image/svg+xml'  => '',
            str_starts_with($mime, 'image/')                  => '.webp',
            default                                           => '',
        };
    }
}
