<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Settings;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;

class Panel extends PanelNode {

	public $icon = 'fa-regular fa-gear';

	/** The request's scope — the settings children read and write through it. */
	public Scope $scope {
		get => Scope::default();
	}

	function buildGranted() {
		return \Systopic\System\Auth\Session::hasOneRole('admin');
	}

	function onLoadThisClass() {
		// Only while this panel is the one on screen. Unconditionally it marked
		// a view on EVERY request - including every keystroke of an ajax input
		// in a sibling panel - and app.php renders the whole body as soon as a
		// single view is marked (see app.php, http::ajax() branch).
		if ($this->inPath()) {
			$this->updateView('pylon');
		}
	}

	function onLoad() {
		// child('page') is the 010_page sub-panel; it is null until the
		// children are built, which is exactly what this guard checks
		$page = $this->child('page');
		if ($page && $page->inPath() && empty($page->action)) {
			$page->updateView();
		}
	}

	function getDefaultState() {
		return (object) [
			    'id' => NULL,
			    'childSelected' => NULL,
			    'pylonWidth' => 250,
			    'openFolds' => [],
		];
	}

}
