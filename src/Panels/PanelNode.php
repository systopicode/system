<?php

declare(strict_types=1);

namespace Systopic\System\Panels;

use Systopic\System\Client\ClientExport;
use Systopic\System\Client\Contracts\ClientExportable;
use Systopic\System\Dom\Contracts\RenderNode;
use Systopic\System\Panels\Navigator\PanelNavigator;
use Systopic\System\Panels\Tree\PanelTree;

/**
 * Base class for every panel instance in the panel tree.
 *
 * Fully replaces the legacy moduleNode:
 *  - All lifecycle hooks (onBuild, onLoad, onLoadThisClass, ...)
 *  - State management, view control, navigation helpers
 *  - Server-side action confirmation (ConfirmsActions)
 *  - label / href / siblings / dirpath as property hooks, child() for sub-panels
 *  - #[\AllowDynamicProperties] inherited by all subclasses
 */
#[\AllowDynamicProperties]
class PanelNode implements ClientExportable, RenderNode
{
    use ConfirmsActions;

    // -------------------------------------------------------------------------
    // Identity & filesystem location
    // -------------------------------------------------------------------------

    public string $name = '';

    /** Logical URL path segments from root to this panel. E.g. ['cms', 'pages']. */
    public array $path = [];

    /** Real (filesystem) folder names from root to this panel. */
    public array $realPath = [];

    /** Relative path of this panel's folder (used for fs::openDir). */
    public string $folder = '';

    /**
     * Absolute disk path of this panel's folder (fs internal /C/... form).
     *
     * Set during build from the FolderHit so navigation can chdir to a panel
     * regardless of which root contributed it. Without this, eachChild() would
     * fail on multi-root setups: e.g. when iterating cms's children, the CWD
     * is sys/modules/cms/, but `events` may live under projectRoot/modules/cms/
     * — opening it via the bare folder name silently fails (chdir error) and
     * desyncs the dir-stack.
     */
    public string $absPath = '';

    /** Nesting depth; root = 0. */
    public int $level = 0;

    /** True when the panel folder contained a module.php with a matching class. */
    public bool $hasOwnClass = false;

    // -------------------------------------------------------------------------
    // Tree relations
    // -------------------------------------------------------------------------

    public ?PanelNode $parent = null;

    /** Reference to the topmost panel in this sub-tree (set during build). */
    public ?PanelNode $root = null;

    public ?PanelNode $next = null;
    public ?PanelNode $prev = null;

    public ?PanelNode $firstChild = null;
    public ?PanelNode $lastChild  = null;

    /** Iteration pointer used by PanelNavigator::openNextChild(). */
    public ?PanelNode $activeChild = null;

    /** The child panel whose name matches the next URL segment. */
    public ?PanelNode $childSelected = null;

    /** Name of the expected child panel (next URL segment). */
    public ?string $childSelectedName = null;

    /** @var PanelNode[] Ordered list of child panels. */
    public array $children = [];

    /** @var array<string, PanelNode> Associative map of child panels by name. */
    public array $childrenByName = [];

    // -------------------------------------------------------------------------
    // Behaviour flags
    // -------------------------------------------------------------------------

    /**
     * When true, child panels inherit this panel's class instead of falling
     * back to PanelNode (legacy: $bequests).
     */
    public bool $bequests = false;

    /**
     * When true, all child panels are constructed eagerly during the initial
     * build (even when not selected by the URL) and their after-build hooks
     * (onLoad / onLoadSelected / onLoadThisFolder) are run before any render
     * begins. Useful for parents whose children must initialise themselves
     * (load DB data, dispatch messages, redirect) before any output is
     * captured by DomLayerTree.
     *
     * If you only need the children to *exist* (so navigation templates can
     * iterate them via eachChild() / openChild()), do nothing — they will be
     * lazy-built on first access by ensureChildrenBuilt(). Lazy-built panels
     * are stubs: only constructor-set / Builder-set properties are
     * available; their lifecycle hooks are intentionally NOT executed.
     */
    public bool $buildChildPanels = false;

