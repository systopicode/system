<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\Tools;

use Systopic\System\Panels\PanelNode;

/**
 * A tools section whose menu opens the child that was open last,
 * or the first one.
 *
 * The children are built when onLoad asks for them (rememberOrOpenChild()
 * → ensureChildrenBuilt()), which works in onLoad because loader.php
 * installs the tree before build(). No `buildChildPanels` needed.
 */
class MenuPanel extends PanelNode
{
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
