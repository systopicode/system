<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Settings\Theme;

use Systopic\System\Panels\Root\Cms\Settings\Panel as SettingsPanel;

/**
 * The four CMS colours. Superusers only. Template settings, tags and media
 * stay in the CMS package.
 */
class Panel extends SettingsPanel
{
    public $icon = 'fa-regular fa-swatchbook';

    public bool $buildChildPanels = false;

    public function __construct($name, $parent = null)
    {
        parent::__construct($name, $parent);
        $this->label = 'Theme';
    }

    function buildGranted()
    {
        return \Systopic\System\Auth\Session::isSuperuser();
    }

    function onLoad() {}

    function onLoadThisClass() {}
}
