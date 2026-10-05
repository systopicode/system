<?php

declare(strict_types=1);

namespace Systopic\System\Dom\Tree;

use Systopic\System\Dom\Debug\OpenerInfo;
use Systopic\System\Dom\Layer\DomLayer;
use Systopic\System\Dom\Layer\DomLayerItem;
use Systopic\System\Dom\Layer\Slice\DomLayerSlice;
use Systopic\System\Dom\Contracts\RenderNode;

/**
 * Owns the DomLayer stack and the reference map for a single request.
 *
 * Replaces all static state that previously lived in the legacy domLayer class.
 * DomRenderer creates one instance per request and injects it into
 * PanelNavigator so both can share the same tree.
 *
 * Output buffering:
 *   The tree starts an ob_start() in create() and flushes the buffer into a
 *   DomLayerSlice when a layer is opened or closed. This matches the legacy
 *   behaviour where every domLayer::create() / close() call flushed the
 *   current OB into the preceding slice.
 */
class DomLayerTree
{
    /** @var DomLayer[] Active layer stack; last element is the current layer. */
    private array $stack = [];

    /**
     * Map of panel addresses → view names → DomLayer.
     *
     * Holds the layers REGISTERED during the current request. After
     * the render phase ends this is what gets persisted to the session
     * as the next request's reference data.
     *
     * @var array<string, array<string, DomLayer>>
     */
    private array $referencesByPanelAddress = [];

    /**
     * Map of panel addresses → view names → DomLayer.
     *
     * Holds the layers LOADED from the previous request's session.
     * Read-only during this request; consulted by diff rendering to
     * find the reference layer for each newly-opened layer. Kept
     * separate from $referencesByPanelAddress so that registering a
     * new layer doesn't overwrite (and then collide with) the
     * reference being looked up for that same address.
     *
     * @var array<string, array<string, DomLayer>>
     */
    private array $referenceLookup = [];

    /**
     * Reference root layer loaded from the previous request's session data.
     * Set via setReferencesFromSession() before the render phase begins.
     */
    private ?DomLayer $referenceRoot = null;

    // =========================================================================
    // Layer lifecycle
    // =========================================================================

    /**
     * Creates a new DomLayer, pushes it onto the stack, and closes the current
     * OB into a slice on the parent layer.
     *
     * @param string     $method      The opener method name (openSelected, openChild, …)
     * @param array      $args        The arguments passed to the opener.
     * @param RenderNode $callerPanel The active node at the time of the call.
     * @param string     $renderMode  The current render mode (from DomRenderer::mode()).
     */
    public function create(
        string     $method,
        array      $args,
        RenderNode $callerPanel,
        string     $renderMode,
    ): DomLayer {
        $layer = new DomLayer();
        $layer->method          = $method;
        $layer->renderMode      = $renderMode;
        $layer->callerPanel     = $callerPanel;
        $layer->callerPanelName = $callerPanel->getLayerIdentity();
        $layer->callerPanelPath = $callerPanel->getLayerPath();
        // provisional: markOpened() overwrites it from the opened node. Only
        // a cancelled open keeps this one, and then the caller is the best
        // answer to "which render model was this slot meant for".
        $layer->source          = $callerPanel->getRenderSource();
        $layer->cwdOnCreate     = \fs::cwd();

        if (defined('DEBUG') && DEBUG()) {
            $layer->createTime = \date::getUsecTimestamp();
        }

        $layer->opener = OpenerInfo::capture();

        // Assign args to layer properties.
        if ($method === 'openChild') {
            $layer->requestedPanelName = (string) ($args[0] ?? '');
            $layer->viewName           = is_string($args[1] ?? '') ? ($args[1] ?? '') : '';
            $layer->callback           = $args[2] ?? null;
        } else {
            $layer->viewName = is_string($args[0] ?? '') ? ($args[0] ?? '') : '';
            $layer->callback = $args[1] ?? null;
        }

        // Flush current OB into a slice on the parent layer, then add this new
        // layer as the next item on the parent.
        $parent = $this->active();
        if ($parent !== null) {
            $this->flushSliceInto($parent);
            $parent->add($layer);
        }

        $this->stack[] = $layer;

        if (count($this->stack) > 100) {
            \t('DomLayerTree: max nesting exceeded (100)');
        }

        // Start a fresh OB so the template content goes into a slice.
        ob_start();

        return $layer;
    }

