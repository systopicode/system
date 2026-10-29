<?php

declare(strict_types=1);

namespace Systopic\System\Queries\NodeAcls;

use Systopic\Db\Schema\Provisioner;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\Nodes\Model as Node;

/**
 * What ACL inheritance reads from the tree — `NodeAcls.heirs.sql`,
 * `NodeAcls.all.sql`: id, parent, ACL and the inherit flag.
 */
final class NodeAcls
{
    /**
     * The nodes below one that take its ACL, down to the first node of each
     * branch with an ACL of its own (that one excluded).
     *
     * @return list<Node>
     */
    public static function heirs(Scope $scope, int $nodeId): array
    {
        Registry::register(Node::class);
        return $scope->rows(__DIR__ . '/NodeAcls.heirs.sql', ['nodeId' => $nodeId],
            provisioner: new Provisioner($scope->connection))->all(Node::class);
    }

    /**
     * Every node of the installation, as the database has it — plain values,
     * not rows: what the repair compares against has to be the table, and a
     * row already in the request keeps the values it was loaded with.
     *
     * @return list<array{id: int, parent: int, acl: ?int, inherit: bool}>
     */
    public static function all(Scope $scope): array
    {
        $out = [];
        foreach ($scope->connection->all((string) file_get_contents(__DIR__ . '/NodeAcls.all.sql')) as $row) {
            $out[] = [
                'id'      => (int) $row['node_id'],
                'parent'  => (int) $row['node_parent_node_id'],
                'acl'     => (int) $row['node_acl_id'] > 0 ? (int) $row['node_acl_id'] : null,
                'inherit' => (bool) $row['node_acl_inherit'],
            ];
        }
        return $out;
    }
}
