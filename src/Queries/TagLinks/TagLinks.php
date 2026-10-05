<?php

declare(strict_types=1);

namespace Systopic\System\Queries\TagLinks;

use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;
use Systopic\System\Tables\TagsInNodes\Model as TagInNode;

/**
 * Which tags a node carries — straight from `cms_tags_in_nodes`.
 *
 * `TagGroups::load()` knows the same thing, but only for what its query lets
 * through (published, not trashed). Whether a link *exists* — before adding
 * it or after removing it — has to be asked without that filter.
 *
 * Plain statements on the connection, not through `Scope::rows()`: two
 * numbers in, one number out, and nobody holds the result.
 */
final class TagLinks
{
    public static function has(Scope $scope, int $tagId, int $nodeId): bool
    {
        return (int) $scope->connection->value(
            'SELECT COUNT(*) FROM ' . self::table() . ' WHERE tin_tag_id = ? AND tin_node_id = ?',
            [$tagId, $nodeId],
        ) > 0;
    }

    /** @return list<int> the tag ids of a node, ascending */
    public static function tagIdsOf(Scope $scope, int $nodeId): array
    {
        return array_map('intval', array_column($scope->connection->all(
            'SELECT tin_tag_id FROM ' . self::table() . ' WHERE tin_node_id = ? ORDER BY tin_tag_id',
            [$nodeId],
        ), 'tin_tag_id'));
    }

    /** @return list<int> the nodes a tag is on, ascending */
    public static function nodeIdsOf(Scope $scope, int $tagId): array
    {
        $ids = array_map(static fn(TagInNode $link): int => (int) $link->relationId('node'), self::linksOf($scope, $tagId));
        sort($ids);
        return $ids;
    }

    /**
     * The link rows of one tag — `TagLinks.sql`, through the scope, because
     * these are rows somebody is going to move or remove.
     *
     * @return list<TagInNode>
     */
    public static function linksOf(Scope $scope, int $tagId): array
    {
        return $scope->rows(__DIR__ . '/TagLinks.sql', ['tagId' => $tagId])->all(TagInNode::class);
    }

    /** cms_tags_in_nodes here, pwk_tags_in_nodes in patchworkkit. */
    private static function table(): string
    {
        return Schema::tableNameOf(TagInNode::class);
    }
}
