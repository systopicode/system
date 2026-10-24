<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

/**
 * One line per sign-in, failed sign-in and sign-out.
 * `var/log/login.log` in the project: `date ; name ; action ; ip`.
 */
final class LoginLog
{
    public static function file(): string
    {
        return rtrim((string) \fs::$root, '/\\') . '/var/log/login.log';
    }

    public static function write(string $name, string $action): void
    {
        $file = self::file();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $clean = static fn(string $value): string => str_replace(["\r", "\n", ' ; '], ' ', trim($value));
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $line = date('d.m.Y-H:i:s')
            . ' ; ' . $clean($name)
            . ' ; ' . $clean($action)
            . ' ; ' . $clean($ip)
            . "\n";
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
