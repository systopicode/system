<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Autoload;

use Systopic\System\Panels\PanelRootFolder;
use Systopic\System\Panels\Tree\PanelTree;

/**
 * Custom autoloader for the module-based panel namespace scheme.
 *
 * Maps FQCNs of the form
 *
 *   <namespaceRoot>\Root\<Seg1>\<Seg2>\...\Panel
 *
 * to panel.php files co-located with templates in the numbered panel folders:
 *
 *   <panelsDir>/seg1_folder/040_seg2/create/panel.php
 *
 * The mapping works by walking the panels/ directory tree level by level.
 * For each namespace segment (e.g. "Backup") every direct sub-folder of the
 * current directory is checked: its name is stripped of the numeric sort
 * prefix (PanelTree::folderToName, e.g. "040_backup" → "backup") and then
 * compared case-insensitively to the segment. The first match wins.
 *
 * The final segment of the FQCN is always "Panel" and is not used for folder
 * navigation — it is the class name defined inside panel.php.
 *
 * Register via registerRoots() once all PanelRootFolders are known:
 *
 *   PanelAutoloader::registerRoots($panelTree->getRoots());
 */
final class PanelAutoloader
{
    /** @var array<string, string>  namespaceRoot → absolute panels dir path */
    private static array $registrations = [];

    private static bool $registered = false;

    /**
     * Register all roots that carry a namespaceRoot and hook into PHP's
     * autoload stack. Safe to call multiple times; subsequent calls simply
     * add more namespace → directory mappings.
     *
     * @param PanelRootFolder[] $roots
     */
    public static function registerRoots(array $roots): void
    {
        foreach ($roots as $root) {
            if ($root->namespaceRoot === '') {
                continue;
            }
            $dir = rtrim(str_replace('\\', '/', (string) $root->dir), '/');
            // Last registration wins (same last-wins policy as FolderResolver)
            self::$registrations[$root->namespaceRoot] = $dir;
        }

        if (!self::$registered && self::$registrations !== []) {
            spl_autoload_register([self::class, 'load'], prepend: false);
            self::$registered = true;
        }
    }

    /**
     * Autoload handler called by PHP's autoload mechanism.
     */
    public static function load(string $fqcn): void
    {
        foreach (self::$registrations as $ns => $panelsDir) {
            $infix    = $ns . '\\Root\\';
            $infixLen = strlen($infix);

            if (strncmp($fqcn, $infix, $infixLen) !== 0) {
                continue;
            }

            // e.g. "Cms\Backup\Create\Panel" → ['Cms', 'Backup', 'Create', 'Panel']
            $rest     = substr($fqcn, $infixLen);
            $segments = explode('\\', $rest);

            // Last segment must be "Panel" — that is the class name, not a folder
            if (count($segments) < 2 || array_pop($segments) !== 'Panel') {
                return;
            }

            $file = self::resolveFile($panelsDir, $segments);
            if ($file !== null) {
                include_once $file;
            }
            return;
        }
    }

    /**
     * Walk the panels directory tree, matching each segment to a folder via
     * folderToName() + case-insensitive comparison, and return the absolute
     * path to panel.php when all segments are resolved.
     *
     * @param  string[] $segments  e.g. ['Cms', 'Backup', 'Create']
     */
    private static function resolveFile(string $panelsDir, array $segments): ?string
    {
        $current = $panelsDir;

        foreach ($segments as $segment) {
            $segLower = strtolower($segment);
            $matched  = null;

            $entries = \fs::glob($current . '/*', \GLOB_ONLYDIR) ?: [];
            foreach ($entries as $entry) {
                $folder     = basename(str_replace('\\', '/', $entry));
                $logicalName = strtolower(PanelTree::folderToName($folder));
                if ($logicalName === $segLower) {
                    $matched = str_replace('\\', '/', $entry);
                    break;
                }
            }

            if ($matched === null) {
                return null;
            }

            $current = $matched;
        }

        $file = $current . '/panel.php';
        return \fs::isFile($file) ? $file : null;
    }
}
