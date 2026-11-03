<?php

declare(strict_types=1);

namespace Systopic\System\Sys;

/** Redirect old administration URLs before the panel tree is built. */
final class AdminRoute
{
    public static function legacyTarget(string $uri, string $root, bool $hasPages = true): ?string
    {
        if (str_contains($uri, "\r") || str_contains($uri, "\n")) {
            return null;
        }
        $prefix = rtrim($root, '/') . '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $legacy = $prefix . 'cms';
        if (!is_string($path) || ($path !== $legacy && !str_starts_with($path, $legacy . '/'))) {
            return null;
        }
        $suffix = substr($path, strlen($legacy));
        if (in_array($suffix, ['', '/'], true)) {
            $suffix = $hasPages ? '/pages/' : '/';
        }
        foreach (['/users/permissions' => '/access/content', '/users' => '/access', '/controlmap' => '/access/panelmap'] as $old => $new) {
            if ($suffix === $old || str_starts_with($suffix, $old . '/')) {
                $suffix = $new . substr($suffix, strlen($old));
                break;
            }
        }
        $target = $prefix . 'admin' . $suffix;
        $query = parse_url($uri, PHP_URL_QUERY);
        return is_string($query) && $query !== '' ? $target . '?' . $query : $target;
    }

    public static function redirectLegacy(): void
    {
        if (PHP_SAPI === 'cli' || !is_dir(dirname(__DIR__, 2) . '/panels/admin')) {
            return;
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $target = self::legacyTarget($uri, \http::$root);
        if ($target === null) {
            return;
        }
        $legacyRoot = rtrim(\http::$root, '/') . '/cms';
        if (in_array(parse_url($uri, PHP_URL_PATH), [$legacyRoot, $legacyRoot . '/'], true)) {
            $hasPages = false;
            foreach (Packages::panels() as $package) {
                if (is_dir(rtrim($package['dir'], '/') . '/admin/010_pages')) {
                    $hasPages = true;
                    break;
                }
            }
            $target = self::legacyTarget($uri, \http::$root, $hasPages);
        }
        if ($target !== null) {
            header('Location: ' . $target, true, 308);
            exit;
        }
    }
}
