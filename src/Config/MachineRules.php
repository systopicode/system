<?php

declare(strict_types=1);

namespace Systopic\System\Config;

/**
 * Rules that pick one of several files by the machine and the request: the
 * way instance files were chosen before config.local.json named the
 * installation. Still used where files travel between machines and have to
 * find their own: the machine files of syscoder, and `bin/config.php
 * --adopt`, which takes the old instance file of this machine over.
 *
 *   "matches": [ { "serverIp": "192.168.22.50", "path": "/projects/x/" }, … ]
 *
 *   serverName   SERVER_NAME / HTTP_HOST, `*` as wildcard
 *   serverIp     SERVER_ADDR, the host when the browser calls the IP, the socket IP on the CLI
 *   rootDir      the project folder (realpath, so a junction still matches)
 *   path         prefix of the URL root: '/projects/x/' matches '/projects/x/public/'
 *
 * Inside a rule every criterion must hold, a criterion may list several
 * values; the most specific rule over all entries wins.
 */
final class MachineRules
{
    /** The criteria a rule may carry; everything else in it is data. */
    public const CRITERIA = ['serverName', 'serverIp', 'rootDir', 'path'];

    /**
     * The entry whose rules fit best: [name, rule, what decided], null when
     * none does.
     *
     * @param array<string, array<string, mixed>> $entries name => entry with `matches`
     * @return array{0: string, 1: array<string, mixed>, 2: string}|null
     */
    public static function select(array $entries, array $server, string $projectDir): ?array
    {
        return self::best($entries, self::facts($server, $projectDir));
    }

    /** what selection sees of a request, for the setup page */
    public static function requestFacts(array $server, string $projectDir): array
    {
        return self::facts($server, $projectDir);
    }

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

}
