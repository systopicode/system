<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\Settings\Config;

use Systopic\System\Panels\Root\Admin\Settings\Panel as SettingsPanel;
use Systopic\System\Settings\EnvironmentForm;

/**
 * The configuration of one package, from its definition: config.local.json
 * of this installation, config.json of the project. Not in the menu itself:
 * the settings menu lists it in the area of every package (pylon.tpl.php),
 * `?package=` says which one.
 */
class Panel extends SettingsPanel
{
    public $icon = 'cmsIcon cmsIcon-sliders';

    public bool $buildChildPanels = false;

    public bool $inNavi = false;

    public function __construct($name, $parent = null)
    {
        parent::__construct($name, $parent);
        $this->label = 'Configuration';
    }

    /** package: the one shown, open: the blocks open (fold keys), file: the config picked in the header */
    function getDefaultState()
    {
        return (object) ((array) parent::getDefaultState() + ['package' => 'system', 'open' => [], 'file' => '']);
    }

    function onLoad()
    {
        $asked = \http::get('package');
        if ($asked !== null && in_array((string) $asked, EnvironmentForm::packages(), true)) {
            $this->state->package = (string) $asked;
        }
        if (!in_array((string) ($this->state->package ?? ''), EnvironmentForm::packages(), true)) {
            $this->state->package = EnvironmentForm::packages()[0] ?? 'system';
        }
        // the header says which package
        $this->label = EnvironmentForm::packageTitle((string) $this->state->package);
    }

    function onLoadThisClass() {}
}
