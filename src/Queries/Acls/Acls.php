<?php

declare(strict_types=1);

namespace Systopic\System\Queries\Acls;

use Systopic\Db\Schema\Provisioner;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\Acls\Model as Acl;
use Systopic\System\Tables\GroupsInAcls\Model as GroupInAcl;

/** ACLs by their groups — `Acls.byGroupIds.sql`. */
final class Acls
{
    /** The ACL of a set, by its key (`Acls\Operator::key()`). */
    public static function byGroupIds(Scope $scope, string $groupIds): ?Acl
    {
        Registry::register(Acl::class);
        Registry::register(GroupInAcl::class);
        return $scope->rows(__DIR__ . '/Acls.byGroupIds.sql', ['groupIds' => $groupIds],
            provisioner: new Provisioner($scope->connection))->first(Acl::class);
    }
}
