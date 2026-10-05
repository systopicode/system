<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Tools\Polyfill\Ncss;

use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;

class Panel extends CmsPanel {
	function onLoadThisClass() {
		if (!$this->inPath()) {
			return;
		}
		
	}

}
