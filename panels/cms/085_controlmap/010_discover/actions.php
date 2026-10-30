<?php

/**
 * cms/controlmap/discover — the actions. The tree is the parent's pylon,
 * its width the parent's state.
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
}
