<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

use Systopic\System\Config\Config;
use Systopic\System\Settings\EnvironmentFile;

/**
 * The "auth" block of the installation\'s config (config.local.json —
 * never git, never synced to another machine):
 *
 *   "auth": {"secret": "<at least 32 random characters>",
 *            "deviceDays": 180, "screenDays": null}
 *
 * The secret keys the HMAC of the one-time codes (Auth\SignInCodes). It is made the first
 * time it is needed. Devices work without it.
 */
final class AuthConfig
{
    public const DEVICE_DAYS = 180;

    public const PASSWORD_LENGTH = 15;

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
        $auth = Config::current()?->package('system')['auth'] ?? null;
        return is_array($auth) ? $auth : [];
    }

    /** null when missing or too short to be a secret. */
    public static function secret(): ?string
    {
        if (self::$heldSecret !== null) {
            return strlen(self::$heldSecret) >= 32 ? self::$heldSecret : null;
        }
        $secret = (string) (self::block()['secret'] ?? '');
        if (strlen($secret) >= 32) {
            return $secret;
        }
        self::$heldSecret = self::made();
        return strlen(self::$heldSecret) >= 32 ? self::$heldSecret : null;
    }

    /**
     * No key yet: whoever needs one first makes it, in this instance's file.
     * Two requests at once: `createAuth()` writes under a lock and leaves a
     * key that is there, so both read the same one back.
     */
    private static function made(): string
    {
        if (EnvironmentFile::activeFile() === null) {
            return '';
        }
        EnvironmentFile::createAuth();
        return EnvironmentFile::authSecret();
    }

    /** How long a person's device lives after its last use. */
    public static function deviceDays(): int
    {
        return max(1, (int) (self::block()['deviceDays'] ?? self::DEVICE_DAYS));
    }

    /** The fewest characters a new password must have - never fewer than 6. */
    public static function passwordLength(): int
    {
        return max(6, (int) (self::block()['passwordLength'] ?? self::PASSWORD_LENGTH));
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
    public static function defaultSignInMethod(): string
    {
        $block = self::block();
        $method = (string) ($block['defaultSignInMethod'] ?? $block['default_login_method'] ?? '');
        $method = $method === 'device' ? 'device_code' : $method;
        return in_array($method, ['', 'code', 'device_code', 'passkey', 'password'], true) ? $method : '';
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

    public static function signInMethod(?string $chosen): string
    {
        if ($chosen === 'choose') {
            return '';
        }
        $method = ($chosen === null || $chosen === '') ? self::defaultSignInMethod() : $chosen;
        if (!in_array($method, ['code', 'device_code', 'passkey', 'password'], true)) {
            return '';
        }
        if (($method === 'code' || $method === 'device_code') && !SignInCodes::available()) {
            return '';
        }
        if ($method === 'passkey' && !Passkeys::available()) {
            return '';
        }
        return $method;
    }
}
