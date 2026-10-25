<?php

declare(strict_types=1);

namespace Systopic\System\Queries\SystemStatus;

use Systopic\Db\Scope;

/** The facts of the system widget on the dashboard that only the database knows. */
final class SystemStatus
{
    public static function databaseVersion(Scope $scope): string
    {
        return (string) ($scope->rows(__DIR__ . '/SystemStatus.sql', [])->looseValues()[0]['status_db_version'] ?? '');
    }
}
