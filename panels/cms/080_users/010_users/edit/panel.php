<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Users\Users\Edit;

use Systopic\Db\Scope;
use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;
use Systopic\System\Queries\UserAdmin\UserAdmin;
use Systopic\System\Tables\Roles\Model as Role;
use Systopic\System\Tables\Users\Model as User;

/**
 * The lightbox editing one user (`?user_id=`) or one role (`?role_id=`).
 *
 * The inputs carry no id of their own; the id comes from the query string
 * the lightbox was opened with, or from `data-role_id` on the role inputs.
 */
class Panel extends CmsPanel {

	public UserAdmin $admin {
		get => UserAdmin::of($this->scope);
	}

	public ?User $user {
		get => $this->admin->user((int) (\http::get('user_id') ?: \http::dataset('user_id')));
	}

	public ?Role $role {
		get => $this->admin->role((int) (\http::get('role_id') ?: \http::dataset('role_id')));
	}

	/** Who created the user being edited — may since have been deleted. */
	public ?User $createdBy {
		get => $this->admin->user($this->user?->createdBy);
	}

	/** Mail the user a fresh activation link; the key must be saved alongside. */
	function mailActivation(User $user) {
		$active = \Systopic\System\Auth\Session::current();
		return $this->sendUserEmail('emailInvitation.tpl.php', 'YOUR INVITATION FROM ' . strtoupper((string) $active->name), $user);
	}
}
