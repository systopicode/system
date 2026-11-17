<?php

declare(strict_types=1);

namespace Systopic\System\Media;

use Systopic\System\Queries\MediaCrops\CropSet;
use Systopic\System\Tables\Medias\Files;

/**
 * Makes the pictures of a medium: the master, and every variant cut from it.
 *
 *   original  ->  mirrored, turned  ->  master crop  ->  look  =  master
 *   master    ->  crop of a ratio   ->  scaled to a size       =  variant
 *
 * The master is the original's pixels one to one; nothing is ever scaled up.
 * It is kept as a file (`--master.webp`) and every variant is cut from it, so
 * the original is read once per medium, not once per size.
 *
 * The four steps are static and take pictures, not paths: the tests run them
 * on drawn images. `make()` adds the files around them.
 *
 * Successor of `mediaImage::createImageFormat()`, which cut every format from
 * a 3 megapixel alias (and scaled the larger ones up).
 */
final class VariantRenderer
{
    /** The types a variant may be asked for in. */
    public const TYPES = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'];

    /** What a picture can be made from; everything else has no variants. */
    public const SOURCES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];

    /** `--original`: the untouched picture for the editor, small enough for a browser. */
    public const ORIGINAL_MEGAPIXELS = 2.0;

    /** The master is read again for every variant: kept close to lossless. */
    private const MASTER_QUALITY = 95;
    private const QUALITY = 84;

    /** Set when the original's real size differs from the row's - the caller saves the row. */
    public bool $sizeChanged = false;

    public function __construct(
        private readonly CropSet $set,
        private readonly string $originalPath,
    ) {}

    public static function supports(?string $mimetype): bool
    {
        return in_array((string) $mimetype, self::SOURCES, true) || str_starts_with((string) $mimetype, 'video/');
    }

    /**
     * Makes one variant and stores it. The master is made on the way when it
     * is not there.
     *
     * @param array{ratio: ?Ratio, megapixels: ?float, original: bool} $variant from `Variants::parse()`
     * @return string the file
     */
    public function make(array $variant, string $type): string
    {
        $media = $this->set->media;
        $suffix = $variant['original'] ? 'original' : Variants::suffix($variant['ratio'], $variant['megapixels']);
        if (!$variant['original'] && $variant['ratio'] === null && $variant['megapixels'] === null && $type === 'webp') {
            // The master as asked for is the master file itself - not a second, weaker encoding over it.
            $this->masterImage()->clear();
            return Files::variantPath((int) $media->id, (string) $media->filename, 'master', 'webp');
        }
        if ($variant['original']) {
            $image = self::scaled($this->original(), self::ORIGINAL_MEGAPIXELS);
        } else {
            $master = $this->masterImage();
            $image = $variant['ratio'] === null
                ? ($variant['megapixels'] === null ? $master : self::scaled($master, $variant['megapixels']))
                : self::cut($master, $this->set->cropIn($variant['ratio'], $master->getImageWidth(), $master->getImageHeight()), $variant['megapixels']);
        }
        $path = Files::writeVariant((int) $media->id, (string) $media->filename, $suffix, $type, self::encode($image, $type, self::QUALITY));
        $image->clear();
        return $path;
    }

    /**
     * The master as a picture: from its file, or made from the original and
     * stored. The medium's row gets the master's size (and the original's,
     * where it was not known) - `$sizeChanged` says so.
     */
    public function masterImage(): \Imagick
    {
        $media = $this->set->media;
        $file = Files::variantPath((int) $media->id, (string) $media->filename, 'master', 'webp');
        if (is_file($file)) {
            $master = new \Imagick();
            $master->readImage($file);
        } else {
            $original = $this->original();
            $geometry = $this->set->masterOf($original->getImageWidth(), $original->getImageHeight());
            $master = self::master($original, $geometry, $this->set->look());
            $original->clear();
            Files::writeVariant((int) $media->id, (string) $media->filename, 'master', 'webp', self::encode($master, 'webp', self::MASTER_QUALITY));
        }
        if ((int) $media->masterWidth !== $master->getImageWidth() || (int) $media->masterHeight !== $master->getImageHeight()) {
            $media->masterWidth = $master->getImageWidth();
            $media->masterHeight = $master->getImageHeight();
            $this->sizeChanged = true;
        }
        return $master;
    }

    // ------------------------------------------------------------- the steps

    /**
     * The master: mirror, turn, cut the master crop, then the look. Where
     * the frame reaches past the picture, the master is transparent.
     *
     * @param array{b: int, c: int, s: int, r: float, f: int} $look
     */
    public static function master(\Imagick $original, MasterGeometry $geometry, array $look): \Imagick
    {
        $image = clone $original;
        $transparent = new \ImagickPixel('transparent');
        $inside = $geometry->insideOriginal();
        if (!$inside) {
            $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_SET);
        }
        $image->setImageBackgroundColor($transparent);
        if ($geometry->flip) {
            $image->flopImage();
        }
        if (fmod($geometry->angle, 360) != 0.0) {
            // The canvas grows to the box around the turned picture; its middle stays the picture's.
            $image->rotateImage($transparent, $geometry->angle);
            $image->setImagePage(0, 0, 0, 0);
        }
        $x = (int) round($geometry->centreFractionX * $image->getImageWidth() - $geometry->width / 2);
        $y = (int) round($geometry->centreFractionY * $image->getImageHeight() - $geometry->height / 2);
        self::frame($image, $geometry->width, $geometry->height, $x, $y);
        self::levels($image, $look);
        return $image;
    }

    /**
     * A crop of the master, scaled down to a size. Megapixels null: the
     * crop's own pixels. A size the crop does not have pixels for gives the
     * crop as it is - smaller than asked, never blown up.
     */
    public static function cut(\Imagick $master, CropBox $crop, ?float $megapixels = null): \Imagick
    {
        $image = clone $master;
        $pixels = $crop->pixels($image->getImageWidth(), $image->getImageHeight());
        self::frame($image, $pixels['w'], $pixels['h'], $pixels['x'], $pixels['y']);
        if ($megapixels !== null) {
            [$width, $height] = Variants::pixels($crop->ratio, $megapixels);
            if ($width < $pixels['w']) {
                $image->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1);
            }
        }
        return $image;
    }

    /** A picture scaled down to about so many megapixels, its ratio kept. Never up. */
    public static function scaled(\Imagick $image, float $megapixels): \Imagick
    {
        $copy = clone $image;
        $width = $copy->getImageWidth();
        $height = $copy->getImageHeight();
        $factor = sqrt($megapixels * 1e6 / ($width * $height));
        if ($factor < 1) {
            $copy->resizeImage(max(1, (int) round($width * $factor)), max(1, (int) round($height * $factor)), \Imagick::FILTER_LANCZOS, 1);
        }
        return $copy;
    }

    /**
     * Brightness, contrast, saturation - the arithmetic of the CSS filters
     * the editor previews with: brightness(), then contrast(), then
     * saturate(). Imagick's own brightnessContrastImage() follows another
     * curve and blows the picture out.
     *
     * @param array{b?: int, c?: int, s?: int} $look 100 = as it is
     */
    public static function levels(\Imagick $image, array $look): void
    {
        $brightness = max(0, min(200, (int) ($look['b'] ?? 100)));
        $contrast = max(0, min(200, (int) ($look['c'] ?? 100)));
        $saturation = max(0, min(200, (int) ($look['s'] ?? 100)));
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
    }

    /**
     * The picture as the bytes of a file. Only the colour profile is kept of
     * what a camera wrote into it. A jpg has no transparency: white instead.
     */
    public static function encode(\Imagick $image, string $type, int $quality): string
    {
        if (!isset(self::TYPES[$type])) {
            throw new \InvalidArgumentException("VariantRenderer: no type '$type'");
        }
        $out = clone $image;
        $profiles = $out->getImageProfiles('icc', true);
        $out->stripImage();
        if (isset($profiles['icc'])) {
            $out->profileImage('icc', $profiles['icc']);
        }
        if ($type === 'jpg') {
            $out->setImageBackgroundColor(new \ImagickPixel('white'));
            $out->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        }
        $out->setImageFormat($type === 'jpg' ? 'jpeg' : $type);
        if ($type !== 'png') {
            $out->setImageCompressionQuality($quality);
        }
        $bytes = $out->getImageBlob();
        $out->clear();
        return $bytes;
    }

    // ---------------------------------------------------------------- private

    /**
     * Cuts the canvas to a frame at x, y. Inside the picture that is a
     * plain crop; a frame reaching past it gets transparent ground there.
     */
    private static function frame(\Imagick $image, int $width, int $height, int $x, int $y): void
    {
        $inside = $x >= 0 && $y >= 0 && $x + $width <= $image->getImageWidth() && $y + $height <= $image->getImageHeight();
        if ($inside) {
            $image->cropImage($width, $height, $x, $y);
        } else {
            $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_SET);
            $image->setImageBackgroundColor(new \ImagickPixel('transparent'));
            $image->extentImage($width, $height, $x, $y);
        }
        $image->setImagePage(0, 0, 0, 0);
    }

    /**
     * The original as a picture, upright (EXIF). Pixel images are read as
     * they are; a pdf's first page and a video's still are drawn at the
     * largest declared size, by the file operators - a tool of its own
     * process, so a broken upload cannot take the PHP worker down.
     */
    private function original(): \Imagick
    {
        $media = $this->set->media;
        $mime = (string) $media->mimetype;
        $image = new \Imagick();
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            $image->readImage($this->originalPath . ($mime === 'image/gif' ? '[0]' : ''));
            $image->autoOrient();
        } else {
            $sizes = Variants::sizes();
            $megapixels = (float) end($sizes);
            $source = \fsFile::get($this->originalPath);
            $operator = str_starts_with($mime, 'video/')
                ? $source->firstOperator(['ffmpeg'], fn($op) => $op->setMegapixels($megapixels)->render('png'))
                : $source->firstOperator(['magick', 'imagick'], fn($op) => $op->setMegapixels($megapixels)->setPage(0)->render('png'));
            if ($operator === null) {
                throw new \RuntimeException('VariantRenderer: no picture of ' . $media->filename . ': ' . $source->operators->lastError);
            }
            $image->readImageBlob($operator->getContents());
            $operator->cleanup();
            $operator->destroy();
        }
        $image->setImagePage(0, 0, 0, 0);
        if ((int) $media->width !== $image->getImageWidth() || (int) $media->height !== $image->getImageHeight()) {
            $media->width = $image->getImageWidth();
            $media->height = $image->getImageHeight();
            $this->sizeChanged = true;
        }
        return $image;
    }
}
