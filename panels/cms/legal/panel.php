<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Legal;

use Systopic\System\Panels\PanelNode;

/**
 * cms/legal/?page=… - the texts of the login footer as a lightbox over the
 * dashboard: signed in, cms/auth/ sends back to cms/.
 */
class Panel extends PanelNode {

	public bool $inNavi = false;

	/** The auth templates read it (releases.tpl.php). */
	public array $releases {
		get => \Systopic\System\Sys\Release::notes();
	}
}
