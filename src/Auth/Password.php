<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

/**
 * Stored passwords: `password_hash()`, and the md5 hashes of before.
 *
 * Until 2026-10-04 every password was stored as an unsalted md5. Those still
 * verify; the next successful login replaces them (`needsRehash()` is true
 * for an md5), so nobody has to set a new password.
 */
final class Password
{
    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verify(?string $hash, string $password): bool
    {
        if ($hash === null || $hash === '' || $password === '') {
            return false;
        }
        if (self::isLegacy($hash)) {
            return hash_equals($hash, md5($password));
        }
        return password_verify($password, $hash);
    }

    /** An md5 of before, or a hash made with weaker settings than today's. */
    public static function needsRehash(?string $hash): bool
    {
        return $hash === null || self::isLegacy($hash) || password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    private static function isLegacy(string $hash): bool
    {
        return (bool) preg_match('~^[0-9a-f]{32}$~', $hash);
    }
}
