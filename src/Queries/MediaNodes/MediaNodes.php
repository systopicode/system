<?php

declare(strict_types=1);

namespace Systopic\System\Queries\MediaNodes;

use Systopic\Db\Result\Completion;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\Medias\Model as MediaRow;
use Systopic\System\Tables\Nodes\Model as Node;

/**
 * Media rows with their nodes — `MediaNodes.sql` (by node id) and
 * `MediaNodes.underParents.sql` (below parent nodes, a tree level at once).
 */
final class MediaNodes
{
    /**
     * @param list<int> $parentIds content nodes (an alias asks with its original)
     * @return array<int, list<MediaRow>> parent node id => media, in their order
     */
    public static function underParents(Scope $scope, array $parentIds, bool $showTrashed, Completion $completion = Completion::Group): array
    {
        $out = array_fill_keys($parentIds, []);
        if ($parentIds === []) {
            return $out;
        }
        Registry::register(MediaRow::class);
        Registry::register(Node::class);
        $rows = $scope->rows(__DIR__ . '/MediaNodes.underParents.sql', [
            'parentIds'   => array_values($parentIds),
            'showtrashed' => $showTrashed ? 1 : 0,
        ], $completion);
        foreach ($rows->all(MediaRow::class) as $media) {
            /** @var MediaRow $media */
            $parent = $media->node?->relationId('parentNode');
            if ($parent !== null) {
                $out[$parent][] = $media;
            }
        }
        return $out;
    }

    /**
     * @param list<int> $nodeIds
     * @return array<int, MediaRow> node id => media
     */
    public static function byNodeIds(Scope $scope, array $nodeIds): array
    {
        if ($nodeIds === []) {
            return [];
        }
        Registry::register(MediaRow::class);
        Registry::register(Node::class);
        $out = [];
        foreach ($scope->rows(__DIR__ . '/MediaNodes.sql', ['nodeIds' => array_values($nodeIds)])->all(MediaRow::class) as $media) {
            /** @var MediaRow $media */
            $nodeId = $media->relationId('node');
            if ($nodeId !== null) {
                $out[$nodeId] = $media;
            }
        }
        return $out;
    }
}
