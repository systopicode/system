<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Nodes;

use Systopic\Db\Observer;
use Systopic\Db\Rows;
use Systopic\Db\Schema\Schema;
use Systopic\Db\Scope;
use Systopic\System\Tables\Acls\Model as Acl;

/**
 * Keeps the inherited ACLs of `cms_nodes` right — after every commit that
 * moved a node or changed its ACL or inherit flag, whoever wrote it.
 *
 * An observer rather than a step every operator has to remember: nodes are
 * hung under other nodes in a dozen places (media, contacts, projects, the
 * page tree), and one of them forgetting would leave a branch with the rights
 * of its old place. Registered in `autoload.php`.
 *
 * **Written with SQL, not through a unit of work.** This runs in the middle
 * of the commit's report: the other observers and the scope's listeners still
 * read the rows' changes after us. A nested commit would make the same rows
 * forget them before those get their turn. So: one `UPDATE … WHERE node_id IN`
 * per ACL, the rows in memory set with `hydrate()` (not a change), and the
 * results that read `cms_nodes` thrown away.
 *
 * The scope is `Scope::default()` — the observer is not told which scope
 * committed, and the request has only that one in practice.
 */
final class AclInheritance implements Observer
{
    /** The properties that decide the inherited ACL. */
    private const DECIDING = ['parentNode' => true, 'acl' => true, 'aclInherit' => true];

    public function read(string $key, Rows $rows): void {}

    public function committed(array $written, array $removed): void
    {
        $changed = [];
        foreach ($written as $row) {
            if ($row instanceof Model && array_intersect_key($row->changes(), self::DECIDING) !== []) {
                $changed[] = $row;
            }
        }
        if ($changed === []) {
            return;
        }
        $scope = Scope::default();
        self::apply($scope, Operator::aclInheritance($scope, $changed));
    }

    /** Every node set right — `Operator::aclRepaired()`, written. Returns how many changed. */
    public static function repair(Scope $scope): int
    {
        $plan = Operator::aclRepaired($scope);
        self::apply($scope, $plan);
        return count($plan);
    }

    /**
     * Write a plan of `Operator::aclInheritance()` / `aclRepaired()`.
     *
     * @param array<int, array{0: ?Model, 1: ?int}> $plan
     */
    public static function apply(Scope $scope, array $plan): void
    {
        if ($plan === []) {
            return;
        }
        $table = Schema::of(Model::class);
        $byAcl = [];
        foreach ($plan as $nodeId => [, $aclId]) {
            $byAcl[$aclId ?? 0][] = $nodeId;
        }
        foreach ($byAcl as $aclId => $nodeIds) {
            foreach (array_chunk($nodeIds, 500) as $chunk) {
                $scope->connection->run(
                    "UPDATE `{$table->name}` SET `{$table->propertyToColumn['acl']}` = ?"
                    . " WHERE `{$table->primary}` IN (" . implode(',', array_fill(0, count($chunk), '?')) . ')',
                    [$aclId === 0 ? null : $aclId, ...$chunk],
                );
            }
        }
        foreach ($plan as [$node, $aclId]) {
            $node?->hydrate('acl', $aclId === null ? null : Acl::withId($aclId));
        }
        $scope->results->invalidate([$table->name]);
    }
}
