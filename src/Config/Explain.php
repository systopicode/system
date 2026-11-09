<?php

declare(strict_types=1);

namespace Systopic\System\Config;

/**
 * Where a value comes from. Sees the layers of one instance side by side,
 * the way Config::package() merges them:
 *
 *   package / stage / computed   the definition (default, x-stages, x-computed)
 *   project                      config.json
 *   instance                     <instance>.config.json
 *   local                        config.local.json, when the instance runs here
 *
 * The later one wins. Works on plain arrays, the caller reads the files. See
 * .cursor/plans/settings-gui.plan.md.
 */
final class Explain
{
    /** the files, in the order they are merged */
    public const LAYERS = ['project', 'instance', 'local'];

    /** @var array<string, array<string, mixed>> */
    private static array $definitions = [];

    /**
     * All definitions of a package as one tree, in the order project,
     * machine, local. Every value carries `x-file`, the file it belongs to.
     *
     * @return array<string, mixed>
     */
    public static function definition(string $package): array
    {
        if (isset(self::$definitions[$package])) {
            return self::$definitions[$package];
        }
        $tree = ['type' => 'object', 'properties' => []];
        foreach (Schema::load($package) as $file => $definition) {
            $tree = self::join($tree, self::mark($definition, $file));
        }
        return self::$definitions[$package] = $tree;
    }

    /** Is this node a value, not a block? Same rule as Schema::children(). */
    public static function isValue(array $node): bool
    {
        return !isset($node['properties']) && !is_array($node['additionalProperties'] ?? null) && ($node['type'] ?? '') !== 'object';
    }

    /**
     * One value through all layers.
     *
     * @param array<string, mixed>                $field  the definition of the value
     * @param array<string, array<string, mixed>|null> $layers layer => the packages of that file (null: not known here)
     * @param list<string>                        $keys   package, then the path
     * @param bool                                $active false when an optional block above it is nowhere: no default applies
     * @return array{stack: list<array{source: string, value: mixed}>, default: array{source: string, value: mixed}|null, source: string, value: mixed}
     */
    public static function value(array $field, array $layers, array $keys, string $stage, bool $active = true): array
    {
        $default = null;
        if ($stage !== '' && array_key_exists($stage, $field['x-stages'] ?? [])) {
            $default = ['source' => 'stage', 'value' => $field['x-stages'][$stage]];
        } elseif (!empty($field['x-computed'])) {
            $default = ['source' => 'computed', 'value' => null];
        } elseif (array_key_exists('default', $field)) {
            $default = ['source' => 'package', 'value' => $field['default']];
        }
        $stack = $default !== null && $active ? [$default] : [];
        foreach (self::LAYERS as $layer) {
            if (is_array($layers[$layer] ?? null) && self::has($layers[$layer], $keys)) {
                $stack[] = ['source' => $layer, 'value' => Tree::get($layers[$layer], $keys)];
            }
        }
        $top = $stack === [] ? ['source' => 'none', 'value' => null] : $stack[count($stack) - 1];
        return ['stack' => $stack, 'default' => $default, 'source' => $top['source'], 'value' => $top['value']];
    }

    /**
     * What applies when $layer has nothing: the entry below it.
     *
     * @param list<array{source: string, value: mixed}> $stack
     * @return array{source: string, value: mixed}
     */
    public static function below(array $stack, string $layer): array
    {
        $rank = array_flip(['package', 'stage', 'computed', ...self::LAYERS]);
        $found = ['source' => 'none', 'value' => null];
        foreach ($stack as $entry) {
            if ($rank[$entry['source']] < $rank[$layer]) {
                $found = $entry;
            }
        }
        return $found;
    }

    /** Does $layer hold the same as what lies below it? Then it can go. */
    public static function redundant(array $stack, string $layer): bool
    {
        foreach ($stack as $entry) {
            if ($entry['source'] === $layer) {
                $below = self::below($stack, $layer);
                return $below['source'] !== 'none' && $below['source'] !== 'computed' && $below['value'] === $entry['value'];
            }
        }
        return false;
    }

