<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Settings\Environment;

use Systopic\System\Panels\Root\Cms\Settings\Panel as SettingsPanel;

/**
 * The instance config files in the project's config folder.
 */
class Panel extends SettingsPanel
{
    public $icon = 'fa-regular fa-sliders';

    public bool $buildChildPanels = false;

    public function __construct($name, $parent = null)
    {
        parent::__construct($name, $parent);
        $this->label = 'Environment';
    }

    function onLoad() {}

    function onLoadThisClass() {}
}
