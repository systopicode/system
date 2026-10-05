<?php

declare(strict_types=1);

namespace Systopic\System\Queries\MediaCrop;

use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\MediaCrops\Model as Crop;
use Systopic\System\Tables\Medias\Model as MediaRow;

/**
 * A medium and its crop for one format — `MediaCrop.sql`.
 *
 * Uncached: the crop is read right before a format is made and written by
 * the same request (the legacy read it fresh, too).
 */
final class MediaCrop
{
    /** @return array{0: ?MediaRow, 1: ?Crop} */
    public static function of(Scope $scope, int $mediaId, string $format): array
    {
        Registry::register(MediaRow::class);
        Registry::register(Crop::class);
        $rows = $scope->rows(__DIR__ . '/MediaCrop.sql', ['mediaId' => $mediaId, 'format' => $format], cache: false);
        return [$rows->first(MediaRow::class), $rows->first(Crop::class)];
    }

    /**
     * The same as the flat record the legacy image code computes with —
     * `media_*` and `media_crop_*` fields, numbers as floats. A missing or
     * empty crop reads as the centred whole image, `media_crop_id` null.
     *
     * For `mediaImage::createImageFormat()` and `mediaEditor`, which keep
     * their arithmetic until the generation moves to the file operators.
     */
    public static function record(Scope $scope, int $mediaId, string $format): ?\stdClass
    {
        [$media, $crop] = self::of($scope, $mediaId, $format);
        if ($media === null) {
            return null;
        }
        $num = static fn($d): float => $d === null ? 0.0 : (float) (string) $d;
        $r = new \stdClass();
        $r->media_id = (int) $media->id;
        $r->media_node_id = $media->relationId('node');
        $r->media_mimetype = $media->mimetype;
        $r->media_filename = $media->filename;
        $r->media_width = (int) $media->width;
        $r->media_height = (int) $media->height;
        $r->media_background_type = (string) $media->backgroundType;
        $r->media_livearea_left = $num($media->liveareaLeft);
        $r->media_livearea_top = $num($media->liveareaTop);
        $r->media_livearea_width = $num($media->liveareaWidth);
        $r->media_livearea_height = $num($media->liveareaHeight);
        $r->media_crop_id = $crop?->id;
        $r->media_crop_left = $num($crop?->left);
        $r->media_crop_top = $num($crop?->top);
        $r->media_crop_width = $num($crop?->width);
        $r->media_crop_height = $num($crop?->height);
        $r->media_crop_auto = $crop === null ? 1 : (int) (bool) $crop->auto;
        if ($crop === null || $r->media_crop_width == 0 || $r->media_crop_height == 0) {
            // invalid or missing: the centred whole image, following the format's rule
            [$r->media_crop_left, $r->media_crop_top, $r->media_crop_width, $r->media_crop_height, $r->media_crop_auto] = [0.5, 0.5, 1.0, 1.0, 1];
        }
        return $r;
    }
}
