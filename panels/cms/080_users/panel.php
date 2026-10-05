<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Users;

use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;
use Systopic\System\Tables\Roles\Model as Role;
use Systopic\System\Tables\Users\Model as User;

/**
 * cms/users — users and roles (`users/`), groups and what roles may do on them
 * (`permissions/`). Every child reads through `Queries\UserAdmin`.
 */
class Panel extends CmsPanel {

	public $showSubnavi = FALSE;
	public $icon = 'fa-regular fa-user';

	function buildGranted() {
		return \Systopic\System\Auth\Session::hasOneRole('userAdmin');
	}

	function onLoad() {
		// Only while this panel is the one on screen. Unconditionally it marked
		// a view on EVERY request - including every keystroke of an ajax input
		// in a sibling panel - and app.php renders the whole body as soon as a
		// single view is marked (see app.php, http::ajax() branch).
		if ($this->inPath()) {
			$this->updateView('pylon');
		}
	}

	function getDefaultState() {
		$defaultState = parent::getDefaultState();
		$defaultState->hijackerId = NULL;
		return $defaultState;
	}

	/**
	 * A role as a coloured tag — in the user list, the group list and the
	 * role list alike. `$action` puts a minus on it that calls that action.
	 */
	public static function roleSticker(Role $role, ?string $action = NULL): string {
		$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
		$style = $role->color ? " style='background:{$e($role->color)};border-color:{$e($role->color)}'" : '';
		$minus = $action !== NULL ? "<i class='far fa-minus-circle' data-on_click='{$e($action)}'></i>" : '';
		return "<div class='tag active' data-role_id='{$role->id}'$style>{$e($role->name)}$minus</div>";
	}

	/** A user's line for readonly fields: "Full Name (name)". */
	public static function userLabel(?User $user): string {
		if ($user === NULL) {
			return '';
		}
		return trim(($user->fullname ?? '') . ' (' . $user->name . ')');
	}
}
