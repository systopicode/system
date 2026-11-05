<?php

declare(strict_types=1);

namespace Systopic\System\Queries\LastSignIn;

use DateTimeImmutable;
use Systopic\Db\Scope;
use Systopic\System\Tables\Requests\Model as Request;

/**
 * When a user last signed in, as the request log has it — `LastSignIn.sql`.
 */
final class LastSignIn
{
    public static function of(Scope $scope, int $userId): ?DateTimeImmutable
    {
        return $scope->rows(__DIR__ . '/LastSignIn.sql', ['userId' => $userId])->first(Request::class)?->datetime;
    }
}
