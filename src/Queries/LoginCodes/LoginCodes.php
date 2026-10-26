<?php

declare(strict_types=1);

namespace Systopic\System\Queries\LoginCodes;

use Systopic\Db\Schema\Provisioner;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\LoginCodes\Model as LoginCode;

/**
 * One-time codes — `LoginCodes.byHash.sql`, `LoginCodes.byPollHash.sql`.
 * Provisioning: the first read creates `usm_logincodes`.
 */
final class LoginCodes
{
    public static function byHash(Scope $scope, string $hash): ?LoginCode
    {
        return self::one($scope, 'LoginCodes.byHash.sql', $hash);
    }

    public static function byPollHash(Scope $scope, string $hash): ?LoginCode
    {
        return self::one($scope, 'LoginCodes.byPollHash.sql', $hash);
    }

    private static function one(Scope $scope, string $file, string $hash): ?LoginCode
    {
        if ($hash === '') {
            return null;
        }
        Registry::register(LoginCode::class);
        return $scope->rows(__DIR__ . '/' . $file, ['hash' => $hash], provisioner: new Provisioner($scope->connection))->first(LoginCode::class);
    }
}
