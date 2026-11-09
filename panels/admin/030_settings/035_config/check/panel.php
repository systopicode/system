<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\Settings\Config\Check;

use Systopic\System\Panels\PanelNode;

/**
 * Check: what to look at in config.json and config.local.json, and what can
 * be done about it (EnvironmentForm::checks()). Opened from the header.
 */
class Panel extends PanelNode
{
    public bool $inNavi = false;

    public function __construct($name, $parent = null)
    {
        parent::__construct($name, $parent);
        $this->label = 'Check';
    }
}
