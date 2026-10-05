<?php

declare(strict_types=1);

namespace Systopic\System\Dom\Layer;

use Systopic\System\Dom\Debug\OpenerInfo;
use Systopic\System\Dom\Layer\Slice\DomLayerSlice;
use Systopic\System\Dom\Contracts\RenderNode;
use Systopic\System\Dom\Contracts\RenderTree;

/**
 * Represents one logical render unit in the DOM layer tree.
 *
 * A layer is created whenever a template opener is called (openSelected,
 * openChildSelected, openChild, …). It holds an ordered list of DomLayerItems
 * which are either child DomLayers or DomLayerSlices (captured HTML chunks).
 *
 * Two independent axes describe a layer in a diff.
 *
 * $type — what the CLIENT must do with it. Only the current tree carries
 * it, and it is the only one the client ever sees:
 *  - 'keep'   → owner unchanged; keep the DOM, walk into the children
 *  - 'update' → same owner, but updateView() asked for a re-render
 *  - 'new'    → a different owner, or none; build from the shipped html
 *
 * $refState — what happened TO a layer of the PREVIOUS render. Set on the
 * reference tree, which is debug-only: never exported, never stored.
 *  - 'kept'     → this dom is still in the browser
 *  - 'replaced' → this dom was thrown away
 *
 * A reference layer keeps the $type it had while it was the current tree,
 * so the debug view shows both at once: the colour is last request's
 * decision, the outline this request's verdict.
 *
 * No static state — all bookkeeping lives in DomLayerTree.
 */
class DomLayer implements DomLayerItem
{
    // -------------------------------------------------------------------------
    // Layer identity
    // -------------------------------------------------------------------------

    public string $method  = '';
    public string $viewName = '';

    /** Optional callback name set by the template caller. */
    public ?string $callback = null;

    /** The panel name that was requested when this layer was created. */
    public string $requestedPanelName = '';

    /**
     * Name and path of the panel whose open() succeeded for this layer.
     * Set by markOpened() right after the open call confirms.
     */
    public string $panelName = '';

    /**
     * The same node, named for a person - see RenderNode::getDebugIdentity().
     *
     * Taken once, at open time, and kept as a plain string on purpose. The
     * node itself cannot be kept: __sleep() drops $panel before the tree goes
     * into the session, so the reference tree of the NEXT request has no node
     * left to ask. A string survives that, and export() can hand it to the
     * client, which has no nodes at all.
     */
    public string $debugName = '';

    /**
     * What the debug label shows in place of a view name when there is none.
     *
     * No view means no include was called: the html was written inline, so
     * there is no template to re-render and this layer can never be updated
     * on its own. The brackets say that without a word.
     */
    public const VIEW_INLINE = '</>';
    public array  $panelPath = [];

    /** Render mode active at layer creation time. */
    public string $renderMode = '';

    public const SOURCE_PANEL = 'panel';
    public const SOURCE_PAGE  = 'page';

    /**
     * Which render model owns this layer — SOURCE_PANEL or SOURCE_PAGE.
     *
     * The two mix in one tree: the cms renders its page preview through the
     * page navigator, so a panel layer can have page layers below it. They
     * cannot be treated alike, because $panelPath means different things —
     * a folder path for a panel, a node-id chain for a page. The client
     * turns a panel path into a libnode under sys.panels; doing that with
     * '1.18.112' created a shadow tree of nodes with neither class nor data.
     *
     * Set from the OPENED node in markOpened(), with the caller as the
     * fallback so a cancelled open is attributed too.
     */
    public string $source = self::SOURCE_PANEL;

    /** What the client must do: keep | update | new. @see class docblock */
    public string $type = 'new';

    /**
     * Only on a reference layer: 'kept' | 'replaced', '' while not compared.
     *
     * Deliberately NOT $type. A reference layer keeps the type it had when
     * it was the current tree, so colour and outline answer two different
     * questions side by side. Not exported either — the client only ever
     * receives the current tree.
     */
    public string $refState = '';

    // -------------------------------------------------------------------------
    // Tree relations
    // -------------------------------------------------------------------------

    public ?DomLayer $parent    = null;
    public ?DomLayer $reference = null;

    /**
     * Back-reference to the containing layer.
     * Declared to allow safe deserialization of sessions written by older code
     * that set this property dynamically. Not used by current code.
     */
    public ?self $layer = null;

    // -------------------------------------------------------------------------
    // Module/panel references (removed before session serialisation)
    // -------------------------------------------------------------------------

    public ?RenderNode $callerPanel    = null;
    public string     $callerPanelName = '';
    public array      $callerPanelPath = [];

    /** The node whose open() succeeded for this layer. Set by markOpened(). */
    public ?RenderNode $panel = null;

