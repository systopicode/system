<?php

declare(strict_types=1);

namespace Systopic\System\Queries\UserAdmin;

use Systopic\Db\Scope;
use Systopic\System\Tables\Groups\Model as Group;
use Systopic\System\Tables\Roles\Model as Role;
use Systopic\System\Tables\RolesOnGroups\Model as RoleOnGroup;
use Systopic\System\Tables\Users\Model as User;
use Systopic\System\Tables\UsersAtRoles\Model as UserAtRole;

/**
 * Users, roles, groups and how they connect — `UserAdmin.sql`.
 *
 * The tables are small and the panels of cms/users show all of them at once,
 * so one query reads everything and this object answers the questions the
 * panels ask. It exists once per request (`objectFor`); a commit to any of the
 * five tables throws it away with its result.
 *
 * The junctions stay rows (`UserAtRole`, `RoleOnGroup`): taking a role away is
 * removing that row, and the panel needs it in hand for that.
 */
final class UserAdmin
{
    /**
     * @param array<int, User>                     $users       by id
     * @param array<int, Role>                     $roles       by id
     * @param array<int, Group>                    $groups      by id
     * @param array<int, array<int, UserAtRole>>   $assignments user id => role id => row
     * @param array<int, array<int, RoleOnGroup>>  $permissions group id => role id => row
     */
    private function __construct(
        public readonly array $users,
        public readonly array $roles,
        public readonly array $groups,
        private readonly array $assignments,
        private readonly array $permissions,
    ) {}

    public static function of(Scope $scope): self
    {
        return $scope->objectFor(self::class, 'all', static fn(): self => self::build($scope));
    }

    private static function build(Scope $scope): self
    {
        $rows = $scope->rows(__DIR__ . '/UserAdmin.sql', []);

        $byId = static function (array $list): array {
            $map = [];
            foreach ($list as $row) {
                $map[(int) $row->id] = $row;
            }
            return $map;
        };

        $assignments = [];
        foreach ($rows->set(3)?->all(UserAtRole::class) ?? [] as $link) {
            $assignments[(int) $link->relationId('user')][(int) $link->relationId('role')] = $link;
        }
        $permissions = [];
        foreach ($rows->set(4)?->all(RoleOnGroup::class) ?? [] as $link) {
            $permissions[(int) $link->relationId('group')][(int) $link->relationId('role')] = $link;
        }

        return new self(
            $byId($rows->set(0)?->all(User::class) ?? []),
            $byId($rows->set(1)?->all(Role::class) ?? []),
            $byId($rows->set(2)?->all(Group::class) ?? []),
            $assignments,
            $permissions,
        );
    }

    public function user(?int $id): ?User
    {
        return $this->users[(int) $id] ?? null;
    }

    public function role(?int $id): ?Role
    {
        return $this->roles[(int) $id] ?? null;
    }

    public function group(?int $id): ?Group
    {
        return $this->groups[(int) $id] ?? null;
    }

    public function roleNamed(string $name): ?Role
    {
        foreach ($this->roles as $role) {
            if ($role->name === $name) {
                return $role;
            }
        }
        return null;
    }

    /** @return list<Role> the roles a user holds, in id order */
    public function rolesOf(User $user): array
    {
        return array_values(array_filter(
            $this->roles,
            fn(Role $role): bool => isset($this->assignments[(int) $user->id][(int) $role->id]),
        ));
    }

    /** The row saying that a user holds a role — NULL if they don't. */
    public function assignment(User $user, Role $role): ?UserAtRole
    {
        return $this->assignments[(int) $user->id][(int) $role->id] ?? null;
    }

    /** The row saying what a role may do on a group — NULL if nothing. */
    public function permission(Group $group, Role $role): ?RoleOnGroup
    {
        return $this->permissions[(int) $group->id][(int) $role->id] ?? null;
    }

    /**
     * The roles that may read, or write, a group.
     *
     * @param 'read'|'write' $right
     * @return list<Role>
     */
    public function rolesWith(Group $group, string $right): array
    {
        return array_values(array_filter(
            $this->roles,
            fn(Role $role): bool => (bool) ($this->permission($group, $role)?->$right ?? false),
        ));
    }
}
