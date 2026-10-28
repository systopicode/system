<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use Systopic\Db\Scope;
use Systopic\System\Queries\Devices\Devices;

/**
 * Who has the page open, according to the hub. Null means there is no
 * live answer: this site has no relay, or the hub did not answer.
 * Last seen is not online. A device can be online and still inactive:
 * the tab is hidden, or nobody touched it for a few minutes.
 */
final class Presence
{
    /** @var array<int, true>|null */
    private static ?array $devices = null;

    /** @var array<int, true>|null */
    private static ?array $active = null;

    /** @var list<int>|null */
    private static ?array $ids = null;

    private static bool $known = false;

    private static bool $activeKnown = false;

    private static bool $idsKnown = false;

    /** @return array<int, true>|null device id => the page is open */
    public static function devices(): ?array
    {
        if (self::$known) {
            return self::$devices;
        }
        self::$known = true;
        $ids = self::ids();
        if ($ids === null) {
            return self::$devices = null;
        }
        $online = \Systopic\Relay\Relay::presentDevices($ids);
        if ($online === null) {
            return self::$devices = null;
        }
        self::$devices = array_fill_keys($online, true);
        return self::$devices;
    }

    /** @return array<int, true>|null device id => the page is open and in use */
    public static function active(): ?array
    {
        if (self::$activeKnown) {
            return self::$active;
        }
        self::$activeKnown = true;
        if (self::devices() === null) {
            return self::$active = null;
        }
        $online = \Systopic\Relay\Relay::activeDevices(self::ids() ?? []);
        if ($online === null) {
            return self::$active = null;
        }
        self::$active = array_fill_keys($online, true);
        return self::$active;
    }

    /** @return array<int, true>|null user id => a device of theirs is online */
    public static function userIds(): ?array
    {
        return self::users(self::devices());
    }

    /** @return array<int, true>|null user id => a device of theirs is in use */
    public static function activeUserIds(): ?array
    {
        return self::users(self::active());
    }

    /**
     * Class and title for one device. The red offline style is the default,
     * so the class is empty then.
     *
     * @return array{0: string, 1: string}
     */
    public static function deviceMark(int $id): array
    {
        $online = self::devices();
        if ($online === null) {
            return ['is-stale', 'No live data'];
        }
        if (!isset($online[$id])) {
            return ['', 'Offline'];
        }
        $active = self::active();
        if ($active !== null && isset($active[$id])) {
            return ['is-on', 'Online'];
        }
        return ['is-idle', 'Online, inactive'];
    }

    /**
     * Class and title for one user.
     *
     * @return array{0: string, 1: string}
     */
    public static function userMark(int $id): array
    {
        $online = self::userIds();
        if ($online === null) {
            return ['is-stale', 'No live data'];
        }
        if (!isset($online[$id])) {
            return ['is-offline', 'Offline'];
        }
        $active = self::activeUserIds();
        if ($active !== null && isset($active[$id])) {
            return ['is-online', 'Online'];
        }
        return ['is-idle', 'Online, inactive'];
    }

    /** @param array<int, true>|null $devices @return array<int, true>|null */
    private static function users(?array $devices): ?array
    {
        if ($devices === null) {
            return null;
        }
        $users = [];
        foreach (Devices::active(Scope::default()) as $device) {
            if (isset($devices[(int) $device->id])) {
                $users[(int) $device->relationId('user')] = true;
            }
        }
        return $users;
    }

    /** @return list<int>|null null when the hub cannot be asked */
    private static function ids(): ?array
    {
        if (self::$idsKnown) {
            return self::$ids;
        }
        self::$idsKnown = true;
        if (!class_exists(\Systopic\Relay\Relay::class) || !\Systopic\Relay\Relay::enabled()) {
            return self::$ids = null;
        }
        $ids = [];
        foreach (Devices::active(Scope::default()) as $device) {
            $ids[] = (int) $device->id;
        }
        return self::$ids = $ids;
    }
}
