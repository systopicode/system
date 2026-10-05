<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Navigator;

use Systopic\System\Dom\Layer\DomLayer;
use Systopic\System\Dom\Renderer\DomRenderer;
use Systopic\System\Dom\Tree\DomLayerTree;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Panels\Tree\PanelTree;

/**
 * Drives template rendering by managing the active-panel stack and the working
 * directory changes that accompany each open/close call.
 *
 * Replaces all static navigation methods on the legacy `module` class
 * (openSelected, openChildSelected, openNextChild, openChild, openRoot,
 * openView, close, closeAll).
 *
 * The navigator maintains an internal stack of panels that have been "opened".
 * Each opener:
 *  1. Checks whether the target panel / template file exists.
 *  2. Pushes the current active panel onto the stack.
 *  3. Changes the CWD to the target panel's folder.
 *  4. Calls confirm() which registers a DomLayer and a slice.
 *
 * close() undoes the last opener: pops the stack and restores the previous CWD.
 *
 * DomRenderer and DomLayerTree are optional — when null, layer recording is
 * skipped so the navigator can also be used in render-less (action-only) phases.
 */
class PanelNavigator
{
    /** @var PanelNode[] Stack of panels that have been opened. */
    private array $panelStack = [];

    private ?PanelNode $activePanel = null;

    /**
     * Last open* attempt: method, args, target panel/file, cwd, result.
     * Populated by every opener via recordRequest() so debugInfo() can
     * report what the navigator did most recently — useful to diagnose
     * "render produced nothing" cases (e.g. body.tpl.php not found).
     *
     * @var array<string, mixed>|null
     */
    private ?array $lastRequest = null;

    public function __construct(
        private readonly PanelTree    $tree,
        private readonly DomLayerTree $layerTree,
        private readonly DomRenderer  $domRenderer,
    ) {}

    // =========================================================================
    // Active panel
    // =========================================================================

    public function setActive(PanelNode $panel): void
    {
        $this->activePanel = $panel;
    }

    public function getActive(): ?PanelNode
    {
        return $this->activePanel;
    }

    // =========================================================================
    // Openers
    // =========================================================================

    /**
     * Opens the root panel for the given view.
     *
     * The "root" for a render pass is the closest ancestor of the selected
     * panel (including itself, and including the synthetic 'app' root)
     * whose own folder contains <viewName>.tpl.php on disk — see
     * findRenderRoot(). This is how a project can place a global
     * panels/body.tpl.php at the synthetic root while another panel
     * (e.g. cms) overrides it with its own panels/cms/body.tpl.php.
     *
     * Lazily ensures the root exists (ensureRoot) then navigates to its
     * folder. Corresponds to module::openRoot().
     */
    public function openRoot(string $viewName): bool
    {
        if (!$this->tree->ensureRoot()) {
            return $this->cancel('openRoot', [$viewName], 'tree->ensureRoot() failed');
        }

        // Walk selected → … → synthetic root and pick the first ancestor
        // that ships <viewName>.tpl.php. Falls back to getMountRoot() when
        // the walk finds nothing — preserves legacy behaviour for setups
        // where absPath is set but the file existence check is supposed to
        // be skipped (e.g. AJAX diff replay).
        $root = $this->findRenderRoot($viewName) ?? $this->getMountRoot();
        if ($root === null) {
            return $this->cancel('openRoot', [$viewName], 'no render-root panel resolved');
        }
        if (!$root->viewVisible($viewName)) {
            return $this->cancel('openRoot', [$viewName], "view '{$viewName}' hidden", $root);
        }

        // Use the panel's absPath so we land in the SOURCE root of the
        // render-host panel regardless of selectedRoot pointing at a
        // different root for the selected leaf (e.g. project/panels for
        // events). Without this, openRoot('body') for /cms/events/ would
        // look up cms/body.tpl.php inside project/panels where it doesn't
        // exist.
        $rel     = $this->relativeOpenPath($root);
        $tplFile = $rel . $viewName . '.tpl.php';

        if ($viewName !== false && !\fs::isFile($tplFile)) {
            return $this->cancel('openRoot', [$viewName], 'template file not found', $root, $tplFile);
        }

        $this->createLayer('openRoot', [$viewName]);
        $this->panelStack[]  = $this->activePanel;
        $this->activePanel   = $root;
        \fs::openDir($rel);

        return $this->confirm('openRoot', [$viewName], $root, $tplFile);
    }

