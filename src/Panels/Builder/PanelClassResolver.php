<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Builder;

use Systopic\System\Panels\PanelNode;
use Systopic\System\Panels\PanelRootFolder;
use Systopic\System\Panels\Tree\PanelTree;

/**
 * Resolves the PHP class name to use when instantiating a panel.
 *
 * === Resolution order ===
 *
 * 1. Module-based FQCN (new scheme, preferred when panel.php present and
 *    sourceRoot has a namespaceRoot):
 *      <namespaceRoot>\Root\<PathInPascalCase>\Panel
 *    e.g. namespaceRoot='Systopic\System\Panels', name='backup', parent [app→cms]:
 *      → Systopic\System\Panels\Root\Cms\Backup\Panel
 *
 * 2. Legacy underscore candidates (unchanged fallback):
 *    Given a panel named "pages" whose ancestors are ["app", "cms"], the
 *    resolver walks down the following candidates in order:
 *      app_cms_pages_panel
 *      app_cms_pages_module
 *      app_cms_pages
 *      app_cms_panel
 *      app_cms_module
 *      app_cms
 *      app_panel
 *      app_module
 *      app
 *    and returns the first class that already exists.
 *
 * 3. Bequests / PanelNode fallback (when no class file was present).
 *
 * During migration both schemes coexist: a folder that ships a new panel.php
 * with a namespaced class is found by step 1; a folder that still ships a
 * legacy module.php is found by step 2.
 */
class PanelClassResolver
{
    /** Suffixes tried per ancestor level, modern first, legacy fallback. */
    private const SUFFIXES = ['panel', 'module'];

    /**
     * @param string              $name        The panel's own name (last segment).
     * @param PanelNode|null      $parent      The already-instantiated parent panel, or null for root.
     * @param bool                $hasOwnFile  Whether the panel folder contains panel.php and/or module.php.
     *                                         The caller is responsible for including them before calling resolve().
     * @param PanelRootFolder|null $sourceRoot  The root that contributed the winning folder — used for
     *                                         the module-based FQCN lookup (step 1).
     */
    public function resolve(
        string           $name,
        ?PanelNode       $parent,
        bool             $hasOwnFile,
        ?PanelRootFolder $sourceRoot = null,
    ): ResolvedClass {
        if ($hasOwnFile) {
            // ------------------------------------------------------------------
            // Step 1: module-based FQCN
            // ------------------------------------------------------------------
            if ($sourceRoot !== null && $sourceRoot->namespaceRoot !== '') {
                $fqcn = $this->buildFqcn($sourceRoot->namespaceRoot, $name, $parent);
                if (class_exists($fqcn, true)) {
                    return new ResolvedClass($fqcn, true);
                }
            }

            // ------------------------------------------------------------------
            // Step 2: legacy underscore candidates
            // ------------------------------------------------------------------
            $ancestors = [$name];
            $walk = $parent;
            while ($walk !== null) {
                $ancestors[] = $walk->name;
                $walk = $walk->parent;
            }
            $trunk = array_reverse($ancestors);

            while (count($trunk) > 0) {
                $prefix = implode('_', $trunk);

                foreach (self::SUFFIXES as $suffix) {
                    $candidate = $prefix . '_' . $suffix;
                    if (class_exists($candidate, false)) {
                        return new ResolvedClass($candidate, true);
                    }
                }
                if (class_exists($prefix, false)) {
                    return new ResolvedClass($prefix, true);
                }

                array_shift($trunk);
            }

            // class file was present but no matching class found — fall through
            // to the bequests / PanelNode fallback below.
        }

        if ($parent !== null && $parent->bequests) {
            return new ResolvedClass(get_class($parent), false);
        }

        return new ResolvedClass(PanelNode::class, false);
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Build the module-based FQCN for a panel.
     *
     * The synthetic 'app' root panel is excluded from the path because it does
     * not correspond to a real directory level. Segment names are ucfirst'd to
     * match PascalCase convention (e.g. 'cms' → 'Cms', 'backup' → 'Backup').
     *
     * Example: namespaceRoot='Systopic\System', name='backup', parent chain [app→cms]
     *   → 'Systopic\System\Panels\Root\Cms\Backup\Panel'
     */
    private function buildFqcn(string $nsRoot, string $name, ?PanelNode $parent): string
    {
        $segments = [];
        $walk     = $parent;
        while ($walk !== null) {
            if ($walk->name !== 'app') {
                array_unshift($segments, ucfirst($walk->name));
            }
            $walk = $walk->parent;
        }
        $segments[] = ucfirst($name);

        // app-root has no own path segment — FQCN is just <nsRoot>\Root\Panel
        if ($segments === ['App']) {
            return $nsRoot . '\\Root\\Panel';
        }

        return $nsRoot . '\\Root\\' . implode('\\', $segments) . '\\Panel';
    }
}
