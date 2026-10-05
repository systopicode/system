<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Tree;

use Systopic\System\Debug\BoxTree;
use Systopic\System\Dom\Contracts\RenderNode;
use Systopic\System\Dom\Contracts\RenderTree;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Panels\PanelRootFolder;
use Systopic\System\Panels\Builder\FolderResolver;
use Systopic\System\Panels\Builder\PanelBuilder;
use Systopic\System\Panels\Builder\PanelClassResolver;

/**
 * Central per-request object for the panel system.
 *
 * Holds the registered roots and orchestrates the unified one-pass build via
 * PanelBuilder/FolderResolver. There is no per-root builder anymore — every
 * recursion sees the merged folder list across all roots, with last-wins
 * resolution on name collisions.
 *
 * Replaces all static state that previously lived in the legacy `module` and
 * `moduleBuilder` classes.
 */
class PanelTree implements RenderTree
{
    /** @var PanelRootFolder[] */
    private array $roots = [];

    private PanelBuilder $builder;

    private ?PanelNode $rootPanel           = null;
    private ?PanelNode $selectedPanel       = null;
    private ?PanelNode $selectedRenderPanel = null;

    /** Logical URL names of panels on the resolved path. */
    private array $path = [];

    /** Real (filesystem) folder names on the resolved path. */
    private array $realPath = [];

    /** The raw URL path that was requested. */
    private array $pathRequested = [];

    /** True when a panel matched the URL but denied access. */
    private bool $selectedDenied = false;

    /**
     * Relative path from process CWD to the panels-root folder of the
     * source-root that contributed the selected panel. PanelNavigator and
     * app.php use it to fs::openDir() back into the panel tree.
     */
    private string $selectedRoot = '';

    /** Count of views that have been marked for update this request. */
    private int $updateViewsCount = 0;

    public function __construct()
    {
        $this->builder = new PanelBuilder(new PanelClassResolver());
    }

    // =========================================================================
    // Singleton access
    // =========================================================================

    private static ?PanelTree $instance = null;

    /**
     * Stores the per-request PanelTree instance so that legacy code
     * (module.php, lib/class/…) can reach it without dependency injection.
     * Called from loader.php immediately after build().
     */
    public static function setInstance(PanelTree $tree): void
    {
        self::$instance = $tree;
    }

    /**
     * Returns the current request's PanelTree, or null before it is built.
     */
    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    // =========================================================================
    // Root registration
    // =========================================================================

    public function addRoot(PanelRootFolder $root): void
    {
        $this->roots[] = $root;
    }

    /**
     * Disables access control on the underlying builder. Called from the
     * loader when a request path matches PUBLIC_ACCESS_ROUTES.
     */
    public function disableAccessControl(): void
    {
        $this->builder->disableAccessControl();
    }

    /**
     * Lazily constructs the children of $parent at any time after the
     * initial build pass — used by PanelNode::ensureChildrenBuilt() so
     * navigation templates that call eachChild() / openChild() on a
     * subtree which was not eagerly expanded (no buildChildPanels /
     * loadChildPanels flag and not on the URL path) still get a populated
     * children list.
     *
     * Hook contract: PanelBuilder::buildChildren() runs onBuild() but
     * does NOT run runAfterBuildHooks() — the lazy children stay as
     * "construction-only" stubs. This preserves the invariant that all
     * onLoad / onLoadSelected / onLoadThisFolder hooks complete BEFORE
     * any rendering output begins.
     *
     * CWD is saved and restored around the build so a render-time call
     * does not leak chdir() side effects into the calling template.
     */
    public function ensureChildrenBuilt(PanelNode $parent): void
    {
        if ($parent->children !== []) return;
        if ($this->roots === []) return;

        $savedCwd = \getcwd();
        try {
            $this->builder->buildChildren($parent, [], $this->roots);
        } finally {
            if (\getcwd() !== $savedCwd) {
                @chdir($savedCwd);
            }
        }
    }

    /** @return PanelRootFolder[] */
    public function getRoots(): array
    {
        return $this->roots;
    }

    // =========================================================================
    // Build
    // =========================================================================

