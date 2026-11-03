<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\Settings\Pagesettings;

use Systopic\System\Panels\Root\Admin\Settings\Panel as SettingsPanel;

/**
 * Global settings — one table per group. The file is config/global.settings.json.
 */
class Panel extends SettingsPanel
{
    public $icon = 'cmsIcon cmsIcon-globe';

    public bool $buildChildPanels = false;

    public function __construct($name, $parent = null)
    {
        parent::__construct($name, $parent);
        $this->label = 'Global Settings';
    }

    function onLoad() {}

    function onLoadThisClass() {}

    public function searchHits(\Systopic\System\Sys\Search $search): void
    {
        \Systopic\System\Settings\ProjectForm::searchHits($search, 'page', $this->getHref(\http::$root));
    }
}
