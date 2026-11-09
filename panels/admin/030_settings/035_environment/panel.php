<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\Settings\Environment;

use Systopic\System\Panels\Root\Admin\Settings\Panel as SettingsPanel;

/**
 * The instance config files in the project's config folder.
 */
class Panel extends SettingsPanel
{
    public $icon = 'cmsIcon cmsIcon-server';

    public bool $buildChildPanels = false;

    public function __construct($name, $parent = null)
    {
        parent::__construct($name, $parent);
        $this->label = 'Environment';
    }

    /** open: the main sections open (fold keys), file: the config picked in the header */
    function getDefaultState()
    {
        return (object) ((array) parent::getDefaultState() + ['open' => [], 'file' => '']);
    }

    function onLoad() {}

    function onLoadThisClass() {}
}
