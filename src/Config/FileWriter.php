<?php

declare(strict_types=1);
namespace Systopic\System\Config;

/** Shared read/modify/write lock and atomic publication for CMS-owned settings. */
final class FileWriter
{
    private static array $locks = [];

    public static function locked(string $path, callable $change, mixed $failure = false): mixed
    {
        $identity = (realpath(dirname($path)) ?: dirname($path)) . '/' . basename($path);
        if (isset(self::$locks[$identity])) return $change();
        $lock = @fopen(sys_get_temp_dir() . '/fluxo-config-' . md5($identity) . '.lock', 'c');
        if ($lock === false) return $failure;
        if (!flock($lock, LOCK_EX)) { fclose($lock); return $failure; }
        self::$locks[$identity] = true;
        try { return $change(); }
        finally { unset(self::$locks[$identity]); flock($lock, LOCK_UN); fclose($lock); }
    }

    /** Call inside locked() when deriving the new content from the old file. */
    public static function write(string $path, string $bytes, int $newPermissions = 0644): bool
    {
        if (is_link($path)) return false;
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return false;
        $temp = @tempnam($dir, '.fluxo-config-');
        if ($temp === false) return false;
        try {
            $permissions = is_file($path) ? (@fileperms($path) & 0777) : $newPermissions;
            if (!@chmod($temp, $permissions) || @file_put_contents($temp, $bytes) !== strlen($bytes)) return false;
            return @rename($temp, $path);
        } finally { if (is_file($temp)) @unlink($temp); }
    }
}
