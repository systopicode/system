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
    /** How a sign-out came about. */
    public const LOGOUT_BUTTON = 'logout button';
    public const TIMEOUT = 'session timeout';
    public const EXPIRED = 'access expired';
    public const REVOKED = 'device revoked';
    /** The IP addresses in it are personal data: kept as long as misuse is worth tracing, then gone. */
    public const KEEP_DAYS = 90;

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
            $method === self::LOGOUT_BUTTON => 'Logout button',
            $method === self::TIMEOUT => 'Session timed out',
            $method === self::EXPIRED => 'Access expired',
            $method === self::REVOKED => 'Device signed out',
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
        self::prune($file);
    }

    /**
     * Whose a session is, kept outside of it: once PHP has thrown the session
     * away, the cookie that still names it tells who timed out. The file name
     * is a hash of the session id - only that cookie leads to it.
     */
    public static function sessionOpened(string $sid, string $name): void
    {
        $dir = dirname(self::file()) . '/sessions';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(self::sessionFile($sid), $name, LOCK_EX);
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (filemtime($file) < time() - self::KEEP_DAYS * 86400) {
                @unlink($file);
            }
        }
    }

    public static function sessionClosed(string $sid): void
    {
        if ($sid !== '' && is_file($file = self::sessionFile($sid))) {
            @unlink($file);
        }
    }

    /** The name of the user a session that is gone belonged to - once. */
    public static function sessionLapsed(string $sid): ?string
    {
        $file = self::sessionFile($sid);
        if (!is_file($file)) {
            return null;
        }
        $name = (string) file_get_contents($file);
        @unlink($file);
        return $name;
    }

    private static function sessionFile(string $sid): string
    {
        return dirname(self::file()) . '/sessions/' . hash('sha256', $sid);
    }

    /** Lines older than KEEP_DAYS go; the oldest line is first, so one look tells whether there are any. */
    private static function prune(string $file): void
    {
        $limit = time() - self::KEEP_DAYS * 86400;
        $old = static function (string $line) use ($limit): bool {
            $date = \DateTime::createFromFormat('d.m.Y-H:i:s', trim(explode(' ; ', $line, 2)[0]));
            return $date !== false && $date->getTimestamp() < $limit;
        };
        $handle = @fopen($file, 'r');
        $first = $handle ? (string) fgets($handle) : '';
        if ($handle) {
            fclose($handle);
        }
        if (!$old($first)) {
            return;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $kept = array_filter($lines, static fn(string $line): bool => !$old($line));
        file_put_contents($file, $kept === [] ? '' : implode("\n", $kept) . "\n", LOCK_EX);
    }
}