    /**
     * Closes the current layer: flushes remaining OB content into its last
     * slice, then adds a new empty slice to the parent for subsequent content.
     */
    public function close(): void
    {
        $layer = array_pop($this->stack);
        if ($layer === null) {
            return;
        }

        $html = ob_get_clean();
        $layer->closeLastItem($html ?? '');

        if (defined('DEBUG') && DEBUG()) {
            $layer->closeTime = \date::getUsecTimestamp();
        }

        // Resume parent OB
        ob_start();

        if ($this->active() !== null) {
            $this->addSlice();
        }
    }

    /**
     * Closes the last open slice on the root layer without popping the stack.
     * Used by DomRenderer::endDocument/endLayers/endDiff.
     *
     * Deliberately does NOT call ob_start() afterwards — the outer OB level
     * opened by DomRenderer::beginXxx() is now closed here, and it is the
     * caller's (DomRenderer::endXxx) responsibility to decide what happens next.
     */
    public function closeLast(): void
    {
        $root = reset($this->stack);
        if ($root === false) {
            return;
        }
        $html = ob_get_clean();
        $root->closeLastItem($html ?? '');
    }

    /**
     * Appends a new DomLayerSlice to the active layer.
     * The slice will be filled with content by the next closeLastItem() call.
     */
    public function addSlice(): void
    {
        $active = $this->active();
        if ($active === null) {
            return;
        }
        $slice = new DomLayerSlice();
        $slice->opener = OpenerInfo::capture();
        $active->add($slice);
    }

    // =========================================================================
    // Stack accessors
    // =========================================================================

    public function active(): ?DomLayer
    {
        return $this->stack !== [] ? end($this->stack) : null;
    }

    public function root(): ?DomLayer
    {
        return $this->stack !== [] ? reset($this->stack) : null;
    }

    public function depth(): int
    {
        return count($this->stack);
    }

    // =========================================================================
    // Reference map
    // =========================================================================

    public function registerReference(RenderNode $panel, string $viewName, DomLayer $layer): void
    {
        $address = $panel->getAddress();
        if (!isset($this->referencesByPanelAddress[$address])) {
            $this->referencesByPanelAddress[$address] = [];
        }
        $this->referencesByPanelAddress[$address][$viewName] = $layer;
    }

    /**
     * Looks up a reference layer (from the previous request) by panel
     * address + view name.
     */
    public function getReference(RenderNode $panel, string $viewName): ?DomLayer
    {
        $address = $panel->getAddress();
        return $this->referenceLookup[$address][$viewName] ?? null;
    }

    /**
     * Returns the layers registered during THIS request — the data
     * that should be persisted to the session for the next diff.
     */
    public function getReferencesByPanelAddress(): array
    {
        return $this->referencesByPanelAddress;
    }

    /**
     * Loads the previous request's layer map into the read-only
     * reference lookup table. Called by DomRenderer::beginDiff() right
     * after the session is available.
     */
    public function setReferencesFromSession(array $refs): void
    {
        $this->referenceLookup = $refs;
    }

    // =========================================================================
    // Reference root (from previous request)
    // =========================================================================

    public function setReferenceRoot(?DomLayer $root): void
    {
        $this->referenceRoot = $root;
    }

    public function getReferenceRoot(): ?DomLayer
    {
        return $this->referenceRoot;
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Flushes the current OB into a slice on $layer without starting a new OB.
     * Used before adding a child layer.
     */
    private function flushSliceInto(DomLayer $layer): void
    {
        $html = ob_get_clean() ?? '';
        $layer->closeLastItem($html);
        // The caller (create) will start a fresh ob_start() after.
    }
}
