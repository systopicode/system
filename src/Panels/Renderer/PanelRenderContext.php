<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Renderer;

use Systopic\System\Panels\PanelNode;
use Systopic\System\Panels\Navigator\PanelNavigator;
use Systopic\System\Panels\Tree\PanelTree;

/**
 * Optional context object for templates that need more than just $panel.
 *
 * Pass $ctx to a template when it needs to drive navigation (openNextChild,
 * openChild, etc.) or query the tree state. Most templates only need $panel
 * and $state, so this object is optional.
 */
readonly class PanelRenderContext
{
    public function __construct(
        public PanelNode      $panel,
        public PanelNavigator $navigator,
        public PanelTree      $tree,
    ) {}
}
