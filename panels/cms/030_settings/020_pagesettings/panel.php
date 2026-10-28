<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Settings\Pagesettings;

use Systopic\System\Panels\Root\Cms\Settings\Panel as SettingsPanel;

/**
 * page.settings.php — one table per group.
 */
class Panel extends SettingsPanel
{
    public $icon = 'fa-regular fa-file-lines';

    public bool $buildChildPanels = false;

    public function __construct($name, $parent = null)
    {
        parent::__construct($name, $parent);
        $this->label = 'Page Settings';
    }

    function onLoad() {}

    function onLoadThisClass() {}

    public function searchHits(\Systopic\System\Sys\Search $search): void
    {
        \Systopic\System\Settings\ProjectForm::searchHits($search, 'page', $this->getHref(\http::$root));
    }
}
