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
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($name)) {
            $path = InstalledVersions::getInstallPath($name);
            if ($path !== null) {
                return rtrim(str_replace('\\', '/', $path), '/');
            }
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

    /**
     * @param array<string, mixed> $node  a definition with `properties`
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private static function fill(array $node, array $value, string $stage): array
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

    /** for a test: forget what was read */
    public static function reset(): void
    {
        self::$loaded = [];
    }
}
