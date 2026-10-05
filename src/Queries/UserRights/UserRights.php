<?php

declare(strict_types=1);

namespace Systopic\System\Queries\UserRights;

use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\Roles\Model as Role;

/**
 * Roles and group rights of the logged-in user — `UserRights.roles.sql`,
 * `UserRights.groups.sql`. One statement each, for the one user of the
 * request; the legacy service asked once per user of the installation.
 */
final class UserRights
{
    /** @return list<Role> the roles of a user, or every role */
    public static function roles(Scope $scope, ?int $userId, bool $all = false): array
    {
        Registry::register(Role::class);
        return $scope->rows(__DIR__ . '/UserRights.roles.sql', ['all' => $all ? 1 : 0, 'userId' => $userId ?? 0])->all(Role::class);
    }

    /**
     * The groups a set of roles may read and write.
     *
     * @param list<int> $roleIds
     * @return array{read: list<int>, write: list<int>}
     */
    public static function groups(Scope $scope, array $roleIds): array
    {
        $out = ['read' => [], 'write' => []];
        if ($roleIds === []) {
            return $out;
        }
        foreach ($scope->rows(__DIR__ . '/UserRights.groups.sql', ['roleIds' => array_values($roleIds)])->looseValues() as $row) {
            $group = (int) $row['rights_group_id'];
            if ((int) $row['rights_read']) {
                $out['read'][] = $group;
            }
            if ((int) $row['rights_write']) {
                $out['write'][] = $group;
            }
        }
        return $out;
    }
}
