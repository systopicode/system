<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Users\Permissions;

use Systopic\Db\Scope;
use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;
use Systopic\System\Queries\UserAdmin\UserAdmin;
use Systopic\System\Tables\Groups\Model as Group;
use Systopic\System\Tables\Roles\Model as Role;

/**
 * cms/users/permissions — content groups, and which roles may read or write
 * them; roles are dropped on a group's read or write cell.
 */
class Panel extends CmsPanel {

	public bool $bequests = true;

	public UserAdmin $admin {
		get => UserAdmin::of($this->scope);
	}

	/** @var list<Group> */
	public array $groups {
		get => array_values($this->admin->groups);
	}

	/** @var list<Role> */
	public array $roles {
		get => array_values($this->admin->roles);
	}

	/** The group of the row an action came from. */
	public ?Group $group {
		get => $this->admin->group((int) \http::dataset('group_id'));
	}

	/** The role dropped on a group, or the tag clicked. */
	public ?Role $role {
		get => $this->admin->role((int) (\http::dragdata('role_id') ?: \http::dataset('role_id')));
	}

	function printSubnaviItem() {
		return '';
	}
}
