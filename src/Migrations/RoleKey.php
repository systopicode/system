<?php

declare(strict_types=1);

namespace Systopic\System\Migrations;

use Systopic\Db\Connection;
use Systopic\Db\Schema\Migration;
use Systopic\System\Tables\Roles\Operator;

/**
 * `usm_roles.role_key` replaces `role_builtin`: every role gets a key.
 *
 *   - the built-in ones keep theirs (`everyone`, `authenticated`)
 *   - the roles of the old CMS that the system now brings along become those
 *     (`LEGACY`): their users keep their rights, the name stays as it was
 *   - every other role gets one from its name (`Lobby Screen` → `lobbyScreen`)
 *
 * `role_builtin` stays until `db audit` drops it: an older checkout on the
 * same database still reads it.
 *
 * Runs when the provisioner adds the column (`UserRights::keyedRoles()`
 * hands it the migrations).
 */
final class RoleKey extends Migration
{
    public const ON_CREATE = 'usm_roles.role_key';

    /** Old role name => the system role it is. */
    public const LEGACY = [
        'admin'  => 'cmsAdmin',
        'editor' => 'cmsEditor',
        'texteditor' => 'cmsPageEditor',
    ];

    public function run(Connection $connection): void
    {
        $hasBuiltin = (int) $connection->value(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usm_roles' AND COLUMN_NAME = 'role_builtin'"
        );
        if ($hasBuiltin > 0) {
            $connection->exec('UPDATE usm_roles SET role_key = role_builtin WHERE role_key IS NULL AND role_builtin IS NOT NULL');
        }
        $taken = array_column($connection->all('SELECT role_key FROM usm_roles WHERE role_key IS NOT NULL'), 'role_key');
        $rows = $connection->all("SELECT role_id, role_name FROM usm_roles WHERE role_key IS NULL AND role_id != 1 AND role_name <> '' ORDER BY role_id");
        foreach ($rows as $row) {
            $legacy = self::LEGACY[strtolower((string) $row['role_name'])] ?? null;
            $key = $legacy !== null && !in_array($legacy, $taken, true)
                ? $legacy
                : Operator::keyFrom((string) $row['role_name'], $taken);
            $connection->run('UPDATE usm_roles SET role_key = ? WHERE role_id = ?', [$key, (int) $row['role_id']]);
            $taken[] = $key;
        }
    }
}
