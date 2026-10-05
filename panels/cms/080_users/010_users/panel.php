<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Users\Users;

use Systopic\Db\Scope;
use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;
use Systopic\System\Queries\UserAdmin\UserAdmin;
use Systopic\System\Tables\Roles\Model as Role;
use Systopic\System\Tables\Users\Model as User;
use Systopic\System\Tables\Users\Operator as UserOperator;

/**
 * cms/users/users — the user list and the role list.
 *
 * Actions address a user by `dataset.user_id` (the table row) and a role by
 * `dataset.role_id`, or `dragdata.role_id` when one is dropped on a user.
 */
class Panel extends CmsPanel {

	public $showSubnavi = FALSE;

	public bool $bequests = true;

	public UserAdmin $admin {
		get => UserAdmin::of($this->scope);
	}

	/** @var list<User> in the order the list is sorted by */
	public array $users {
		get => UserOperator::sort(array_values($this->admin->users), (string) $this->state->sortBy, (bool) $this->state->sortDesc);
	}

	/** @var list<Role> */
	public array $roles {
		get => array_values($this->admin->roles);
	}

	/** The user of the row an action came from. */
	public ?User $user {
		get => $this->admin->user((int) \http::dataset('user_id'));
	}

	/** The role an action names — dropped on a user, or the tag clicked. */
	public ?Role $role {
		get => $this->admin->role((int) (\http::dragdata('role_id') ?: \http::dataset('role_id')));
	}

	function printSubnaviItem() {
		return '';
	}

	function getDefaultState() {
		return (object) [
					'sortBy' => 'name',
					'sortDesc' => FALSE,
		];
	}
}
