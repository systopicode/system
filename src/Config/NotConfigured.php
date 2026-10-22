<?php

declare(strict_types=1);

namespace Systopic\System\Config;

use RuntimeException;

/**
 * This installation has no config of its own yet — config/ is missing, holds
 * no instance, no instance matches the request, or (on a local web request)
 * the only candidate is the LIVE default.
 *
 * index.php answers it with the setup page (Setup\Setup) instead of an
 * error: it asks for what is missing and writes config/.
 */
final class NotConfigured extends RuntimeException
{
    public const NO_CONFIG     = 'no-config';
    public const NO_INSTANCES  = 'no-instances';
    public const NO_MATCH      = 'no-match';
    public const LOCAL_DEFAULT = 'local-default';

    public function __construct(
        string $message,
        /** one of the constants above */
        public readonly string $reason,
        /** what the request looked like — hosts, ips, url root, project dir */
        public readonly array $facts = [],
        /** the default instance a local request was kept away from */
        public readonly ?string $refusedDefault = null,
    ) {
        parent::__construct($message);
    }
}
