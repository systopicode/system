<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Tools\Database\Audit;

use Systopic\System\Panels\PanelNode;

class Panel extends PanelNode
{
	/**
	 * The table filter is a get to this same panel. The layer identity does
	 * not change, so the client would keep the old list unless the view is
	 * marked for update.
	 */
	public function onLoadSelected(): void
	{
		if (\http::method() === 'GET') {
			$this->updateViews(['main']);
		}
	}
}
