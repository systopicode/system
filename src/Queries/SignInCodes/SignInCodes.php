<?php

declare(strict_types=1);

namespace Systopic\System\Queries\SignInCodes;

use Systopic\Db\Schema\Provisioner;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\SignInCodes\Model as SignInCode;

/**
 * One-time codes — `SignInCodes.byHash.sql`, `SignInCodes.byPollHash.sql`.
 * Provisioning: the first read creates `usm_signincodes`.
 */
final class SignInCodes
{
    public static function byHash(Scope $scope, string $hash): ?SignInCode
    {
        return self::one($scope, 'SignInCodes.byHash.sql', $hash);
    }

    public static function byPollHash(Scope $scope, string $hash): ?SignInCode
    {
        return self::one($scope, 'SignInCodes.byPollHash.sql', $hash);
    }

    private static function one(Scope $scope, string $file, string $hash): ?SignInCode
    {
        if ($hash === '') {
            return null;
        }
        Registry::register(SignInCode::class);
        return $scope->rows(__DIR__ . '/' . $file, ['hash' => $hash], provisioner: new Provisioner($scope->connection))->first(SignInCode::class);
    }
}
