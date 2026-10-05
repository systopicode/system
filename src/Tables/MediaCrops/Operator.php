<?php

declare(strict_types=1);

namespace Systopic\System\Tables\MediaCrops;

use Systopic\Db\Type\Decimal;
use Systopic\System\Tables\Medias\Model as MediaRow;

/**
 * The crop of a medium for one format — what the crop editor saves and what
 * the first making of a format starts with. Stateless; the caller saves.
 *
 * Coordinates are fractions of the image: left/top the centre of the cut,
 * width/height its size.
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

    public static function decimal(float $value): Decimal
    {
        return new Decimal(sprintf('%.12F', $value));
    }
}
