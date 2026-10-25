<?php

declare(strict_types=1);

namespace Systopic\System\Sys;

/**
 * Times as the signed-in user reads them. The browser reports its zone in
 * the `cmsTimezone` cookie (head.tpl.php); stored times stay in the server
 * zone, this is for display only.
 */
final class LocalTime
{
    public const COOKIE = 'cmsTimezone';

    private static ?\DateTimeZone $zone = null;

    public static function zone(): \DateTimeZone
    {
        if (self::$zone !== null) {
            return self::$zone;
        }
        $name = (string) ($_COOKIE[self::COOKIE] ?? '');
        if ($name !== '' && in_array($name, \DateTimeZone::listIdentifiers(), true)) {
            return self::$zone = new \DateTimeZone($name);
        }
        return self::$zone = new \DateTimeZone(date_default_timezone_get());
    }

    public static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', self::zone());
    }

    /** A stored time (server zone), a unix timestamp or a date, in the user's zone. */
    public static function of(string|int|\DateTimeInterface $time): \DateTimeImmutable
    {
        $date = match (true) {
            $time instanceof \DateTimeInterface => \DateTimeImmutable::createFromInterface($time),
            is_int($time) => new \DateTimeImmutable('@' . $time),
            default => new \DateTimeImmutable($time, new \DateTimeZone(date_default_timezone_get())),
        };
        return $date->setTimezone(self::zone());
    }

    public static function format(string|int|\DateTimeInterface|null $time, string $format): string
    {
        return $time === null || $time === '' ? '' : self::of($time)->format($format);
    }
}
