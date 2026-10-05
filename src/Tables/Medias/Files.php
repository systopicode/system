<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Medias;

/**
 * The files of a medium below `public/var/`:
 *
 *   var/original/<id path><filename>               the upload (and an mp4's poster <name>.webp)
 *   var/formats/<format>/<id path><filename>[.ext] made on first request (system media.php)
 *
 * The id path is the media id in two levels: 42 → `000/42/`. Every method
 * changes the file system only; the row is the caller's to save.
 */
final class Files
{
    /** Mime types the CMS takes, with the extension it knows them by — the legacy `mediaInfo::$mimetypes`. */
    public const MIMETYPES = [
        'image/webp' => 'webp', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
        'image/tiff' => 'tif', 'image/svg+xml' => 'svg', 'application/pdf' => 'pdf',
        'application/vnd.sketchup.skp' => 'skp', 'application/x-sketchup' => 'skp',
        'application/msword' => 'doc', 'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/x-zip-compressed' => 'zip', 'audio/mpeg' => 'mp3', 'video/mpeg' => 'mpg',
        'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'text/vcard' => 'vcf', 'text/x-vcard' => 'vcf',
    ];

    /** 42 → '000/42/'. */
    public static function idPath(int $mediaId): string
    {
        $zerofill = str_pad((string) $mediaId, 5, '0', STR_PAD_LEFT);
        return substr($zerofill, 0, 3) . '/' . substr($zerofill, 3, 5) . '/';
    }

    /** var/ of the site, native spelling, with trailing separator. */
    public static function varDir(): string
    {
        return rtrim(\fs::toNative((string) \fs::$siteRoot . 'var'), '\\/') . DIRECTORY_SEPARATOR;
    }

    public static function originalDir(Model $media): string
    {
        return self::varDir() . 'original' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::idPath((int) $media->id));
    }

    public static function originalPath(Model $media): string
    {
        return self::originalDir($media) . $media->filename;
    }

    /**
     * Move an uploaded file into place as the medium's original — replacing
     * whatever was there, formats included — and read type and mime from it.
     */
    public static function store(Model $media, string $tmpFile, string $filename): void
    {
        self::remove($media);   // a replaced file leaves no stale formats behind
        $dir = self::originalDir($media);
        @mkdir($dir, 0777, true);
        $media->filename = $filename;
        $path = self::originalPath($media);
        rename($tmpFile, $path);
        // Upload temps are often 0600; Apache (psacln) cannot serve those (403).
        @chmod($dir, 0775);
        @chmod($path, 0664);
        self::describe($media);
    }

    /** Write the original from a string (an edited svg). Formats of it go. */
    public static function writeOriginal(Model $media, string $content): void
    {
        file_put_contents(self::originalPath($media), $content);
        self::removeFormats($media);
    }

    /**
     * A generated format (or the `alias` working copy) into
     * `var/formats/<format>/<idPath>` — written by media.php's generator.
     * A `.webp` of the same name from before goes.
     */
    public static function writeFormat(\Imagick $image, int $mediaId, string $format, string $filename): void
    {
        if ($mediaId <= 0 || $format === '') {
            return;
        }
        $dir = self::varDir() . 'formats' . DIRECTORY_SEPARATOR . $format . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, self::idPath($mediaId));
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (is_file($dir . $filename . '.webp')) {
            @unlink($dir . $filename . '.webp');
        }
        $image->writeImage($dir . $filename);
    }

    /** The poster of an mp4: `<name>.webp` next to the original, older posters go. */
    public static function writePoster(Model $media, string $webp): void
    {
        $dir = self::originalDir($media);
        foreach (glob($dir . '*.webp') ?: [] as $old) {
            @unlink($old);
        }
        file_put_contents($dir . str_replace('.mp4', '', (string) $media->filename) . '.webp', $webp);
    }

    /**
     * Rename the original and every format file made of it.
     *
     * @return bool false when the original could not be renamed — then nothing changed
     */
    public static function rename(Model $media, string $filename): bool
    {
        $old = (string) $media->filename;
        $id = self::idPath((int) $media->id);
        if (!@rename(self::originalPath($media), self::originalDir($media) . $filename)) {
            return false;
        }
        foreach (glob(self::varDir() . 'formats' . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $formatDir) {
            $dir = $formatDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $id);
            foreach (glob($dir . $old . '*') ?: [] as $file) {
                @rename($file, $dir . $filename . substr(basename($file), strlen($old)));
            }
        }
        $media->filename = $filename;
        return true;
    }

    /** The original and every format of a medium — its folders go. */
    public static function remove(Model $media): void
    {
        if ($media->id === null) {
            return;
        }
        self::removeDir(self::originalDir($media));
        self::removeFormats($media);
    }

    /** Every format file of a medium; they are made again on request. */
    public static function removeFormats(Model $media): void
    {
        $id = str_replace('/', DIRECTORY_SEPARATOR, self::idPath((int) $media->id));
        foreach (glob(self::varDir() . 'formats' . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $formatDir) {
            self::removeDir($formatDir . DIRECTORY_SEPARATOR . $id);
        }
    }

    /**
     * Type and mime type from the original — `mediaInfo::updateMimeInfo()`.
     * An unknown mime is kept as `nosupport:<mime>`, as before.
     */
    public static function describe(Model $media): void
    {
        $path = self::originalPath($media);
        if (!is_file($path)) {
            $media->mimetype = 'nofile/null';
            return;
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $type = (string) preg_replace('~/.*$~', '', $mime);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            [$mime, $type] = ['image/svg+xml', 'image'];
        } elseif ($ext === 'skp') {
            [$mime, $type] = ['application/vnd.sketchup.skp', 'application'];
        } elseif (!isset(self::MIMETYPES[$mime])) {
            $mime = 'nosupport:' . $mime;
        } elseif (preg_match('~360\.zip$~', $path)) {
            [$mime, $type] = ['image/gif', 'image360'];
        }
        $media->mimetype = $mime;
        $media->type = $type;
        if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') {
            $size = @getimagesize($path);
            if ($size !== false) {
                [$media->width, $media->height] = [(int) $size[0], (int) $size[1]];
            }
        }
    }

    /**
     * Every file of one format ('total': of all formats) — they are made again
     * on request. The crop parameters stay.
     *
     * @return int how many format folders went
     */
    public static function clearFormat(string $format): int
    {
        $root = self::varDir() . 'formats' . DIRECTORY_SEPARATOR;
        $dirs = $format === 'total'
            ? (glob($root . '*', GLOB_ONLYDIR) ?: [])
            : (preg_match('~^[A-Za-z0-9_-]+$~', $format) && is_dir($root . $format) ? [$root . $format] : []);
        foreach ($dirs as $dir) {
            self::removeDir($dir);
        }
        return count($dirs);
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob(rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            is_dir($file) ? self::removeDir($file) : @unlink($file);
        }
        @rmdir($dir);
    }
}
