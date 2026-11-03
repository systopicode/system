<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\Legal;

use Systopic\System\Panels\PanelNode;

/**
 * admin/legal/?page=… - the texts of the login footer as a lightbox over the
 * dashboard: signed in, admin/auth/ sends back to admin/.
 */
class Panel extends PanelNode {

	public bool $inNavi = false;

	/** The auth templates read it (releases.tpl.php). */
	public array $releases {
		get => \Systopic\System\Sys\Release::notes();
	}
}
