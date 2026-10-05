<?php

declare(strict_types=1);

namespace Systopic\System\Config;

/**
 * The sites of a project, and which one a request belongs to.
 *
 * WHAT a site is comes from cms_sites (or config.json before the database is
 * connected). WHERE it answers on this instance comes from the instance's
 * `matches` (see Instance): every rule naming the site — or naming none, for
 * the default site — that carries a host and/or a path is an address of it,
 * the first one the canonical one:
 *
 *   { "serverIp": "192.168.22.50", "path": "/projects/expo-systems.de/", "site": "expo-systems.de" }
 *       → '/projects/expo-systems.de/'               a path below the host of the request
 *   { "serverName": "staging.systopic.net", "path": "/projects/expo-systems.de/", "site": … }
 *       → 'staging.systopic.net/projects/expo-systems.de/'
 *   { "serverName": "expo-systems.systopic.net", "site": "expo-systems.de" }
 *       → 'expo-systems.systopic.net'
 *
 * An instance without rules (LIVE) addresses every site by its name. The
 * site's alias domains (cms_sites, `aliases`) follow its own entries on every
 * instance: they never come first, so they are never canonical.
 */
final class Sites
{
    /** @var array<string, Site> name => site */
    private array $sites = [];

    /**
     * @param array<string, array<string, mixed>> $data    name => root, default, meta, aliases
     * @param list<array<string, mixed>>           $matches the instance's rules
     */
    public function __construct(array $data, array $matches = [])
    {
        foreach ($data as $name => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name = (string) $name;
            $entries = $matches === []
                ? [strtolower($name)]
                : self::entriesOf($matches, $name, (bool) ($entry['default'] ?? false));
            foreach ((array) ($entry['aliases'] ?? []) as $alias) {
                if (is_string($alias) && $alias !== '' && !in_array(strtolower($alias), $entries, true)) {
                    $entries[] = strtolower($alias);
                }
            }
            $this->sites[$name] = new Site($name, $entry, $entries);
        }
        // No sites configured: one implicit site that is the whole tree, so
        // a project without sites behaves as before.
        if ($this->sites === []) {
            $this->sites['default'] = new Site('default', ['default' => true], ['*']);
        }
    }

    /**
     * The addresses of one site among the rules, in rule order.
     *
     * @param list<array<string, mixed>> $matches
     * @return list<string>
     */
    private static function entriesOf(array $matches, string $name, bool $isDefault): array
    {
        $entries = [];
        foreach ($matches as $match) {
            $site = $match['site'] ?? null;
            $site = is_string($site) && $site !== '' ? $site : null;
            if ($site !== $name && !($site === null && $isDefault)) {
                continue;
            }
            $entry = self::entryOf($match);
            if ($entry !== null && !in_array($entry, $entries, true)) {
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    /**
     * The address a rule stands for: its first host without a wildcard, its
     * path, or both — null when it has neither (an ip-only rule says which
     * machine, not where on it).
     *
     * @param array<string, mixed> $match
     */
    public static function entryOf(array $match): ?string
    {
        $host = null;
        foreach ((array) ($match['serverName'] ?? []) as $name) {
            if (is_string($name) && $name !== '' && !str_contains($name, '*')) {
                $host = strtolower(trim($name));
                break;
            }
        }
        $path = null;
        foreach ((array) ($match['path'] ?? []) as $p) {
            if (is_string($p) && trim($p) !== '') {
                $path = Instance::normalizePath($p);
                break;
            }
        }
        if ($host === null) {
            return $path;
        }
        return $path === null ? $host : $host . $path;
    }

    /** @return array<string, Site> */
    public function all(): array
    {
        return $this->sites;
    }

    public function byName(string $name): ?Site
    {
        return $this->sites[$name] ?? null;
    }

    /** The site an unknown host lands on — marked default, else the first. */
    public function default(): Site
    {
        foreach ($this->sites as $site) {
            if ($site->default) {
                return $site;
            }
        }
        return reset($this->sites);
    }

    /** The site whose branch root is this node, if any. */
    public function byRoot(int $nodeId): ?Site
    {
        foreach ($this->sites as $site) {
            if ($site->root === $nodeId) {
                return $site;
            }
        }
        return null;
    }

    /** @return array<int, Site> node id => branch site */
    public function branches(): array
    {
        $out = [];
        foreach ($this->sites as $site) {
            if ($site->root !== null) {
                $out[$site->root] = $site;
            }
        }
        return $out;
    }

    /**
     * Which site answers `host + rootPath` — for an instance without rules
     * (LIVE: names and aliases) or a rule without a site.
     *
     * Every entry is tried as a prefix — a path entry ('/projects/x/') against
     * the root path alone, a host entry against 'host/root/path/'. The LONGEST
     * matching entry wins; '*' only when nothing else does; nothing → the
     * default site with entry '*'.
     */
    public function resolve(string $host, string $rootPath): SiteHit
    {
        $host     = strtolower(preg_replace('~:\d+$~', '', trim($host)) ?? $host);
        $rootPath = rtrim('/' . ltrim(strtolower($rootPath), '/'), '/') . '/';
        $key      = $host . $rootPath;

        $best       = null;
        $bestEntry  = '*';
        $bestLength = -1;

        foreach ($this->sites as $site) {
            foreach ($site->entries as $entry) {
                if ($entry === '*') {
                    if ($bestLength < 0) {
                        [$best, $bestEntry, $bestLength] = [$site, '*', 0];
                    }
                    continue;
                }
                $isPath  = str_starts_with($entry, '/');
                $subject = $isPath ? $rootPath : $key;
                $prefix  = str_contains($entry, '/') ? rtrim($entry, '/') . '/' : $entry . '/';
                if (str_starts_with($subject, $prefix) && strlen($prefix) > $bestLength) {
                    [$best, $bestEntry, $bestLength] = [$site, $entry, strlen($prefix)];
                }
            }
        }

        if ($best === null) {
            [$best, $bestEntry] = [$this->default(), '*'];
        }

        return new SiteHit($best, $bestEntry, self::remainder($bestEntry, $rootPath));
    }

    /**
     * What an entry leaves of the root path: '' for a host entry on a host
     * root, 'public/' for '/projects/x/' on '/projects/x/public/'.
     */
    public static function remainder(string $entry, string $rootPath): string
    {
        if ($entry === '*') {
            return ltrim($rootPath, '/');
        }
        $entryPath = str_starts_with($entry, '/')
            ? $entry
            : (str_contains($entry, '/') ? substr($entry, strpos($entry, '/')) : '/');
        $entryPath = rtrim($entryPath, '/') . '/';
        return str_starts_with($rootPath, $entryPath) ? substr($rootPath, strlen($entryPath)) : ltrim($rootPath, '/');
    }
}
