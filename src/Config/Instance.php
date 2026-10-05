<?php

declare(strict_types=1);

namespace Systopic\System\Config;

use RuntimeException;

/**
 * One installation of the project — a DEV checkout, the staging host, LIVE.
 *
 * Recognised by its `matches`: a list of rules, each one a machine and —
 * optionally — the site the request is for. Inside a rule every criterion
 * must hold (AND), a criterion may list several values (OR); between rules
 * one suffices:
 *
 *   "matches": [
 *     { "serverIp": "192.168.22.50", "path": "/projects/systopic.net/",            "site": "systopic.net" },
 *     { "serverIp": "192.168.22.50", "path": "/projects/beautiful-technology.de/", "site": "beautiful-technology.de" },
 *     { "serverName": "rc.systopic.net",                                          "site": "systopic.net" }
 *   ]
 *
 *   serverName   SERVER_NAME / HTTP_HOST, `*` as wildcard
 *   serverIp     SERVER_ADDR, the host when the browser calls the IP, the socket IP on the CLI
 *   rootDir      the project folder (realpath, so a junction still matches)
 *   path         prefix of the URL root (dirname of SCRIPT_NAME): '/projects/x/'
 *                matches '/projects/x/public/'
 *   site         the site (cms_sites.site_name) the rule stands for; none = the default site
 *
 * The most specific rule over all instances wins — an exact host before a
 * wildcard, a host before ip and folder, a longer path before a shorter one;
 * among equals, file order. `*.systopic.net` (RC) therefore never catches
 * `staging.systopic.net` (STAGING). An instance without `matches` is chosen
 * when pinned or when it is the `default` (LIVE: every site on its name).
 *
 * The rules are also where the sites answer on this instance (Sites): a rule
 * with a host and/or a path is an address of its site, the first one the
 * canonical one. Site add/rename/remove keep them in step (InstanceFiles).
 */
final class Instance
{
    /** What made this instance the one — for the debug panel. */
    public string $matchedBy = 'pinned';

    /**
     * The rule that matched — its `site` is the site of the request. Null
     * for the default instance and a pinned one without a fitting rule.
     *
     * @var array<string, mixed>|null
     */
    public ?array $match = null;

    /** The criteria a rule may carry; everything else in it (`site`, `//`) is data. */
    public const CRITERIA = ['serverName', 'serverIp', 'rootDir', 'path'];

    public function __construct(
        public readonly string $name,
        public readonly string $stage,
        /** @var array<string, mixed> the instance's entry, merged */
        public readonly array $data,
    ) {}

    /**
     * @param array<string, array<string, mixed>> $instances name => entry
     */
    public static function select(array $instances, array $server, string $projectDir, ?string $pinned): self
    {
        if ($instances === []) {
            throw new RuntimeException('config: no instances defined in config.json');
        }
        foreach ($instances as $name => $entry) {
            if (isset($entry['match'])) {
                throw new RuntimeException("config: instance '$name' uses the old `match` object — write it as `matches` (a list of rules), see SYS/.cursor/docs/config.md");
            }
        }

        $facts = self::facts($server, $projectDir);

        if ($pinned !== null) {
            if (!isset($instances[$pinned])) {
                throw new RuntimeException("config: pinned instance '$pinned' is not defined; known: " . implode(', ', array_keys($instances)));
            }
            $instance = self::of($pinned, $instances[$pinned]);
            // the site still comes from the instance's own rules, when one fits
            $best = self::best([$pinned => $instances[$pinned]], $facts);
            $instance->match = $best[1] ?? null;
            return $instance;
        }

        $best = self::best($instances, $facts);
        if ($best !== null) {
            [$name, $match, $by] = $best;
            $instance = self::of($name, $instances[$name]);
            $instance->match = $match;
            $instance->matchedBy = $by;
            return $instance;
        }

        foreach ($instances as $name => $entry) {
            if (!empty($entry['default'])) {
                $instance = self::of((string) $name, $entry);
                $instance->matchedBy = 'default';
                return $instance;
            }
        }

        throw new RuntimeException(
            'config: no instance matches this request and none is marked default. Facts: '
            . json_encode($facts, JSON_UNESCAPED_SLASHES)
        );
    }

    /** The site the matched rule names — null: the default site. */
    public function matchedSite(): ?string
    {
        $site = $this->match['site'] ?? null;
        return is_string($site) && $site !== '' ? $site : null;
    }

    /** @return list<array<string, mixed>> the rules of this instance */
    public function matches(): array
    {
        $matches = $this->data['matches'] ?? [];
        return is_array($matches) ? array_values(array_filter($matches, 'is_array')) : [];
    }

    private static function of(string $name, array $entry): self
    {
        $stage = (string) ($entry['stage'] ?? '');
        if ($stage === '') {
            // 'DEV.ojhome' → 'DEV'; a plain 'STAGING' is its own stage
            $stage = strtoupper(explode('.', $name, 2)[0]);
        }
        return new self($name, $stage, $entry);
    }

    // -------------------------------------------------------------------------
    // Matching
    // -------------------------------------------------------------------------

    /**
     * The best rule over all instances: [instance name, rule, description],
     * null when none fits.
     *
     * @return array{0: string, 1: array<string, mixed>, 2: string}|null
     */
    private static function best(array $instances, array $facts): ?array
    {
        $best = null;
        $bestScore = -1;
        foreach ($instances as $name => $entry) {
            foreach ((array) ($entry['matches'] ?? []) as $match) {
                if (!is_array($match)) {
                    continue;
                }
                $hit = self::score($match, $facts);
                if ($hit !== null && $hit[0] > $bestScore) {
                    [$bestScore, $best] = [$hit[0], [(string) $name, $match, $hit[1]]];
                }
            }
        }
        return $best;
    }