    /**
     * Main entry point.
     *
     * Constructs a synthetic root panel ('app') with realPath=[] and then
     * delegates to PanelBuilder::buildChildren which walks $path using the
     * FolderResolver across every registered root.
     */
    public function build(array $path): void
    {
        $this->pathRequested = $path;
        $this->path          = [];
        $this->realPath      = [];
        $this->selectedPanel = null;

        if ($this->roots === []) {
            \logroute('PanelTree::build — no roots registered');
            return;
        }

        // Synthetic top-level "app" panel; its children are the contents of
        // every modules/ directory across all registered roots.
        $this->rootPanel = $this->builder->constructAppRoot($this->roots);
        $this->rootPanel->onBuild();

        $result = $this->builder->buildChildren($this->rootPanel, $path, $this->roots);

        $this->path     = $result->resolvedPath;
        $this->realPath = $result->resolvedRealPath;

        // Walk down to the deepest selected panel; if a URL segment failed to
        // resolve to a child, mark selectedDenied so callers can fall back.
        $this->selectedPanel = $this->findDeepestSelected($this->rootPanel);
        if ($this->selectedPanel === null && $path !== []) {
            $this->selectedDenied = true;
        }

        if ($this->selectedPanel !== null && count($path) > 0) {
            $this->selectedPanel->action = \app::request()->action;
        }

        // Fallback: if nothing was selected (root URL or denied), the root
        // panel itself becomes the selection target.
        if ($this->selectedPanel === null) {
            $this->selectedPanel = $this->rootPanel;
        }

        $this->selectedRoot        = $this->computeSelectedRoot($this->selectedPanel);
        $this->selectedRenderPanel = $this->selectedPanel;

        $this->runAfterBuildHooks($this->rootPanel);
    }

    // =========================================================================
    // Ensure root (lazy init)
    // =========================================================================

    /**
     * Ensures that rootPanel exists even when build() was skipped (empty URL).
     * Mirrors the legacy moduleBuilder::ensureRootModule() contract.
     */
    public function ensureRoot(): bool
    {
        if ($this->rootPanel !== null) {
            return true;
        }
        if ($this->roots === []) {
            return false;
        }

        $this->rootPanel = $this->builder->constructAppRoot($this->roots);
        $this->rootPanel->onBuild();
        $this->builder->buildChildren($this->rootPanel, [], $this->roots);

        $this->selectedRoot = $this->computeSelectedRoot($this->rootPanel);
        $this->runAfterBuildHooks($this->rootPanel);
        return true;
    }

    // =========================================================================
    // Rebuild (for AJAX render-path divergence)
    // =========================================================================

    /**
     * Re-selects within the already-built tree for a different render path.
     * Mirrors moduleBuilder::rebuild() — only updates selection pointers, no
     * new construction unless a previously-unbuilt sub-tree is encountered.
     */
    public function rebuild(array $path): void
    {
        if ($this->rootPanel === null) {
            return;
        }
        $this->rebuildChildren($path, $this->rootPanel);
    }

    private function rebuildChildren(array $limb, PanelNode $panel): void
    {
        $panel->childSelectedName = array_shift($limb) ?? '';

        foreach ($panel->children as $child) {
            if ($child->name !== $panel->childSelectedName) {
                continue;
            }
            $panel->childSelected = $child;

            if (count($limb) > 0) {
                if ($child->children === []) {
                    $this->builder->buildChildren($child, $limb, $this->roots);
                } else {
                    $this->rebuildChildren($limb, $child);
                }
            } else {
                $child->childSelected = null;
                $this->selectedPanel  = $child;
            }
            return;
        }
    }

    // =========================================================================
    // After-build hooks
    // =========================================================================