    /**
     * Alias of $buildChildPanels with identical semantics under the new
     * builder. Kept as a separate flag for legacy intent ("hooks too"); the
     * builder treats both as eager-build-with-hooks triggers.
     */
    public bool $loadChildPanels = false;

    /**
     * True once the children of this panel have been constructed (eagerly
     * during the initial build, or lazily on first access). Prevents
     * ensureChildrenBuilt() from re-scanning folders on repeat lookups for
     * panels that legitimately have no children.
     */
    public bool $childrenBuilt = false;

    // -------------------------------------------------------------------------
    // Request context
    // -------------------------------------------------------------------------

    /** Action name extracted from the request (populated by PanelBuilder). */
    public ?string $action = null;

    /** True when the panel folder contains a panel.js (or legacy module.js) file. */
    public ?string $jsClass = null;

    /**
     * Optional icon class (e.g. Font-Awesome) used by getNaviItem().
     * Untyped to stay compatible with legacy subclasses that declare
     * `public $icon = '...'` without a type.
     */
    public $icon = null;

    // -------------------------------------------------------------------------
    // View / render state
    // -------------------------------------------------------------------------

    /** View names that have been requested to re-render on this request. */
    public array $viewsToBeUpdated = [];

    /** Debug info: caller info for each update request, keyed by view name. */
    public array $updateViewsCallerInfo = [];

    /** Rendered HTML per view name (used for client data export). */
    public array $viewsHTML = [];

    // -------------------------------------------------------------------------
    // Per-panel user state (linked into the session state tree)
    // -------------------------------------------------------------------------

    public ?object $state = null;

    // -------------------------------------------------------------------------
    // Source-root tracking (used for re-render lookups and debug)
    // -------------------------------------------------------------------------

    /**
     * The PanelRootFolder that contributed this panel's folder. Set by
     * PanelBuilder::constructPanel() so PanelTree can recover the on-disk
     * location when re-rendering (e.g. action paths different from the URL
     * path) and so debug output can attribute panels to their root.
     */
    public ?PanelRootFolder $sourceRoot = null;

    // =========================================================================
    // Constructor
    // =========================================================================

    /**
     * Sets up name, parent/root references and path, then attaches user state.
     *
     * Compatible with legacy module.php classes that call parent::__construct($name, $parent).
     */
    public function __construct(string $name, ?PanelNode $parent = null)
    {
        $this->name   = $name;
        $this->parent = $parent;
        $this->root   = $parent !== null ? ($parent->root ?? $parent) : $this;

        $this->path = $parent !== null
            ? [...$parent->path, $name]
            : ($name !== '' ? [$name] : []);

        if ($name !== '') {
            $this->initState();
        }
    }

    /**
     * Walks the user-session state tree down to this panel's path and attaches
     * the matching state object. Extracted so subclasses can override if needed.
     */
    private function initState(): void
    {
        $state = \Systopic\System\Auth\Session::state();
        $path  = $this->path;

        // Walk down to the parent's state node (all but last segment).
        while (count($path) > 1) {
            $seg = array_shift($path);
            if (!isset($state->children)) {
                $state->children = new \stdClass();
            }
            if (!isset($state->children->$seg)) {
                $state->children->$seg = new \stdClass();
            }
            $state = $state->children->$seg;
        }

        $panelName = reset($path);
        if ($panelName !== false && $panelName !== '') {
            $this->attachState($state, $panelName);
        }
    }

    // =========================================================================
    // Template rendering
    // =========================================================================

    /**
     * Includes a template file with $this bound to this panel instance.
     *
     * Because the include happens inside a method, the template has access to
     * $this (the active PanelNode/moduleNode), plus two backward-compatibility
     * shims: $module = $this and $state = $this->state.
     *
     * Use via the module::render() facade so the caller does not need to hold
     * a reference to the panel instance:
     *
     *   if (module::openView('pylon')) {
     *       module::render('pylon.tpl.php');
     *       module::close();
     *   }
     */
    public function render(string $file): void
    {
        // Mirror the active panel/state into GLOBALS *and* alias the locals
        // by reference so legacy templates that call module::openChild() (or
        // module::openView() etc.) mid-template see the updated $module /
        // $state — the open* compat methods rewrite $GLOBALS['module'] and
        // $GLOBALS['state'] via syncModuleGlobal in module::__callStatic.
        // Without the by-reference binding the locals would freeze on the
        // outer panel's state, breaking patterns like:
        //     if (module::openChild('sidebar', FALSE)) {
        //         foreach ($state->activeSidebarTemplates as ...) { ... }
        //     }
        $GLOBALS['module'] = $this;
        $GLOBALS['state']  = $this->state;
        $module = &$GLOBALS['module'];
        $state  = &$GLOBALS['state'];
        include $file;
    }

