<?php

declare(strict_types=1);

namespace Systopic\System\Queries\AuthFailures;

use Systopic\Db\Schema\Provisioner;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\AuthFailures\Model as AuthFailure;

/**
 * The failed code entries of one IP — `AuthFailures.sql`. Provisioning: the
 * first read creates `usm_authfailures`.
 */
final class AuthFailures
{
    public static function of(Scope $scope, string $ip): ?AuthFailure
    {
        Registry::register(AuthFailure::class);
        return $scope->rows(__DIR__ . '/AuthFailures.sql', ['ip' => $ip], provisioner: new Provisioner($scope->connection))->first(AuthFailure::class);
    }
}
