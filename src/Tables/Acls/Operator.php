<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Acls;

use Systopic\Db\Scope;
use Systopic\Db\TableRow;
use Systopic\System\Queries\Acls\Acls;
use Systopic\System\Tables\Groups\Model as Group;
use Systopic\System\Tables\GroupsInAcls\Model as GroupInAcl;

/**
 * Finding the ACL of a set of groups — nothing here saves.
 */
final class Operator
{
    /**
     * The ACL of exactly these groups: the existing one, or a new one with
     * its group rows to save. No group at all is no ACL — NULL on the row.
     *
     * Two requests creating the same new set at the same moment: the second
     * insert hits the unique key, and the caller sees the error rather than a
     * second copy.
     *
     * @param list<int|Group> $groups
     * @return array{0: ?Model, 1: list<TableRow>} the ACL, and what to save for it (empty when it exists)
     */
    public static function forGroups(Scope $scope, array $groups): array
    {
        $key = self::key($groups);
        if ($key === null) {
            return [null, []];
        }
        $acl = Acls::byGroupIds($scope, $key);
        if ($acl !== null) {
            return [$acl, []];
        }
        $acl = new Model();
        $acl->groupIds = $key;
        $save = [$acl];
        foreach (self::groupIds($acl) as $id) {
            $link = new GroupInAcl();
            $link->acl = $acl;
            $link->group = Group::withId($id);
            $save[] = $link;
        }
        return [$acl, $save];
    }

    /**
     * `3,7` for 7, 3 and 3 again; NULL when nothing is left.
     *
     * @param list<int|Group> $groups
     */
    public static function key(array $groups): ?string
    {
        $ids = [];
        foreach ($groups as $group) {
            $id = (int) ($group instanceof Group ? $group->id : $group);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return null;
        }
        sort($ids);
        return implode(',', $ids);
    }

    /**
     * The groups of an ACL, read off its key — no query.
     *
     * @return list<int>
     */
    public static function groupIds(?Model $acl): array
    {
        $key = (string) $acl?->groupIds;
        return $key === '' ? [] : array_map('intval', explode(',', $key));
    }
}
