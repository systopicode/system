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
        '~iPad|Macintosh.*Mobile~'            => 'iPad',
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

    /** Desktop-mode iPad Safari needs the browser's touch hint. This affects display only. */
    public static function requestAgent(): string
    {
        $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (($_SERVER['HTTP_X_FLUXO_DEVICE'] ?? '') === 'iPad' && str_contains($agent, 'Macintosh')) {
            $agent .= ' Fluxo-iPad';
        }
        return $agent;
    }

    public static function from(string $userAgent): string
    {
        $system = self::first(self::SYSTEMS, $userAgent);
        $browser = self::first(self::BROWSERS, $userAgent);
        // Desktop-mode iPads are normalised by requestAgent() when touch is reported.
        // A bare Macintosh user agent remains a Mac; no security decision uses this name.
        return implode(' · ', array_filter([$system, $browser])) ?: 'Unknown device';
    }

    /** Small mark for the device list: phone, tablet or computer. */
    public static function icon(string $userAgent): string
    {
        if (preg_match('~iPad|Macintosh.*Mobile|Tablet|Android(?!.*Mobile)~', $userAgent)) {
            return 'cmsIcon cmsIcon-tablet-screen';
        }
        if (preg_match('~iPhone|iPod|Android.*Mobile|Mobile~', $userAgent)) {
            return 'cmsIcon cmsIcon-mobile-screen';
        }
        return 'cmsIcon cmsIcon-laptop';
    }

    /** The same device, crossed out: the control that signs it out. */
    public static function slash(string $userAgent): string
    {
        if (preg_match('~iPad|Macintosh.*Mobile|Tablet|Android(?!.*Mobile)~', $userAgent)) {
            return 'cmsIcon cmsIcon-display-slash';
        }
        if (preg_match('~iPhone|iPod|Android.*Mobile|Mobile~', $userAgent)) {
            return 'cmsIcon cmsIcon-mobile-slash';
        }
        return 'cmsIcon cmsIcon-laptop-slash';
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
