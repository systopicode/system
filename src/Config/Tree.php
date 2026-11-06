<?php

declare(strict_types=1);

namespace Systopic\System\Config;

/** Values in a nested array, by path. */
final class Tree
{
    /** @param list<string> $path */
    public static function get(array $tree, array $path): mixed
    {
        foreach ($path as $key) {
            if (!is_array($tree) || !array_key_exists($key, $tree)) {
                return null;
            }
            $tree = $tree[$key];
        }
        return $tree;
    }

    /** @param list<string> $path */
    public static function put(array &$tree, array $path, mixed $value): void
    {
        $node = &$tree;
        foreach ($path as $key) {
            if (!is_array($node[$key] ?? null)) {
                $node[$key] = [];
            }
            $node = &$node[$key];
        }
        $node = $value;
    }

    /**
     * Removes a value, and the blocks that are empty after it.
     *
     * @param list<string> $path
     */
    public static function drop(array &$tree, array $path): void
    {
        $key = array_shift($path);
        if (!array_key_exists($key, $tree)) {
            return;
        }
        if ($path === []) {
            unset($tree[$key]);
            return;
        }
        if (is_array($tree[$key])) {
            self::drop($tree[$key], $path);
            if ($tree[$key] === []) {
                unset($tree[$key]);
            }
        }
    }
}