    // -------------------------------------------------------------------------
    // Content
    // -------------------------------------------------------------------------

    /** @var DomLayerItem[] */
    public array $items = [];

    /** Iteration pointer used by getNextSublayer() during diff rendering. */
    private ?int $activeSublayerIndex = null;

    // -------------------------------------------------------------------------
    // Debug
    // -------------------------------------------------------------------------

    public string $cwdOnCreate  = '';
    public ?float $createTime   = null;
    public ?float $closeTime    = null;

    /**
     * User-code call site that triggered create() — populated by DomLayerTree
     * via Dom\Debug\OpenerInfo::capture() when DEBUGRENDER is on.
     *
     * @var array{path:string,file:string,line:int,function:string,class:string}|null
     */
    public ?array $opener = null;

    /**
     * Template that was opened for this layer (e.g. lightbox.tpl.php), when
     * result is true and a view template exists. Same schema as $opener so
     * OpenerInfo::renderHtml() / domLayersCaller.js can open it in syscoder.
     *
     * @var array{path:string,file:string,line:int,function:string,class:string}|null
     */
    public ?array $template = null;

    /**
     * Mirrors the legacy `if (module::openXX()) { … }` return value:
     *  - true   the panel/view exists, access was granted, the layer was opened
     *           (whether the template produced any HTML is irrelevant — leaf
     *           templates with empty output still count as opened)
     *  - false  open was denied/cancelled — in this case no DomLayer is
     *           created at all, so callers never see this state on a tree node
     *
     * Set by markOpened().
     */
    public bool   $result       = false;

    /**
     * Why the open was cancelled — set by the navigator's cancel() path.
     * Null on layers that opened successfully. Exported so a diff response
     * can be read without re-running the request; the legacy routeLayer had
     * the same field and it is the fastest way to answer "why is this slot
     * a placeholder?".
     */
    public ?string $reason = null;

    // =========================================================================
    // DomLayerItem
    // =========================================================================

    public function isLayer(): bool
    {
        return true;
    }

    // =========================================================================
    // Item management (called by DomLayerTree)
    // =========================================================================

    /**
     * Records that the open() that produced this layer succeeded and stores
     * the panel whose content the layer holds.
     *
     * Idempotent — only the first call wins so that nested open/close cycles
     * never overwrite the original ownership.
     */
    public function markOpened(RenderNode $panel): void
    {
        if ($this->panel !== null) {
            return;
        }
        $this->result    = true;
        $this->panel     = $panel;
        $this->panelName = $panel->getLayerIdentity();
        $this->debugName = $panel->getDebugIdentity();
        $this->panelPath = $panel->getLayerPath();
        // together with panelPath: both describe the OPENED node, and the
        // client needs them to agree to know how to read the path
        $this->source    = $panel->getRenderSource();
    }

    /**
     * Records the view template path for debug hover (below "result").
     * Prefer an absolute/internal path; falls back to the node's view base
     * path + viewName.
     */
    public function setTemplateFile(?string $file, ?RenderNode $panel = null): void
    {
        if (!(defined('DEBUGRENDER') && \DEBUGRENDER)) {
            return;
        }

        $base     = $panel?->getViewBasePath() ?? '';
        $resolved = null;
        if (is_string($file) && $file !== '') {
            $resolved = $file;
            if (!\fs::isFile($resolved) && $base !== '' && $this->viewName !== '') {
                $candidate = rtrim($base, '/') . '/' . $this->viewName . '.tpl.php';
                if (\fs::isFile($candidate)) {
                    $resolved = $candidate;
                }
            }
        } elseif ($base !== '' && $this->viewName !== '') {
            $candidate = rtrim($base, '/') . '/' . $this->viewName . '.tpl.php';
            if (\fs::isFile($candidate)) {
                $resolved = $candidate;
            }
        }

        $this->template = OpenerInfo::fromFile($resolved);
    }

    /** Appends a child item (slice or sub-layer) to this layer. */
    public function add(DomLayerItem $item): void
    {
        $this->items[] = $item;

        if ($item instanceof DomLayerSlice) {
            $item->layer = $this;
        } elseif ($item instanceof self) {
            $item->parent = $this;
        }
    }

    /**
     * Closes the last open slice in this layer by injecting the current OB
     * content. Called by DomLayerTree before adding a child layer or closing
     * this layer.
     */
    public function closeLastItem(string $capturedHtml): void
    {
        if ($this->items === []) {
            return;
        }

        $last = end($this->items);
        if (!($last instanceof DomLayerSlice)) {
            return;
        }

        if (strlen($capturedHtml) === 0) {
            array_pop($this->items); // discard empty slice
        } else {
            $last->close($capturedHtml);
        }
    }

