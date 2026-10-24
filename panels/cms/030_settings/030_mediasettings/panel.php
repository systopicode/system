<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Settings\Mediasettings;

use Systopic\System\Panels\Root\Cms\Settings\Panel as SettingsPanel;

/**
 * media.settings.php — one table per group.
 */
class Panel extends SettingsPanel
{
    public $icon = 'fa-regular fa-image';

    public bool $buildChildPanels = false;

    public function __construct($name, $parent = null)
    {
        parent::__construct($name, $parent);
        $this->label = 'Media Settings';
    }

    function onLoad() {}

    function onLoadThisClass() {}
}