    /**
     * Returns a relative path (always ending with '/') from the current CWD
     * to $panel's folder, suitable for fs::openDir() / fs::isFile().
     *
     * In single-root setups this collapses to the panel's bare folder name
     * (e.g. "010_pages/"). In multi-root setups, where a panel's folder may
     * live in a different root than the current CWD (e.g. cms is at sys/,
     * but cms.events lives at projectRoot/), the path may walk up across
     * roots (e.g. "../../../../projects/.../modules/cms/010_events/").
     *
     * fs::openDir/closeDir handle the resulting close-stack correctly by
     * remembering the popped CWD segments — so even cross-root chdir round
     * trips back to the original directory on close().
     */
    private function relativeOpenPath(PanelNode $panel): string
    {
        $abs = $panel->absPath;
        if ($abs === '') {
            return $panel->folder . '/';
        }

        $cwd    = rtrim(\fs::toInternal(\getcwd()), '/') . '/';
        $target = rtrim(\fs::toInternal($abs), '/') . '/';

        if ($target === $cwd) {
            return './';
        }

        if (str_starts_with($target, $cwd)) {
            return substr($target, strlen($cwd));
        }

        $cwdParts    = array_values(array_filter(explode('/', $cwd), 'strlen'));
        $targetParts = array_values(array_filter(explode('/', $target), 'strlen'));
        $i = 0;
        $n = min(count($cwdParts), count($targetParts));
        while ($i < $n && $cwdParts[$i] === $targetParts[$i]) {
            $i++;
        }
        $up = count($cwdParts) - $i;
        $rel = str_repeat('../', $up) . implode('/', array_slice($targetParts, $i));
        return rtrim($rel, '/') . '/';
    }

    /**
     * Walks selected → parent → … → synthetic root and returns the first
     * panel whose own folder contains <viewName>.tpl.php on disk.
     *
     * This is the canonical resolver for "host templates" — templates that
     * are entry points of a render pass and are never included from another
     * template (body, optionally head). It allows a project to ship a
     * panels/body.tpl.php at the synthetic root while another panel
     * (e.g. cms) overrides it with its own panels/cms/body.tpl.php; the
     * deepest match on the ancestor chain wins.
     *
     * Returns null when no ancestor on the chain ships the template — the
     * caller (typically openRoot) should then either skip the render pass
     * or fall back to a different lookup strategy.
     */
    public function findRenderRoot(string $viewName = 'body'): ?PanelNode
    {
        $cur = $this->tree->getSelected() ?? $this->tree->getRoot();
        while ($cur !== null) {
            if ($cur->absPath !== ''
             && \fs::isFile(rtrim($cur->absPath, '/') . '/' . $viewName . '.tpl.php')) {
                return $cur;
            }
            $cur = $cur->parent;
        }
        return null;
    }

    /**
     * Returns the panel that acts as the URL "mount root" — i.e. the first
     * ancestor of the selected panel whose parent is the synthetic root.
     *
     * @deprecated Use findRenderRoot($viewName) instead. This method
     *   ignores template-file existence and always returns the first child
     *   of the synthetic root, which is wrong when the body/head/etc.
     *   actually lives one level higher (on the synthetic root itself) or
     *   when the selected panel doesn't ship the expected templates.
     *   Kept for backward-compat callers and as a fallback in openRoot().
     */
    public function getMountRoot(): ?PanelNode
    {
        $synthetic = $this->tree->getRoot();
        $selected  = $this->tree->getSelected();

        if ($selected === null || $selected === $synthetic) {
            return $synthetic;
        }

        $cur = $selected;
        while ($cur->parent !== null && $cur->parent !== $synthetic) {
            $cur = $cur->parent;
        }
        return $cur;
    }