    /**
     * Runs onLoad / onLoadSelected / onLoadThisFolder hooks recursively.
     * Mirrors moduleBuilder::runAfterBuildHooks().
     */
    public function runAfterBuildHooks(PanelNode $root): void
    {
        $root->onLoad();

        if (\obj::hasOwnMethod($root, 'onLoadThisClass')) {
            $root->onLoadThisClass();
        }
        if ($this->isSelected($root)) {
            $root->onLoadSelected();
        }
        if ($root->hasOwnClass) {
            $root->onLoadThisFolder();
        }

        $loadAll = $root->loadChildPanels
            || (property_exists($root, 'loadChildModules') && (bool) $root->loadChildModules);

        if ($loadAll && $root->children !== []) {
            foreach ($root->children as $child) {
                $this->runAfterBuildHooks($child);
            }
        } elseif ($root->childSelected !== null) {
            $this->runAfterBuildHooks($root->childSelected);
        }
    }

    // =========================================================================
    // Getters
    // =========================================================================

    public function getRoot(): ?PanelNode
    {
        return $this->rootPanel;
    }

    public function getSelected(): ?PanelNode
    {
        return $this->selectedPanel;
    }

    public function getSelectedRender(): ?PanelNode
    {
        return $this->selectedRenderPanel;
    }

    public function setSelectedRender(?PanelNode $panel): void
    {
        $this->selectedRenderPanel = $panel;
    }

    public function getPath(): array
    {
        return $this->path;
    }

    public function getRealPath(): array
    {
        return $this->realPath;
    }

    public function getRequestedPath(): array
    {
        return $this->pathRequested;
    }

    public function getSelectedRoot(): string
    {
        return $this->selectedRoot;
    }

    public function isSelectedDenied(): bool
    {
        return $this->selectedDenied;
    }

    /**
     * CSS classes for <body>: selected panel path segments plus the primary
     * view/template name (main, else home). Used for full HTML renders and
     * AJAX bodyClasses sync via DomRenderer.
     *
     * Example: selected /patchworks/edit/ with main.tpl.php →
     * ['patchworks', 'edit', 'main'].
     *
     * @return string[]
     */
    public function getBodyClasses(): array
    {
        $selected = $this->selectedPanel;
        if ($selected === null) {
            return [];
        }

        $classes = [];
        foreach ($selected->path as $segment) {
            $segment = (string) $segment;
            if ($segment !== '') {
                $classes[] = $segment;
            }
        }

        $viewName = $this->resolveSelectedViewName($selected);
        if ($viewName !== null) {
            $classes[] = $viewName;
        }

        return array_values(array_unique($classes));
    }

    /**
     * Primary content view for the selected panel — mirrors body.tpl.php:
     * withChildSelected('main') || withView('home').
     */
    private function resolveSelectedViewName(PanelNode $selected): ?string
    {
        $abs = rtrim($selected->absPath, '/');
        if ($abs !== '' && \fs::isFile($abs . '/main.tpl.php')) {
            return 'main';
        }

        $rootAbs = rtrim($this->rootPanel?->absPath ?? '', '/');
        if ($rootAbs !== '' && \fs::isFile($rootAbs . '/home.tpl.php')) {
            return 'home';
        }

        return null;
    }

    /**
     * True when the request should be dispatched to app.php (panel render)
     * rather than site.php (Pages).
     *
     * Two cases qualify:
     *   1. A real panel below the synthetic root was selected by the URL
     *      (e.g. /cms/pages/ → selectedPanel = pages ≠ rootPanel).
     *   2. The synthetic root itself is a "real" panel — it ships its own
     *      class file (panel.php / module.php with a matching class) or
     *      its own body.tpl.php in a registered panels/ root. In that
     *      case even the bare "/" URL (selectedPanel == rootPanel == app)
     *      has something to render via PanelNavigator::findRenderRoot().
     *
     * Without case 2, a project that places a global panels/body.tpl.php
     * would never see it rendered on "/" because the loader would dispatch
     * to site.php (Pages) instead.
     */
    public function inApp(): bool
    {
        if ($this->selectedPanel === null) {
            return false;
        }
        if ($this->selectedPanel !== $this->rootPanel) {
            return true;
        }

        $root = $this->rootPanel;
        if ($root === null) {
            return false;
        }
        if ($root->hasOwnClass) {
            return true;
        }
        if ($root->absPath !== ''
         && \fs::isFile(rtrim($root->absPath, '/') . '/body.tpl.php')) {
            return true;
        }
        return false;
    }

