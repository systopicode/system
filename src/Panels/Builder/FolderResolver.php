<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Builder;

use Systopic\System\Panels\PanelRootFolder;
use Systopic\System\Panels\Tree\PanelTree;

/**
 * Discovers panel folders across all registered roots in a single pass.
 *
 * Replaces the old two-phase build (PanelBuilder::buildChildren + PanelTree::
 * mergeSecondaryBuilders) with one consistent enumeration: iterate roots in
 * registration order, glob each root's view of $parentRealPath/* and merge
 * into a name-keyed map. Later roots overwrite earlier ones (last-wins) so
 * a project can override a system panel by placing a folder of the same
 * logical name in its own modules/ tree. After the merge the map is
 * re-sorted by the numeric folder prefix of the winning source
 * (010_events before 040_backup), so project panels are not stuck at
 * the end of the system glob order.
 */
final class FolderResolver
{
    /**
     * Resolves the synthetic 'app' root across all registered roots.
     *
     * Same multi-root, last-wins policy as discover() — but applied to
     * the BASE directory of each root rather than to children. Lets a
     * project ship a panel.php / body.tpl.php / head.tpl.php directly
     * inside its panels/ folder so the synthetic 'app' panel becomes a
     * real, rendering panel (with its own class and templates).
     *
     * Winning policy:
     *   - If any root's base contains panel.php / module.php, the LAST
     *     such root wins (carries the canonical class file + templates).
     *   - Otherwise the LAST root wins (pure last-wins fallback).
     * altPaths[] lists every root's base path in registration order so
     * PanelBuilder can include_once panel.php / module.php from each.
     *
     * @param PanelRootFolder[] $roots in registration order
     */
    public static function discoverRoot(array $roots): ?FolderHit
    {
        if ($roots === []) {
            return null;
        }

        $altPaths = [];
        $winning  = null;
        foreach ($roots as $root) {
            $abs = rtrim(str_replace('\\', '/', (string) $root->dir), '/');
            if (!\fs::isDir($abs)) {
                continue;
            }
            $altPaths[] = $abs;
            if (\fs::isFile($abs . '/panel.php') || \fs::isFile($abs . '/module.php')) {
                $winning = ['absPath' => $abs, 'root' => $root];
            }
        }

        if ($winning === null) {
            $lastRoot = end($roots);
            $abs      = rtrim(str_replace('\\', '/', (string) $lastRoot->dir), '/');
            if (!\fs::isDir($abs)) {
                return null;
            }
            $winning = ['absPath' => $abs, 'root' => $lastRoot];
            if ($altPaths === []) {
                $altPaths[] = $abs;
            }
        }

        return new FolderHit(
            name:     'app',
            folder:   '',
            absPath:  $winning['absPath'],
            root:     $winning['root'],
            altPaths: $altPaths,
        );
    }

    /**
     * @param string[]            $parentRealPath  e.g. ['cms'] for /cms's children
     * @param PanelRootFolder[]   $roots           in registration order
     * @return array<string, FolderHit>            keyed by folderToName(), last-wins
     */
    public static function discover(array $parentRealPath, array $roots): array
    {
        $sub = $parentRealPath !== [] ? implode('/', $parentRealPath) . '/' : '';

        // First pass: collect per-name source list across all roots so a panel
        // that lives in several roots gets every panel.php / module.php
        // included on construct (see FolderHit::$altPaths).
        /** @var array<string, array{folder:string,absPath:string,root:PanelRootFolder}[]> */
        $perName = [];

        foreach ($roots as $root) {
            $dir = rtrim(str_replace('\\', '/', (string) $root->dir), '/') . '/' . $sub;
            if (!\fs::isDir($dir)) {
                continue;
            }
            $absFolders = \fs::glob($dir . '*', GLOB_ONLYDIR) ?: [];
            foreach ($absFolders as $abs) {
                $abs    = str_replace('\\', '/', $abs);
                $folder = basename($abs);
                $name   = PanelTree::folderToName($folder);
                $perName[$name][] = [
                    'folder'  => $folder,
                    'absPath' => $abs,
                    'root'    => $root,
                ];
            }
        }

        // Second pass: build FolderHits. Winning policy:
        //   - If any source has panel.php or module.php, the LAST such source
        //     wins (so a project root with its own class file still overrides
        //     sys, but a project root that just contributes sub-folders
        //     without a class file does not displace sys's class definition
        //     + templates).
        //   - Otherwise last source wins (pure last-wins for empty overrides).
        // This keeps multi-root merging predictable: sub-folders are merged
        // from every root, but the canonical templates / class file follow
        // the root that actually defines the panel.
        $out = [];
        foreach ($perName as $name => $sources) {
            $altPaths = array_map(static fn(array $s) => $s['absPath'], $sources);

            $winning = null;
            foreach ($sources as $src) {
                if (\fs::isFile($src['absPath'] . '/panel.php')
                 || \fs::isFile($src['absPath'] . '/module.php')) {
                    $winning = $src; // last with class file
                }
            }
            // No class file anywhere: the last source with templates of its
            // own wins, not a folder that only contributes sub-panels (the
            // CMS package's cms/090_tools/030_maintenance/ next to the
            // system's, which carries main/controls/footer).
            if ($winning === null) {
                foreach ($sources as $src) {
                    if ((\fs::glob($src['absPath'] . '/*.tpl.php') ?: []) !== []) {
                        $winning = $src;
                    }
                }
            }
            $winning ??= end($sources);

            $out[$name] = new FolderHit(
                name:     $name,
                folder:   $winning['folder'],
                absPath:  $winning['absPath'],
                root:     $winning['root'],
                altPaths: $altPaths,
            );
        }

        uasort($out, static function (FolderHit $a, FolderHit $b): int {
            $oa = PanelTree::folderToOrder($a->folder);
            $ob = PanelTree::folderToOrder($b->folder);
            return $oa <=> $ob ?: strnatcasecmp($a->folder, $b->folder);
        });

        return $out;
    }
}
