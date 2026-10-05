<?php

declare(strict_types=1);

namespace Systopic\System\Tables\RolesOnGroups;

use Systopic\System\Tables\Groups\Model as Group;
use Systopic\System\Tables\Roles\Model as Role;

/**
 * What a role may do on a group — nothing here saves.
 */
final class Operator
{
    /**
     * Grant or take away one right. A row that grants nothing any more is
     * removed rather than kept with two zeros.
     *
     * @param Model|null     $link  the row as it stands, NULL if there is none
     * @param 'read'|'write' $right
     * @return array{0: list<Model>, 1: list<Model>} rows to save, rows to remove
     */
    public static function permit(?Model $link, Group $group, Role $role, string $right, bool $granted): array
    {
        if ($right !== 'read' && $right !== 'write') {
            throw new \InvalidArgumentException("unknown right $right");
        }
        if ($link === null) {
            if (!$granted) {
                return [[], []];
            }
            $link = new Model();
            $link->role = $role;
            $link->group = $group;
            $link->read = false;
            $link->write = false;
        }
        $link->$right = $granted;
        return $link->read || $link->write ? [[$link], []] : [[], [$link]];
    }
}