    /**
     * True when the selected panel's first path segment is 'cms'.
     */
    public function inCms(): bool
    {
        return !empty($this->path) && $this->path[0] === 'cms';
    }

    /**
     * Returns the panel name at the given URL-path depth (0-based).
     * Returns null when the path is shorter than $level + 1.
     */
    public function nameAtLevel(int $level): ?string
    {
        return $this->path[$level] ?? null;
    }

    /**
     * Resolves the panel a DomLayer belonged to, from the logical path the
     * layer carries (RenderNode::getLayerPath()). Needed on the diff path,
     * where layers come back from the session with their object references
     * stripped.
     *
     * Moved here from DomLayer::resolvePanel() when the layer stack was made
     * render-model agnostic — walking childrenByName is panel-specific.
     */
    public function findByLayerPath(array $path): ?RenderNode
    {
        $root = $this->rootPanel;
        if ($root === null) {
            return null;
        }

        $node = $root;
        $skip = count($root->path ?? []);

        for ($i = $skip; $i < count($path); $i++) {
            $segment = (string) $path[$i];
            if (!isset($node->childrenByName[$segment])) {
                return null;
            }
            $node = $node->childrenByName[$segment];
        }

        return $node;
    }

    /**
     * Depth-first search for a panel by logical name.
     * Replacement for the legacy module::get($name) facade.
     *
     * @param string         $name   Logical panel name (without numeric prefix).
     * @param PanelNode|null $parent Start node — defaults to the root panel.
     */
    public function findByName(string $name, ?PanelNode $parent = null): ?PanelNode
    {
        $node = $parent ?? $this->rootPanel;
        if ($node === null) {
            return null;
        }
        if ($node->name === $name) {
            return $node;
        }
        foreach ($node->childrenByName as $child) {
            $found = $this->findByName($name, $child);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    // =========================================================================
    // Update-view counter
    // =========================================================================

    /** @deprecated No-op — the count is now derived by traversing the tree. */
    public function incrementUpdateViewsCount(): void {}

    /**
     * Returns the total number of view-update requests across all built panels.
     */
    public function getUpdateViewsCount(): int
    {
        if ($this->rootPanel === null) {
            return 0;
        }
        return $this->countViewsToUpdate($this->rootPanel);
    }

    private function countViewsToUpdate(PanelNode $panel): int
    {
        $count = count($panel->viewsToBeUpdated);
        foreach ($panel->childrenByName as $child) {
            $count += $this->countViewsToUpdate($child);
        }
        return $count;
    }

    // =========================================================================
    // Client data export
    // =========================================================================

    /**
     * Static entry-point for the legacy clientInterfaces registry.
     * Registered in loader.php so that html.tpl.php exports the full panel
     * tree to sys.lib.updateClientData() via clientInterfaces::clientData_export().
     *
     * Returns the same array that the former module::clientData_export() did.
     */
    public static function clientData_export(): array
    {
        return self::$instance?->exportClientData() ?? [];
    }

    /**
     * Export the full panel tree as an array of ClientExport DTOs.
     *
     * @return ClientExport[]
     */
    public function exportClientData(): array
    {
        if ($this->rootPanel === null) {
            return [];
        }

        $exports = [];
        $exports[] = $this->rootPanel->exportClientData();
        $this->collectChildExports($this->rootPanel, $exports);
        return $exports;
    }

    private function collectChildExports(PanelNode $parent, array &$exports): void
    {
        foreach ($parent->childrenByName as $child) {
            $exports[] = $child->exportClientData();
            $this->collectChildExports($child, $exports);
        }
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    private function isSelected(PanelNode $panel): bool
    {
        return $panel === $this->selectedPanel;
    }

    /**
     * Walks down the childSelected chain to find the deepest selected panel.
     * Returns null when nothing was selected (e.g. root URL).
     */
    private function findDeepestSelected(PanelNode $root): ?PanelNode
    {
        if ($root->childSelected === null) {
            // Root URL → no selection. A failed segment leaves childSelectedName
            // set but childSelected null on the parent that couldn't resolve it,
            // and we surface that as null too (caller maps it to selectedDenied).
            return null;
        }
        $cur = $root->childSelected;
        while ($cur->childSelected !== null) {
            $cur = $cur->childSelected;
        }
        return $cur;
    }

    /**
     * Computes the relative path from the process CWD to the panels-root
     * directory that contributed $panel. app.php fs::openDir()s this and
     * later fs::closeDir()s it — the close walks up the same number of
     * segments, so the path MUST be relative (an absolute path would
     * confuse fs::getCloseDir() and unwind too far).
     *
     * The relative computation is done by string-prefix comparison rather
     * than realpath() to stay junction-aware: on Windows, getcwd() returns
     * the junction path (e.g. .../vendor/systopic/system) while realpath
     * resolves it to the real target (.../packages/system). Mixing the two
     * yields a wrong relative path that fails to chdir back.
     */
    private function computeSelectedRoot(PanelNode $panel): string
    {
        $root = $panel->sourceRoot ?? $this->roots[0] ?? null;
        if ($root === null) {
            return \fs::$sitePath . 'panels/';
        }

        // Use fs::toInternal() so both sides share the same forward-slash
        // /C/... shape that fsDir uses; also keeps the comparison
        // junction-aware because toInternal does NOT realpath-resolve.
        $cwd    = rtrim(\fs::toInternal(\getcwd()), '/') . '/';
        $target = rtrim(\fs::toInternal((string) $root->dir), '/') . '/';

        // If the target already lives under the cwd, strip the prefix.
        if (str_starts_with($target, $cwd)) {
            $rel = substr($target, strlen($cwd));
            return $rel !== '' ? $rel : '.';
        }

        // Otherwise walk up by common-prefix segments.
        $cwdParts    = array_values(array_filter(explode('/', $cwd), 'strlen'));
        $targetParts = array_values(array_filter(explode('/', $target), 'strlen'));
        $i = 0;
        $n = min(count($cwdParts), count($targetParts));
        while ($i < $n && $cwdParts[$i] === $targetParts[$i]) {
            $i++;
        }
        $up  = count($cwdParts) - $i;
        $rel = str_repeat('../', $up) . implode('/', array_slice($targetParts, $i));
        return $rel !== '' ? rtrim($rel, '/') . '/' : './';
    }

    // =========================================================================
    // Debug
    // =========================================================================

    /**
     * The panel tree — and the builder's log, if it wrote one — as box-tree
     * data (Debug\BoxTree), for the `panelTreeDebug` prompt.
     *
     * @return list<array<string, mixed>>
     */
    public function debug(): array
    {
        $roots = [];

        if ($this->rootPanel !== null) {
            $roots[] = $this->debugNode($this->rootPanel);
        }

        if (!empty($this->builder->getBuildLog())) {
            $roots[] = $this->builder->debugBuildLog();
        }

        return $roots;
    }

    private function debugNode(PanelNode $panel): array
    {
        $children = [];
        foreach ($panel->children as $child) {
            $children[] = $this->debugNode($child);
        }
        return BoxTree::node($panel->name, $panel->debugInfo(), [
            'panel',
            $panel === $this->selectedPanel ? 'selected' : null,
            $panel->childSelected !== null ? 'inPath' : null,
        ], $children);
    }

    // =========================================================================
    // Static pure helpers
    // =========================================================================

    /**
     * Strips the numeric ordering prefix from a folder name.
     * E.g. '10_pages' → 'pages', 'pages' → 'pages'.
     */
    public static function folderToName(string $folder): string
    {
        if (preg_match('~^\d+_~', $folder, $match)) {
            return substr($folder, strlen($match[0]));
        }
        return $folder;
    }

    /**
     * Numeric ordering prefix of a folder name.
     * E.g. '010_pages' → 10. Folders without a prefix sort last.
     */
    public static function folderToOrder(string $folder): int
    {
        if (preg_match('~^(\d+)_~', $folder, $match)) {
            return (int) $match[1];
        }
        return PHP_INT_MAX;
    }
}
