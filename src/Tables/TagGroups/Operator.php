<?php

declare(strict_types=1);

namespace Systopic\System\Tables\TagGroups;

/**
 * What can be done to tag groups — nothing here saves.
 */
final class Operator
{
    /**
     * A new group, named `new_taggroup_<n>` with the first n no group has.
     *
     * @param list<Model> $existing
     */
    public static function create(array $existing): Model
    {
        $taken = array_flip(array_map(static fn(Model $g): string => (string) $g->name, $existing));
        $n = 1;
        while (isset($taken["new_taggroup_$n"])) {
            $n++;
        }
        $group = new Model();
        $group->name = "new_taggroup_$n";
        return $group;
    }
}
