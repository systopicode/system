<?php

declare(strict_types=1);

namespace Systopic\System\Auth;

/**
 * A name a person recognises their device by, from the user agent:
 * "iPhone · Safari", "Windows · Edge". Good enough for a list, not for any
 * decision - the user can rename it.
 */
final class DeviceName
{
    private const SYSTEMS = [
        '~iPhone~'          => 'iPhone',
        '~iPad~'            => 'iPad',
        '~Android.+Mobile~' => 'Android-Handy',
        '~Android~'         => 'Android-Tablet',
        '~Windows~'         => 'Windows',
        '~Macintosh~'       => 'Mac',
        '~CrOS~'            => 'ChromeOS',
        '~Linux~'           => 'Linux',
    ];

    /** Order matters: Edge and Samsung say "Chrome" too, Chrome says "Safari". */
    private const BROWSERS = [
        '~Edg(e|A|iOS)?/~'  => 'Edge',
        '~SamsungBrowser/~' => 'Samsung Internet',
        '~(OPR|Opera)/~'    => 'Opera',
        '~(Firefox|FxiOS)/~' => 'Firefox',
        '~(Chrome|CriOS)/~' => 'Chrome',
        '~Safari/~'         => 'Safari',
    ];

    public static function from(string $userAgent): string
    {
        $system = self::first(self::SYSTEMS, $userAgent);
        $browser = self::first(self::BROWSERS, $userAgent);
        // an iPad asking for the desktop site says Macintosh, but has touch:
        // that is not visible here, so it stays "Mac"
        return implode(' · ', array_filter([$system, $browser])) ?: 'Unbekanntes Gerät';
    }

    /** @param array<string, string> $patterns */
    private static function first(array $patterns, string $userAgent): string
    {
        foreach ($patterns as $pattern => $name) {
            if (preg_match($pattern, $userAgent)) {
                return $name;
            }
        }
        return '';
    }
}
