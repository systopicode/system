<?php

declare(strict_types=1);
namespace Systopic\System\Maintenance;

/** Fixed technical-log policy; business records and backup archives are separate. */
final class RetainedLogs
{
    public const DAYS = 90;

    public static function path(string $kind): string
    {
        return match ($kind) {
            'email' => rtrim((string) \fs::$root, '/\\') . '/var/log/email.log',
            'php' => rtrim((string) \fs::$root, '/\\') . '/var/log/PHP_errors.log',
            'backup' => rtrim((string) \fs::$siteRoot, '/\\') . '/var/backups/backup.log',
            default => throw new \InvalidArgumentException('Unknown log.'),
        };
    }

    public static function records(string $raw, string $kind): array
    {
        if ($kind === 'email') {
            $separator = str_repeat('-', 80) . "\n";
            $parts = explode($separator, $raw);
            $records = [];
            foreach ($parts as $i => $part) {
                if ($part !== '') $records[] = $part . ($i < count($parts) - 1 ? $separator : '');
            }
            return $records;
        }
        // A PHP stack trace belongs to its dated first line. Preserve undated prefixes.
        if ($kind === 'php') return preg_split('/(?=^\[[^\]\r\n]+\])/m', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return preg_split('/(?<=\n)/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public static function timestamp(string $record, string $kind): ?int
    {
        if (preg_match('/^\[([^\]\r\n]+)\]/', $record, $m)) {
            if (!preg_match('/^(?:\d{4}-\d{2}-\d{2}[T ]|\d{2}-[A-Za-z]{3}-\d{4} )/', $m[1])) return null;
            try {
                $date = new \DateTimeImmutable($m[1]);
                $errors = \DateTimeImmutable::getLastErrors();
                return $errors && ($errors['warning_count'] || $errors['error_count']) ? null : $date->getTimestamp();
            }
            catch (\Exception) { return null; }
        }
        if ($kind === 'backup' && preg_match('/ - ([0-9.]+)s [0-9.]+MB\s*$/', $record, $m) && (float) $m[1] >= 1000000000) return (int) (float) $m[1];
        return null;
    }

    public static function cleanup(string $kind, bool $preview, int $limit, float $deadline): array
    {
        $path = self::path($kind);
        if (!is_file($path)) return ['count' => 0, 'more' => false];
        $file = fopen($path, 'r+');
        if (!$file) throw new \RuntimeException('Log is not writable.');
        try {
            if (!flock($file, LOCK_EX | LOCK_NB)) throw new \RuntimeException('Log is busy.');
            if (fstat($file)['size'] > 16777216) throw new \RuntimeException('Large log needs supervised cleanup.');
            $raw = stream_get_contents($file);
            if ($raw === false) throw new \RuntimeException('Log could not be read.');
            $kept = ''; $count = 0; $more = false;
            foreach (self::records($raw, $kind) as $record) {
                if (microtime(true) > $deadline) return ['count' => 0, 'more' => true];
                $stamp = self::timestamp($record, $kind);
                // Fail closed: no partial rewrite of unrecognised legacy formats.
                if ($stamp === null && trim($record) !== '') throw new \RuntimeException('Undated or unrecognised log entries need administrator review; original retained.');
                $expired = $stamp !== null && $stamp < time() - self::DAYS * 86400;
                if ($expired && $count < $limit) { $count++; continue; }
                if ($expired) $more = true;
                $kept .= $record;
            }
            if (!$preview && $count) {
                clearstatcache(true, $path);
                if (fstat($file)['size'] !== strlen($raw)) throw new \RuntimeException('Log changed during cleanup; original retained.');
                rewind($file);
                if (fwrite($file, $kept) !== strlen($kept) || !ftruncate($file, strlen($kept)) || !fflush($file)) {
                    rewind($file); fwrite($file, $raw); ftruncate($file, strlen($raw)); fflush($file);
                    throw new \RuntimeException('Cleanup failed; original restoration attempted.');
                }
            }
            return compact('count', 'more');
        } finally { flock($file, LOCK_UN); fclose($file); }
    }

    /** Remove only records addressed to this account, under the writer lock. */
    public static function clearEmailsFor(string $email): int
    {
        if (!\Systopic\System\Auth\Session::isSuperuser()) throw new \RuntimeException('Only superusers can clear logs.');
        if (trim($email) === '') return 0;
        $path = self::path('email');
        if (!is_file($path)) return 0;
        $file = fopen($path, 'r+');
        if (!$file) throw new \RuntimeException('Email log could not be opened.');
        try {
            if (!flock($file, LOCK_EX)) throw new \RuntimeException('Email log is busy.');
            if (fstat($file)['size'] > 16777216) throw new \RuntimeException('Large log needs supervised cleanup.');
            $original = stream_get_contents($file);
            if ($original === false) throw new \RuntimeException('Email log could not be read.');
            $records = self::records($original, 'email');
            $kept = []; $count = 0;
            foreach ($records as $record) {
                if (self::emailRecordMatches($record, $email)) $count++;
                else $kept[] = $record;
            }
            if ($count) {
                $raw = implode('', $kept);
                rewind($file);
                if (fwrite($file, $raw) !== strlen($raw) || !ftruncate($file, strlen($raw)) || !fflush($file)) {
                    rewind($file); fwrite($file, $original); ftruncate($file, strlen($original)); fflush($file);
                    throw new \RuntimeException('Email log could not be saved; original restoration attempted.');
                }
            }
            return $count;
        } finally { flock($file, LOCK_UN); fclose($file); }
    }

    private static function emailRecordMatches(string $record, string $email): bool
    {
        if (!preg_match('/^JSON_DATA: (.+)$/m', $record, $match)) return false;
        $data = json_decode($match[1], true);
        foreach ((array) ($data['recipients'] ?? []) as $recipient) {
            if (!is_string($recipient)) continue;
            if (preg_match('/<([^<>]+)>/', $recipient, $match)) $recipient = $match[1];
            if (strcasecmp(trim($recipient), trim($email)) === 0) return true;
        }
        return false;
    }

    /** Only exact recipient addresses, never the sending administrator's attribution. */
    public static function emailsFor(string $email): array
    {
        if (trim($email) === '') return [];
        $path = self::path('email');
        if (!is_file($path)) return [];
        $file = fopen($path, 'r');
        if (!$file) return [];
        try {
            if (!flock($file, LOCK_SH) || fstat($file)['size'] > 16777216) return [];
            $raw = stream_get_contents($file);
        } finally { fclose($file); }
        $rows = [];
        foreach (self::records((string) $raw, 'email') as $record) {
            if (!preg_match('/^JSON_DATA: (.+)$/m', $record, $m)) continue;
            $data = json_decode($m[1], true);
            if (!is_array($data) || !is_array($data['recipients'] ?? null)) continue;
            if (self::emailRecordMatches($record, $email)) $rows[] = $data;
        }
        return array_reverse($rows);
    }
}
