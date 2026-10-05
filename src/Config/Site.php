<?php

declare(strict_types=1);

namespace Systopic\System\Config;

/**
 * One website served from the shared page tree, named after its domain.
 *
 * What a site IS lives in cms_sites and is the same on every installation:
 * name (= domain), root (its node, null = the default site), meta, aliases.
 *
 * WHERE it answers is installation-specific and comes from the rules of the
 * instance file (`matches`, see Sites). An entry is
 *
 *   'beautiful-technology.de'                   a host (LIVE — the site's own name)
 *   '/projects/beautiful-technology.de/'        a path below any host (DEV — the ip was
 *                                               already matched by the rule)
 *   'staging.systopic.net/projects/x/'          host and path
 *
 * and matched as a prefix against `host + http::$root`. The first entry is
 * the canonical one; links from other sites point there. Alias domains come
 * last.
 */
final class Site
{
    public readonly ?int  $root;
    public readonly bool  $default;
    public readonly bool  $cms;

    /** @var array<string, mixed> */
    public readonly array $meta;

    /**
     * Alias domains (cms_sites.site_aliases): further host entries after the
     * canonical ones; a request on one is redirected (SiteRegistry::redirectAlias()).
     *
     * @var list<string>
     */
    public readonly array $aliases;

    /**
     * @param array<string, mixed> $data     the site's data (cms_sites, or config.json before the db)
     * @param list<string>         $entries  its match entries on this instance
     */
    public function __construct(
        public readonly string $name,
        public readonly array $data,
        public readonly array $entries = [],
    ) {
        $root          = $data['root'] ?? null;
        $this->root    = is_numeric($root) && (int) $root > 0 ? (int) $root : null;
        $this->default = (bool) ($data['default'] ?? false);
        $this->cms     = (bool) ($data['cms'] ?? true);
        $this->meta    = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $this->aliases = array_values(array_filter((array) ($data['aliases'] ?? []), 'is_string'));
    }

    /** True for a branch site, false for the site that is the whole tree. */
    public function isBranch(): bool
    {
        return $this->root !== null;
    }

    /** The canonical entry on this instance — where links from elsewhere point. */
    public function firstEntry(): ?string
    {
        return $this->entries[0] ?? null;
    }

    /** 'beautiful-technology.de' → 'beautiful-technology-de', for css classes and the like. */
    public function slug(): string
    {
        return strtolower((string) preg_replace('~[^a-z0-9_-]+~i', '-', $this->name));
    }

    public function meta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }
}
