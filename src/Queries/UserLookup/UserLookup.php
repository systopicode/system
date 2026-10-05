<?php

declare(strict_types=1);

namespace Systopic\System\Queries\UserLookup;

use Systopic\Db\Scope;
use Systopic\System\Tables\Users\Model as User;

/**
 * Finding one user for the login forms — `UserLookup.sql`.
 */
final class UserLookup
{
    /** By id — the logged-in user's own row. */
    public static function byId(Scope $scope, int $id): ?User
    {
        return $id > 1 ? self::find($scope, $id, null, null) : null;
    }

    /** By name or email. */
    public static function byName(Scope $scope, string $name): ?User
    {
        return $name === '' ? null : self::find($scope, null, null, $name);
    }

    /** By the key of an activation or reset mail — keys are 32 hex chars. */
    public static function byActivationKey(Scope $scope, ?string $key): ?User
    {
        return strlen((string) $key) === 32 ? self::find($scope, null, $key, null) : null;
    }

    private static function find(Scope $scope, ?int $id, ?string $key, ?string $name): ?User
    {
        $rows = $scope->rows(__DIR__ . '/UserLookup.sql', ['id' => $id, 'key' => $key, 'name' => $name]);
        /** @var User|null */
        return $rows->first(User::class);
    }
}
