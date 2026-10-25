<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

/** Bounded, single-use ceremonies: background autofill in another tab must not replace one. */
final class PasskeyChallenge
{
    private const KEY = 'systopic_passkey_challenge';

    public static function issue(string $purpose, ?int $userId, string $challenge, array $data = []): string
    {
        $values = $_SESSION[self::KEY] ?? [];
        $values = is_array($values) ? array_filter($values, static fn($value): bool => is_array($value) && ($value['expires'] ?? 0) > time()) : [];
        while (count($values) >= 8) unset($values[array_key_first($values)]);
        $ticket = bin2hex(random_bytes(16));
        $values[$ticket] = ['purpose' => $purpose, 'user' => $userId,
            'challenge' => $challenge, 'expires' => time() + 120, 'data' => $data];
        $_SESSION[self::KEY] = $values;
        return $ticket;
    }

    public static function take(string $purpose, ?int $userId, ?string $ticket = null): array
    {
        $values = $_SESSION[self::KEY] ?? [];
        $ticket ??= is_array($values) ? array_key_last($values) : null;
        $value = $ticket === null ? null : ($values[$ticket] ?? null);
        if ($ticket !== null) unset($_SESSION[self::KEY][$ticket]);
        if (!is_array($value) || $value['purpose'] !== $purpose || $value['user'] !== $userId
            || $value['expires'] <= time()) {
            throw new \DomainException('This passkey request expired. Please try again.');
        }
        return $value;
    }

    public static function cancel(?string $ticket = null): void
    {
        if ($ticket === null) unset($_SESSION[self::KEY]);
        else unset($_SESSION[self::KEY][$ticket]);
    }

    public static function token(): string
    {
        return $_SESSION['systopic_passkey_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function checkToken(string $token): void
    {
        $expected = $_SESSION['systopic_passkey_csrf'] ?? '';
        if ($expected === '' || !hash_equals($expected, $token)) {
            throw new \DomainException('Reload this page and try again.');
        }
    }
}
