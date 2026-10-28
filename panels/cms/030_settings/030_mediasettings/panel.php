<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Settings\Mediasettings;

use Systopic\System\Panels\Root\Cms\Settings\Panel as SettingsPanel;

/**
 * Media settings — one table per group. The file is config/media.settings.json.
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

    public function searchHits(\Systopic\System\Sys\Search $search): void
    {
        \Systopic\System\Settings\ProjectForm::searchHits($search, 'media', $this->getHref(\http::$root));
    }
}
