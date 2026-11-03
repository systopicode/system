<?php

declare(strict_types=1);

namespace Systopic\System\Tables\MigrationHooks;

use Systopic\Db\Schema\Col;
use Systopic\Db\Schema\Index;
use Systopic\Db\TableRow;

/**
 * A migration hook that has run — `sys_migration_hooks`, the bookkeeping of
 * `Db\Schema\Migrations`.
 *
 * Migrations writes it itself, without this class (it creates the table
 * when it first needs it, on the error path). The class is here so the
 * schema knows the table: Status shows it with its model, and the audit
 * neither calls it an orphan nor stays blind to other tables under `sys_`.
 * The shape follows the house convention Migrations explains
 * (`migration_hook_id`, `migration_hook_name`).
 */
final class Model extends TableRow
{
    public const TABLE        = 'migration_hooks';
    public const TABLE_PREFIX = 'sys';
    public const PREFIX       = 'migration_hook';

    /** The migration that ran: its class name, once. */
    #[Col(length: 190, index: Index::Unique)]
    public string $name = '' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
