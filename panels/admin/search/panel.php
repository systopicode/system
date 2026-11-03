<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\Search;

use Systopic\System\Panels\PanelNode;
use Systopic\System\Sys\Search;

/**
 * admin/search/ - one field for pages, media, tags, users and sections, as a
 * lightbox over whatever is open (Cmd+K, the magnifier in the menu).
 */
class Panel extends PanelNode {

	public bool $inNavi = false;

	public Search $results {
		get => Search::run((string) $this->state->query, $this->parent);
	}

	function getDefaultState() {
		return (object) ['query' => ''];
	}
}
