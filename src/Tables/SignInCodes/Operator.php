<?php

declare(strict_types=1);

namespace Systopic\System\Tables\SignInCodes;

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

    /** Seconds a code lives. An invitation has to survive a night in the inbox. */
    public const LIFETIME            = 600;
    public const INVITATION_LIFETIME = 86_400;

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
        ?User $user,
        ?int $createdBy,
        string $codeHash,
        ?string $pollHash,
        int $lifetime,
        DateTimeImmutable $now,
        ?string $ip = null,
        ?string $userAgent = null,
    ): Model {
        $code = new Model();
        $code->user = $user;
        $code->codeHash = $codeHash;
        $code->pollHash = $pollHash;
        $code->createdBy = $createdBy;
        $code->createdAt = $now;
        $code->expiresAt = $now->modify('+' . $lifetime . ' seconds');
        $code->ip = $ip;
        $code->userAgent = $userAgent;
        return $code;
    }

    /** Not spent and not expired. */
    public static function usable(?Model $code, DateTimeImmutable $now): bool
    {
        return $code !== null
            && $code->usedAt === null
            && $code->expiresAt !== null && $code->expiresAt >= $now;
    }

    /** A device shows it and polls for it. It has no account until someone names one. */
    public static function isDeviceCode(Model $code): bool
    {
        return $code->pollHash !== null;
    }

    public static function used(Model $code, DateTimeImmutable $now): Model
    {
        $code->usedAt = $now;
        return $code;
    }

    /** Someone named the account the waiting device becomes. */
    public static function approved(Model $code, User $account, int $approvedBy, DateTimeImmutable $now): Model
    {
        $code->user = $account;
        $code->approvedAt = $now;
        $code->approvedBy = $approvedBy;
        return $code;
    }
}