    /**
     * Includes an actions file with $this bound to this panel instance.
     *
     * Replaces the legacy pattern of letting app.php / site.php include
     * actions.php at global scope, where the file relied on globals like
     * $module, $state, $action, $user, $modulePath. Inside the new file
     * shape, action handlers should access:
     *
     *   $this              — the active panel (was $module)
     *   $this->action      — the dispatched action name (was $action)
     *   $this->state       — per-panel session state (was $state)
     *   $this->path        — panel path segments (was $modulePath)
     *   \Systopic\System\Auth\Session::current() — current user (was $user)
     *
     * For backwards compatibility with action files that have not yet
     * been migrated, the legacy locals/globals ($module, $state, $action, $user)
     * are still populated — by-reference for $module/$state, by-value
     * for $action/$user. Sub-includes that switch on $action keep working.
     *
     * Caller (app.php) typically: `$module->runActions();`
     */
    public function runActions(string $file = 'actions.php'): void
    {
        if (!\class_exists('fsUploader', false)) {
            \fs::load('fsUploader');
        }
        if (\class_exists('fsUploader', false) && \fsUploader::handleAction((string) ($this->action ?? ''))) {
            return;
        }
        if (!is_file($file)) return;
        $GLOBALS['module'] = $this;
        $GLOBALS['state']  = $this->state;
        $module = &$GLOBALS['module'];
        $state  = &$GLOBALS['state'];
        $action = $this->action;
        $user = \Systopic\System\Auth\Session::current();
        include $file;
    }

    // =========================================================================
    // Closure-based navigation — open / run fn / auto-close
    // =========================================================================

    /**
     * Static navigator reference, installed by PanelNode::setNavigator() (see loader.php).
     * Kept here so templates can call $this->withView() etc. directly.
     */
    private static ?PanelNavigator $nav = null;

    public static function setNavigator(PanelNavigator $navigator): void
    {
        self::$nav = $navigator;
    }

    /**
     * Returns the active PanelNavigator for this request, or null before
     * app.php installs it. Used by module.php to delegate navigation calls
     * without going through any compat shim.
     */
    public static function getNavigator(): ?PanelNavigator
    {
        return self::$nav;
    }

    /**
     * Static panel-tree reference, installed by PanelNode::setTree() (see loader.php).
     * Used by ensureChildrenBuilt() to lazy-build a panel's children on
     * first access (e.g. when a navigation template calls eachChild() on a
     * subtree that wasn't eagerly expanded by buildChildPanels /
     * loadChildPanels flags).
     */
    private static ?PanelTree $tree = null;

    public static function setTree(PanelTree $tree): void
    {
        self::$tree = $tree;
    }

    /**
     * Lazy-builds this panel's children if they have not been constructed
     * yet. Idempotent: the $childrenBuilt flag prevents repeated folder
     * scans even when the panel legitimately has zero children.
     *
     * Lazy-built panels are STUBS — their lifecycle hooks (onLoad,
     * onLoadSelected, onLoadThisFolder) are intentionally NOT run.
     * Templates iterating them via eachChild() can rely on constructor
     * data (name, label, icon, path, folder, parent) but MUST NOT depend
     * on properties that are typically populated by hooks. Panels that
     * need hook-driven properties for siblings must opt into eager build
     * via $buildChildPanels = true on the parent.
     *
     * Called automatically from PanelNode::eachChild() and from
     * PanelNavigator::openChild / openFirstChild / openNextChild.
     */
    public function ensureChildrenBuilt(): void
    {
        if ($this->childrenBuilt) return;
        $this->childrenBuilt = true;
        self::$tree?->ensureChildrenBuilt($this);
    }

