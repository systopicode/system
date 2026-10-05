<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Users\Permissions\Edit;

use Systopic\Db\Scope;
use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;
use Systopic\System\Queries\UserAdmin\UserAdmin;
use Systopic\System\Tables\Groups\Model as Group;
use Systopic\System\Tables\Roles\Model as Role;

/**
 * The lightbox editing one group (`?group_id=`) or one role (`?role_id=`).
 */
class Panel extends CmsPanel {

	public UserAdmin $admin {
		get => UserAdmin::of($this->scope);
	}

	public ?Group $group {
		get => $this->admin->group((int) (\http::get('group_id') ?: \http::dataset('group_id')));
	}

	public ?Role $role {
		get => $this->admin->role((int) (\http::get('role_id') ?: \http::dataset('role_id')));
	}
}
