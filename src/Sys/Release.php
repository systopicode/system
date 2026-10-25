<?php

declare(strict_types=1);

namespace Systopic\System\Sys;

/** The GUI versions and the revision the login footer and the dashboard show. */
final class Release
{
    /** @return array<string, array{date: string, new?: list<string>, fixed?: list<string>}> newest first */
    public static function notes(): array
    {
        return require dirname(__DIR__, 2) . '/panels/cms/auth/releases.php';
    }

    /**
     * A released install sets versionDate in its config; otherwise the
     * system package's last git commit.
     */
    public static function revision(): string
    {
        if (defined('PUBLIC_VERSIONDATE') && PUBLIC_VERSIONDATE !== '') {
            return (string) PUBLIC_VERSIONDATE;
        }
        if (!function_exists('shell_exec')) {
            return '';
        }
        $dir = rtrim((string) \fs::$sysRoot, '/');
        return trim((string) shell_exec('git -C ' . escapeshellarg($dir) . ' log -1 --format=%cs\ %h 2>/dev/null'));
    }
}
