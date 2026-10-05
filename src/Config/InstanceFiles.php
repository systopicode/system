<?php

declare(strict_types=1);

namespace Systopic\System\Config;

/**
 * Keeps the rules of the instance files in step with the sites — called
 * when a site is added, renamed or removed (Pages\Sites\SiteRegistry).
 *
 * Every `<stage>[.<machine>].config.json` of the project, not only the one
 * of this machine: the files are synced, and a rule missing elsewhere would
 * leave a site unreachable there. What can be derived is derived, nothing is
 * guessed:
 *
 *   rename   `site` and the path segment (/projects/<old>/ → /projects/<new>/);
 *            hosts stay — a host that spells the old name is reported
 *   remove   every rule of the site goes
 *   add      a file with path rules gets a copy of one for the new site
 *            (same ip/host, /projects/<new>/); a file with host rules only
 *            (RC) cannot be guessed — reported, to be written by hand
 *
 * A file without rules (LIVE: every site on its name) needs nothing. A file
 * with real comments (JSONC) is not rewritten — they would be lost; it is
 * reported instead. `"//"` keys are data and survive. Written back with tabs.
 */
final class InstanceFiles
{
    /** @return list<string> what was done, one line per change or hint */
    public static function renameSite(string $configDir, string $old, string $new): array
    {
        $log = [];
        foreach (self::files($configDir) as $file) {
            $data = self::read($file, $log);
            if ($data === null || !is_array($data['matches'] ?? null)) {
                continue;
            }
            $changed = false;
            foreach ($data['matches'] as $i => $match) {
                if (!is_array($match)) {
                    continue;
                }
                if (($match['site'] ?? null) === $old) {
                    $data['matches'][$i]['site'] = $new;
                    $changed = true;
                }
                if (isset($match['path'])) {
                    $renamed = self::mapPaths($match['path'], static fn(string $p) => self::renamePath($p, $old, $new));
                    if ($renamed !== $match['path']) {
                        $data['matches'][$i]['path'] = $renamed;
                        $changed = true;
                    }
                }
                foreach ((array) ($match['serverName'] ?? []) as $host) {
                    if (is_string($host) && ($match['site'] ?? null) === $old && str_contains(strtolower($host), self::label($old))) {
                        $log[] = basename($file) . ": Host $host nennt noch den alten Namen — von Hand anpassen";
                    }
                }
            }
            if ($changed) {
                self::write($file, $data);
                $log[] = basename($file) . ": $old → $new";
            }
        }
        return $log;
    }

    /** @return list<string> */
    public static function removeSite(string $configDir, string $name): array
    {
        $log = [];
        foreach (self::files($configDir) as $file) {
            $data = self::read($file, $log);
            if ($data === null || !is_array($data['matches'] ?? null)) {
                continue;
            }
            $kept = array_values(array_filter($data['matches'], static fn($m) => !is_array($m) || ($m['site'] ?? null) !== $name));
            if (count($kept) !== count($data['matches'])) {
                $removed = count($data['matches']) - count($kept);
                $data['matches'] = $kept;
                self::write($file, $data);
                $log[] = basename($file) . ": $removed Regel(n) für $name entfernt";
            }
        }
        return $log;
    }

    /**
     * @param list<string> $known the names of the existing sites — a path
     *                            segment equal to one of them marks a rule
     *                            that can serve as the pattern
     * @return list<string>
     */
    public static function addSite(string $configDir, string $name, array $known): array
    {
        $log = [];
        $known = array_map('strtolower', $known);
        foreach (self::files($configDir) as $file) {
            $data = self::read($file, $log);
            if ($data === null || !is_array($data['matches'] ?? null) || $data['matches'] === []) {
                continue;   // no rules: every site answers on its name
            }
            $copy = null;
            foreach ($data['matches'] as $match) {
                if (!is_array($match) || ($match['site'] ?? null) === $name) {
                    if (is_array($match)) {
                        $copy = false;   // already there
                        break;
                    }
                    continue;
                }
                $paths = (array) ($match['path'] ?? []);
                foreach ($paths as $path) {
                    $segment = self::siteSegment((string) $path, $known);
                    if ($segment !== null) {
                        $copy = $match;
                        $copy['site'] = $name;
                        $copy['path'] = self::mapPaths($match['path'], static fn(string $p) => self::renamePath($p, $segment, $name));
                        break 2;
                    }
                }
            }
            if ($copy === false) {
                continue;
            }
            if ($copy === null) {
                $log[] = basename($file) . ": keine Pfad-Regel als Vorlage — Host für $name von Hand eintragen";
                continue;
            }
            $data['matches'][] = $copy;
            self::write($file, $data);
            $log[] = basename($file) . ': Regel für ' . $name . ' (' . (Sites::entryOf($copy) ?? '?') . ')';
        }
        return $log;
    }

    // ------------------------------------------------------------------ paths

    /** '/projects/old/' → '/projects/new/' — whole segments only. */
    public static function renamePath(string $path, string $old, string $new): string
    {
        $parts = explode('/', $path);
        foreach ($parts as $i => $part) {
            if (strtolower($part) === strtolower($old)) {
                $parts[$i] = $new;
            }
        }
        return implode('/', $parts);
    }

    /** The segment of a path that is the name of a known site, null when none is. */
    private static function siteSegment(string $path, array $known): ?string
    {
        foreach (explode('/', $path) as $part) {
            if ($part !== '' && in_array(strtolower($part), $known, true)) {
                return $part;
            }
        }
        return null;
    }

    /** A path value is a string or a list of them; map each. */
    private static function mapPaths(mixed $value, \Closure $fn): mixed
    {
        if (is_string($value)) {
            return $fn($value);
        }
        return is_array($value) ? array_map(static fn($p) => is_string($p) ? $fn($p) : $p, $value) : $value;
    }

    /** 'beautiful-technology.de' → 'beautiful-technology' — to spot a host that spells it. */
    private static function label(string $name): string
    {
        $parts = explode('.', strtolower($name));
        if (count($parts) > 1) {
            array_pop($parts);
        }
        return implode('.', $parts);
    }

    // ------------------------------------------------------------------- files

    /** @return list<string> the instance files, sorted like Config::load() */
    private static function files(string $configDir): array
    {
        $files = glob(rtrim(str_replace('\\', '/', $configDir), '/') . '/*.config.json') ?: [];
        sort($files, SORT_STRING);
        return array_values(array_filter($files, static fn($f) => basename($f) !== 'config.local.json'));
    }

    /** @return array<string, mixed>|null null when the file must not be rewritten */
    private static function read(string $file, array &$log): ?array
    {
        $raw = ltrim((string) file_get_contents($file), "\xEF\xBB\xBF");
        if (Config::stripComments($raw) !== $raw) {
            $log[] = basename($file) . ': enthält Kommentare, nicht automatisch geändert — von Hand anpassen';
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $log[] = basename($file) . ': kein gültiges JSON, nicht geändert';
            return null;
        }
        return $data;
    }

    private static function write(string $file, array $data): void
    {
        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // four spaces per level → one tab, as the files are written by hand
        $json = (string) preg_replace_callback('~^( {4})+~m', static fn($m) => str_repeat("\t", intdiv(strlen($m[0]), 4)), $json);
        file_put_contents($file, $json . "\n");
    }
}
