<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use Systopic\System\Config\Config;

/**
 * The "auth" block of the instance's config (<Instance>.config.json or
 * config.local.json — never git, never synced to another machine):
 *
 *   "auth": {"secret": "<at least 32 random characters>",
 *            "deviceDays": 180, "screenDays": null}
 *
 * The secret keys the HMAC of the one-time codes (Auth\LoginCodes); without
 * it there are no codes. Devices work without it.
 */
final class AuthConfig
{
    public const DEVICE_DAYS = 180;

    /** @return array<string, mixed> */
    public static function block(): array
    {
        $auth = Config::current()?->instance->get('auth');
        return is_array($auth) ? $auth : [];
    }

    /** null when missing or too short to be a secret. */
    public static function secret(): ?string
    {
        $secret = (string) (self::block()['secret'] ?? '');
        return strlen($secret) >= 32 ? $secret : null;
    }

    /** How long a person's device lives after its last use. */
    public static function deviceDays(): int
    {
        return max(1, (int) (self::block()['deviceDays'] ?? self::DEVICE_DAYS));
    }

    /** How long a screen lives after its last use - null: until revoked. */
    public static function screenDays(): ?int
    {
        $days = self::block()['screenDays'] ?? null;
        return $days === null ? null : max(1, (int) $days);
    }
}
