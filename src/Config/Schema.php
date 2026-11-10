<?php

declare(strict_types=1);

namespace Systopic\System\Config;

use Composer\InstalledVersions;

/**
 * The definition of a package: `<package>/config/config.schema.json`, JSON
 * Schema with `x-` keywords. One file per package, the master of every
 * block and value: title, description, order, defaults, where it is kept.
 * See .cursor/docs/config-struktur.md.
 *
 * A value belongs to this installation (config.local.json) unless it or a
 * block above it says `x-project`: then it is the same everywhere and lives
 * in config.json. load() marks every value with its file (`x-file`:
 * project or local), so nobody has to walk up again.
 *
 * The package defines, the project stores. What the definitions say about a
 * value that is not in any file comes from here, at the bottom of the tree:
 * a new default works right away, no file has to change.
 */
final class Schema
{
    /** the definition file of a package, in its config/ */
    public const FILE = 'config.schema.json';

    /** @var array<string, array<string, mixed>> package => definition, marked */
    private static array $loaded = [];

    /**
     * The definition of a package, every value marked with `x-file` and,
     * below an overridable block, `x-overridable`. [] when the package has
     * none.
     *
     * @return array<string, mixed>
     */
    public static function load(string $package): array
    {
        if (isset(self::$loaded[$package])) {
            return self::$loaded[$package];
        }
        $file = self::file($package);
        $definition = $file === null ? null : json_decode((string) file_get_contents($file), true);
        return self::$loaded[$package] = is_array($definition) ? self::mark($definition) : [];
    }

    /**
     * Every value gets the file it is kept in, from itself or the nearest
     * block above it: `x-project` → project, else local.
     *
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private static function mark(array $node, bool $project = false, bool $overridable = false): array
    {
        $project = $project || !empty($node['x-project']);
        if (!isset($node['properties']) && !is_array($node['additionalProperties'] ?? null) && ($node['type'] ?? '') !== 'object') {
            return ['x-file' => $project ? 'project' : 'local'] + $node + ($overridable ? ['x-overridable' => true] : []);
        }
        $overridable = $overridable || !empty($node['x-overridable']);
        foreach ($node['properties'] ?? [] as $key => $child) {
            $node['properties'][$key] = self::mark($child, $project, $overridable);
        }
        if (is_array($node['additionalProperties'] ?? null)) {
            $node['additionalProperties'] = self::mark($node['additionalProperties'], $project, $overridable);
        }
        return $node;
    }

    /**
     * Where a package lies. Asked of composer: `db` does not know `system` and
     * cannot sign up anywhere. Without composer the packages lie side by side,
     * in vendor/systopic/ and in packages/ alike.
     */
    private static function directory(string $package): ?string
    {
        $name = "systopic/$package";
        if (class_exists(InstalledVersions::class)) {
            $path = InstalledVersions::isInstalled($name) ? InstalledVersions::getInstallPath($name) : null;
            if ($path !== null) {
                return rtrim(str_replace('\\', '/', $path), '/');
            }
            if ($package !== 'system') {
                return null;
            }
        }
        $dir = str_replace('\\', '/', dirname(__DIR__, 3)) . "/$package";
        return is_dir($dir) ? $dir : null;
    }

    /**
     * $tree with what the definition of $package gives for the values it does
     * not have.
     *
     * @param array<string, mixed> $tree
     * @return array<string, mixed>
     */
    public static function withDefaults(string $package, array $tree, string $stage): array
    {
        $definition = self::load($package);
        if ($definition === [] || !empty($definition['x-optional']) && $tree === []) {
            return $tree;
        }
        return self::fill($definition, $tree, $stage);
    }

    /**
     * The definition file itself, for a look into it (EnvironmentForm links
     * it, at the line). Null when the package or the file is not there.
     */
    public static function file(string $package): ?string
    {
        $dir = self::directory($package);
        $file = $dir === null ? '' : "$dir/config/" . self::FILE;
        // vendor/composer/../systopic/system is a link: the file behind it is the one to edit
        return $file !== '' && is_file($file) ? (realpath($file) ?: $file) : null;
    }

    /** Is the package there on this installation? */
    public static function installed(string $package): bool
    {
        return self::directory($package) !== null;
    }