    /**
     * Shared runner used by all with* methods.
     * If $opened is true, calls $fn($activePanel) then always closes.
     *
     * The closure runs in EVERY render mode, diff included — there is no
     * alias short-circuit. That is deliberate: a layer typed 'alias' only
     * tells the client "keep this DOM subtree", but its children still have
     * to be walked, because the change may sit further down (see
     * panel-diff-rendering.md §7). Skipping the closure here would hide
     * those descendants from the diff.
     */
    private function runWith(bool $opened, callable $fn): bool
    {
        if (!$opened) return false;
        try {
            $GLOBALS['module'] = self::$nav->getActive();
            $GLOBALS['state']  = self::$nav->getActive()?->state;
            $fn(self::$nav->getActive());
        } finally {
            self::$nav->close();
            $GLOBALS['module'] = self::$nav->getActive();
            $GLOBALS['state']  = self::$nav->getActive()?->state;
        }
        return true;
    }

    /**
     * Opens a view of the current panel and calls $fn with the active panel.
     * When $fn is omitted, renders "{$viewName}.tpl.php" automatically.
     *
     *   Before: if (module::openView('tree')) { module::render('tree.tpl.php'); module::close(); }
     *   After:  $this->withView('tree');
     */
    public function withView(string $viewName, ?callable $fn = null): bool
    {
        $fn ??= fn($p) => $p->render("{$viewName}.tpl.php");
        return $this->runWith(self::$nav?->openView($viewName) ?? false, $fn);
    }

    /**
     * Opens the URL-selected child panel and calls $fn.
     *
     * $viewOrFn accepts:
     *   string   — view name to check for; auto-renders "{view}.tpl.php" when $fn is omitted.
     *   false    — skip template existence check.
     *   callable — skip check, use as $fn directly (shorthand for no-view-name variant).
     *
     *   Before: if (module::openChildSelected('pylon')) { module::render('pylon.tpl.php'); module::close(); }
     *   After:  $this->withChildSelected('pylon');
     *
     *   Before: if (module::openChildSelected()) { module::render('main.tpl.php'); module::close(); }
     *   After:  $this->withChildSelected();
     */
    public function withChildSelected(string|false|callable $viewOrFn = 'main', ?callable $fn = null): bool
    {
        // Strings are view names, even if they collide with a PHP function
        // (e.g. 'header'). Only Closures / invokable objects are the callable shorthand.
        if (!is_string($viewOrFn) && is_callable($viewOrFn)) {
            $fn       = $viewOrFn;
            $viewName = false;
        } else {
            $viewName = $viewOrFn;
            if ($fn === null && is_string($viewName)) {
                $fn = fn($p) => $p->render("{$viewName}.tpl.php");
            }
        }
        return $this->runWith(self::$nav?->openChildSelected($viewName ?? false) ?? false, $fn ?? fn($p) => null);
    }

    /**
     * Opens a named child panel and calls $fn.
     * Auto-renders "{$viewName}.tpl.php" when $fn is omitted.
     *
     *   Before: if (module::openChild('user','signetmenu')) { module::render('signetmenu.tpl.php'); module::close(); }
     *   After:  $this->withChild('user', 'signetmenu');
     */
    public function withChild(string $panelName, string $viewName, ?callable $fn = null): bool
    {
        $fn ??= fn($p) => $p->render("{$viewName}.tpl.php");
        return $this->runWith(self::$nav?->openChild($panelName, $viewName) ?? false, $fn);
    }

    /**
     * Opens the URL-selected panel and calls $fn.
     * Auto-renders "{$viewName}.tpl.php" when $fn is omitted.
     */
    public function withSelected(string $viewName, ?callable $fn = null): bool
    {
        $fn ??= fn($p) => $p->render("{$viewName}.tpl.php");
        return $this->runWith(self::$nav?->openSelected($viewName) ?? false, $fn);
    }

    /**
     * Opens the root panel and calls $fn.
     * Auto-renders "{$viewName}.tpl.php" when $fn is omitted.
     */
    public function withRoot(string $viewName, ?callable $fn = null): bool
    {
        $fn ??= fn($p) => $p->render("{$viewName}.tpl.php");
        return $this->runWith(self::$nav?->openRoot($viewName) ?? false, $fn);
    }

