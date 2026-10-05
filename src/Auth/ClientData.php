<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

/**
 * What the browser knows of the user (`users.js`, `sys.users`): the logged-in
 * one only — roles, group rights, fake roles. Anonymous: nothing.
 *
 * Until 2026-10-04 this was the legacy class `users`, which brought the script
 * along only when it happened to be loaded. loader.php registers it on every
 * request now, so it no longer depends on the legacy record.
 */
final class ClientData
{
    /** users.js and its data, once per request (loader.php). */
    public static function register(): void
    {
        \client::loadJS('sys', 'src/Auth/users.js', 'class', ['users']);
        \clientInterfaces::$clientDataClasses[] = self::class;
    }

    public static function clientData_export(): object
    {
        $active = Session::current();
        $data = [
            'ids' => [],
            'active' => null,
            'active_fake_roles' => [],
            'superuser_roles' => [],
        ];
        if ($active->user !== null) {
            $data['active'] = $active->id;
            $data['active_fake_roles'] = Session::fakeRoles();
            $data['superuser_roles'] = $active->isSuperuser ? $active->allRoleNames : [];
            $data['ids']['#' . $active->id] = [
                'id' => $active->id,
                'name' => $active->name,
                'role_ids' => $active->roleIds,
                'role_names' => $active->roleNames,
                'read' => $active->readGroupIds,
                'write' => $active->writeGroupIds,
                'isSuperuser' => $active->isSuperuser,
            ];
        }
        return (object) [
            'route' => 'singletons.users',
            'data' => $data,
        ];
    }
}
