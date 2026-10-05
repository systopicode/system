<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Tags;

use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\TagsInNodes\Model as TagInNode;

/**
 * What can be done to tags — nothing here saves.
 */
final class Operator
{
    /**
     * Merge `$source` into `$target`: every node the source tags and the
     * target does not gets the target; the source goes, links and all.
     *
     * @param list<TagInNode> $sourceLinks the source's links (`TagLinks::linksOf()`)
     * @param list<int>       $targetNodeIds the nodes the target tags already
     * @return array{0: list<TagInNode>, 1: list<TagInNode|Model>} to save, to remove — links before the tag
     */
    public static function join(Model $source, Model $target, array $sourceLinks, array $targetNodeIds): array
    {
        $save = [];
        foreach ($sourceLinks as $link) {
            $nodeId = (int) $link->relationId('node');
            if (!in_array($nodeId, $targetNodeIds, true)) {
                $new = new TagInNode();
                $new->tag = $target;
                $new->node = Node::withId($nodeId);
                $save[] = $new;
            }
        }
        return [$save, [...$sourceLinks, $source]];
    }
}
