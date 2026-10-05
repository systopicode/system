<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Auth\Root;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Queries\UserAdmin\UserAdmin;

/**
 * cms/auth/root — the first account of a new installation. Refuses once any
 * user exists.
 */
class Panel extends PanelNode {

	public Scope $scope {
		get => Scope::default();
	}

	public bool $hasUsers {
		get => UserAdmin::of($this->scope)->users !== [];
	}
}
