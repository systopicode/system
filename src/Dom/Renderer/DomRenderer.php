<?php

declare(strict_types=1);

namespace Systopic\System\Dom\Renderer;

use Systopic\System\Debug\BoxTree;
use Systopic\System\Dom\Contracts\RenderNode;
use Systopic\System\Dom\Contracts\RenderTree;
use Systopic\System\Dom\Layer\DomLayer;
use Systopic\System\Dom\Tree\DomLayerTree;

/**
 * Controls the render mode and orchestrates the output buffer during rendering.
 *
 * Replaces the static domRenderer class. One instance is created per request
 * and shared with PanelNavigator via DomLayerTree.
 *
 * Render modes:
 *  - 'build'    → default; no layer recording
 *  - 'render'   → direct HTML output (plain pass-through)
 *  - 'document' → full-document capture with comment markers
 *  - 'layers'   → full-document capture with layer JSON for client-side hydration
 *  - 'diff'     → only changed layers are serialised for an AJAX response
 */
class DomRenderer
{
    /** @var string[] Mode stack; last element is the current mode. */
    private array $modeStack = ['build'];

    private array $bodyClasses = [];

    public function __construct(
        private readonly DomLayerTree       $layerTree,
        private readonly DomRendererSession $session,
    ) {}

    // =========================================================================
    // Mode management
    // =========================================================================

    public function mode(): string
    {
        return end($this->modeStack);
    }

    public function checkMode(string|array $modes): bool
    {
        $modes = is_array($modes) ? $modes : [$modes];
        return in_array($this->mode(), $modes, true);
    }

    public function setMode(string $mode): void
    {
        $active = $this->layerTree->active();
        if ($active !== null) {
            $active->renderMode = $mode;
        }
        $this->modeStack[] = $mode;
    }

    public function unsetMode(): void
    {
        array_pop($this->modeStack);
    }

    // =========================================================================
    // High-level render phase starters
    // =========================================================================

    /**
     * Activates slice output without layer recording.
     * Corresponds to domRenderer::renderStart().
     */
    public function beginRender(): void
    {
        $this->setMode('render');
    }

    /**
     * Begins full-document capture with comment markers.
     * Corresponds to domRenderer::getDocument().
     *
     * @param RenderNode $callerPanel  The root/active node at render start (provided by the navigator).
     */
    public function beginDocument(RenderNode $callerPanel): void
    {
        ob_start();
        $this->setMode('document');
        $root = $this->layerTree->create('getDocument', ['body'], $callerPanel, $this->mode());
        $root->markOpened($callerPanel);
        $this->layerTree->addSlice();
    }

    public function endDocument(): void
    {
        $this->layerTree->closeLast();
        $this->unsetMode();
    }

    /**
     * Begins full-document capture with layer data for client hydration.
     * Corresponds to domRenderer::getLayers().
     *
     * @param RenderNode $callerPanel  The root/active node at render start (provided by the navigator).
     */
    public function beginLayers(RenderNode $callerPanel): void
    {
        ob_start();
        $this->setMode('layers');
        $root = $this->layerTree->create('getDocument', ['body'], $callerPanel, $this->mode());
        $root->markOpened($callerPanel);
        $this->layerTree->addSlice();
    }

    public function endLayers(): void
    {
        $this->layerTree->closeLast();
        $this->unsetMode();
    }

    /**
     * Begins diff rendering: only changed layers are serialised.
     * Also loads the reference tree from the session for comparison.
     * Corresponds to domRenderer::getDiff().
     *
     * @param RenderNode $callerPanel  The root/active node at render start (provided by the navigator).
     */
    public function beginDiff(RenderNode $callerPanel): void
    {
        ob_start();
        $this->setMode('diff');
        $root = $this->layerTree->create('getDiff', ['body'], $callerPanel, $this->mode());
        $root->markOpened($callerPanel);
        $this->layerTree->addSlice();

        // Reload reference data from the session NOW — bootstrap.php loaded
        // it once at request start, but at that point the PHP session was
        // not yet active (sessionStartOnce() runs in app.php). Re-pulling
        // both the reference root AND the panel-address map ensures diff
        // comparisons can find prior layers by panel address.
        $this->layerTree->setReferencesFromSession($this->session->loadReferencesByAddress());

        $refRoot = $this->session->loadReferenceRoot();
        $this->layerTree->setReferenceRoot($refRoot);
        $this->addReference($refRoot);
    }

    public function endDiff(): void
    {
        $this->layerTree->closeLast();
        $this->unsetMode();
    }

    // =========================================================================
    // Reference management (diff mode)
    // =========================================================================

