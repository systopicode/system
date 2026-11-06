<?php

declare(strict_types=1);

namespace Systopic\System\Config;

use Composer\InstalledVersions;

/**
 * The definitions of the packages: `<package>/config/{project,machine,local}.schema.json`,
 * JSON Schema with `x-` keywords. See .cursor/docs/config-struktur.md.
 *
 * The package defines, the project stores. What the definitions say about a
 * value that is not in any file comes from here, at the bottom of the tree:
 * a new default works right away, no file has to change.
 */
final class Schema
{
    /** the files of a package, in the order their values are loaded */
    public const FILES = ['project', 'machine', 'local'];

    /** @var array<string, array<string, array<string, mixed>>> package => file => definition */
    private static array $loaded = [];

    /** @return array<string, array<string, mixed>> file => definition, what the package has of them */
    public static function load(string $package): array
    {
        if (isset(self::$loaded[$package])) {
            return self::$loaded[$package];
        }
        $dir = self::directory($package);
        $found = [];
        foreach ($dir === null ? [] : self::FILES as $name) {
            $file = "$dir/config/$name.schema.json";
            $definition = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            if (is_array($definition)) {
                $found[$name] = $definition;
            }
        }
        return self::$loaded[$package] = $found;
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
            return $path === null ? null : rtrim(str_replace('\\', '/', $path), '/');
        }
        $dir = str_replace('\\', '/', dirname(__DIR__, 3)) . "/$package";
        return is_dir($dir) ? $dir : null;
    }

    /**
     * $tree with what the definitions of $package give for the values it does
     * not have.
     *
     * @param array<string, mixed> $tree
     * @return array<string, mixed>
     */
    public static function withDefaults(string $package, array $tree, string $stage): array
    {
        foreach (self::load($package) as $definition) {
            if (!empty($definition['x-optional']) && $tree === []) {
                continue;
            }
            $tree = self::fill($definition, $tree, $stage);
        }
        return $tree;
    }

    /** Is the package there on this installation? */
    public static function installed(string $package): bool
    {
        return self::directory($package) !== null;
    }

    /** Does the path lie in a block that takes anything (`ini`)? */
    public static function open(string $package, string $path): bool
    {
        foreach (self::load($package) as $definition) {
            $node = $definition;
            foreach (explode('.', $path) as $key) {
                if (($node['additionalProperties'] ?? false) === true) {
                    return true;
                }
                $node = $node['properties'][$key] ?? null;
                if (!is_array($node)) {
                    break;
                }
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
     * The definition of one value, and the file it belongs to. `path` is dotted,
     * a name of a map stands for itself: 'connections.main.host'.
     *
     * @return array{0: array<string, mixed>, 1: string}|null
     */
    public static function field(string $package, string $path): ?array
    {
        foreach (self::load($package) as $file => $definition) {
            $node = self::node($definition, explode('.', $path));
            if ($node !== null && !isset($node['properties']) && !is_array($node['additionalProperties'] ?? null)) {
                return [$node, $file];
            }
        }
        return null;
    }

    /**
     * The values directly below a path, from all files of the package: key =>
     * [definition, file]. Blocks are left out, a form has them as groups.
     *
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function children(string $package, string $path): array
    {
        $found = [];
        foreach (self::load($package) as $file => $definition) {
            $node = self::node($definition, $path === '' ? [] : explode('.', $path));
            foreach ($node['properties'] ?? [] as $key => $child) {
                if (!isset($child['properties']) && !is_array($child['additionalProperties'] ?? null) && ($child['type'] ?? '') !== 'object') {
                    $found[(string) $key] = [$child, $file];
                }
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
     * A value from a form as what the definition wants.
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
        if (isset($definition['enum']) && !in_array($raw, $definition['enum'], true)) {
            return [false, null];
        }
        if (($definition['format'] ?? '') === 'email' && !filter_var($raw, FILTER_VALIDATE_EMAIL)) {
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
