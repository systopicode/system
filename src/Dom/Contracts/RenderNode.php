<?php

declare(strict_types=1);

namespace Systopic\System\Dom\Contracts;

/**
 * A node that can own a DomLayer.
 *
 * The DomLayer stack is render-model agnostic: it records opens, captures
 * output buffers and compares layers against the previous request. What it
 * needs from the owning node is only identity and addressing — which panel
 * (folder tree) or page (DB tree) sits in this slot.
 *
 * Implemented by:
 *  - Panels\PanelNode — identity is the panel name, path is the URL path
 *  - Pages\Route\RouteNode   — identity is "id=…&lang=…&tpl=…", path is the node-id chain
 */
interface RenderNode
{
    /**
     * Key under which layers opened BY this node are registered in the
     * reference map, so the next request can find them again.
     *
     * Must be stable across requests for the same logical slot.
     */
    public function getAddress(): string;

    /**
     * The value DomRenderer::addReference() compares between the new layer
     * and its reference layer. Equal identity → 'keep' (client keeps the
     * DOM); different → 'new' (client re-renders the subtree).
     *
     * The view name is NOT part of this — it is already the reference-map key.
     */
    public function getLayerIdentity(): string;

    /**
     * Path segments stored on the layer so the owning node can be resolved
     * again after the layer came back from the session (where object
     * references are dropped). Consumed by RenderTree::findByLayerPath().
     *
     * @return array<int, string|int>
     */
    public function getLayerPath(): array;

    /**
     * How this node names itself in the domLayers debug panel - the human
     * counterpart to getLayerIdentity(), which is built for the diff and
     * reads like a query string. The view is NOT part of it; the layer
     * appends its own.
     */
    public function getDebugIdentity(): string;

    /**
     * View names this node has requested to re-render on this request
     * (updateView / updateViews). Turns a 'keep' into an 'update'.
     *
     * @return string[]
     */
    public function getViewsToBeUpdated(): array;

    /**
     * Absolute folder this node's view templates are resolved against.
     * Debug only (DomLayer::setTemplateFile); '' when not applicable.
     */
    public function getViewBasePath(): string;

    /**
     * Which render model this node belongs to — DomLayer::SOURCE_PANEL or
     * SOURCE_PAGE.
     *
     * The two mix: the cms renders a page preview with the page navigator
     * (panels/cms/010_pages/main.tpl.php), so panel layers and page layers
     * end up in one tree. They are not interchangeable — getLayerPath() is a
     * folder path for one and a node-id chain for the other, and the client
     * resolves a panel path to a libnode under sys.panels. Without this the
     * page half produced sys.panels['1']['18']… — nodes with neither a class
     * nor data, which is what panelTree.build() warns about.
     */
    public function getRenderSource(): string;
}
