<?php

declare(strict_types=1);
namespace Systopic\System\Auth;

/** Local whole-password screening; never sends a secret or hash to a service. */
final class PasswordBlocklist
{
    public static function contains(string $password): bool
    {
        $candidate = strtolower(trim($password));
        if (preg_match('/^(.{1,6})\\1+$/us', $candidate)) return true;
        // Fixed-width sorted digests allow a bounded-memory binary search.
        $file = fopen(__DIR__ . '/data/common-passwords.bin', 'rb');
        if ($file === false) throw new \RuntimeException('Password screening data unavailable.');
        try {
            $size = fstat($file)['size'];
            if ($size === 0 || $size % 32 !== 0) throw new \RuntimeException('Invalid password screening data.');
            $needle = hash('sha256', $candidate, true);
            $low = 0;
            $high = intdiv($size, 32) - 1;
            while ($low <= $high) {
                $middle = intdiv($low + $high, 2);
                if (fseek($file, $middle * 32) !== 0) throw new \RuntimeException('Password screening read failed.');
                $digest = fread($file, 32);
                if (strlen($digest) !== 32) throw new \RuntimeException('Password screening read failed.');
                $comparison = strcmp($digest, $needle);
                if ($comparison === 0) return true;
                if ($comparison < 0) $low = $middle + 1;
                else $high = $middle - 1;
            }
            return false;
        } finally {
            fclose($file);
        }
    }
}
