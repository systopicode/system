<?php

declare(strict_types=1);

namespace Systopic\System\Queries\MediaLibrary;

use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\Medias\Model as MediaRow;
use Systopic\System\Tables\Nodes\Model as Node;

/**
 * The media library of a page — `MediaLibrary.sql`, the successor of
 * `lib/class/mml`. One entry per media node below the page, in sibling order.
 */
final class MediaLibrary
{
    /**
     * @return list<array{node: Node, media: ?MediaRow, alias: bool, originalParentId: ?int}>
     */
    public static function of(Scope $scope, int $pageNodeId): array
    {
        if ($pageNodeId <= 0) {
            return [];
        }
        Registry::register(Node::class);
        Registry::register(MediaRow::class);
        $rows = $scope->rows(__DIR__ . '/MediaLibrary.sql', ['parentId' => $pageNodeId]);
        $loose = $rows->looseValues();
        $out = [];
        foreach ($rows->records() as $i => $record) {
            $node = $record[Node::class] ?? null;
            if ($node === null) {
                continue;
            }
            $original = (int) ($node->relationId('originalNode') ?? 0);
            $parent = $loose[$i]['library_original_parent_id'] ?? null;
            $out[] = [
                'node'             => $node,
                'media'            => $record[MediaRow::class] ?? null,
                'alias'            => $original > 0,
                'originalParentId' => $parent === null ? null : (int) $parent,
            ];
        }
        return $out;
    }
}
