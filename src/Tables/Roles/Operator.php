<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Roles;

use Systopic\Db\Type\Text;

/**
 * What can be done to roles — nothing here saves.
 */
final class Operator
{
    /**
     * The roles every request holds without anybody assigning them — the
     * well-known ones of Windows and Drupal. Key => the name they start with.
     *
     *   everyone       every request, signed in or not
     *   authenticated  every signed-in user
     *
     * What they may do is set like for any role (`usm_roles_on_groups`):
     * a group "public" that `everyone` may read is the public web site.
     */
    public const BUILTIN = [
        'everyone'      => 'Everyone',
        'authenticated' => 'Signed in',
    ];

    /**
     * The built-in roles that are missing from `$existing`, to save.
     *
     * @param array<string, Model> $existing key => role
     * @return list<Model>
     */
    public static function missingBuiltins(array $existing): array
    {
        $new = [];
        foreach (self::BUILTIN as $key => $name) {
            if (!isset($existing[$key])) {
                $role = new Model();
                $role->builtin = $key;
                $role->name = $name;
                $role->color = '#8a8f93';
                $new[] = $role;
            }
        }
        return $new;
    }

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
