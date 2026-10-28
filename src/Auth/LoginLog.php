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
    public const INVITATION = 'invitation';
    public const RECOVERY = 'recovery code';
    public const QR_CODE = 'qr code';
    public const PASSKEY = 'passkey';
    public const DEVICE = 'device';
    public const REMOTE = 'remote login';
    public const SCREEN = 'screen';
    public const SWITCH = 'superuser switch';
    /** A provider signs in as `oauth:<provider>`, e.g. `oauth:google`. */
    public const OAUTH = 'oauth:';
    /** How a sign-out came about. */
    public const LOGOUT_BUTTON = 'logout button';
    public const TIMEOUT = 'session timeout';
    public const EXPIRED = 'access expired';
    public const REVOKED = 'device revoked';
    /**
     * Session files that only name a timed-out session. The login log itself
     * is not cut down: it stays as long as the file is on disk.
     */
    public const KEEP_DAYS = 90;

    public static function file(): string
    {
        return rtrim((string) \fs::$root, '/\\') . '/var/log/login.log';
    }

    /**
     * Drop this user's lines. Other people stay. The names are the same
     * ones `entries()` matches: the account name and the email.
     *
     * @param list<string> $names
     */
    public static function forget(array $names): void
    {
        $want = [];
        foreach ($names as $name) {
            $name = mb_strtolower(trim($name));
            if ($name !== '') {
                $want[$name] = true;
            }
        }
        if ($want === []) {
            return;
        }
        $file = self::file();
        if (!is_file($file)) {
            return;
        }
        $handle = fopen($file, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            return;
        }
        $text = stream_get_contents($handle);
        $keep = [];
        foreach (preg_split("/\r\n|\n|\r/", (string) $text) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            $who = mb_strtolower(trim((string) (explode(' ; ', $line)[1] ?? '')));
            if (!isset($want[$who])) {
                $keep[] = $line;
            }
        }
        rewind($handle);
        ftruncate($handle, 0);
        if ($keep !== []) {
            fwrite($handle, implode("\n", $keep) . "\n");
        }
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
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
            $method === self::INVITATION => 'Invitation',
            $method === self::RECOVERY => 'Recovery code',
            $method === self::QR_CODE => 'QR code',
            $method === self::PASSKEY => 'Passkey',
            $method === self::DEVICE => 'Trusted device',
            $method === self::REMOTE => 'Remote login',
            $method === self::SCREEN => 'Screen',
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

    /**
     * One line under a device: when it was first paired, how, and who set
     * that in motion. The login log is the record; the device row itself
     * only knows its own time.
     *
     * @return array{how: string, by: string}
     */
    public static function origin(string $account, ?\DateTimeInterface $at): array
    {
        $empty = ['how' => '', 'by' => ''];
        $account = trim($account);
        if ($at === null || $account === '') {
            return $empty;
        }
        $stamp = $at->getTimestamp();
        $want = mb_strtolower($account);
        $login = null;
        $actor = null;
        $actorGap = 20 * 60;
        foreach (self::lines() as $parts) {
            $when = \DateTimeImmutable::createFromFormat('d.m.Y-H:i:s', $parts[0]);
            if ($when === false) {
                continue;
            }
            $delta = $when->getTimestamp() - $stamp;
            $who = mb_strtolower($parts[1]);
            $action = mb_strtolower($parts[2]);
            if ($who === $want && $action === 'logged in' && abs($delta) <= 90) {
                if ($login === null || abs($delta) < $login[0]) {
                    $login = [abs($delta), $parts[4] === '' ? '' : self::methodLabel($parts[4])];
                }
            }
            $names = str_ends_with($action, ' as ' . $want) || str_ends_with($action, ' for ' . $want);
            $led = str_starts_with($action, 'approved ') || str_starts_with($action, 'created ');
            if ($names && $led && $delta <= 15 && $delta >= -20 * 60 && abs($delta) < $actorGap && $parts[1] !== '' && $parts[1] !== '–') {
                $actorGap = abs($delta);
                $actor = $parts[1];
            }
        }
        $how = $login[1] ?? '';
        $remote = in_array($how, [
            'Remote login', 'Recovery code', 'Invitation', 'QR code', 'Screen',
            'Trusted device', 'Code by email', 'Email link',
        ], true);
        $by = $remote ? (string) $actor : '';
        // A password or a passkey was this person. A remote code names whoever sent it.
        if ($by === '' && $how !== '') {
            $by = $account;
        }
        return ['how' => $how, 'by' => $by];
    }

    /** "Paired 28 Oct 2026, 00:34 · Remote login · by Lars", or '' when there is no time. */
    public static function paired(string $account, ?\DateTimeInterface $at): string
    {
        if ($at === null) {
            return '';
        }
        $origin = self::origin($account, $at);
        $line = 'Paired ' . \Systopic\System\Sys\LocalTime::format($at, 'j M Y, H:i');
        if ($origin['how'] !== '') {
            $line .= ' · ' . $origin['how'];
        }
        if ($origin['by'] !== '') {
            $line .= ' · by ' . $origin['by'];
        }
        return $line;
    }

    /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}>|null */
    private static ?array $lineCache = null;

    /** @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}> */
    private static function lines(): array
    {
        if (self::$lineCache !== null) {
            return self::$lineCache;
        }
        $file = self::file();
        $raw = is_file($file) ? (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];
        $lines = [];
        foreach ($raw as $line) {
            $lines[] = array_pad(array_map('trim', explode(' ; ', $line)), 5, '');
        }
        return self::$lineCache = $lines;
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

}
