<?php

/**
 * cms/controlmap/discover — the actions. The tree is the parent's pylon,
 * its width and its folds the parent's state.
 *
 * @var \Systopic\System\Panels\Root\Cms\Controlmap\Discover\Panel $this
 */

switch ($this->action) {

	case 'updatePylonWidth':   // the pylon was pulled (cms/panel.js draginitPylonwidth)
		$width = (int) http::put('width');
		if ($width >= 200) {
			$this->parent->state->treeWidth = min($width, 1200);
		}
		break;

	case 'fold':               // the chevron of a node in the tree
		$this->parent->toggleFold(trim((string) http::dataset('path'), '/'));
		$this->parent->updateView('pylon');
		break;

	case 'openAllFolds':
		$this->parent->state->openFolds = $this->parent->foldablePaths();
		$this->parent->updateView('pylon');
		break;

	case 'closeAllFolds':
		$this->parent->state->openFolds = [];
		$this->parent->updateView('pylon');
		break;

	case 'filterPanels':       // the field above the tree, while showing all: the tree and the table
		$this->state->filter = trim((string) http::values('filter'));
		$this->updateView('main');
		$this->parent->updateView('pylon');
		break;
}
