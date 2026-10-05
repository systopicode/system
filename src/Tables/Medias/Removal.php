<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Medias;

use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;
use Systopic\System\Tables\MediaCrops\Model as Crop;

/**
 * Deleting media — row, node, crops, files; and whatever else named it, through
 * the hook `mediaRemoved` (systopic/cms clears the pages' share images).
 *
 * The legacy action deleted only the node, and not even that: `cms_medias`
 * points at it with a foreign key, the DELETE failed, and `db` swallowed the
 * error. Row, crops and files of every "deleted" medium stayed behind.
 *
 * Crops and the share-image references go past the unit of work (nobody
 * holds those rows); their tables are invalidated by hand — orm-migration.md §1.
 * The files go after the commit: a rolled-back delete must not lose them.
 */
final class Removal
{
    /** @return int how many media were removed */
    public static function remove(Scope $scope, Model ...$media): int
    {
        $media = array_values(array_filter($media, static fn(Model $m): bool => $m->id !== null));
        if ($media === []) {
            return 0;
        }
        $connection = $scope->connection;
        $uow = $scope->uow();
        foreach ($media as $m) {
            $uow->remove($m);          // the row first: it points at the node
        }
        foreach ($media as $m) {
            if ($m->node !== null) {
                $uow->remove($m->node);
            }
        }
        $ids = array_map(static fn(Model $m): int => (int) $m->id, $media);
        $nodeIds = array_values(array_filter(array_map(static fn(Model $m): ?int => $m->relationId('node'), $media)));
        $crops = Schema::tableNameOf(Crop::class);
        $connection->transactional(static function () use ($scope, $connection, $uow, $crops, $ids, $nodeIds): void {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $connection->run("DELETE FROM $crops WHERE media_crop_media_id IN ($marks)", $ids);
            if ($nodeIds !== []) {
                // in the same transaction: what points at the media nodes
                \Systopic\System\Sys\Hooks\Hooks::emit('mediaRemoved', $scope, $nodeIds);
            }
            $uow->commit();
        });
        $scope->results->invalidate([$crops]);
        foreach ($media as $m) {
            Files::remove($m);
        }
        return count($media);
    }
}
