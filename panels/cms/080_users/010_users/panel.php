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

	public $icon = 'fa-regular fa-users';

	public $showSubnavi = FALSE;

	public bool $bequests = true;

	public UserAdmin $admin {
		get => UserAdmin::of($this->scope);
	}

	/** @var list<User> in the order the list is sorted by */
	public array $users {
		get => UserOperator::sort(array_values($this->admin->users), (string) $this->state->sortBy, (bool) $this->state->sortDesc);
	}

	/** @var list<Role> the roles a user can be given — not the built-in ones */
	public array $roles {
		get => array_values($this->admin->assignableRoles());
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

	public function searchHits(\Systopic\System\Sys\Search $search): void {
		$users = \Systopic\Db\Schema\Schema::tableNameOf(User::class);
		$rows = $search->scope->connection->all(
			"SELECT user_id, user_name, user_fullname, user_email FROM $users
			 WHERE LOWER(CONCAT_WS(' ', user_name, user_fullname, user_email)) LIKE ? ORDER BY user_fullname, user_name LIMIT " . $search::PER_GROUP,
			[$search->like()],
		);
		foreach ($rows as $row) {
			$name = trim((string) $row['user_fullname']) !== '' ? (string) $row['user_fullname'] : (string) $row['user_name'];
			$search->add('Users', 'fa-user', $name, (string) $row['user_email'], $this->getHref(\http::$root) . 'edit/?user_id=' . (int) $row['user_id']);
		}
	}
}