    // =========================================================================
    // Diff rendering helpers
    // =========================================================================

    /**
     * Returns the next child DomLayer for sequential diff traversal.
     * Maintains an internal pointer; returns null when exhausted.
     */
    public function getNextSublayer(): ?self
    {
        $count = count($this->items);
        if ($this->activeSublayerIndex === null) {
            if ($count === 0) {
                return null;
            }
            $this->activeSublayerIndex = 0;
        }

        for ($i = $this->activeSublayerIndex; $i < $count; $i++) {
            if ($this->items[$i]->isLayer()) {
                $this->activeSublayerIndex = $i + 1;
                /** @var self $layer */
                $layer = $this->items[$i];
                return $layer;
            }
        }

        return null;
    }

    /**
     * Determines whether this layer needs to be re-rendered.
     * A 'new' layer always does; a 'keep' only when the owning panel has
     * requested a view update.
     */
    public function checkUpdate(RenderTree $tree): bool
    {
        if ($this->type === 'new') {
            return true;
        }

        $panel = $this->resolvePanel($tree);
        if ($panel !== null && in_array($this->viewName, $panel->getViewsToBeUpdated(), true)) {
            return true;
        }

        return false;
    }

    /**
     * Resolves the owning node from the tree when $this->panel is not set —
     * which is the case for every layer that came back from the session,
     * since __sleep() drops object references.
     *
     * The actual walk is the tree's business (folder path for panels, node-id
     * chain for pages), so it lives behind RenderTree::findByLayerPath().
     */
    private function resolvePanel(RenderTree $tree): ?RenderNode
    {
        if ($this->panel !== null) {
            return $this->panel;
        }

        if (empty($this->panelPath)) {
            return null;
        }

        return $tree->findByLayerPath($this->panelPath);
    }

    // =========================================================================
    // Type propagation (used when a module changes between requests)
    // =========================================================================

    /**
     * Marks a reference layer and everything below it. Recursive because a
     * slot whose content is rebuilt takes its whole subtree with it — the
     * client drops the lot and builds again from the shipped html.
     */
    public function setRefStateRec(string $state): void
    {
        $this->refState = $state;
        foreach ($this->items as $item) {
            if ($item->isLayer()) {
                /** @var self $item */
                $item->setRefStateRec($state);
            }
        }
    }

    // =========================================================================
    // Addressing
    // =========================================================================

    public function level(): int
    {
        return $this->parent !== null ? $this->parent->level() + 1 : 0;
    }

    /**
     * How this layer reads in the debug tree: 'kontakt:main', 'cms:body'.
     *
     * The same string the client builds in core/domLayer.js layer.label() -
     * the two trees are drawn from different data and have to agree on how a
     * layer is named.
     */
    public function debugLabel(): string
    {
        return ($this->debugName ?: $this->panelName) . ':' . $this->debugView();
    }

    // =========================================================================
    // Export (sent to JS client)
    // =========================================================================

    public function export(): object
    {
        $slices = [];
        $layers = [];

        foreach ($this->items as $item) {
            if ($item->isLayer()) {
                /** @var self $item */
                $layers[] = $item->export();
                $slices[] = "\n<div class=domLayerPlaceholder></div>\n";
            } else {
                $slices[] = (string) $item;
            }
        }

        $panelPathStr = implode('.', $this->panelPath);

        return (object) [
            'data' => (object) [
                'method'      => $this->method,
                'result'      => $this->result,
                'panelName'   => $this->panelName,
                'debugName'   => $this->debugName,
                'panelPath'   => $panelPathStr,
                // 'panel' | 'page' — tells the client how to read panelPath,
                // see core/domLayer.js addToLibnode()
                'source'      => $this->source,
                // Legacy aliases consumed by public/js/core/domLayer.js (see
                // layer.label() and layer.addToLibnode()). Kept until the
                // client is migrated to the panel* naming.
                'moduleName'  => $this->panelName,
                'modulePath'  => $panelPathStr,
                'viewName'    => $this->viewName,
                // The view as the debug tree names it: the capture root shows
                // its method ('getDiff'), an inline layer the VIEW_INLINE
                // marker. Exported rather than rebuilt on the client, so the
                // rule lives once - the two trees used to disagree about the
                // root, 'app:getDiff' here and 'app:body' there.
                'debugView'   => $this->debugView(),
                'callback'    => $this->callback,
                'type'        => $this->type,
                'reason'      => $this->reason,
            ],
            // Failed opens (no template, hidden view, …) still occupy this
            // slot so the child index stays stable. Export empty HTML — the
            // same as a full reload, which never rendered the missing view —
            // so the client removes any previous DOM instead of leaving it.
            'html'        => $this->result ? implode('', $slices) : '',
            'childLayers' => $layers,
        ];
    }

