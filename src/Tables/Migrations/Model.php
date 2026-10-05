<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Migrations;

use DateTimeImmutable;
use Systopic\Db\TableRow;
use Systopic\Db\Type\Text;

/**
 * What the legacy migration mechanism wrote down — `cms_migrations`.
 *
 * **Not** `Db\Schema\Migrations`, which books hooks in `sys_migration_hooks`.
 * Two mechanisms, two tables, and for the duration of the rebuild both exist:
 * this one records a file and the SQL it ran, the new one records only that a
 * named hook has run and leaves the code in the class.
 *
 * The class is called `MigrationLog` and not `Migration` so that it cannot be
 * confused at an import with `Db\Schema\Migration`, which is the base class
 * for a hook.
 */
final class Model extends TableRow
{
    public const TABLE  = 'migrations';
    public const PREFIX = 'migration';

    public ?string $file {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?DateTimeImmutable $date {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Text $query {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
