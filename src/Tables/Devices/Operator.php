<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Devices;

use DateTimeImmutable;
use Systopic\Db\Type\Text;
use Systopic\System\Tables\Users\Model as User;

/**
 * What happens to a device — nothing here saves, everything takes the time
 * as an argument, so the rules can be tested without a clock or a database.
 */
final class Operator
{
    /** A secret is replaced at most this often (seconds). */
    public const ROTATE_AFTER = 86_400;

    /** The secret before stays good this long after a rotation (seconds). */
    public const GRACE = 60;

    public const OK      = 'ok';
    public const GRACED  = 'grace';    // the one before, within the grace
    public const REUSED  = 'reuse';    // the one before, after the grace: stolen
    public const WRONG   = 'wrong';
    public const REVOKED = 'revoked';
    public const EXPIRED = 'expired';

    /** A new device of `$user`, holding the sha256 of `$secret`. */
    public static function issued(
        User $user,
        string $secret,
        string $name,
        string $client,
        string $userAgent,
        ?string $ip,
        ?DateTimeImmutable $expiresAt,
        DateTimeImmutable $now,
    ): Model {
        $device = new Model();
        $device->user = $user;
        $device->name = mb_substr($name, 0, 100);
        $device->client = $client;
        $device->userAgent = mb_substr($userAgent, 0, 255);
        $device->tokenHash = hash('sha256', $secret);
        $device->createdAt = $now;
        $device->rotatedAt = $now;
        $device->lastSeen = $now;
        $device->lastIp = $ip;
        $device->expiresAt = $expiresAt;
        return $device;
    }

    /** What a cookie's secret is worth for this device. */
    public static function verdict(Model $device, string $secret, DateTimeImmutable $now): string
    {
        if ($device->revokedAt !== null) {
            return self::REVOKED;
        }
        if ($device->expiresAt !== null && $device->expiresAt < $now) {
            return self::EXPIRED;
        }
        $hash = hash('sha256', $secret);
        if (hash_equals((string) $device->tokenHash, $hash)) {
            return self::OK;
        }
        if ((string) $device->prevTokenHash !== '' && hash_equals((string) $device->prevTokenHash, $hash)) {
            $rotated = $device->rotatedAt?->getTimestamp() ?? 0;
            return $now->getTimestamp() - $rotated <= self::GRACE ? self::GRACED : self::REUSED;
        }
        return self::WRONG;
    }

    public static function dueForRotation(Model $device, DateTimeImmutable $now): bool
    {
        $since = ($device->rotatedAt ?? $device->createdAt)?->getTimestamp() ?? 0;
        return $now->getTimestamp() - $since >= self::ROTATE_AFTER;
    }

    /** A new secret; the old one goes into the grace. */
    public static function rotated(Model $device, string $secret, DateTimeImmutable $now): Model
    {
        $device->prevTokenHash = $device->tokenHash;
        $device->tokenHash = hash('sha256', $secret);
        $device->rotatedAt = $now;
        return $device;
    }

    /** Seen again: when, from where, and the lifetime slides along. */
    public static function touched(Model $device, ?string $ip, ?DateTimeImmutable $expiresAt, DateTimeImmutable $now): Model
    {
        $device->lastSeen = $now;
        $device->lastIp = $ip;
        $device->expiresAt = $expiresAt;
        return $device;
    }

    public static function revoked(Model $device, string $reason, DateTimeImmutable $now): Model
    {
        $device->revokedAt = $now;
        $device->revokedReason = $reason;
        return $device;
    }

    public static function renamed(Model $device, string $name): Model
    {
        $device->name = mb_substr(trim($name), 0, 100);
        return $device;
    }

    /** 'app' once the page runs standalone (installed web app), else 'browser'. */
    public static function reported(Model $device, bool $standalone): Model
    {
        $device->client = $standalone ? 'app' : 'browser';
        return $device;
    }

    /**
     * Until when a device of this user lives from `$now` on: a person's for
     * `$personDays`, a screen's for `$screenDays` - null for never.
     */
    public static function expiresAt(?string $userType, int $personDays, ?int $screenDays, DateTimeImmutable $now): ?DateTimeImmutable
    {
        $days = $userType === User::SCREEN ? $screenDays : $personDays;
        return $days === null ? null : $now->modify("+$days days");
    }

    public static function prefs(?Model $device): object
    {
        $raw = trim((string) $device?->prefs);
        $decoded = $raw === '' ? null : json_decode($raw);
        return is_object($decoded) ? $decoded : new \stdClass();
    }

    public static function withPref(Model $device, string $key, mixed $value): Model
    {
        $prefs = (array) self::prefs($device);
        $prefs[$key] = $value;
        $device->prefs = new Text((string) json_encode((object) $prefs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $device;
    }

    public static function withPrefs(Model $device, object $prefs): Model
    {
        $device->prefs = new Text((string) json_encode($prefs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $device;
    }
}
