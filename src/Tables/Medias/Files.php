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
        // Loud on failure: a silent one leaves a row with 'nofile/null' and a
        // 404 in the page (a path in the internal spelling /C/… is one cause).
        if (!@rename($tmpFile, $path)) {
            throw new \RuntimeException("Files::store(): cannot move '$tmpFile' to '$path'");
        }
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

    /**
     * Look of one format, never of the original. The original file stays as uploaded.
     *
     * @return array<string, array{b:int,c:int,s:int,r:int,f:int}>
     */
    public static function tunes(int $mediaId, string $filename): array
    {
        $file = self::tuneFile($mediaId, $filename);
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    /** null removes the format. An empty list removes every format. */
    public static function saveTune(int $mediaId, string $filename, ?string $format, ?array $tune): void
    {
        $all = self::tunes($mediaId, $filename);
        if ($format === null) {
            $all = [];
        } elseif ($tune === null) {
            unset($all[$format]);
        } else {
            $all[$format] = $tune;
        }
        $file = self::tuneFile($mediaId, $filename);
        if ($all === []) {
            @unlink($file);
            return;
        }
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        file_put_contents($file, json_encode($all));
    }

    /** The rendered format file, so the next view builds it again from the original. */
    public static function dropFormatFile(int $mediaId, string $format, string $filename): void
    {
        if (!preg_match('~^[A-Za-z0-9_-]+$~', $format) || $filename === '') {
            return;
        }
        $dir = self::varDir() . 'formats' . DIRECTORY_SEPARATOR . $format . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, self::idPath($mediaId));
        foreach (glob($dir . $filename . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    public static function tune(\Imagick $image, int $mediaId, string $filename, string $format): void
    {
        $tune = self::tunes($mediaId, $filename)[$format] ?? null;
        if (!is_array($tune)) {
            return;
        }
        $brightness = max(0, min(200, (int) ($tune['b'] ?? 100)));
        $contrast = max(0, min(200, (int) ($tune['c'] ?? 100)));
        $saturation = max(0, min(200, (int) ($tune['s'] ?? 100)));
        $rotate = ((int) ($tune['r'] ?? 0) % 360 + 360) % 360;
        $flip = (int) ($tune['f'] ?? 0) === 1;
        if ($brightness === 100 && $contrast === 100 && $saturation === 100 && $rotate === 0 && !$flip) {
            return;
        }
        // Same math as the CSS preview: brightness(), then contrast(), then saturate().
        // brightnessContrastImage() uses a different curve and blows the picture out.
        $channels = \Imagick::CHANNEL_RED | \Imagick::CHANNEL_GREEN | \Imagick::CHANNEL_BLUE;
        if ($brightness !== 100) {
            $image->evaluateImage(\Imagick::EVALUATE_MULTIPLY, $brightness / 100, $channels);
        }
        if ($contrast !== 100) {
            $factor = $contrast / 100;
            $image->evaluateImage(\Imagick::EVALUATE_MULTIPLY, $factor, $channels);
            $image->evaluateImage(\Imagick::EVALUATE_ADD, (1 - $factor) * 0.5 * \Imagick::getQuantum(), $channels);
        }
        if ($saturation !== 100) {
            $image->modulateImage(100, $saturation, 100);
        }
        if ($flip) {
            $image->flopImage();
        }
        if ($rotate !== 0) {
            // The format stays its size. The picture turns inside that frame.
            $frameW = $image->getImageWidth();
            $frameH = $image->getImageHeight();
            $fmt = strtolower($image->getImageFormat());
            $pixel = new \ImagickPixel(in_array($fmt, ['jpeg', 'jpg'], true) ? 'white' : 'transparent');
            $image->rotateImage($pixel, $rotate);
            $image->setImagePage(0, 0, 0, 0);
            $image->setImageBackgroundColor($pixel);
            $image->extentImage(
                $frameW,
                $frameH,
                (int) round(($image->getImageWidth() - $frameW) / 2),
                (int) round(($image->getImageHeight() - $frameH) / 2)
            );
            $image->setImagePage(0, 0, 0, 0);
        }
    }

    private static function tuneFile(int $mediaId, string $filename): string
    {
        // The rendered file is often "name.jpg.webp"; the row's name is "name.jpg".
        // The look is stored per medium, not per that spelling.
        return self::varDir() . 'adjust' . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, self::idPath($mediaId)) . 'look.json';
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
