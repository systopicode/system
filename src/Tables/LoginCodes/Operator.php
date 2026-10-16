<?php

declare(strict_types=1);

namespace Systopic\System\Tables\LoginCodes;

use DateTimeImmutable;
use Systopic\System\Tables\Users\Model as User;

/**
 * Making, reading and spending one-time codes — nothing here saves.
 *
 * A code is 8 characters of Crockford's base32: digits and capitals without
 * I, L, O and U, so nothing can be misread. Shown as `K7Q4-M2XD`, read
 * forgivingly (case, dashes, spaces, O for 0, I and L for 1). 32^8 = 2^40
 * combinations; with codes living 10 minutes to a day and an IP blocked
 * after a few failures, guessing is hopeless.
 */
final class Operator
{
    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    public const LENGTH   = 8;

    public const INVITE      = 'invite';
    public const RECOVER     = 'recover';
    public const PAIR        = 'pair';
    public const PAIR_SCREEN = 'pair_screen';

    /** Seconds a code of each purpose lives. */
    public const LIFETIMES = [
        self::INVITE      => 86_400,
        self::RECOVER     => 600,
        self::PAIR        => 600,
        self::PAIR_SCREEN => 600,
    ];

    public static function generate(): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, 31)];
        }
        return $code;
    }

    /** What was typed, as a code - '' when it cannot be one. */
    public static function normalize(string $input): string
    {
        $code = strtr(strtoupper((string) preg_replace('~[\s\-_.]+~', '', $input)), ['O' => '0', 'I' => '1', 'L' => '1']);
        return strlen($code) === self::LENGTH && strspn($code, self::ALPHABET) === self::LENGTH ? $code : '';
    }

    public static function format(string $code): string
    {
        return substr($code, 0, 4) . '-' . substr($code, 4);
    }

    /** What is stored and looked up: the HMAC of the normalized code. */
    public static function hash(string $code, string $secret): string
    {
        return hash_hmac('sha256', $code, $secret);
    }

    public static function created(
        string $purpose,
        ?User $user,
        ?int $createdBy,
        string $codeHash,
        ?string $pollHash,
        DateTimeImmutable $now,
    ): Model {
        if (!isset(self::LIFETIMES[$purpose])) {
            throw new \InvalidArgumentException("login code: unknown purpose '$purpose'");
        }
        $code = new Model();
        $code->user = $user;
        $code->purpose = $purpose;
        $code->codeHash = $codeHash;
        $code->pollHash = $pollHash;
        $code->createdBy = $createdBy;
        $code->createdAt = $now;
        $code->expiresAt = $now->modify('+' . self::LIFETIMES[$purpose] . ' seconds');
        return $code;
    }

    /** Not spent, not expired, and of one of these purposes. @param list<string> $purposes */
    public static function usable(?Model $code, array $purposes, DateTimeImmutable $now): bool
    {
        return $code !== null
            && $code->usedAt === null
            && $code->expiresAt !== null && $code->expiresAt >= $now
            && in_array($code->purpose, $purposes, true);
    }

    public static function used(Model $code, DateTimeImmutable $now): Model
    {
        $code->usedAt = $now;
        return $code;
    }

    /** pair_screen: a user admin names the screen account. */
    public static function approved(Model $code, User $screen, int $approvedBy, DateTimeImmutable $now): Model
    {
        $code->user = $screen;
        $code->approvedAt = $now;
        $code->approvedBy = $approvedBy;
        return $code;
    }
}