    /**
     * Opens the selected (URL-matched) panel.
     * Corresponds to module::openSelected().
     */
    public function openSelected(string $viewName): bool
    {
        $selected = $this->tree->getSelected();
        if ($selected === null) {
            return $this->cancel('openSelected', [$viewName], 'no selected panel');
        }
        if (!$selected->viewVisible($viewName)) {
            return $this->cancel('openSelected', [$viewName], "view '{$viewName}' hidden", $selected);
        }

        $rel     = $this->relativeOpenPath($selected);
        $tplFile = $rel . $viewName . '.tpl.php';

        if ($viewName !== false && !\fs::isFile($tplFile)) {
            return $this->cancel('openSelected', [$viewName], 'template file not found', $selected, $tplFile);
        }

        $this->createLayer('openSelected', [$viewName]);
        $this->panelStack[]  = $this->activePanel;
        $this->activePanel   = $selected;
        \fs::openDir($rel);

        return $this->confirm('openSelected', [$viewName], $selected, $tplFile);
    }

    /**
     * Opens the child panel that was selected by the URL path.
     * $viewName = false skips the template existence check (used in action phase).
     * Corresponds to module::openChildSelected().
     *
     * @param string|false $viewName
     */
    public function openChildSelected(string|false $viewName): bool
    {
        if ($this->activePanel === null) {
            return $this->cancel('openChildSelected', [$viewName], 'no active panel');
        }
        if ($this->activePanel->childSelected === null) {
            return $this->cancel('openChildSelected', [$viewName], 'active panel has no childSelected', $this->activePanel);
        }

        $child   = $this->activePanel->childSelected;
        $rel     = $this->relativeOpenPath($child);
        $tplFile = $viewName !== false ? $rel . $viewName . '.tpl.php' : null;

        if ($tplFile !== null && !\fs::isFile($tplFile)) {
            return $this->cancel('openChildSelected', [$viewName], 'template file not found', $child, $tplFile);
        }

        $this->createLayer('openChildSelected', [$viewName]);
        $this->panelStack[]  = $this->activePanel;
        $this->activePanel   = $child;
        \fs::openDir($rel);

        return $this->confirm('openChildSelected', [$viewName], $child, $tplFile);
    }

    /**
     * Opens a named child panel.
     * Corresponds to module::openChild().
     */
    public function openChild(string $panelName, string $viewName): bool
    {
        if ($this->activePanel === null) {
            return $this->cancel('openChild', [$panelName, $viewName], 'no active panel');
        }
        $this->activePanel->ensureChildrenBuilt();
        if (!isset($this->activePanel->childrenByName[$panelName])) {
            return $this->cancel('openChild', [$panelName, $viewName], "child panel '{$panelName}' not found", $this->activePanel);
        }

        $child = $this->activePanel->childrenByName[$panelName];

        if (!$child->viewVisible($viewName)) {
            return $this->cancel('openChild', [$panelName, $viewName], "view '{$viewName}' hidden", $child);
        }

        $rel     = $this->relativeOpenPath($child);
        $tplFile = $rel . $viewName . '.tpl.php';

        $this->createLayer('openChild', [$panelName, $viewName]);
        $this->panelStack[]  = $this->activePanel;
        $this->activePanel   = $child;
        \fs::openDir($rel);

        return $this->confirm('openChild', [$panelName, $viewName], $child, $tplFile);
    }

    /**
     * Opens the first child panel.
     * Initialises the activeChild iterator.
     * Corresponds to module::openFirstChild().
     *
     * @param string|false $viewName
     */
    public function openFirstChild(string|false $viewName): bool
    {
        if ($this->activePanel === null) {
            return $this->cancel('openFirstChild', [$viewName], 'no active panel');
        }
        $this->activePanel->ensureChildrenBuilt();
        if (empty($this->activePanel->children)) {
            return $this->cancel('openFirstChild', [$viewName], 'active panel has no children', $this->activePanel);
        }

        $first = reset($this->activePanel->children);
        $this->activePanel->activeChild = $first;

        $rel     = $this->relativeOpenPath($first);
        $tplFile = $viewName !== false ? $rel . $viewName . '.tpl.php' : null;

        if ($tplFile !== null && !\fs::isFile($tplFile)) {
            return $this->cancel('openFirstChild', [$viewName], 'template file not found', $first, $tplFile);
        }

        $this->createLayer('openFirstChild', [$viewName]);
        $this->panelStack[]  = $this->activePanel;
        $this->activePanel   = $first;
        \fs::openDir($rel);

        return $this->confirm('openFirstChild', [$viewName], $first, $tplFile);
    }

