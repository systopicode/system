<?php

declare(strict_types=1);

namespace Systopic\System\Queries\MediaCrops;

use Systopic\Db\Schema\Provisioner;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\MediaCrops\Model as Crop;
use Systopic\System\Tables\Medias\Model as MediaRow;

/**
 * Media with all their crop rows - `MediaCrops.media.sql` and
 * `MediaCrops.sql`, two statements for any number of media.
 *
 * Successor of `Queries\MediaCrop`, which reads one medium for one named
 * format; that one stays until the old renderer is gone.
 *
 * Both statements run with the provisioner: they name the columns that came
 * with the master and the ratios, so a database from before gets them here,
 * on the first picture that is asked for.
 */
final class MediaCrops
{
    /**
     * @param list<int> $mediaIds
     * @return array<int, CropSet> media id => the medium and its crops; an unknown id is missing
     */
    public static function of(Scope $scope, array $mediaIds): array
    {
        $mediaIds = array_values(array_unique(array_filter(array_map('intval', $mediaIds), static fn(int $id): bool => $id > 0)));
        if ($mediaIds === []) {
            return [];
        }
        Registry::register(MediaRow::class);
        Registry::register(Crop::class);
        $provisioner = new Provisioner($scope->connection);

        $rows = [];
        $crops = $scope->rows(__DIR__ . '/MediaCrops.sql', ['mediaIds' => $mediaIds], cache: false, provisioner: $provisioner);
        foreach ($crops->all(Crop::class) as $crop) {
            /** @var Crop $crop */
            $rows[(int) $crop->relationId('media')][] = $crop;
        }
        $sets = [];
        $medias = $scope->rows(__DIR__ . '/MediaCrops.media.sql', ['mediaIds' => $mediaIds], cache: false, provisioner: $provisioner);
        foreach ($medias->all(MediaRow::class) as $media) {
            /** @var MediaRow $media */
            $sets[(int) $media->id] = new CropSet($media, $rows[(int) $media->id] ?? []);
        }
        return $sets;
    }

    /** One medium and its crops - null when there is no such medium. */
    public static function one(Scope $scope, int $mediaId): ?CropSet
    {
        return self::of($scope, [$mediaId])[$mediaId] ?? null;
    }

    // ------------------------------------------------- for a page's pictures

    /** @var array<int, true> media a page holds and may ask the crops of */
    private static array $announced = [];
    /** @var array<int, ?CropSet> */
    private static array $read = [];

    /**
     * A medium the page holds. Its crops are not read yet - they are read
     * with those of every other announced medium, the first time one of
     * them is asked for.
     */
    public static function announce(int $mediaId): void
    {
        if ($mediaId > 0 && !array_key_exists($mediaId, self::$read)) {
            self::$announced[$mediaId] = true;
        }
    }

    /**
     * The crops of a medium, read together with all that were announced: a
     * page with fifty pictures asks the database twice, not a hundred times.
     */
    public static function lazy(int $mediaId, ?Scope $scope = null): ?CropSet
    {
        if (!array_key_exists($mediaId, self::$read)) {
            self::$announced[$mediaId] = true;
            $ids = array_keys(self::$announced);
            self::$announced = [];
            self::$read += self::of($scope ?? Scope::default(), $ids) + array_fill_keys($ids, null);
        }
        return self::$read[$mediaId];
    }

    /** After a crop, a look or a medium was saved: read again next time. */
    public static function forget(?int $mediaId = null): void
    {
        if ($mediaId === null) {
            self::$read = self::$announced = [];
        } else {
            unset(self::$read[$mediaId]);
        }
    }
}