    /**
     * Iterates all children, calling $fn($child) for each.
     * $viewName=false skips template-existence checks (typical for nav rendering).
     * close() is called automatically after each iteration, even on exception.
     *
     *   Before: while (module::openNextChild(false)) { ... module::close(); }
     *   After:  $this->eachChild(fn($child) => ...);
     */
    public function eachChild(callable $fn, string|false $viewName = false): void
    {
        if (self::$nav === null) return;
        $this->ensureChildrenBuilt();
        while (self::$nav->openNextChild($viewName)) {
            try {
                $fn(self::$nav->getActive());
            } finally {
                self::$nav->close();
            }
        }
    }

    // =========================================================================
    // Lifecycle hooks — override in subclasses
    // =========================================================================

    /** Called during the build walk (only ancestors are built at this point). */
    public function onBuild() {}

    /** Called after the full tree is built (children are available). */
    public function onLoad() {}

    /**
     * Called only on instances whose class defines this method itself —
     * extension/bequests panels that inherit the class are skipped.
     * Use for initialisation that should run exactly once per folder.
     */
    public function onLoadThisClass() {}

    /** Called only when this panel is the selected (deepest URL-matched) panel. */
    public function onLoadSelected() {}

    /**
     * Called once per folder for instances whose class was defined in the
     * folder's own module.php.
     */
    public function onLoadThisFolder() {}

    /** Called after the selected panel's view has been rendered. */
    public function onAfterRenderSelected() {}

    // =========================================================================
    // Access control — override to restrict
    // =========================================================================

    public function buildGranted()
    {
        return \Systopic\System\Auth\Session::known();
    }

    public function actionGranted($action = null)
    {
        return \Systopic\System\Auth\Session::known();
    }

    // =========================================================================
    // Render mode
    // =========================================================================

    /**
     * Determines how the panel's body template is rendered.
     * Returns 'slices' | 'html' | 'markers'
     */
    public function getRenderMode()
    {
        return 'slices';
    }

    // =========================================================================
    // Path helpers
    // =========================================================================

    /** Returns the logical path as a slash-separated string. E.g. 'cms/pages/'. */
    public function getPath(): string
    {
        $str = implode('/', $this->path);
        return $str !== '' ? $str . '/' : '';
    }

    /**
     * Returns a unique address string combining the root name and logical path.
     * Used as a key in DomLayerTree's reference map.
     */
    public function getAddress(): string
    {
        return ($this->root?->name ?? $this->name) . ':' . $this->getPath();
    }

    // =========================================================================
    // Dom\Contracts\RenderNode
    // =========================================================================

    /**
     * What the diff compares between a new layer and its reference layer.
     * For panels that is the folder-derived panel name — the same value the
     * comparison used before RenderNode was introduced.
     */
    public function getLayerIdentity(): string
    {
        return $this->name;
    }

    /** A panel is its name, and that was always readable - nothing to translate. */
    public function getDebugIdentity(): string
    {
        return $this->getLayerIdentity();
    }

    /** @return array<int, string> Logical URL path, resolved by findByLayerPath(). */
    public function getLayerPath(): array
    {
        return $this->path;
    }

    /** @return string[] */
    public function getViewsToBeUpdated(): array
    {
        return $this->viewsToBeUpdated;
    }

    /** Folder this panel's view templates are resolved against. */
    public function getViewBasePath(): string
    {
        return $this->absPath;
    }

    /** @see RenderNode::getRenderSource() */
    public function getRenderSource(): string
    {
        return \Systopic\System\Dom\Layer\DomLayer::SOURCE_PANEL;
    }

    /** True when this panel is on the active URL path (all ancestors are selected). */
    public function inPath(): bool
    {
        if ($this->parent !== null) {
            return $this->parent->inPath()
                && $this->parent->childSelectedName === $this->name;
        }
        return true; // root is always in path
    }

