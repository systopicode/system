<?php

declare(strict_types=1);

namespace Systopic\System\Config;

/**
 * The sites of a project, and which one a request belongs to.
 *
 * WHAT a site is comes from cms_sites (or config.json before the database is
 * connected), the same on every installation. WHERE it answers here comes
 * from `packages.cms.siteAddress` of the installation: patterns, regular
 * expressions with a placeholder for the site.
 *
 *   /projects(\.[a-z]+)?/{site}/   DEV, STAGING: a folder per site, named like it
 *                                  ('/projects.archive/expo-systems.de/' as well)
 *   {name}.systopic.net            RC: the name without its TLD as subdomain
 *   rc.systopic.net                no placeholder: the address of the default site
 *   (none)                         LIVE: every site on its name and its aliases
 *
 * A pattern becomes an address (an entry) per site: matched against the
 * request, the part the placeholder took is swapped for the site, so
 * '/projects.archive/x/' stays in its folder; a pattern the request does
 * not match is written out, as far as it is plain text. The first entry of
 * a site is its canonical one. The alias domains (cms_sites, `aliases`)
 * follow its own entries everywhere: never first, never canonical.
 */
final class Sites
{
    /** @var array<string, Site> name => site */
    private array $sites = [];

    /**
     * @param array<string, array<string, mixed>> $data      name => root, default, meta, aliases
     * @param list<string>                         $addresses the patterns of the installation, [] on LIVE
     * @param string                               $host      of the request, '' before there is one
     * @param string                               $rootPath  of the request ('/projects/x/public/')
     */
    public function __construct(array $data, array $addresses = [], string $host = '', string $rootPath = '')
    {
        $host = strtolower(preg_replace('~:\d+$~', '', trim($host)) ?? $host);
        $rootPath = $rootPath === '' ? '' : rtrim('/' . ltrim(strtolower($rootPath), '/'), '/') . '/';
        $addresses = array_values(array_filter(array_map(static fn($a): string => trim((string) $a), $addresses), static fn(string $a): bool => $a !== ''));
        foreach ($data as $name => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name = (string) $name;
            $entries = $addresses === []
                ? [strtolower($name)]
                : self::entriesOf($addresses, $name, (bool) ($entry['default'] ?? false), $host, $rootPath);
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
     * The addresses of one site, in the order of the patterns.
     *
     * @param list<string> $addresses
     * @return list<string>
     */
    private static function entriesOf(array $addresses, string $name, bool $isDefault, string $host, string $rootPath): array
    {
        $entries = [];
        foreach ($addresses as $pattern) {
            $placeholder = str_contains($pattern, '{site}') ? 'site' : (str_contains($pattern, '{name}') ? 'name' : null);
            if ($placeholder === null) {
                $entry = $isDefault ? self::plain($pattern) : null;
            } else {
                $entry = self::entryOf($pattern, $placeholder, $placeholder === 'site' ? strtolower($name) : self::label($name), $host, $rootPath);
            }
            if ($entry !== null && !in_array($entry, $entries, true)) {
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    /** 'beautiful-technology.de' → 'beautiful-technology': the name without its TLD. */
    public static function label(string $name): string
    {
        $name = strtolower($name);
        $dot = strrpos($name, '.');
        return $dot === false ? $name : substr($name, 0, $dot);
    }

    /**
     * The address of a pattern for one site: the request where it matches,
     * with what the placeholder took swapped for the site; else the pattern
     * written out. Null when it is no plain text then.
     */
    public static function entryOf(string $pattern, string $placeholder, string $value, string $host, string $rootPath): ?string
    {
        $isPath = str_starts_with($pattern, '/');
        $subject = $isPath ? $rootPath : ($host === '' ? '' : $host . $rootPath);
        if ($subject !== '') {
            $regex = '~^' . str_replace(['~', '{site}', '{name}'], ['\~', '(?<site>[^/]+)', '(?<name>[^./]+)'], $pattern) . '~i';
            if (@preg_match($regex, $subject, $m, PREG_OFFSET_CAPTURE) === 1 && isset($m[$placeholder]) && $m[$placeholder][1] >= 0) {
                [$whole] = $m[0];
                [$taken, $at] = $m[$placeholder];
                $entry = strtolower(substr($whole, 0, $at) . $value . substr($whole, $at + strlen($taken)));
                return str_contains($entry, '/') ? rtrim($entry, '/') . '/' : $entry;
            }
        }
        $plain = self::plain(str_replace('{' . $placeholder . '}', "\0", $pattern));
        return $plain === null ? null : str_replace("\0", $value, $plain);
    }

    /** A pattern as plain text: '\.' is a dot; null when it holds more of a regular expression. */
    private static function plain(string $pattern): ?string
    {
        $text = str_replace(['\.', '\/', '\-'], ['.', '/', '-'], $pattern);
        if (preg_match('~[\\\\()\[\]?*+|^$]~', $text) === 1) {
            return null;
        }
        $text = strtolower($text);
        return str_contains($text, '/') ? rtrim($text, '/') . '/' : $text;
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
