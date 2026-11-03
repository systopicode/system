<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\Settings\Theme;

use Systopic\System\Panels\Root\Admin\Settings\Panel as SettingsPanel;

/**
 * The four CMS colours. Superusers only (panel.config.json: an empty list). Template settings, tags and media
 * stay in the CMS package.
 */
class Panel extends SettingsPanel
{
    public $icon = 'cmsIcon cmsIcon-swatchbook';

    public bool $buildChildPanels = false;

    public function __construct($name, $parent = null)
    {
        parent::__construct($name, $parent);
        $this->label = 'Theme';
    }

    function onLoad() {}

    function onLoadThisClass() {}
}
