<?php

declare(strict_types=1);

namespace Systopic\System\Tables\MediaCrops;

use Systopic\Db\Type\Decimal;
use Systopic\Db\Type\Text;
use Systopic\System\Media\CropBox;
use Systopic\System\Media\Ratio;
use Systopic\System\Media\Variants;
use Systopic\System\Tables\Medias\Model as MediaRow;

/**
 * The crop of a medium for one format — what the crop editor saves and what
 * the first making of a format starts with. Stateless; the caller saves.
 *
 * Coordinates are fractions of the image: left/top the centre of the cut,
 * width/height its size.
 *
 * Two forms live side by side until the old renderer is gone: `cropped()`,
 * `computed()` and `blank()` write the legacy columns (left, top, width,
 * height, per format name); `put()`, `box()` and the look write the new
 * ones (ratio, centre, scale - see `Media\CropBox`).
 */
final class Operator
{
    /** A crop set by hand: no longer automatic, version bumped (the format is made again). */
    public static function cropped(?Model $crop, MediaRow $media, string $format, float $left, float $top, float $width, float $height): Model
    {
        $crop ??= self::blank($media, $format, true, 1.0, 1.0);
        $crop->left = self::decimal($left);
        $crop->top = self::decimal($top);
        $crop->width = self::decimal($width);
        $crop->height = self::decimal($height);
        $crop->auto = false;
        $crop->version = (int) $crop->version + 1;
        return $crop;
    }

    /** An automatic crop as computed from the live area: stays automatic, version bumped. */
    public static function computed(Model $crop, float $left, float $top, float $width, float $height): Model
    {
        $crop->left = self::decimal($left);
        $crop->top = self::decimal($top);
        $crop->width = self::decimal($width);
        $crop->height = self::decimal($height);
        $crop->version = (int) $crop->version + 1;
        return $crop;
    }

    /** The first crop of a format: centred, as wide as given. */
    public static function blank(MediaRow $media, string $format, bool $auto, float $width, float $height): Model
    {
        $crop = new Model();
        $crop->media = $media;
        $crop->format = $format;
        $crop->left = self::decimal(0.5);
        $crop->top = self::decimal(0.5);
        $crop->width = self::decimal($width);
        $crop->height = self::decimal($height);
        $crop->auto = $auto;
        return $crop;
    }

    /** The look of an untouched master: nothing changed, not turned, not mirrored. */
    public const LOOK = ['b' => 100, 'c' => 100, 's' => 100, 'r' => 0.0, 'f' => 0];

    /**
     * A crop set by hand in the new form: the crop of a ratio (found by the
     * ratio's name), or - `$master` - the master crop, whose ratio is free.
     * Version bumped: every picture made of the medium is made again.
     */
    public static function put(?Model $crop, MediaRow $media, CropBox $box, bool $master = false): Model
    {
        if ($crop === null) {
            $crop = new Model();
            $crop->media = $media;
        }
        $crop->format = $master ? Variants::MASTER : $box->ratio->name;
        $crop->ratio = $box->ratio->name;
        $crop->alignX = self::decimal($box->alignX);
        $crop->alignY = self::decimal($box->alignY);
        $crop->scale = self::decimal($box->scale);
        $crop->auto = false;
        $crop->version = (int) $crop->version + 1;
        return $crop;
    }

    /** The crop of a row in the new form - null for a row that only has the legacy columns. */
    public static function box(?Model $crop): ?CropBox
    {
        $ratio = $crop === null || $crop->ratio === null ? null : Ratio::of((string) $crop->ratio);
        $scale = $crop?->scale === null ? 0.0 : (float) (string) $crop->scale;
        if ($ratio === null || $scale <= 0 || $crop->alignX === null || $crop->alignY === null) {
            return null;
        }
        return new CropBox($ratio, (float) (string) $crop->alignX, (float) (string) $crop->alignY, $scale, CropBox::MANUAL);
    }

    /**
     * The look of the master as the renderer and the editor read it: every
     * key there, every value in range. Anything unreadable is "untouched".
     *
     * @return array{b: int, c: int, s: int, r: float, f: int}
     */
    public static function look(?Model $crop): array
    {
        $stored = $crop === null || $crop->edit === null ? null : json_decode((string) $crop->edit, true);
        return self::normalLook(is_array($stored) ? $stored : []);
    }

    /** Sets the look of the master's row; an untouched one is stored as null. Version bumped. */
    public static function withLook(Model $crop, array $look): Model
    {
        $look = self::normalLook($look);
        $crop->edit = $look == self::LOOK ? null : new Text((string) json_encode($look));
        $crop->version = (int) $crop->version + 1;
        return $crop;
    }

    /** @return array{b: int, c: int, s: int, r: float, f: int} */
    private static function normalLook(array $look): array
    {
        $level = static fn(string $key): int => max(0, min(200, (int) round((float) (is_numeric($look[$key] ?? null) ? $look[$key] : 100))));
        $angle = is_numeric($look['r'] ?? null) && is_finite((float) $look['r']) ? fmod((float) $look['r'], 360) : 0.0;
        if ($angle <= -180) {
            $angle += 360;
        } elseif ($angle > 180) {
            $angle -= 360;
        }
        return ['b' => $level('b'), 'c' => $level('c'), 's' => $level('s'), 'r' => round($angle, 4) + 0.0, 'f' => empty($look['f']) ? 0 : 1];
    }

    public static function decimal(float $value): Decimal
    {
        return new Decimal(sprintf('%.12F', $value));
    }
}
