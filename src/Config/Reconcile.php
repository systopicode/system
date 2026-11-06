<?php

declare(strict_types=1);

namespace Systopic\System\Config;

/**
 * One config file against the definitions of the packages. See
 * .cursor/docs/config-struktur.md, "Abgleich".
 *
 *   missing    defined, not in the file: goes in when the file is saved
 *   orphan     below an installed package, defined nowhere: goes when the
 *              instance file of this very installation is saved. Another
 *              instance may run newer or older code, config.local.json lies
 *              nowhere else, config.json counts for every stage.
 *   foreign    below a package that is not installed here: stays
 *   misplaced  belongs into another file: shown, moved by a click
 *   exposed    a secret outside of config.local.json: shown, moved by a click
 *
 * Runs when a file is saved, for that file. Never at boot, and not in
 * `composer update`: that would change every instance file at once.
 */
final class Reconcile
{
    public const MISSING = 'missing';
    public const ORPHAN = 'orphan';
    public const FOREIGN = 'foreign';
    public const MISPLACED = 'misplaced';
    public const EXPOSED = 'exposed';

    /** the packages that may define something */
    private const PACKAGES = ['system', 'db', 'cms', 'relay'];

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
        foreach (self::PACKAGES as $package) {
            $tree = (array) ($packages[$package] ?? []);
            $filled = self::filled($file, $package, $tree);
            foreach (array_keys(array_diff_key(self::leaves($filled), self::leaves($tree))) as $path) {
                $add(self::MISSING, $package, (string) $path);
            }
        }
        return $found;
    }

    /**
     * The file as it is to be saved: in the new layout, with the defaults of
     * this file, and without what is orphaned when it is `$own`. Keeps the
     * order, what is new comes last in its block.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function apply(string $file, array $data, bool $own): array
    {
        $data = Layout::lift($data);
        foreach (self::PACKAGES as $package) {
            $tree = (array) ($data['packages'][$package] ?? []);
            $filled = self::filled($file, $package, $tree);
            if ($filled !== [] && $filled !== $tree) {
                $data['packages'][$package] = $filled;
            }
        }
        if ($own && self::kind($file) === 'machine') {
            foreach (self::findings($file, $data) as $finding) {
                if ($finding['status'] === self::ORPHAN) {
                    Tree::drop($data, ['packages', $finding['package'], ...explode('.', $finding['path'])]);
                }
            }
        }
        return $data;
    }

    /**
     * Moves the secrets out of a file into config.local.json. What local
     * already has wins, it is the newer one. Both arrays are in the new layout.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $local
     * @return list<string> what moved
     */
    public static function moveSecrets(array &$data, array &$local): array
    {
        $moved = [];
        foreach ((array) ($data['packages'] ?? []) as $package => $tree) {
            foreach (self::leaves((array) $tree) as $path => $value) {
                if (empty(Schema::field($package, $path)[0]['writeOnly'])) {
                    continue;
                }
                $keys = ['packages', $package, ...explode('.', $path)];
                if ((string) $value !== '' && Tree::get($local, $keys) === null) {
                    Tree::put($local, $keys, $value);
                }
                Tree::drop($data, $keys);
                $moved[] = "$package.$path";
            }
        }
        return $moved;
    }

    /** project, machine or local: which definitions speak for this file */
    public static function kind(string $file): string
    {
        return match ($file) {
            'config.json' => 'project',
            'config.local.json' => 'local',
            default => 'machine',
        };
    }

    /** Defaults only in the file they belong to: anywhere later they would cover the values below. */
    private static function filled(string $file, string $package, array $tree): array
    {
        $kind = self::kind($file);
        $definition = Schema::load($package)[$kind] ?? null;
        if ($kind === 'local' || $definition === null || (!empty($definition['x-optional']) && $tree === [])) {
            return $tree;
        }
        return Schema::fill($definition, $tree, strtoupper(explode('.', $file)[0]));
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
