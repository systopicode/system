<?php

declare(strict_types=1);

namespace Systopic\System\Debug;

/**
 * The address that opens a file at a line in syscoder (packages/tools/syscoder,
 * the editor behind the debug links, see tools/.cursor/docs/syscoder.md).
 *
 *   Syscoder::href('/C/laragon/www/packages/system/config/config.schema.json', 8)
 *
 * Null where syscoder is not installed under the document root, as on a
 * stage: a link to it would lead nowhere.
 */
final class Syscoder
{
    private static string|false|null $base = false;

    /** @param string $file internal or native path */
    public static function href(string $file, int $line = 1): ?string
    {
        $base = self::base();
        return $base === null ? null : $base . '?file=' . rawurlencode(\fs::toInternal($file)) . '&line=' . max(1, $line);
    }

    /** Is syscoder there? */
    public static function available(): bool
    {
        return self::base() !== null;
    }

    private static function base(): ?string
    {
        if (self::$base === false) {
            self::$base = null;
            $root = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
            foreach (['/packages/tools/syscoder/', '/_tools/syscoder/'] as $candidate) {
                if ($root !== '' && \fs::isFile($root . $candidate . 'index.php')) {
                    self::$base = $candidate;
                    break;
                }
            }
        }
        return self::$base;
    }
}
