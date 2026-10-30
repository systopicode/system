<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Controlmap;

use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;

/**
 * cms/controlmap — who may build which panel and run which of its actions.
 *
 * `discover/` reads every panel and action off the folders and the code
 * (`Controlmap\Discover`), nothing stored. The maps and the configs the
 * running system reads come next (`.cursor/docs/controlmap.md`).
 */
class Panel extends CmsPanel {

	public $showSubnavi = FALSE;
	public $icon = 'fa-duotone fa-regular fa-sitemap';
	public ?int $naviOrder = 45;
	public bool $buildChildPanels = true;

	function buildGranted() {
		return \Systopic\System\Auth\Session::isSuperuser();
	}

	function onLoad() {
		// only while this panel is on screen (see cms/users)
		if ($this->inPath()) {
			$this->updateView('pylon');
		}
		$this->rememberOrOpenChild();
	}

	function getDefaultState() {
		$defaultState = parent::getDefaultState();
		$defaultState->openChild = NULL;
		return $defaultState;
	}
}