    /** Does the path lie in a block that takes anything (`ini`)? */
    public static function open(string $package, string $path): bool
    {
        $node = self::load($package);
        foreach (explode('.', $path) as $key) {
            if (($node['additionalProperties'] ?? false) === true) {
                return true;
            }
            $node = $node['properties'][$key] ?? null;
            if (!is_array($node)) {
                return false;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $node  a definition with `properties`
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    public static function fill(array $node, array $value, string $stage): array
    {
        foreach ($node['properties'] ?? [] as $key => $child) {
            if (is_array($child['additionalProperties'] ?? null)) {
                // a map with names of its own: only the entries that are there
                foreach (is_array($value[$key] ?? null) ? $value[$key] : [] as $name => $entry) {
                    if (is_array($entry)) {
                        $value[$key][$name] = self::fill($child['additionalProperties'], $entry, $stage);
                    }
                }
            } elseif (isset($child['properties'])) {
                if (!empty($child['x-optional']) && !isset($value[$key])) {
                    continue;
                }
                $value[$key] = self::fill($child, is_array($value[$key] ?? null) ? $value[$key] : [], $stage);
            } elseif (!array_key_exists($key, $value)) {
                if (array_key_exists($stage, $child['x-stages'] ?? [])) {
                    $value[$key] = $child['x-stages'][$stage];
                } elseif (array_key_exists('default', $child)) {
                    $value[$key] = $child['default'];
                }
            }
        }
        return $value;
    }


    /**
     * The definition of one value, and the file it belongs to (project or
     * local). `path` is dotted, a name of a map stands for itself:
     * 'connections.main.host'.
     *
     * @return array{0: array<string, mixed>, 1: string}|null
     */
    public static function field(string $package, string $path): ?array
    {
        $node = self::node(self::load($package), explode('.', $path));
        if ($node === null || isset($node['properties']) || is_array($node['additionalProperties'] ?? null)) {
            return null;
        }
        return [$node, (string) ($node['x-file'] ?? 'local')];
    }

    /**
     * The values directly below a path: key => [definition, file]. Blocks are
     * left out, a form has them as groups.
     *
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function children(string $package, string $path): array
    {
        $found = [];
        $node = self::node(self::load($package), $path === '' ? [] : explode('.', $path));
        foreach ($node['properties'] ?? [] as $key => $child) {
            if (!isset($child['properties']) && !is_array($child['additionalProperties'] ?? null) && ($child['type'] ?? '') !== 'object') {
                $found[(string) $key] = [$child, (string) ($child['x-file'] ?? 'local')];
            }
        }
        return $found;
    }

    /**
     * @param array<string, mixed> $node
     * @param list<string>         $keys
     * @return array<string, mixed>|null
     */
    private static function node(array $node, array $keys): ?array
    {
        foreach ($keys as $key) {
            if (isset($node['properties'][$key])) {
                $node = $node['properties'][$key];
            } elseif (is_array($node['additionalProperties'] ?? null) && preg_match('~^[a-z][a-zA-Z0-9]*$~', $key) === 1) {
                $node = $node['additionalProperties'];
            } else {
                return null;
            }
        }
        return $node;
    }

    /**
     * A value from a form as what the definition wants. A value that may be a
     * list takes several, separated by commas.
     *
     * @param array<string, mixed> $definition
     * @return array{0: bool, 1: mixed} valid, value
     */
    public static function parse(array $definition, string $raw): array
    {
        $types = (array) ($definition['type'] ?? 'string');
        if (in_array('boolean', $types, true)) {
            $text = strtolower($raw);
            return in_array($text, ['0', '1', 'true', 'false'], true) ? [true, $text === '1' || $text === 'true'] : [false, null];
        }
        if (in_array('integer', $types, true)) {
            $number = filter_var($raw, FILTER_VALIDATE_INT);
            $valid = $number !== false && $number >= ($definition['minimum'] ?? PHP_INT_MIN) && $number <= ($definition['maximum'] ?? PHP_INT_MAX);
            return [$valid, $valid ? $number : null];
        }
        if (in_array('array', $types, true) && in_array('string', $types, true)) {
            $items = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn(string $item): bool => $item !== ''));
            return [true, count($items) === 1 ? $items[0] : $items];
        }
        if (isset($definition['enum']) && !in_array($raw, $definition['enum'], true)) {
            return [false, null];
        }
        if (($definition['format'] ?? '') === 'email' && !filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return [false, null];
        }
        if (isset($definition['pattern']) && preg_match('~' . str_replace('~', '\~', (string) $definition['pattern']) . '~', $raw) !== 1) {
            return [false, null];
        }
        return [true, $raw];
    }

    /** for a test: forget what was read */
    public static function reset(): void
    {
        self::$loaded = [];
    }
}
