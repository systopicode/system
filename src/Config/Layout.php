<?php

declare(strict_types=1);

namespace Systopic\System\Config;

/**
 * One config file as a tree of packages - whichever way it is written.
 *
 * The files move from the old layout (`db`, `relay`, `debug` … at the top) to
 * `packages.<package>`, project by project. Nothing that reads the config has
 * to know: every file passes through here on its way in. In a file holding
 * both, the new place wins.
 *
 * Each file is lifted on its own, before merging. Merged first, an instance
 * that has moved already would beat a config.local.json that has not.
 *
 * Goes away with the old paths, step 8 in .cursor/docs/config-struktur.md.
 */
final class Layout
{
    /** top level keys that belong to packages.system */
    private const SYSTEM = ['debug', 'versionAppendix', 'versionDate', 'linkSites', 'assets', 'sysPath', 'ini', 'auth', 'mail'];

    /** what packages() takes out of the top of a file */
    public const MOVED = [...self::SYSTEM, 'db', 'relay'];

    /** the constants of the system - every other one stays free, at the top */
    public const SYSTEM_CONSTANTS = ['DEFAULT_TABLE_PREFIX', 'PROJECT_NAMESPACE', 'ACCOUNT_WHITELIST'];

    /**
     * @param array<string, mixed> $file
     * @return array<string, array<string, mixed>> package => its part of this file
     */
    public static function packages(array $file): array
    {
        $tree = [];
        foreach (self::SYSTEM as $key) {
            if (array_key_exists($key, $file)) {
                $tree['system'][$key] = $file[$key];
            }
        }
        $old = $tree['system']['auth']['default_login_method'] ?? null;
        if ($old !== null) {
            $tree['system']['auth']['defaultSignInMethod'] ??= $old;
            unset($tree['system']['auth']['default_login_method']);
        }
        foreach (self::SYSTEM_CONSTANTS as $name) {
            if (array_key_exists($name, (array) ($file['constants'] ?? []))) {
                $tree['system']['constants'][$name] = $file['constants'][$name];
            }
        }
        if (is_array($file['db'] ?? null)) {
            $db = $file['db'];
            if (array_key_exists('bindOnlyUsedParams', $db)) {
                $tree['db']['bindOnlyUsedParams'] = $db['bindOnlyUsedParams'];
                unset($db['bindOnlyUsedParams']);
            }
            $tree['db']['connections']['main'] = $db;
        }
        if (is_array($file['relay'] ?? null)) {
            $tree['relay'] = $file['relay'];
        }
        return Config::merge($tree, is_array($file['packages'] ?? null) ? $file['packages'] : []);
    }
}