    /**
     * Opens the next child panel in the iteration sequence.
     * Calls openFirstChild on the first iteration.
     * Corresponds to module::openNextChild().
     *
     * @param string|false $viewName
     */
    public function openNextChild(string|false $viewName): bool
    {
        if ($this->activePanel === null) {
            return $this->cancel('openNextChild', [$viewName], 'no active panel');
        }
        $this->activePanel->ensureChildrenBuilt();

        $current = $this->activePanel->activeChild;

        if ($current === null) {
            return $this->openFirstChild($viewName);
        }

        if ($current->next === null) {
            $this->activePanel->activeChild = null;
            return $this->cancel('openNextChild', [$viewName], 'reached end of children', $this->activePanel);
        }

        $next = $current->next;
        $this->activePanel->activeChild = $next;

        $rel     = $this->relativeOpenPath($next);
        $tplFile = $viewName !== false ? $rel . $viewName . '.tpl.php' : null;

        if ($tplFile !== null && !\fs::isFile($tplFile)) {
            return $this->cancel('openNextChild', [$viewName], 'template file not found', $next, $tplFile);
        }

        $this->createLayer('openNextChild', [$viewName]);
        $this->panelStack[]  = $this->activePanel;
        $this->activePanel   = $next;
        \fs::openDir($rel);

        return $this->confirm('openNextChild', [$viewName], $next, $tplFile);
    }

    /**
     * Opens a view template in the current panel's folder without switching
     * the active panel.
     * Corresponds to module::openView().
     */
    public function openView(string $viewName): bool
    {
        if ($this->activePanel === null) {
            return $this->cancel('openView', [$viewName], 'no active panel');
        }
        if (!$this->activePanel->viewVisible($viewName)) {
            return $this->cancel('openView', [$viewName], "view '{$viewName}' hidden", $this->activePanel);
        }

        $tplFile = "{$viewName}.tpl.php";
        if (!\fs::isFile($tplFile)) {
            return $this->cancel('openView', [$viewName], 'template file not found in cwd', $this->activePanel, $tplFile);
        }

        $this->createLayer('openView', [$viewName]);
        $this->panelStack[] = $this->activePanel;
        \fs::openDir(); // stay in same dir

        return $this->confirm('openView', [$viewName], $this->activePanel, $tplFile);
    }

    // =========================================================================
    // Close
    // =========================================================================

    /**
     * Closes the most recently opened panel and restores the previous state.
     * Corresponds to module::close().
     */
    public function close(): bool
    {
        if (empty($this->panelStack)) {
            $this->closeDomLayer();
            return false;
        }

        $this->closeDomLayer();
        \fs::closeDir();
        $this->activePanel = array_pop($this->panelStack);

        return true;
    }

    /**
     * Closes all open panels.
     * Corresponds to module::closeAll().
     */
    public function closeAll(): void
    {
        while ($this->close()) {
            // loop until stack is empty
        }
    }

    // =========================================================================
    // Render phase starters (delegated to DomRenderer, supply active panel)
    // =========================================================================

    /**
     * Begins full-document capture with comment markers.
     * Passes the currently active panel to DomRenderer so no dummy stub is needed.
     */
    public function beginDocument(): void
    {
        $this->domRenderer->beginDocument($this->resolveCallerPanel());
    }

    public function endDocument(): void
    {
        $this->domRenderer->endDocument();
    }

    /**
     * Begins full-document capture with layer JSON for client hydration.
     */
    public function beginLayers(): void
    {
        $this->domRenderer->beginLayers($this->resolveCallerPanel());
    }

    public function endLayers(): void
    {
        $this->domRenderer->endLayers();
    }

    /**
     * Begins diff rendering (AJAX partial update).
     */
    public function beginDiff(): void
    {
        $this->domRenderer->beginDiff($this->resolveCallerPanel());
    }

    public function endDiff(): void
    {
        $this->domRenderer->endDiff();
    }

    // =========================================================================
    // Render mode helpers (delegated to DomRenderer)
    // =========================================================================

    public function getRenderMode(): string
    {
        return $this->activePanel?->getRenderMode() ?? 'html';
    }

    // =========================================================================
    // Internal — confirm / cancel
    // =========================================================================

