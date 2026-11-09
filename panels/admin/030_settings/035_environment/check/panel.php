<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\Settings\Environment\Check;

use Systopic\System\Panels\PanelNode;

/**
 * Check: what to look at in one config file, and what can be done about it
 * (EnvironmentForm::checks()). Opened from the header with `?file=`; the
 * file stays in the state, the calls of the lightbox name other files.
 */
class Panel extends PanelNode
{
    public bool $inNavi = false;

    public function __construct($name, $parent = null)
    {
        parent::__construct($name, $parent);
        $this->label = 'Check';
    }

    public function getDefaultState()
    {
        return (object) ['file' => ''];
    }

    public function onLoad()
    {
        if (\http::get('file') !== null) {
            $this->state->file = basename((string) \http::get('file'));
        }
    }
}
