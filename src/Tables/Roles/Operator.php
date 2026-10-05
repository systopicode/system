<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Roles;

use Systopic\Db\Type\Text;

/**
 * What can be done to roles — nothing here saves.
 */
final class Operator
{
    /** The fields the role editor writes. */
    public const EDITABLE = ['name', 'description', 'color'];

    /** A new role. Save it, then save `named()` of it — the name carries the id. */
    public static function create(): Model
    {
        $role = new Model();
        $role->color = '#cccccc';
        return $role;
    }

    public static function named(Model $role): Model
    {
        $role->name = "##New Role {$role->id}";
        return $role;
    }

    /** One field from the editor — only those in `EDITABLE`; empty is NULL. */
    public static function assign(Model $role, string $field, string $value): Model
    {
        $value = trim($value);
        match ($field) {
            'name'        => $role->name = $value,
            'description' => $role->description = $value === '' ? null : new Text($value),
            'color'       => $role->color = $value === '' ? null : $value,
            default       => throw new \InvalidArgumentException("role field $field is not editable"),
        };
        return $role;
    }
}