    // =========================================================================
    // String output (document / markers mode)
    // =========================================================================

    public function __toString(): string
    {
        $indent      = str_repeat("\t>>", $this->level());
        $openMarker  = "\n<!--{$indent} openLayer {$this->panelName}::{$this->viewName}-->";
        $closeMarker = "\n<!--{$indent} closeLayer {$this->panelName}::{$this->viewName}-->";
        return $openMarker . implode('', $this->items) . $closeMarker;
    }

    // =========================================================================
    // Session serialisation — drop object references
    // =========================================================================

    /**
     * $reference points at the layer of the PREVIOUS render. Serialising it
     * would take that whole tree into the session along with this one — and
     * since that tree came out of the session with its own $reference still
     * attached, every request added another generation. Six clicks grew the
     * store from 25 KB to 575 KB.
     *
     * Nothing reads it after a round trip: it is set during the diff
     * (DomRenderer::addReference) and read once, in this class's debugInfo(),
     * on the in-memory tree. $parent stays — it points inside the same tree
     * and costs nothing.
     */
    public function __sleep(): array
    {
        $vars = get_object_vars($this);
        unset(
            $vars['panel'],
            $vars['callerPanel'],
            $vars['activeSublayerIndex'],
            $vars['reference'],
        );
        return array_keys($vars);
    }

    // =========================================================================
    // Debug
    // =========================================================================

    /**
     * Is this the layer beginLayers() / beginDiff() wraps around the whole
     * request? It owns the output buffer and every other layer sits inside
     * it - but it is not a view, and the client never receives it: only the
     * head and body BELOW the document layer have a counterpart in the
     * browser (see PagesRenderer::exportDocument()).
     */
    public function isCaptureRoot(): bool
    {
        return $this->method === 'getDocument' || $this->method === 'getDiff';
    }

    /**
     * The view for the debug label.
     *
     * The capture root is named by its method instead. Its viewName is the
     * literal 'body' that beginLayers() passes as the first create() argument
     * - which made it indistinguishable from the real body layer two levels
     * below it. The field itself stays as it is: it keys the reference map,
     * and renaming it would reach into the diff.
     */
    public function debugView(): string
    {
        if ($this->isCaptureRoot()) {
            return $this->method;
        }
        return $this->viewName !== '' ? $this->viewName : self::VIEW_INLINE;
    }

    /**
     * Cuts the project root off a path for the debug table.
     *
     * Every path in there starts with the same eighty characters, which is
     * eighty characters of nothing to read. What is left is the part that
     * differs: 'vendor/systopic/system/panels/cms/010_pages/' rather than
     * '/V/www/projects/x/vendor/systopic/system/panels/cms/010_pages/'. The
     * system package sits under the project root as well (via the vendor
     * junction), so one root covers both.
     *
     * A path outside the project is returned whole — then the full spelling
     * IS the information.
     */
    private static function shortenPath(string $path): string
    {
        $root = (string) (\fs::$projectRoot ?? '');
        if ($root !== '' && str_starts_with($path, $root)) {
            return substr($path, strlen($root)) ?: './';
        }
        return $path;
    }

    public function debugInfo(): array
    {
        $closeTime = $this->closeTime ?? \date::getUsecTimestamp();
        return [
            'opener'       => OpenerInfo::caller($this->opener),
            'caller'       => $this->callerPanelName ?: 'unknown',
            'callerPath'   => implode('/', $this->callerPanelPath) . '/',
            'method'       => $this->method,
            'view'         => $this->viewName,
            'callback'     => $this->callback,
            'result'       => $this->result ? 'TRUE' : 'FALSE',
            ...($this->reason !== null ? ['reason' => $this->reason] : []),
            ...($this->result
                ? ['template' => OpenerInfo::caller($this->template)]
                : []),
            '---'          => '---',
            'cwd on open'  => self::shortenPath($this->cwdOnCreate),
            'panel'        => $this->panelName,
            'source'       => $this->source,
            'panelPath'    => implode('/', $this->panelPath) . '/',
            // No 'address' row: it was the chain of every ancestor, which is
            // exactly what the nesting in front of you already says. The
            // reference is worth naming, though - it is the one layer you
            // cannot see from here.
            'ref'          => $this->reference?->debugLabel() ?? '[]',
            'rendermode'   => $this->renderMode,
            'type'         => $this->type,
            'requested'    => $this->requestedPanelName,
            'time'         => $this->createTime !== null
                ? round($closeTime - $this->createTime, 3)
                : '?',
        ];
    }
}
