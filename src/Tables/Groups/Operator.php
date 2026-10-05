<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Groups;

use Systopic\Db\Type\Text;

/**
 * What can be done to groups — nothing here saves.
 */
final class Operator
{
    /** The fields the group editor writes. */
    public const EDITABLE = ['name', 'description', 'color'];

    /**
     * A new group. Save it, then save `named()` of it: the name is unique and
     * carries the id, so the row goes in unnamed (NULL may repeat, '' not).
     */
    public static function create(): Model
    {
        return new Model();
    }

    public static function named(Model $group): Model
    {
        $group->name = "##New Group {$group->id}";
        return $group;
    }

    /** One field from the editor — only those in `EDITABLE`; empty is NULL. */
    public static function assign(Model $group, string $field, string $value): Model
    {
        $value = trim($value);
        match ($field) {
            'name'        => $group->name = $value === '' ? null : $value,
            'description' => $group->description = $value === '' ? null : new Text($value),
            'color'       => $group->color = $value === '' ? null : $value,
            default       => throw new \InvalidArgumentException("group field $field is not editable"),
        };
        return $group;
    }
}
