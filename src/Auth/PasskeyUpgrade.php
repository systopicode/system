<?php

declare(strict_types=1);
namespace Systopic\System\Auth;
use Systopic\Db\Connection;

/** Explicit, lossless upgrade of the pre-ORM passkey table. Run before enabling passkeys. */
final class PasskeyUpgrade
{
    public static function run(Connection $db): void
    {
        $columns = [];
        foreach ($db->run('SHOW COLUMNS FROM usm_passkeys')->fetchAll() as $column) $columns[$column['Field']] = $column;
        // Keep the existing credential digest column; preserve any newer digest values too.
        if (!isset($columns['passkey_credential_sha'])) {
            $db->run('ALTER TABLE usm_passkeys ADD passkey_credential_sha CHAR(64) NULL');
        }
        if (isset($columns['passkey_credential_hash'])) {
            $db->run("UPDATE usm_passkeys SET passkey_credential_sha = passkey_credential_hash WHERE (passkey_credential_sha IS NULL OR passkey_credential_sha = '') AND passkey_credential_hash <> ''");
            $db->run('ALTER TABLE usm_passkeys MODIFY passkey_credential_hash VARCHAR(64) NULL DEFAULT NULL');
        }
        if (isset($columns['passkey_record'])) {
            $db->run('ALTER TABLE usm_passkeys MODIFY passkey_record MEDIUMTEXT NULL DEFAULT NULL');
        }
        foreach (['passkey_project'=>'VARCHAR(64)', 'passkey_rp_id'=>'VARCHAR(253)', 'passkey_user_handle'=>'VARCHAR(64)'] as $name=>$type) {
            if (!isset($columns[$name])) $db->run("ALTER TABLE usm_passkeys ADD `$name` $type NOT NULL DEFAULT ''");
        }
        $db->run("ALTER TABLE usm_passkeys MODIFY passkey_name VARCHAR(100) NULL DEFAULT NULL");
        $unique = [];
        foreach ($db->run('SHOW INDEX FROM usm_passkeys')->fetchAll() as $index) {
            if ((int) $index['Non_unique'] === 0) $unique[$index['Key_name']][] = $index['Column_name'];
        }
        if (!in_array(['passkey_credential_sha'], $unique, true)) {
            $db->run('CREATE UNIQUE INDEX passkey_credential_sha_unique ON usm_passkeys (passkey_credential_sha)');
        }
        // Existing unscoped records stay unscoped. Never assign another project's credentials.
    }
}