    /**
     * The values of one file that only repeat what lies below them. An
     * optional block or a named entry keeps one value, it would vanish
     * otherwise, and with it its defaults.
     *
     * @param array<string, array<string, mixed>|null> $layers
     * @return list<array{package: string, path: string}>
     */
    public static function redundantIn(array $layers, string $layer, string $stage): array
    {
        $found = [];
        foreach ((array) ($layers[$layer] ?? []) as $package => $tree) {
            if (!is_array($tree) || !Schema::installed((string) $package)) {
                continue;
            }
            $definition = self::definition((string) $package);
            foreach (self::blocks($definition, $tree, []) as $block) {
                $drop = [];
                foreach ($block['values'] as $key => $field) {
                    $keys = [$package, ...$block['keys'], $key];
                    if (!empty($field['writeOnly']) || !self::has($layers[$layer], $keys)) {
                        continue;
                    }
                    $active = self::active($definition, $layers, [...$block['keys'], $key], (string) $package);
                    if (self::redundant(self::value($field, $layers, $keys, $stage, $active)['stack'], $layer)) {
                        $drop[] = implode('.', [...$block['keys'], $key]);
                    }
                }
                $kept = array_diff(array_keys(array_diff_key((array) Tree::get($tree, $block['keys']), ['//' => true])), array_map(static fn(string $p): string => substr((string) strrchr('.' . $p, '.'), 1), $drop));
                if ($block['keep'] && $kept === [] && $drop !== []) {
                    array_pop($drop);
                }
                foreach ($drop as $path) {
                    $found[] = ['package' => (string) $package, 'path' => $path];
                }
            }
        }
        return $found;
    }

    /**
     * Do the optional blocks above a path exist in any layer? Without them
     * Config::package() gives their values no default.
     *
     * @param list<string> $path below the package
     */
    public static function active(array $definition, array $layers, array $path, string $package): bool
    {
        $node = $definition;
        $keys = [];
        $exists = static function (array $keys) use ($layers, $package): bool {
            foreach (self::LAYERS as $layer) {
                if (is_array($layers[$layer] ?? null) && self::has($layers[$layer], [$package, ...$keys])) {
                    return true;
                }
            }
            return false;
        };
        if (!empty($node['x-optional']) && !$exists([])) {
            return false;
        }
        foreach ($path as $key) {
            $node = $node['properties'][$key] ?? (is_array($node['additionalProperties'] ?? null) ? $node['additionalProperties'] : null);
            $keys[] = $key;
            if (!is_array($node)) {
                return true;
            }
            if (!empty($node['x-optional']) && !$exists($keys)) {
                return false;
            }
        }
        return true;
    }

    /** @param list<string> $keys */
    public static function has(array $tree, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!is_array($tree) || !array_key_exists($key, $tree)) {
                return false;
            }
            $tree = $tree[$key];
        }
        return true;
    }

    /** for a test */
    public static function reset(): void
    {
        self::$definitions = [];
    }

    /**
     * The blocks of a definition that a tree has: keys, their values, and
     * whether the block must not run empty (optional block, named entry).
     *
     * @return list<array{keys: list<string>, values: array<string, array<string, mixed>>, keep: bool}>
     */
    private static function blocks(array $node, array $tree, array $keys, bool $keep = false): array
    {
        $values = [];
        $out = [];
        foreach ($node['properties'] ?? [] as $key => $child) {
            if (self::isValue($child)) {
                $values[(string) $key] = $child;
            } elseif (is_array($child['additionalProperties'] ?? null)) {
                foreach ((array) ($tree[$key] ?? []) as $name => $entry) {
                    if (is_array($entry)) {
                        array_push($out, ...self::blocks($child['additionalProperties'], $entry, [...$keys, (string) $key, (string) $name], true));
                    }
                }
            } elseif (is_array($tree[$key] ?? null)) {
                array_push($out, ...self::blocks($child, $tree[$key], [...$keys, (string) $key], !empty($child['x-optional'])));
            }
        }
        array_unshift($out, ['keys' => $keys, 'values' => $values, 'keep' => $keep || ($keys === [] && !empty($node['x-optional']))]);
        return $out;
    }

    /** every value of a definition gets the file it lies in */
    private static function mark(array $node, string $file, bool $overridable = false): array
    {
        if (self::isValue($node)) {
            return $node + ['x-file' => $file] + ($overridable ? ['x-overridable' => true] : []);
        }
        $overridable = $overridable || !empty($node['x-overridable']);
        foreach ($node['properties'] ?? [] as $key => $child) {
            $node['properties'][$key] = self::mark($child, $file, $overridable);
        }
        if (is_array($node['additionalProperties'] ?? null)) {
            $node['additionalProperties'] = self::mark($node['additionalProperties'], $file, $overridable);
        }
        return $node;
    }

    /** two definitions of the same block as one: the first one's order, then what is new */
    private static function join(array $a, array $b): array
    {
        foreach ($b as $key => $value) {
            if ($key === 'properties') {
                foreach ($value as $name => $child) {
                    $a['properties'][$name] = isset($a['properties'][$name]) && !self::isValue($child) ? self::join($a['properties'][$name], $child) : $child;
                }
            } elseif ($key === 'additionalProperties' && is_array($value) && is_array($a[$key] ?? null)) {
                $a[$key] = self::join($a[$key], $value);
            } elseif (!array_key_exists($key, $a) || in_array($key, ['x-optional'], true) && !empty($value)) {
                $a[$key] = $value;
            }
        }
        return $a;
    }
}