    /**
     * True when this panel is the selected (deepest URL-matched) panel.
     * Named isSelectedIn() to avoid signature conflict with legacy moduleNode::isSelected().
     */
    public function isSelectedIn(PanelTree $tree): bool
    {
        return $this === $tree->getSelected();
    }

    /**
     * True when this panel is the currently selected panel.
     * Convenience shorthand — uses the static tree reference installed by PanelNode::setTree().
     */
    public function isSelected(): bool
    {
        return self::$tree !== null && $this === self::$tree->getSelected();
    }

    /** Backward-compat alias for isSelected(). */
    public function selected(): bool
    {
        return $this->isSelected();
    }

    // =========================================================================
    // Child helpers
    // =========================================================================

    public function hasChild(string $childName): bool
    {
        return array_key_exists($childName, $this->childrenByName);
    }

    public function selectedChildNameIs(string $name): bool
    {
        return $this->childSelected !== null
            && $this->childSelected->name === $name;
    }

    // =========================================================================
    // Siblings / href / dirpath — replaces legacy __get() magic
    // =========================================================================

    /** @return array<string, PanelNode> */
    public function getSiblings(): array
    {
        return $this->parent?->childrenByName ?? [];
    }

    public function getHref(string $httpRoot = ''): string
    {
        $suffix = count($this->path) > 0 ? '/' : '';
        return $httpRoot . implode('/', $this->path) . $suffix;
    }

    public function getDirPath(): string
    {
        if ($this->parent !== null) {
            return $this->parent->getDirPath() . $this->folder . '/';
        }
        return '/';
    }

    // =========================================================================
    // Navigation rendering
    // =========================================================================

    /**
     * Contribution of this panel to a project-specific main menu (legacy
     * moduleNode API). The markup belongs to the project, so the base class
     * stays silent: a panel that ships no toNavi() of its own — and does not
     * inherit one from a project base class — simply has no entry.
     *
     * Usage in templates:
     *   $this->eachChild(fn($child) => print $child->toNavi());
     */
    public function toNavi()
    {
        return '';
    }

    /**
     * Builds an <li><a><span><i></i></span></a></li> navigation entry for this
     * panel. Override in subclasses to customise; return null to hide the item.
     *
     * Usage in templates:
     *   $this->eachChild(fn($child) => print $child->getNaviItem());
     */
    public function getNaviItem(): ?\htmlElement
    {
        $icon = $this->icon ?? $this->name;
        $li = \html::create('li.nav_' . $this->name)
            ->attr('data-name', $this->name)
            ->class($this->inPath() ? 'active' : null);

        $a = $li->append('a')->href($this->getHref(\http::$root));
        $a->append('span')->text($this->name);
        $a->append('i')->class($icon);

        return $li;
    }

    /**
     * Builds a nested <ul> navigation tree rooted at this panel's children.
     *
     * For each child, getNaviItem() is called. When $depth > 1 and the child
     * is in the current URL path, its own children are appended recursively
     * as a nested <ul> down to $depth levels.
     *
     * Usage in templates:
     *   echo $this->getNavi();          // 2 levels (default)
     *   echo $this->getNavi(1);         // flat, no sub-items
     *   echo $this->getNavi(3);         // three levels deep
     */
    public function getNavi(int $depth = 2): \htmlElement
    {
        $ul = \html::create('ul');
        $this->eachChild(function (self $child) use ($ul, $depth): void {
            $item = $child->getNaviItem();
            if ($item === null) return;
            if ($depth > 1 && $child->inPath()) {
                $item->append($child->getNavi($depth - 1));
            }
            $ul->append($item);
        });
        return $ul;
    }

    // =========================================================================
    // View visibility
    // =========================================================================

    public function viewVisible(string $viewName): bool
    {
        if ($viewName === self::CONFIRM_VIEW) {
            // Derived, never stored — see ConfirmsActions / PendingConfirm.
            return $this->confirmPending() !== null;
        }
        if ($viewName !== '' && isset($this->state->hiddenViews)) {
            return !in_array($viewName, $this->state->hiddenViews, true);
        }
        return true;
    }

    public function viewHidden(string $viewName): bool
    {
        return !$this->viewVisible($viewName);
    }

