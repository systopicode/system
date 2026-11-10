<?php

declare(strict_types=1);

namespace Systopic\System\Config;

/**
 * One config file against the definitions of the packages. See
 * .cursor/docs/config-struktur.md, "Abgleich".
 *
 *   orphan     below an installed package, defined nowhere: goes by a click
 *              in config.local.json. config.json counts for every
 *              installation, another one may run newer or older code.
 *   foreign    below a package that is not installed here: stays
 *   misplaced  belongs into the other file: a value of the installation in
 *              config.json, or a value of the project that may not be
 *              overridden in config.local.json. Shown.
 *   exposed    a secret in config.json, which travels with git: shown
 *
 * A save writes what was changed and nothing else: no defaults go into a
 * file, they lie at the bottom of the tree (Schema). Never at boot, and not in
 * `composer update`.
 */
final class Reconcile
{
    public const ORPHAN = 'orphan';
    public const FOREIGN = 'foreign';
    public const MISPLACED = 'misplaced';
    public const EXPOSED = 'exposed';

    /** the packages that may define something */
    public const PACKAGES = ['system', 'db', 'cms', 'relay'];

    /**
     * @param array<string, mixed> $data the file, in either layout
     * @return list<array{status: string, package: string, path: string, owner: string}>
     */
    public static function findings(string $file, array $data): array
    {
        $kind = self::kind($file);
        $packages = Layout::packages($data);
        $found = [];
        $add = static function (string $status, string $package, string $path, string $owner = '') use (&$found): void {
            $found[] = ['status' => $status, 'package' => $package, 'path' => $path, 'owner' => $owner];
        };
        foreach ($packages as $package => $tree) {
            if (!Schema::installed($package)) {
                $add(self::FOREIGN, $package, '');
                continue;
            }
            foreach (self::leaves($tree) as $path => $value) {
                if (Schema::open($package, $path)) {
                    continue;
                }
                $field = Schema::field($package, $path);
                if ($field === null) {
                    $add(self::ORPHAN, $package, $path);
                } elseif (!empty($field[0]['writeOnly'])) {
                    if ($kind !== 'local' && (string) $value !== '') {
                        $add(self::EXPOSED, $package, $path, 'local');
                    }
                } elseif ($field[1] !== $kind && empty($field[0]['x-overridable'])) {
                    $add(self::MISPLACED, $package, $path, $field[1]);
                }
            }
        }
        return $found;
    }

    /**
     * The file without what nobody defines. `$paths` limits it to these
     * (`package.path`), null takes every orphan.
     *
     * @param array<string, mixed> $data
     * @param list<string>|null    $paths
     * @return array<string, mixed>
     */
    public static function withoutOrphans(string $file, array $data, ?array $paths = null): array
    {
        $data = Layout::lift($data);
        foreach (self::findings($file, $data) as $finding) {
            $name = $finding['package'] . '.' . $finding['path'];
            if ($finding['status'] === self::ORPHAN && ($paths === null || in_array($name, $paths, true))) {
                Tree::drop($data, ['packages', $finding['package'], ...explode('.', $finding['path'])]);
            }
        }
        return $data;
    }

    /** project or local: which values belong into this file */
    public static function kind(string $file): string
    {
        return $file === 'config.json' ? 'project' : 'local';
    }

    /**
     * Every value below a package: dotted path => value. A list is one value.
     *
     * @return array<string, mixed>
     */
    private static function leaves(array $tree, string $path = ''): array
    {
        $out = [];
        foreach ($tree as $key => $value) {
            if ($key === '//') {
                continue;
            }
            $full = $path === '' ? (string) $key : "$path.$key";
            if (is_array($value) && !array_is_list($value) && $value !== []) {
                $out += self::leaves($value, $full);
            } elseif ($value !== []) {
                $out[$full] = $value;
            }
        }
        return $out;
    }
}
