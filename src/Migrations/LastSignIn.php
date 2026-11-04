<?php

declare(strict_types=1);

namespace Systopic\System\Migrations;

use Systopic\Db\Connection;
use Systopic\Db\Schema\Migration;

/**
 * `user_last_login` becomes `user_last_sign_in`. The old column stays until
 * `db audit` drops it: an older checkout on the same database still reads it.
 *
 * The groups `authPersons` and `authScreens` go along: they are rights of
 * the controlmap now (`panels/panel.config.json`).
 */
final class LastSignIn extends Migration
{
    public const ON_CREATE = 'usm_users.user_last_sign_in';

    public function run(Connection $connection): void
    {
        $old = (int) $connection->value(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usm_users' AND COLUMN_NAME = 'user_last_login'"
        );
        if ($old > 0) {
            $connection->exec('UPDATE usm_users SET user_last_sign_in = user_last_login WHERE user_last_sign_in IS NULL');
        }
        $groups = (int) $connection->value(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usm_groups'"
        );
        if ($groups > 0) {
            $connection->exec("DELETE FROM usm_groups WHERE group_name IN ('authPersons', 'authScreens')");
        }
    }
}