    public function showView(string $viewName): void
    {
        if (isset($this->state->hiddenViews)) {
            $key = array_search($viewName, $this->state->hiddenViews, true);
            if ($key !== false) {
                unset($this->state->hiddenViews[$key]);
                $this->updateView($viewName, true);
            }
        }
    }

    public function hideView(string $viewName): void
    {
        if (isset($this->state->hiddenViews)) {
            if (!in_array($viewName, $this->state->hiddenViews, true)) {
                $this->state->hiddenViews[] = $viewName;
                $this->updateView($viewName, true);
            }
        }
    }

    /**
     * Marks a view to be re-rendered on this request.
     *
     * @param bool $force Re-render even if the view is currently hidden.
     */
    public function updateView(string $viewName = 'main', bool $force = false): void
    {
        if (!$this->viewVisible($viewName) && !$force) {
            return;
        }
        if (!in_array($viewName, $this->viewsToBeUpdated, true)) {
            $this->viewsToBeUpdated[] = $viewName;
        }
    }

    /** @param string[] $viewNames */
    public function updateViews(array $viewNames = ['main'], bool $force = false): void
    {
        foreach ($viewNames as $viewName) {
            $this->updateView($viewName, $force);
        }
    }

    // =========================================================================
    // State management
    // =========================================================================

    public function getDefaultState()
    {
        return new \stdClass();
    }

    public function resetState(?array $keys = null): void
    {
        foreach (($keys ?? (array) $this->state) as $key => $val) {
            unset($this->state->$key);
        }
        foreach ((array) $this->getDefaultState() as $key => $val) {
            if ($keys === null || in_array($key, $keys, true)) {
                $this->state->$key = $val;
            }
        }
    }

    public function updateState(object $data): void
    {
        \obj::fill($this->state, $data);
    }

    /**
     * Attaches the panel's state object from the user session tree.
     * Called by PanelBuilder after instantiation.
     */
    public function attachState(object $parentState, string $moduleName): void
    {
        if (!isset($parentState->children)) {
            $parentState->children = new \stdClass();
        }
        if (!isset($parentState->children->$moduleName)) {
            $parentState->children->$moduleName = new \stdClass();
        }
        $node = $parentState->children->$moduleName;
        if (!isset($node->data)) {
            $node->data = new \stdClass();
        }

        $this->state = $node->data;

        $defaultState = (object) $this->getDefaultState();
        if (is_object($this->state)) {
            \obj::fillIfNotSet($this->state, $defaultState);
            \obj::strip($this->state, $defaultState, 1);
        } else {
            error_log(static::class . '::attachState — state was ' . gettype($this->state)
                . ', path ' . $this->getPath() . ' — reset to default');
            $this->state = new \stdClass();
            \obj::extend($this->state, $this->getDefaultState());
            $node->data  = $this->state;
        }
    }

    // =========================================================================
    // Client data export
    // =========================================================================

    /**
     * Export this panel's data as a typed ClientExport DTO.
     *
     * The route is 'panels' for the synthetic root (empty path), or
     * 'panels.<dot-separated-path>' for all other nodes.
     */
    public function exportClientData(): ClientExport
    {
        $route = $this->path === []
            ? 'panels'
            : 'panels.' . implode('.', $this->path);

        return new ClientExport(
            route: $route,
            data: (object) [
                'name'          => $this->name,
                'path'          => implode('.', $this->path),
                'folder'        => $this->folder,
                'node_ordering' => ($pos = strpos($this->folder, '_')) !== false
                    ? substr($this->folder, 0, $pos)
                    : '',
                'jsClass'       => $this->jsClass,
                'inPath'        => $this->inPath(),
                'childSelected' => $this->childSelected?->name ?? false,
                'views'         => $this->viewsHTML,
                // Per-panel session state — JS reads __libnode.__data.state
                // (e.g. edit.patchworkId, toolbar.showTags). Without this,
                // onUpdate* hooks throw and abort panelTree.update().
                'state'         => $this->state ?? (object) [],
            ],
        );
    }

    // =========================================================================
    // JS loading
    // =========================================================================

