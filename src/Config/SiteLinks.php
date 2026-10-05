<?php

declare(strict_types=1);

namespace Systopic\System\Config;

/**
 * Creates the directory links that let the other sites answer under their
 * own path on a development machine — what used to be a manual
 *
 *   mklink /J V:\www\projects\beautiful-technology.de V:\www\projects\systopic.net
 *
 * For every path entry of the instance ('/projects/{site}/') the link is
 * <document root><entry> → <project dir>. Opt-in per instance
 * (`"linkSites": true` in <Instance>.config.json), so STAGING and LIVE are
 * never touched unless asked.
 *
 * Careful by design:
 *   - only runs when one of the entries already IS this project
 *     (document root + entry = project dir), i.e. the path scheme holds here
 *   - only path entries; host entries are another machine's business
 *   - anything that already exists at the link path is left alone, so the
 *     cost per request is one stat per site
 *
 * Windows only for now: a junction needs no admin rights (a symlink does).
 * macOS / Linux would be a symlink() in link().
 */
final class SiteLinks
{
    /**
     * @return array<string, string> link path => 'created' or why not
     */
    public static function ensure(Sites $sites, string $documentRoot, string $projectDir): array
    {
        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $target = realpath($projectDir);
        if ($documentRoot === '' || $target === false || !is_dir($documentRoot)) {
            return [];
        }

        $paths = [];
        foreach ($sites->all() as $site) {
            foreach ($site->entries as $entry) {
                // path entries only; '..' would leave the document root
                if (str_starts_with($entry, '/') && !str_contains($entry, '..') && trim($entry, '/') !== '') {
                    $paths[] = $documentRoot . '/' . trim($entry, '/');
                }
            }
        }
        $paths = array_unique($paths);

        $self = self::normalize($target);
        $schemeHolds = false;
        foreach ($paths as $path) {
            $real = realpath($path);
            if ($real !== false && self::normalize($real) === $self) {
                $schemeHolds = true;
                break;
            }
        }
        if (!$schemeHolds) {
            return [];
        }

        $out = [];
        foreach ($paths as $path) {
            if (file_exists($path) || is_link($path) || !is_dir(dirname($path))) {
                continue;
            }
            $out[$path] = self::link($target, $path);
        }
        return $out;
    }

    /**
     * The link paths of one site on this machine: <document root><path entry>.
     *
     * @param list<string> $entries the site's match entries
     * @return list<string>
     */
    public static function pathsOf(array $entries, string $documentRoot): array
    {
        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
        $paths = [];
        foreach ($entries as $entry) {
            if (str_starts_with($entry, '/') && !str_contains($entry, '..') && trim($entry, '/') !== '') {
                $paths[] = $documentRoot . '/' . trim($entry, '/');
            }
        }
        return array_values(array_unique($paths));
    }

    /**
     * True when $path is a link (junction or symlink) onto $target — not the
     * folder itself. Renaming must never move the real project folder.
     */
    public static function isLinkTo(string $path, string $target): bool
    {
        $real = realpath($path);
        $self = realpath($target);
        return $real !== false && $self !== false
            && self::normalize($real) === self::normalize($self)
            && self::normalize($path) !== self::normalize($real);
    }

    /**
     * Moves a link: removes the one at $from (only the link, the target
     * stays) and creates one at $to. Returns what happened.
     */
    public static function move(string $from, string $to, string $target): string
    {
        if (file_exists($to) || is_link($to)) {
            return "skipped: '$to' exists";
        }
        // rmdir() removes a junction or directory symlink without touching its target
        if (!@rmdir($from) && !@unlink($from)) {
            return "failed: could not remove '$from'";
        }
        return self::link($target, $to);
    }

    private static function link(string $target, string $link): string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return 'skipped: only Windows junctions so far';
        }
        $command = 'mklink /J '
            . escapeshellarg(str_replace('/', '\\', $link)) . ' '
            . escapeshellarg(str_replace('/', '\\', $target)) . ' 2>&1';
        exec($command, $output, $code);
        if ($code === 0) {
            return 'created';
        }
        $message = "config: could not link '$link' to '$target': " . trim(implode(' ', $output));
        trigger_error($message, E_USER_WARNING);
        return $message;
    }

    /** Forward slashes, lower-case on Windows (case-insensitive file system). */
    private static function normalize(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    }
}
