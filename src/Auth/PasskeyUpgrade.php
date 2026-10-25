<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use Systopic\Db\Connection;
use Systopic\Db\Scope;
use Systopic\System\Queries\Passkeys\Passkeys as Keys;
use Systopic\System\Tables\Passkeys\Model as Key;

/** Lossless compatibility update, checked only when credentials are added or renamed. */
final class PasskeyUpgrade
{
    public static function save(Scope $scope, Key $key): void
    {
        self::prepare($scope);
        $scope->save($key);
    }

    /** Also called before opening the native registration prompt. */
    public static function prepare(Scope $scope): void
    {
        if ($scope->connection->pdo()->inTransaction()) {
            throw new \LogicException('Passkey schema updates must run before a transaction.');
        }
        Keys::ofUser($scope, -1); // Existing provisioner creates a missing table.
        self::run($scope->connection);
    }

    public static function run(Connection $db): void
    {
        if ($db->pdo()->inTransaction()) {
            throw new \LogicException('Passkey schema updates must run before a transaction.');
        }
        $lock = 'passkey-schema-' . substr(hash('sha256', (string) $db->value('SELECT DATABASE()')), 0, 40);
        if ((int) $db->value('SELECT GET_LOCK(?, 10)', [$lock]) !== 1) {
            throw new \RuntimeException('The passkey database is being updated. Please try again.');
        }
        try {
            self::update($db);
        } finally {
            $db->value('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    private static function update(Connection $db): void
    {
        $columns = [];
        foreach ($db->run('SHOW COLUMNS FROM usm_passkeys')->fetchAll() as $column) {
            $columns[$column['Field']] = $column;
        }
        if (!isset($columns['passkey_credential_sha'])) {
            $db->run('ALTER TABLE usm_passkeys ADD passkey_credential_sha CHAR(64) NULL');
        }
        if (isset($columns['passkey_credential_hash'])) {
            $db->run("UPDATE usm_passkeys SET passkey_credential_sha = passkey_credential_hash WHERE (passkey_credential_sha IS NULL OR passkey_credential_sha = '') AND passkey_credential_hash <> ''");
            if ($columns['passkey_credential_hash']['Null'] !== 'YES') {
                $db->run('ALTER TABLE usm_passkeys MODIFY passkey_credential_hash VARCHAR(64) NULL DEFAULT NULL');
            }
        }
        if (isset($columns['passkey_record']) && $columns['passkey_record']['Null'] !== 'YES') {
            $db->run('ALTER TABLE usm_passkeys MODIFY passkey_record MEDIUMTEXT NULL DEFAULT NULL');
        }
        foreach (['passkey_project' => 'VARCHAR(64)', 'passkey_rp_id' => 'VARCHAR(253)', 'passkey_user_handle' => 'VARCHAR(64)'] as $name => $type) {
            if (!isset($columns[$name])) $db->run("ALTER TABLE usm_passkeys ADD `$name` $type NOT NULL DEFAULT ''");
        }
        if (preg_match('/^varchar\((\d+)\)$/i', $columns['passkey_name']['Type'] ?? '', $size) && (int) $size[1] < 100) {
            $db->run('ALTER TABLE usm_passkeys MODIFY passkey_name VARCHAR(100) NULL DEFAULT NULL');
        }
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