    /**
     * Called after a successful open. Fires onRender() if defined, then adds
     * a DomLayerSlice to the current layer. Records the request so
     * debugInfo() can report the most recent open* attempt.
     */
    private function confirm(
        string     $method,
        array      $args,
        ?PanelNode $panel = null,
        ?string    $file  = null,
    ): bool {
        $this->recordRequest($method, $args, $panel ?? $this->activePanel, $file, 'opened');

        if ($this->domRenderer->checkMode(['document', 'layers', 'diff'])) {
            // Mark the just-created layer as successfully opened. Mirrors the
            // legacy `if (module::openXX()) { … }` semantics: the layer is
            // "result=true" because the open succeeded — independent of
            // whether the template later produces sub-layers or empty html.
            $active = $this->layerTree->active();
            if ($active !== null && $this->activePanel !== null) {
                $active->markOpened($this->activePanel);
                $active->setTemplateFile($file, $this->activePanel);

                // Diff comparison happens HERE (after markOpened set the
                // panelName) — not in createLayer where the layer's
                // panelName is still empty. Without this ordering the
                // first comparison always mismatches and every layer gets
                // flagged 'replace', defeating the alias optimisation.
                //
                // Reference lookup uses the CALLER panel (top of the
                // stack), not $this->activePanel — by the time confirm()
                // runs, activePanel is already the just-opened child.
                // The previous request stored references keyed by caller
                // address (see registerReference() in createLayer).
                if ($this->domRenderer->checkMode(['diff'])) {
                    $caller = end($this->panelStack) ?: $this->activePanel;
                    $refLayer = $this->getRefLayer($method, $args, $caller);
                    $this->domRenderer->addReference($refLayer, $active, $this->tree);
                }
            }
            if (method_exists($this->activePanel, 'onRender')) {
                $viewName = $args[0] ?? '';
                $this->activePanel->onRender($viewName);
            }
            $this->layerTree->addSlice();
        }
        return true;
    }

    /**
     * Called when an opener fails. Records the failed request, and —
     * during layer-recording render modes — emits a "placeholder" layer
     * so the layer tree's structure stays stable across requests.
     *
     * The placeholder has result=false and panelName='' (markOpened is
     * intentionally NOT called). Export is empty HTML (`html: ''`), not
     * null: a full reload would also render nothing in that slot, and
     * the AJAX client must remove any previous DOM the same way.
     * Diff comparison treats it like any other layer:
     *
     *   - prev failed, new failed → both panelName=''     → alias (kept)
     *   - prev failed, new opened → panelName mismatch    → original
     *     (placeholder slot is filled with the new content; parent
     *      stays alias because the child slot is in the same position)
     *   - prev opened, new failed → panelName mismatch    → original
     *     (real content collapses back into a placeholder)
     *
     * The key invariant this preserves: every open* call — successful
     * or not — produces exactly one DomLayer in the same position.
     * That keeps the JS client's index-based child walk valid even
     * when a template conditionally fails to find a sub-template
     * (e.g. `openSelected('lightbox')` with no lightbox active yet).
     */
    private function cancel(
        string     $method,
        array      $args,
        ?string    $reason = null,
        ?PanelNode $panel  = null,
        ?string    $file   = null,
    ): bool {
        $this->recordRequest($method, $args, $panel, $file, 'cancelled', $reason);

        if ($this->domRenderer->checkMode(['document', 'layers', 'diff'])
            && $this->activePanel !== null
        ) {
            // Caller stays as $this->activePanel — no panelStack push
            // happened. createLayer registers the placeholder under the
            // caller's address so the next request's diff lookup finds
            // it (whether the next request fails or succeeds).
            $this->createLayer($method, $args);
            $active = $this->layerTree->active();

            if ($active !== null) {
                $active->reason = $reason;
                if ($this->domRenderer->checkMode(['diff'])) {
                    $refLayer = $this->getRefLayer($method, $args, $this->activePanel);
                    $this->domRenderer->addReference($refLayer, $active, $this->tree);
                }
            }

            // Close the placeholder layer immediately. close() flushes
            // current OB into the layer (which has no slice content for
            // the failed body) and resumes the parent OB.
            $this->layerTree->close();
        }

        return false;
    }

