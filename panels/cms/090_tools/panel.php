<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Tools;

use Systopic\System\Panels\PanelNode;

class Panel extends PanelNode {

//		public $loadChildModules = TRUE;
	public $showSubnavi = TRUE;
	public $icon = 'fa-regular fa-wrench';

	function getDefaultState() {
		return (object) [
					'id' => NULL,
					'childSelected' => NULL,
					'pylonWidth' => 250,
					'openFolds' => [],
		];
	}

	public function buildGranted() {
		return \Systopic\System\Auth\Session::isSuperuser();
	}

	function onLoad() {
		// Only while this panel is the one on screen. Unconditionally it marked
		// a view on EVERY request - including every keystroke of an ajax input
		// in a sibling panel - and app.php renders the whole body as soon as a
		// single view is marked (see app.php, http::ajax() branch).
		if ($this->inPath()) {
			$this->updateView('pylon');
		}
	}

}
