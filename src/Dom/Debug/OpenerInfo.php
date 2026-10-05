<?php

declare(strict_types=1);

namespace Systopic\System\Dom\Debug;

use Systopic\System\Debug\BoxTree;

/**
 * Captures the user-code caller (file + line) that triggered a DOM layer/slice
 * lifecycle event.
 *
 * The DomLayerTree calls this from create()/addSlice() at runtime; the result
 * is later embedded into debugInfo() so the domLayersServer hover table can
 * render a clickable link that opens the source in syscoder.
 *
 * Heuristic: filter ONLY by frame['file'] (the call site of the frame's
 * method), never by frame['class']. The class describes where the method
 * lives, but the file is where it was called from — and that is what we want
 * to surface. Filtering by class would skip the boundary frame whose call site
 * sits in real application code (e.g. PanelNavigator::openSelected called from
 * app.php — class is internal, file is app.php, which is the answer we want).
 *
 * Files inside the framework-internal src/ subtrees (Dom, Panels, Compat) are
 * skipped so we keep walking until we hit the application call site.
 *
 * Bare `include`/`require` frames (no class, function = include/require*) are
 * also skipped so we don't end up reporting "loader.php:207" — the include
 * line that loads app.php — instead of the actual opener inside app.php.
 */
final class OpenerInfo
{
    /**
     * Path fragments (forward-slash) whose call sites are considered
     * framework-internal and skipped.
     */
    private const SKIP_PATH_FRAGMENTS = [
        '/src/Dom/',
        '/src/Panels/',
        '/src/Compat/',
    ];

    /**
     * Frame functions that represent a bare file include rather than an
     * actual call. Skipping them avoids reporting the include line of the
     * outer loader as the layer opener.
     */
    private const SKIP_INCLUDE_FUNCTIONS = ['include', 'include_once', 'require', 'require_once'];

    /**
     * Returns null when DEBUGRENDER is off so production paths pay no cost.
     *
     * Schema mirrors what tools/debug/js/caller.js consumes (path, file, line),
     * so the same client-side syscoder open() works for trace events and
     * domLayersServer alike.
     *
     * @return array{path:string,file:string,line:int,function:string,class:string}|null
     */
    public static function capture(): ?array
    {
        if (!defined('DEBUGRENDER') || !\DEBUGRENDER) {
            return null;
        }

        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25);
        foreach ($stack as $frame) {
            if (!isset($frame['file'])) {
                continue;
            }
            if (self::isInternalFrame($frame)) {
                continue;
            }

            $file = str_replace('\\', '/', (string) $frame['file']);
            $lastSlash = strrpos($file, '/');
            $path = $lastSlash !== false ? substr($file, 0, $lastSlash + 1) : '';
            $name = $lastSlash !== false ? substr($file, $lastSlash + 1) : $file;

            return [
                'path'     => $path,
                'file'     => $name,
                'line'     => (int) ($frame['line'] ?? 0),
                'function' => (string) ($frame['function'] ?? ''),
                'class'    => (string) ($frame['class'] ?? ''),
            ];
        }

        return null;
    }

    /**
     * Builds an opener-compatible array from a filesystem path (internal or native).
     * Line defaults to 1 (file start) — used for template links in domLayers debug.
     *
     * @return array{path:string,file:string,line:int,function:string,class:string}|null
     */
    public static function fromFile(?string $file, int $line = 1): ?array
    {
        if ($file === null || $file === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', $file);
        // Prefer absolute/internal path when fs can resolve it.
        if (\fs::isFile($file)) {
            $abs = \fs::toInternal((string) (\fs::realpath($file) ?: $file));
            $normalized = str_replace('\\', '/', $abs);
        }

        $lastSlash = strrpos($normalized, '/');
        $path = $lastSlash !== false ? substr($normalized, 0, $lastSlash + 1) : '';
        $name = $lastSlash !== false ? substr($normalized, $lastSlash + 1) : $normalized;

        return [
            'path'     => $path,
            'file'     => $name,
            'line'     => max(1, $line),
            'function' => '',
            'class'    => '',
        ];
    }

    /**
     * The captured opener as a box-tree info value — the debug tool draws it
     * as a link that opens the file in syscoder (tools/debug/js/boxTreePrompt.js).
     * Used to be an html span built here; the tree is data now, see
     * Debug\BoxTree.
     */
    public static function caller(?array $opener): ?array
    {
        if ($opener === null) {
            return null;
        }
        return BoxTree::caller($opener['path'] ?? '', $opener['file'] ?? '', (int) ($opener['line'] ?? 0));
    }

    private static function isInternalFrame(array $frame): bool
    {
        $function = (string) ($frame['function'] ?? '');
        if (in_array($function, self::SKIP_INCLUDE_FUNCTIONS, true)) {
            return true;
        }

        $file = isset($frame['file']) ? str_replace('\\', '/', (string) $frame['file']) : '';
        foreach (self::SKIP_PATH_FRAGMENTS as $fragment) {
            if (str_contains($file, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
