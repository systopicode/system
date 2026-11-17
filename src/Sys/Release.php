<?php

declare(strict_types=1);

namespace Systopic\System\Sys;

/** The GUI versions and the revision the login footer and the dashboard show. */
final class Release
{
    /** @return array<string, array{date: string, new?: list<string>, fixed?: list<string>}> newest first */
    public static function notes(): array
    {
        return require dirname(__DIR__, 2) . '/panels/auth/releases.php';
    }

    /**
     * A released install sets versionDate in its config; otherwise the
     * system package's last git commit.
     */
    /** The system package LICENSE. Login and the dashboard read this, never another package. */
    public static function license(): string
    {
        $path = dirname(__DIR__, 2) . '/LICENSE';
        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    /** Display the package license with headings, paragraphs, lists and inline code. */
    public static function licenseHtml(): string
    {
        $inline = static fn(string $text): string => preg_replace('/`([^`]+)`/', '<code>$1</code>', htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $html = ''; $paragraph = []; $list = false;
        $flush = static function () use (&$html, &$paragraph, $inline): void {
            if ($paragraph !== []) { $html .= '<p>' . $inline(implode(' ', $paragraph)) . '</p>'; $paragraph = []; }
        };
        foreach (preg_split('/\R/', self::license()) as $line) {
            if (preg_match('/^#{1,2} (.+)$/', $line, $match)) {
                $flush(); if ($list) { $html .= '</ul>'; $list = false; }
                $html .= '<h2>' . $inline($match[1]) . '</h2>';
            } elseif (str_starts_with($line, '- ')) {
                $flush(); if (!$list) { $html .= '<ul>'; $list = true; }
                $html .= '<li>' . $inline(substr($line, 2)) . '</li>';
            } else {
                if ($list) { $html .= '</ul>'; $list = false; }
                if (trim($line) === '') $flush(); else $paragraph[] = $line;
            }
        }
        $flush(); if ($list) $html .= '</ul>';
        return $html;
    }

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
