<?php

declare(strict_types=1);

namespace Systopic\System\Config;

/**
 * This installation of the project — a DEV checkout, the staging host, LIVE.
 *
 * Named in its own config.local.json (`packages.system.instance`), which
 * lies in the project folder of exactly one installation and travels
 * nowhere: nothing has to be recognised. The name is the stage, then a dot
 * and the machine (DEV.ojhome, STAGING, LIVE), the same names as `.sync`.
 */
final class Instance
{
    public readonly string $stage;

    public function __construct(
        public readonly string $name,
        ?string $stage = null,
    ) {
        // 'DEV.ojhome' → 'DEV'; a plain 'STAGING' is its own stage
        $this->stage = $stage ?? strtoupper(explode('.', $name, 2)[0]);
    }

    /**
     * Was this request addressed the way only a dev machine is — by an IP,
     * localhost, or a *.local / *.test / *.localhost name? A live site is
     * always called by its domain.
     */
    public static function isLocalHost(array $server): bool
    {
        $host = strtolower(trim((string) ($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? '')));
        $host = preg_replace('~:\d+$~', '', $host) ?? $host;
        $host = trim($host, '[]'); // an IPv6 literal
        if ($host === '') {
            return false;
        }
        return filter_var($host, FILTER_VALIDATE_IP) !== false
            || $host === 'localhost'
            || (bool) preg_match('~\.(local|test|localhost)$~', $host);
    }
}
