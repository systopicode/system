<?php

declare(strict_types=1);

namespace Systopic\System\Config;

/** The outcome of Sites::resolve(): the site, the entry that matched, the rest of the root path. */
final readonly class SiteHit
{
    public function __construct(
        public Site   $site,
        public string $entry,
        public string $remainder,
    ) {}
}
