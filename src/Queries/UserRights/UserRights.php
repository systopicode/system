<?php

declare(strict_types=1);

namespace Systopic\System\Queries\UserRights;

use Systopic\Db\Schema\Provisioner;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\GroupsInAcls\Model as GroupInAcl;
use Systopic\System\Tables\Roles\Model as Role;
use Systopic\System\Tables\Roles\Operator as RoleOperator;

/**
 * Roles, group and ACL rights of the logged-in user — `UserRights.roles.sql`,
 * `UserRights.groups.sql`, `UserRights.acls.sql`. One statement each, for the
 * one user of the request; the legacy service asked once per user of the
 * installation.
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
     * The built-in roles (`Roles\Operator::BUILTIN`), key => role.
     *
     * The one place that writes on a read: a role that is missing is made
     * here, at its first use — the same as the provisioner makes a missing
     * column. That happens once per installation; a second request racing
     * the first hits the unique key and reads what the first one made.
     *
     * @return array<string, Role>
     */
    public static function builtinRoles(Scope $scope): array
    {
        Registry::register(Role::class);
        $read = static function () use ($scope): array {
            $out = [];
            foreach ($scope->rows(__DIR__ . '/UserRights.builtin.sql', [],
                provisioner: new Provisioner($scope->connection))->all(Role::class) as $role) {
                $out[(string) $role->builtin] = $role;
            }
            return $out;
        };
        $roles = $read();
        $missing = RoleOperator::missingBuiltins($roles);
        if ($missing !== []) {
            try {
                $scope->save(...$missing);
            } catch (\Throwable $e) {
                // made by a request at the same moment — or a real error
                if (RoleOperator::missingBuiltins($read()) !== []) {
                    throw $e;
                }
            }
            $roles = $read();
        }
        return $roles;
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

    /**
     * The ACLs a set of roles may read and write: those with at least one
     * group the roles may read (write).
     *
     * Not to be kept by the caller beyond the request's scope: a new ACL is
     * written during a request, and the scope throws this result away when
     * that happens — a copy would not know.
     *
     * @param list<int> $roleIds
     * @return array{read: list<int>, write: list<int>}
     */
    public static function acls(Scope $scope, array $roleIds): array
    {
        $out = ['read' => [], 'write' => []];
        if ($roleIds === []) {
            return $out;
        }
        Registry::register(GroupInAcl::class);
        $rows = $scope->rows(__DIR__ . '/UserRights.acls.sql', ['roleIds' => array_values($roleIds)],
            provisioner: new Provisioner($scope->connection));
        foreach ($rows->looseValues() as $row) {
            $acl = (int) $row['rights_acl_id'];
            if ((int) $row['rights_read']) {
                $out['read'][] = $acl;
            }
            if ((int) $row['rights_write']) {
                $out['write'][] = $acl;
            }
        }
        return $out;
    }
}