    /**
     * How specific a fitting rule is, and what decided — null when one of
     * its criteria fails or it has none.
     *
     *   serverName exact 1000, wildcard 500 · serverIp 100 · rootDir 100 ·
     *   path 10 + its length
     *
     * A `path` cannot be judged without a root (a CLI tool that sets no
     * SCRIPT_NAME): it is skipped then, neither hit nor miss, so the machine
     * is still recognised by its other criteria.
     *
     * @return array{0: int, 1: string}|null
     */
    private static function score(array $match, array $facts): ?array
    {
        $score = 0;
        $decided = [];
        foreach (self::CRITERIA as $criterion) {
            if (!isset($match[$criterion])) {
                continue;
            }
            if ($criterion === 'path' && $facts['path'] === []) {
                continue;
            }
            $hit = null;
            foreach ((array) $match[$criterion] as $pattern) {
                $pattern = (string) $pattern;
                foreach ($facts[$criterion] as $fact) {
                    if (self::matchOne($criterion, $pattern, $fact)) {
                        $hit = $pattern;
                        break 2;
                    }
                }
            }
            if ($hit === null) {
                return null;
            }
            $score += match ($criterion) {
                'serverName' => str_contains($hit, '*') ? 500 : 1000,
                'serverIp', 'rootDir' => 100,
                'path' => 10 + strlen($hit),
            };
            $decided[] = "$criterion=$hit";
        }
        return $decided === [] ? null : [$score, implode(' & ', $decided)];
    }

    /**
     * @return array{serverName: list<string>, serverIp: list<string>, rootDir: list<string>, path: list<string>}
     */
    private static function facts(array $server, string $projectDir): array
    {
        $names = [];
        foreach (['SERVER_NAME', 'HTTP_HOST'] as $key) {
            $value = strtolower(trim((string) ($server[$key] ?? '')));
            $value = preg_replace('~:\d+$~', '', $value) ?? $value; // strip a port
            if ($value !== '') {
                $names[] = $value;
            }
        }

        $ips = [];
        foreach (['SERVER_ADDR', 'LOCAL_ADDR'] as $key) {
            $value = trim((string) ($server[$key] ?? ''));
            if ($value !== '') {
                $ips[] = $value;
            }
        }
        // The browser called the IP: the host IS the ip. This is how the
        // legacy switch matched its DEV cases. On the CLI the harness puts
        // the socket ip into SERVER_NAME (Http::getCliEnvironment), so it
        // arrives here the same way — nothing is read besides $server, which
        // keeps the selection reproducible for a given request.
        foreach ($names as $name) {
            if (filter_var($name, FILTER_VALIDATE_IP)) {
                $ips[] = $name;
            }
        }

        $roots = [];
        $dir = self::normalizeDir($projectDir);
        if ($dir !== '') {
            $roots[] = $dir;
        }
        $real = @realpath($projectDir);
        if ($real !== false) {
            $real = self::normalizeDir($real);
            if ($real !== '' && !in_array($real, $roots, true)) {
                $roots[] = $real;
            }
        }

        // the URL root as http::setRoot() computes it
        $paths = [];
        $script = (string) ($server['SCRIPT_NAME'] ?? '');
        if ($script !== '') {
            $paths[] = strtolower(rtrim(str_replace('\\', '/', dirname($script)), '/') . '/');
        }

        return [
            'serverName' => array_values(array_unique($names)),
            'serverIp'   => array_values(array_unique($ips)),
            'rootDir'    => $roots,
            'path'       => $paths,
        ];
    }

    private static function matchOne(string $criterion, string $pattern, string $fact): bool
    {
        switch ($criterion) {
            case 'serverName':
                $pattern = strtolower($pattern);
                if (str_contains($pattern, '*')) {
                    $regex = '~^' . str_replace('\*', '.*', preg_quote($pattern, '~')) . '$~';
                    return (bool) preg_match($regex, $fact);
                }
                return $pattern === $fact;

            case 'serverIp':
                return $pattern === $fact;

            case 'rootDir':
                $wanted = self::normalizeDir($pattern);
                $real   = @realpath($pattern);
                if ($real !== false) {
                    $realNorm = self::normalizeDir($real);
                    if ($realNorm === $fact) {
                        return true;
                    }
                }
                return $wanted === $fact;

            case 'path':
                return str_starts_with($fact, self::normalizePath($pattern));
        }
        return false;
    }

    /** '/projects/x' → '/projects/x/', lower case. */
    public static function normalizePath(string $path): string
    {
        return strtolower('/' . trim(str_replace('\\', '/', $path), '/') . '/');
    }

    /** Forward slashes, no trailing slash, lower-case on Windows (case-insensitive file system). */
    private static function normalizeDir(string $dir): string
    {
        $dir = rtrim(str_replace('\\', '/', trim($dir)), '/');
        return PHP_OS_FAMILY === 'Windows' ? strtolower($dir) : $dir;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> host, user, pass, name, bindOnlyUsedParams */
    public function db(): array
    {
        return is_array($this->data['db'] ?? null) ? $this->data['db'] : [];
    }

    /** @return array<string, mixed> debug, render, time, displayErrors */
    public function debug(): array
    {
        return is_array($this->data['debug'] ?? null) ? $this->data['debug'] : [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