    /**
     * Captures everything debugInfo() may want to surface about the latest
     * open* attempt. Called from confirm() / cancel().
     */
    private function recordRequest(
        string     $method,
        array      $args,
        ?PanelNode $panel,
        ?string    $file,
        string     $result,
        ?string    $reason = null,
    ): void {
        $this->lastRequest = [
            'method'        => $method,
            'args'          => $args,
            'cwd'           => \fs::cwd(),
            'panel'         => $panel?->name,
            'panelPath'     => $panel !== null ? '/' . implode('/', $panel->path) : null,
            'panelAbsPath'  => $panel?->absPath,
            'file'          => $file,
            'fileExists'    => $file !== null ? \fs::isFile($file) : null,
            'result'        => $result,
            'reason'        => $reason,
            'activePanel'   => $this->activePanel?->name,
            'stackDepth'    => count($this->panelStack),
        ];
    }

    // =========================================================================
    // Debug
    // =========================================================================

    /**
     * Returns an info array describing the navigator's most recent open*
     * attempt — method, args, target panel, target template file (and
     * whether it existed), cwd at the time, success/cancel reason.
     *
     * Intended for use in `d($nav->debugInfo())` after an opener returned
     * false to figure out why the render didn't happen.
     *
     * @return array<string, mixed>
     */
    public function debugInfo(): array
    {
        return $this->lastRequest ?? [
            'method' => null,
            'note'   => 'no open* request issued yet',
        ];
    }

    /**
     * True when the navigator is currently in diff render mode.
     * Used by templates / opener code to branch on diff-vs-full renders.
     */
    public function inDiffMode(): bool
    {
        return $this->domRenderer->checkMode(['diff']);
    }

    /**
     * Creates a DomLayer before the open attempt. The layer records the
     * context (method, args, caller panel) regardless of whether the open
     * succeeds. A failed open calls cancel() which closes the layer.
     *
     * Diff-mode reference comparison is NOT done here — it runs in
     * confirm() after markOpened() has set the layer's panelName.
     */
    private function createLayer(string $method, array $args): void
    {
        if ($this->domRenderer->checkMode(['document', 'layers', 'diff'])
            && $this->activePanel !== null
        ) {
            $layer = $this->layerTree->create(
                $method,
                $args,
                $this->activePanel,
                $this->domRenderer->mode(),
            );

            // Register this layer in the reference map so the NEXT request's
            // diff lookup can find it by parent panel address + view name.
            if (isset($args[0])) {
                $viewName = is_string($args[0]) ? $args[0] : '';
                if ($viewName !== '') {
                    $this->layerTree->registerReference($this->activePanel, $viewName, $layer);
                }
            }
        }
    }

    private function closeDomLayer(): void
    {
        if ($this->domRenderer->checkMode(['document', 'layers', 'diff'])) {
            $this->layerTree->close();
        }
    }

    /**
     * Looks up the reference layer for the current opener from the previous
     * request's reference map (used in diff mode).
     *
     * $caller is the panel that was active BEFORE the current open* call
     * (= the parent panel for the layer about to be diffed). Reference
     * map keys were registered under the caller's address so lookups
     * here must use the same panel.
     */
    private function getRefLayer(string $method, array $args, ?PanelNode $caller = null): ?DomLayer
    {
        $caller ??= $this->activePanel;
        if ($caller === null) {
            return null;
        }
        $viewName = is_string($args[0] ?? null) ? ($args[0] ?? '') : '';
        return $this->layerTree->getReference($caller, $viewName);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Returns the relative up-directory prefix needed to navigate from the
     * current active panel's folder back to the process root CWD.
     */
    private function updir(): string
    {
        $level = $this->activePanel?->level ?? 0;
        return str_repeat('../', $level);
    }

    /**
     * Returns the panel's realPath relative to the process root CWD (which is
     * the rootFolder). Strips the rootFolder's own realPath prefix so the
     * result can be used directly as a filesystem path from the open CWD.
     *
     * Example: rootFolder->realPath = ['cms'], panel->realPath = ['cms', '010_pages']
     * => returns ['010_pages'] (path inside the cms/ root).
     */
    private function relativeRealPath(PanelNode $panel): array
    {
        $rootRealPath = $this->tree->getRoot()?->realPath ?? [];
        return array_slice($panel->realPath, count($rootRealPath));
    }

    /**
     * Returns the active panel, or a minimal fallback when none is set yet.
     * Used by beginXxx() to avoid passing a null or a static dummy.
     */
    private function resolveCallerPanel(): PanelNode
    {
        return $this->activePanel ?? new PanelNode('__root__');
    }
}
