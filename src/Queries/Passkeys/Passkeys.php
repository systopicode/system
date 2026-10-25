<?php

declare(strict_types=1);

namespace Systopic\System\Queries\Passkeys;

use Systopic\Db\Scope;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Schema\Provisioner;
use Systopic\System\Tables\Passkeys\Model as Passkey;

final class Passkeys
{
    /** @return list<Passkey> */
    public static function ofUser(Scope $scope, int $userId, ?string $project = null): array
    {
        Registry::register(Passkey::class);
        return $scope->rows(__DIR__ . '/Passkeys.ofUser.sql', ['userId' => $userId, 'project' => $project],
            provisioner: new Provisioner($scope->connection))->all(Passkey::class);
    }

    public static function byCredential(Scope $scope, string $id): ?Passkey
    {
        Registry::register(Passkey::class);
        return $scope->rows(__DIR__ . '/Passkeys.byCredential.sql', ['hash' => hash('sha256', $id)], cache: false,
            provisioner: new Provisioner($scope->connection))->first(Passkey::class);
    }
}
