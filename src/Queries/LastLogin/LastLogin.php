<?php

declare(strict_types=1);

namespace Systopic\System\Queries\LastLogin;

use DateTimeImmutable;
use Systopic\Db\Scope;
use Systopic\System\Tables\Requests\Model as Request;

/**
 * When a user last logged in, as the request log has it — `LastLogin.sql`.
 */
final class LastLogin
{
    public static function of(Scope $scope, int $userId): ?DateTimeImmutable
    {
        return $scope->rows(__DIR__ . '/LastLogin.sql', ['userId' => $userId])->first(Request::class)?->datetime;
    }
}