    /**
     * Registers this panel's JS file with the client loader.
     * Detects whether the panel lives in the system root (→ 'sys') or project
     * root (→ 'app'). Companion JS lives next to panel.php under panels/;
     * loader.js resolves 'app' via settings.projectRoot (DEV/STAGING).
     */
    public function loadJS(): void
    {
        $path   = count($this->realPath) ? implode('/', $this->realPath) . '/' : '';
        $jsFile = $this->jsClass ?? 'panel.js';

        $root = 'app';
        if (isset($this->sourceRoot) && $this->sourceRoot !== null) {
            $sourceDir = (string) $this->sourceRoot->dir;
            if (\fs::$sysRoot && str_starts_with($sourceDir, (string) \fs::$sysRoot)) {
                $root = 'sys';
            } elseif (($package = \Systopic\System\Sys\Packages::rootOf($sourceDir)) !== null) {
                $root = $package;   // a package's panels (systopic/cms), settings.packageRoots
            }
        } elseif (isset($this->root) && $this->root?->name === 'cms') {
            $root = 'sys';
        }

        \client::loadJS($root, "panels/{$path}{$jsFile}", 'module', 'module');
    }

    // =========================================================================
    // Backward-compat magic properties (__get)
    // =========================================================================

    /** set by a panel that wants a headline other than the one from its name */
    protected ?string $labelOverride = null;

    /**
     * $this->name as a headline ('user_roles' → 'User Roles'), unless a panel
     * assigned its own (several settings panels do, in their constructor).
     */
    public string $label {
        get => $this->labelOverride ?? \str::name2label($this->name);
        set (string $value) { $this->labelOverride = $value; }
    }

    /** URL to this panel */
    public string $href {
        get => \http::$root . implode('/', $this->path) . (count($this->path) ? '/' : '');
    }

    /** sibling panels as an object, keyed by name */
    public object $siblings {
        get => (object) ($this->parent?->childrenByName ?? []);
    }

    /** relative dir path */
    public string $dirpath {
        get => $this->parent !== null
            ? $this->parent->dirpath . $this->folder . '/'
            : '/';
    }

    /**
     * A child panel by its name, or null if this panel has no such child —
     * which is also the case while the children have not been built yet.
     *
     * Replaces the last remaining use of __get. Child names come from the
     * folders on disk, so they can never be declared properties; reading them
     * as `$panel->pages` looked like a property but silently returned null for
     * every typo. There is no magic left on PanelNode: an undeclared name is
     * now an ordinary undefined property.
     */
    public function child(string $name): ?PanelNode
    {
        return $this->childrenByName[$name] ?? null;
    }

    // =========================================================================
    // Storage helpers
    // =========================================================================

    public function storeVarFile(string $name, string $content): int|false
    {
        $path = (string) \fs::$root . 'var/modules/' . $this->getPath();
        if (!\fs::isDir($path)) {
            \fs::fsMkdir($path, 0777, true);
        }
        return \fs::file_put_contents($path . $name, $content);
    }

    // =========================================================================
    // Debug
    // =========================================================================

    public function debug(): array
    {
        $children = [];
        foreach ($this->childrenByName as $name => $child) {
            $children[$name] = $child->debug();
        }
        return [$this->name => $children];
    }

    public function debugInfo(): array
    {
        return [
            'name'          => $this->name,
            'path'          => implode('/', $this->path) . '/',
            'realPath'      => implode('/', $this->realPath) . '/',
            'folder'        => $this->folder,
            'level'         => $this->level,
            'class'         => get_class($this),
            'hasOwnClass'   => $this->hasOwnClass ? 'TRUE' : 'FALSE',
            'sourceRoot'    => $this->sourceRoot !== null ? (string) $this->sourceRoot->dir : '?',
            'childSelected' => $this->childSelected?->name ?? '[]',
            'children'      => implode(', ', array_keys($this->childrenByName)) ?: '[]',
            'bequests'      => $this->bequests ? 'TRUE' : 'FALSE',
            'jsClass'       => $this->jsClass ?? '[]',
            'action'        => $this->action ?? '[]',
            'viewsToUpdate' => implode(', ', $this->viewsToBeUpdated) ?: '[]',
        ];
    }
}
