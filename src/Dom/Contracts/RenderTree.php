<?php

declare(strict_types=1);

namespace Systopic\System\Dom\Contracts;

/**
 * The tree a DomLayer's owning node can be looked up in.
 *
 * Only needed on the diff path: a layer restored from the session has lost
 * its object references (see DomLayer::__sleep) and carries just its
 * getLayerPath() — findByLayerPath() turns that back into a live node so
 * DomLayer::checkUpdate() can ask it for pending view updates.
 */
interface RenderTree
{
    /**
     * @param array<int, string|int> $path As produced by RenderNode::getLayerPath().
     */
    public function findByLayerPath(array $path): ?RenderNode;
}
