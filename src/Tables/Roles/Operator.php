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
     * The roles the system brings along to be assigned — what the panels of
     * the packages ask for. Key => the name they start with.
     *
     *   cmsAdmin       everything in the CMS: backups, settings, deleting
     *   cmsEditor      edits pages and content, the page tree included
     *   cmsPageEditor  edits pages, but only orders them in the tree:
     *                  never gives a page another parent
     */
    public const SYSTEM = [
        'cmsAdmin'      => 'CMS Admin',
        'cmsEditor'     => 'CMS Editor',
        'cmsPageEditor' => 'CMS Page Editor',
    ];

    /** What a key looks like: a letter, then letters and digits (camelCase). */
    public const KEY_PATTERN = '~^[a-zA-Z][a-zA-Z0-9]{0,31}$~';

    /**
     * The system's roles (`BUILTIN`, `SYSTEM`) that are missing from
     * `$existing`, to save.
     *
     * @param array<string, Model> $existing key => role
     * @return list<Model>
     */
    public static function missingSystemRoles(array $existing): array
    {
        $new = [];
        foreach (self::BUILTIN + self::SYSTEM as $key => $name) {
            if (!isset($existing[$key])) {
                $role = new Model();
                $role->key = $key;
                $role->name = $name;
                $role->color = '#8a8f93';
                $new[] = $role;
            }
        }
        return $new;
    }

    /**
     * A key for a role named `$name`: camelCase of its words
     * (`Lobby Screen` → `lobbyScreen`, `CMS Admin` → `cmsAdmin`,
     * `HREditor` → `hrEditor`), with a number when `$taken` has it.
     *
     * @param list<string> $taken the keys there are already
     */
    public static function keyFrom(string $name, array $taken): string
    {
        $words = array_map('ucfirst', preg_split('~[^a-zA-Z0-9]+~', $name, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($words !== []) {
            // a leading abbreviation goes lower case as a whole, else the first letter
            $words[0] = preg_match('~^[A-Z]+(?=[A-Z][a-z]|$)~', $words[0], $m)
                ? strtolower($m[0]) . substr($words[0], strlen($m[0]))
                : lcfirst($words[0]);
        }
        $key = implode('', $words);
        if (!preg_match('~^[a-zA-Z]~', $key)) {
            $key = 'role' . ucfirst($key);
        }
        $key = substr($key, 0, 28);
        $taken = array_flip(array_map('strtolower', $taken));
        $candidate = $key;
        for ($n = 2; isset($taken[strtolower($candidate)]); $n++) {
            $candidate = $key . $n;
        }
        return $candidate;
    }

    /** The fields the role editor writes. */
    public const EDITABLE = ['name', 'key', 'description', 'color'];

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
        $role->key = "role{$role->id}";
        return $role;
    }

    /**
     * One field from the editor — only those in `EDITABLE`; empty is NULL.
     * The key of a system role stays; another key has to fit `KEY_PATTERN`
     * (unique the database says).
     */
    public static function assign(Model $role, string $field, string $value): Model
    {
        $value = trim($value);
        if ($field === 'key' && ($role->isSystem || !preg_match(self::KEY_PATTERN, $value))) {
            throw new \InvalidArgumentException($role->isSystem
                ? "the key of the system role {$role->key} is fixed"
                : 'a key is a letter, then letters and digits, at most 32');
        }
        match ($field) {
            'name'        => $role->name = $value,
            'key'         => $role->key = $value,
            'description' => $role->description = $value === '' ? null : new Text($value),
            'color'       => $role->color = $value === '' ? null : $value,
            default       => throw new \InvalidArgumentException("role field $field is not editable"),
        };
        return $role;
    }
}
