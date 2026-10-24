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
 * Addresses are the legacy ones, byte for byte:
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

    public function __construct(public readonly MediaRow $row) {}

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
            $base = $this->isDerived()
                ? \http::$root . "var/formats/{$this->format}/"
                : \http::$root . 'var/original/';
            return $base . $this->folders . $this->filename . $this->appendix();
        }
    }

    /** The address to show: with the file time, files are cached as immutable and a new crop needs a new address. */
    public string $src {
        get => is_file($this->path) ? $this->url . '?v=' . filemtime($this->path) : $this->url;
    }

    /** The legacy `link` — the same as src. */
    public string $link { get => $this->src; }

    /** The folder of this format in the file system. */
    public string $dir {
        get {
            $base = $this->isDerived()
                ? \fs::$siteRoot . "var/formats/{$this->format}/"
                : \fs::$siteRoot . 'var/original/';
            return $base . $this->folders;
        }
    }

    /** The file of this format in the file system. */
    public string $path { get => $this->dir . $this->filename . $this->appendix(); }

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
