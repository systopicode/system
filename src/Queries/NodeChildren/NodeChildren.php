<?php

declare(strict_types=1);

namespace Systopic\System\Queries\NodeChildren;

use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Nodes\Operator;

/**
 * The children of one node, all of them — `NodeChildren.sql`.
 *
 * What a move has to renumber. The page tree's own level query filters —
 * published, not trashed, this language — and that is right for drawing, but
 * wrong for ordering: a hidden sibling keeps its `node_ordering`, and
 * renumbering around it would put two rows on the same number. This one
 * filters by nothing but parent and type.
 *
 * Through the scope, so a node the caller already holds — the one being
 * dragged, say — is the same object in the list, and the list is read once
 * per request until a node is written.
 */
final class NodeChildren
{
    /**
     * @param int|null $parentId NULL for the topmost level
     * @return list<Node> in sibling order
     */
    public static function of(Scope $scope, ?int $parentId, ?string $type = null): array
    {
        Registry::register(Node::class);

        $rows = $scope->rows(__DIR__ . '/NodeChildren.sql', [
            'parentId' => $parentId ?? 0,
            'roots'    => $parentId === null || $parentId === 0 ? 1 : 0,
            'nodeType' => $type,
        ]);

        $nodes = $rows->all(Node::class);
        usort($nodes, Operator::compare(...));
        return $nodes;
    }
}
