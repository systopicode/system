<?php

declare(strict_types=1);

namespace Systopic\System\Tables\AuthFailures;

use DateTimeImmutable;

/**
 * Slowing down guessing per IP — nothing here saves.
 *
 * `LIMIT` failures within `WINDOW` seconds block the IP for `BLOCK` seconds.
 */
final class Operator
{
    public const LIMIT  = 10;
    public const WINDOW = 900;
    public const BLOCK  = 900;

    public static function blocked(?Model $failures, DateTimeImmutable $now): bool
    {
        return $failures?->blockedUntil !== null && $failures->blockedUntil > $now;
    }

    /** One more failure from `$ip`: a new window when the last one is over. */
    public static function failed(?Model $failures, string $ip, DateTimeImmutable $now): Model
    {
        if ($failures === null) {
            $failures = new Model();
            $failures->ip = $ip;
        }
        $since = $failures->since?->getTimestamp() ?? 0;
        if ($now->getTimestamp() - $since > self::WINDOW) {
            $failures->since = $now;
            $failures->count = 0;
        }
        $failures->count = (int) $failures->count + 1;
        if ($failures->count >= self::LIMIT) {
            $failures->blockedUntil = $now->modify('+' . self::BLOCK . ' seconds');
        }
        return $failures;
    }
}
