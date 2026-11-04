<?php

declare(strict_types=1);

namespace Systopic\System\Migrations;

use Systopic\Db\Connection;
use Systopic\Db\Schema\Migration;

/** `user_first_login` becomes `user_first_sign_in`, see `LastSignIn`. */
final class FirstSignIn extends Migration
{
    public const ON_CREATE = 'usm_users.user_first_sign_in';

    public function run(Connection $connection): void
    {
        $old = (int) $connection->value(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usm_users' AND COLUMN_NAME = 'user_first_login'"
        );
        if ($old > 0) {
            $connection->exec('UPDATE usm_users SET user_first_sign_in = user_first_login WHERE user_first_sign_in IS NULL');
        }
    }
}
