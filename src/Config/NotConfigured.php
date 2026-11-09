<?php

declare(strict_types=1);

namespace Systopic\System\Config;

use RuntimeException;

/**
 * This installation has no config of its own yet: config/ has no
 * config.json, or no config.local.json that names the installation.
 *
 * index.php answers it with the setup page (Setup\Setup) instead of an
 * error: it asks for what is missing and writes config/.
 */
final class NotConfigured extends RuntimeException
{
    public const NO_CONFIG   = 'no-config';
    /** no config.local.json, or one without packages.system.instance */
    public const NO_INSTANCE = 'no-instance';

    public function __construct(
        string $message,
        /** one of the constants above */
        public readonly string $reason,
        /** what the request looked like — hosts, ips, url root, project dir */
        public readonly array $facts = [],
    ) {
        parent::__construct($message);
    }
}
