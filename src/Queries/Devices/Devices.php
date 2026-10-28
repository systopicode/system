<?php

declare(strict_types=1);

namespace Systopic\System\Queries\Devices;

use Systopic\Db\Schema\Provisioner;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\Devices\Model as Device;

/**
 * Devices — `Devices.byId.sql`, `Devices.ofUser.sql`. Provisioning: the
 * first read on an installation without `usm_devices` creates it.
 */
final class Devices
{
    public static function byId(Scope $scope, int $id): ?Device
    {
        if ($id <= 0) {
            return null;
        }
        Registry::register(Device::class);
        return $scope->rows(__DIR__ . '/Devices.byId.sql', ['id' => $id], provisioner: new Provisioner($scope->connection))->first(Device::class);
    }

    /** @return list<Device> not revoked, newest use first */
    public static function active(Scope $scope): array
    {
        Registry::register(Device::class);
        return $scope->rows(__DIR__ . '/Devices.active.sql', [], provisioner: new Provisioner($scope->connection))->all(Device::class);
    }

    /** @return list<Device> not revoked, newest use first */
    public static function ofUser(Scope $scope, int $userId): array
    {
        Registry::register(Device::class);
        return $scope->rows(__DIR__ . '/Devices.ofUser.sql', ['userId' => $userId], provisioner: new Provisioner($scope->connection))->all(Device::class);
    }
}