    /**
     * Associates a reference layer with the current (or given) active layer.
     * Corresponds to domRenderer::addReference().
     *
     * When the panel name is unchanged the layer becomes 'keep' — unless the
     * owning panel has called updateView() for this view, in which case it
     * is promoted to 'update' so the client re-renders it even though the
     * panel identity did not change.
     *
     * The reference layer is marked on its own axis ($refState), not by
     * overwriting its type — see the DomLayer class docblock.
     *
     * @param RenderTree|null $tree Required for checkUpdate() view-update
     *                              detection. Pass null to skip that check.
     */
    public function addReference(?DomLayer $refLayer, ?DomLayer $originalLayer = null, ?RenderTree $tree = null): bool
    {
        $layer = $originalLayer ?? $this->layerTree->active();
        if ($layer === null) {
            return false;
        }
        if ($refLayer === null) {
            $layer->type = 'new';
            return false;
        }

        $layer->reference = $refLayer;

        if ($layer->panelName === $refLayer->panelName) {
            // Same owner: the client keeps the dom and walks the children,
            // so the old layer survives.
            $layer->type        = 'keep';
            $refLayer->refState = 'kept';

            // Unless the panel asked for a re-render (updateView). Then the
            // client rebuilds this subtree after all, and the old one does
            // NOT survive — recursively, because rebuild() drops the whole
            // subtree and builds it again from the shipped html.
            if ($tree !== null && $layer->checkUpdate($tree)) {
                $layer->type = 'update';
                $refLayer->setRefStateRec('replaced');
            }

            return true;
        }

        // Different owner: something else stands in this slot now.
        $layer->type = 'new';
        $refLayer->setRefStateRec('replaced');
        return false;
    }

    /**
     * Persists the current layer tree to the session for the next request.
     * Corresponds to domRenderer::storeReference().
     */
    public function storeReference(): void
    {
        $this->session->storeReference($this->layerTree);
    }

    // =========================================================================
    // Body classes (passed to the JS client)
    // =========================================================================

    public function setBodyClasses(array $classes): void
    {
        $this->bodyClasses = $classes;
    }

    public function addBodyClass(string $class): void
    {
        $this->bodyClasses[] = $class;
    }

    public function getBodyClasses(): array
    {
        return $this->bodyClasses;
    }

    // =========================================================================
    // Client data export
    // =========================================================================

    /**
     * Returns the payload that will be sent to the JS client.
     * Route: 'singletons.dom.renderer'.
     */
    public function clientData_export(): object
    {
        // No fallback to the stored reference any more. It used to stand in
        // when no render phase had run, but a stored tree carries no html
        // (DomLayerSlice::__serialize) and reading it would consume the
        // entry the next diff needs. A request that rendered nothing exports
        // nothing.
        $domLayers = $this->layerTree->root();

        // No layers key at all when nothing was rendered - NOT an empty one.
        // The client merges this data into what it already holds (mergeDeep in
        // core/helper.js), and an empty ARRAY replaces the object tree that is
        // still on screen. The next response's tree would then be merged into
        // that array, which mergeDeep skips silently: the client keeps its old
        // dom and nothing happens. That is how a lightbox survived being
        // closed, as long as one state-only PUT had gone out in between.
        $data = ['bodyClasses' => $this->bodyClasses];
        if ($domLayers) {
            $data = ['layers' => $domLayers->export()] + $data;
        }

        return (object) [
            'route' => 'singletons.dom.renderer',
            'data'  => (object) $data,
        ];
    }

    public function exportJson(): array
    {
        // see clientData_export()
        $domLayers = $this->layerTree->root();

        return [
            'layers'      => $domLayers ? $domLayers->export() : [],
            'bodyClasses' => $this->bodyClasses,
            'mode'        => $this->mode(),
        ];
    }

    // =========================================================================
    // Debug
    // =========================================================================

    /**
     * The layer trees of this request as box-tree data (Debug\BoxTree):
     * the reference the diff compared against, then the one just rendered.
     *
     *   p(...$renderer->debug())->target('domLayersServer')->type('boxTree');
     *
     * @return list<array<string, mixed>> zero, one or two roots
     */
    public function debug(): array
    {
        $roots = [];

        // Only what beginDiff() already pulled in — asking the session again
        // would consume an entry the next request is waiting for.
        $refRoot = $this->layerTree->getReferenceRoot();

        if ($refRoot !== null) {
            $roots[] = $this->debugNode($refRoot);
        }
        if ($this->layerTree->root() !== null) {
            $roots[] = $this->debugNode($this->layerTree->root());
        }

        return $roots;
    }

    private function debugNode(DomLayer $layer): array
    {
        $children = [];
        foreach ($layer->items as $item) {
            /** @var DomLayer|DomLayerSlice $item */
            $children[] = $item->isLayer() ? $this->debugNode($item) : $item->debugNode();
        }

        // debugLabel() is the one place the name is built — the client draws
        // its tree from different data and has to end up with the same
        // string, see core/domLayer.js layer.label().
        return BoxTree::node($layer->debugLabel(), $layer->debugInfo(), [
            'layer',
            // colour = what the client does with the layer
            $layer->type,
            // The render model is a second, independent axis, so it gets a
            // non-colour channel — the stylesheet can frame page layers.
            $layer->source !== DomLayer::SOURCE_PANEL ? 'source-' . $layer->source : null,
            // An insertion point that stayed empty — the open was cancelled and
            // the slot exists only to keep the child index aligned (§6b). NOT a
            // type: such a layer still takes part in the diff, and its empty
            // html is what makes the client drop whatever was there before.
            !$layer->result ? 'placeholder' : null,
            // Only ever set on the reference tree: did this dom survive into
            // the current render? Drawn as an outline, so it does not compete
            // with the colour (what this layer was when it WAS the current tree).
            $layer->refState !== '' ? $layer->refState : null,
        ], $children);
    }

}
