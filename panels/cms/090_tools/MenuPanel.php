<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Tools;

use Systopic\System\Panels\PanelNode;

/**
 * A tools section whose menu opens the child that was open last,
 * or the first one.
 *
 * Children must be built during the tree pass (`buildChildPanels`), not
 * lazily in onLoad: PanelNode::setTree() runs only after build()/onLoad,
 * so ensureChildrenBuilt() would mark the panel built with an empty list
 * and the nested menu + redirect would never recover.
 */
class MenuPanel extends PanelNode
{
    public bool $buildChildPanels = true;

    public function getDefaultState()
    {
        return (object) [
            'openChild' => null,
        ];
    }

    public function onLoad()
    {
        $this->rememberOrOpenChild();
    }
}
