<?php

declare(strict_types=1);
namespace Systopic\System\Maintenance;

/** The fixed system-wide sign-in log policy. Other log types are deliberately independent. */
final class LogRetention
{
    public const SIGN_IN_DAYS = 365;

    public static function cleanupLogin(bool $preview, int $limit, float $deadline): array
    {
        $path = \Systopic\System\Auth\SignInLog::file();
        if (!is_file($path)) return ['count' => 0, 'more' => false];
        $handle = fopen($path, 'r+');
        if (!$handle) throw new \RuntimeException('Sign-in log is not writable.');
        $kept = null; $backup = null;
        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) throw new \RuntimeException('Sign-in log is busy.');
            if ((int) fstat($handle)['size'] > 16777216) throw new \RuntimeException('Large sign-in log needs a supervised cleanup.');
            if (!$preview) {
                $kept = tmpfile(); $backup = tmpfile();
                if (!$kept || !$backup) throw new \RuntimeException('Temporary cleanup storage unavailable.');
            }
            $cutoff = time() - self::SIGN_IN_DAYS * 86400;
            $count = 0; $more = false;
            while (($line = fgets($handle, 1048577)) !== false) {
                if (microtime(true) > $deadline) return ['count' => 0, 'more' => true];
                if (!$preview && fwrite($backup, $line) !== strlen($line)) throw new \RuntimeException('Cleanup backup could not be written.');
                $expired = false;
                if (trim($line) !== '') {
                    $stamp = trim(explode(' ; ', $line, 2)[0]);
                    $date = \DateTimeImmutable::createFromFormat('!d.m.Y-H:i:s', $stamp);
                    if (!$date || $date->format('d.m.Y-H:i:s') !== $stamp) throw new \RuntimeException('Unrecognised signin-log date; original file retained.');
                    $expired = $date->getTimestamp() < $cutoff;
                }
                if ($expired && $count < $limit) { $count++; continue; }
                if ($expired) $more = true;
                if ($preview && $more) break;
                if (!$preview && fwrite($kept, $line) !== strlen($line)) throw new \RuntimeException('Cleanup output could not be written.');
            }
            if (!$preview && !feof($handle)) throw new \RuntimeException('Sign-in log could not be read completely.');
            if (!$preview && $count > 0) {
                $size = ftell($kept); rewind($kept); rewind($handle);
                if (stream_copy_to_stream($kept, $handle) !== $size || !ftruncate($handle, $size) || !fflush($handle)) {
                    rewind($backup); rewind($handle); $originalSize = fstat($backup)['size'];
                    stream_copy_to_stream($backup, $handle); ftruncate($handle, $originalSize); fflush($handle);
                    throw new \RuntimeException('Login cleanup failed; original content restored where possible.');
                }
                \Systopic\System\Auth\SignInLog::invalidateCache();
            }
            return compact('count', 'more');
        } finally {
            if (is_resource($kept)) fclose($kept);
            if (is_resource($backup)) fclose($backup);
            flock($handle, LOCK_UN); fclose($handle);
        }
    }
}
