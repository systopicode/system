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

    /**
     * Secret written in this request. The loaded instance stays as it was
     * until the next request, so a key created a moment ago still looks missing.
     */
    private static ?string $heldSecret = null;

    public static function rememberSecret(?string $secret): void
    {
        self::$heldSecret = $secret ?? '';
    }

    /** @return array<string, mixed> */
    public static function block(): array
    {
        $auth = Config::current()?->instance->get('auth');
        return is_array($auth) ? $auth : [];
    }

    /** null when missing or too short to be a secret. */
    public static function secret(): ?string
    {
        if (self::$heldSecret !== null) {
            return strlen(self::$heldSecret) >= 32 ? self::$heldSecret : null;
        }
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

    /**
     * Which sign-in the card opens with. Empty means the person picks.
     * Anything else is treated as empty.
     */
    public static function defaultLoginMethod(): string
    {
        $method = (string) (self::block()['default_login_method'] ?? '');
        return in_array($method, ['', 'code', 'device', 'passkey', 'password'], true) ? $method : '';
    }

    /**
     * The view to show. `$chosen` is the click on this card (`choose` is the
     * list). Empty uses the config. A method that this server cannot offer
     * falls back to the list.
     */
    /**
     * A full page open follows the saved default. A click on this visit,
     * and an invitation link, still choose the view.
     */
    public static function openingChoice(string $stored, bool $freshGet, bool $codeLink): string
    {
        if ($codeLink) {
            return 'code';
        }
        return $freshGet ? '' : $stored;
    }

    public static function loginMethod(?string $chosen): string
    {
        if ($chosen === 'choose') {
            return '';
        }
        $method = ($chosen === null || $chosen === '') ? self::defaultLoginMethod() : $chosen;
        if (!in_array($method, ['code', 'device', 'passkey', 'password'], true)) {
            return '';
        }
        if (($method === 'code' || $method === 'device') && !LoginCodes::available()) {
            return '';
        }
        if ($method === 'passkey' && !Passkeys::available()) {
            return '';
        }
        return $method;
    }
}
