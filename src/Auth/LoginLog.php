<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

/**
 * One line per sign-in, failed sign-in and sign-out.
 * `var/log/login.log` in the project: `date ; name ; action ; ip ; method`
 * (lines of before have no method).
 */
final class LoginLog
{
    public const PASSWORD = 'password';
    public const EMAIL_LINK = 'email link';
    public const EMAIL_CODE = 'email code';
    public const QR_CODE = 'qr code';
    public const PASSKEY = 'passkey';
    public const DEVICE = 'device';
    public const SWITCH = 'superuser switch';
    /** A provider signs in as `oauth:<provider>`, e.g. `oauth:google`. */
    public const OAUTH = 'oauth:';

    public static function file(): string
    {
        return rtrim((string) \fs::$root, '/\\') . '/var/log/login.log';
    }

    /**
     * Newest first, each [date, name, action, ip, method]. With names: only
     * the lines of one of them (a failed attempt logs what was typed, name or email).
     *
     * @param list<string> $names
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function entries(array $names = []): array
    {
        $file = self::file();
        $lines = is_file($file) ? (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];
        $names = array_filter(array_map(static fn(string $n): string => mb_strtolower(trim($n)), $names));
        $entries = [];
        foreach (array_reverse($lines) as $line) {
            $parts = array_pad(array_map('trim', explode(' ; ', $line)), 5, '');
            if ($names === [] || in_array(mb_strtolower($parts[1]), $names, true)) {
                $entries[] = $parts;
            }
        }
        return $entries;
    }

    /** How a method reads in a list: `oauth:google` is "Google". */
    public static function methodLabel(string $method): string
    {
        return match (true) {
            $method === self::PASSWORD => 'Password',
            $method === self::EMAIL_LINK => 'Email link',
            $method === self::EMAIL_CODE => 'Code by email',
            $method === self::QR_CODE => 'QR code',
            $method === self::PASSKEY => 'Passkey',
            $method === self::DEVICE => 'Remembered device',
            $method === self::SWITCH => 'Superuser switch',
            str_starts_with($method, self::OAUTH) => ucfirst(substr($method, strlen(self::OAUTH))),
            $method === '' => '–',
            default => ucfirst($method),
        };
    }

    public static function write(string $name, string $action, string $method = ''): void
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
            . ($method !== '' ? ' ; ' . $clean($method) : '')
            . "\n";
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
