<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\User;

use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;
use Systopic\System\Queries\UserAdmin\UserAdmin;
use Systopic\System\Tables\Roles\Model as Role;
use Systopic\System\Tables\Users\Model as User;
use Systopic\System\Queries\UserLookup\UserLookup;

/**
 * cms/user — the logged-in user's own profile, password and adopted roles.
 *
 * The row is read and written through the ORM; login, logout and the adopted
 * ("fake") roles are session matters of the `\user` service.
 */
class Panel extends CmsPanel {

	/** The logged-in user's row — NULL for the anonymous or recovery user. */
	public ?User $me {
		get => UserLookup::byId($this->scope, (int) \Systopic\System\Auth\Session::id());
	}

	/**
	 * Initials and gravatar address for the signet, which is on every CMS
	 * page: from the session service, which has the user loaded anyway —
	 * reading the row here would cost a query per request.
	 */
	public string $initials {
		// No `??`: it asks __isset() first, and the legacy record does not
		// count a computed value like `initials` as set.
		get => (string) \Systopic\System\Auth\Session::current()->initials;
	}

	public ?string $email {
		get => \Systopic\System\Auth\Session::current()->email;
	}

	/**
	 * The roles the user may adopt for a while, to see the CMS as that role
	 * does: every role for a superuser, else their own.
	 *
	 * @var list<Role>
	 */
	public array $adoptableRoles {
		get {
			$admin = UserAdmin::of($this->scope);
			if (\Systopic\System\Auth\Session::isSuperuser(TRUE)) {
				return array_values($admin->roles);
			}
			return $this->me ? $admin->rolesOf($this->me) : [];
		}
	}

	/** @var list<string> names of the roles adopted right now */
	public array $fakeRoles {
		get => (array) (\Systopic\System\Auth\Session::current()->state->fakeRoles ?? []);
	}

	function buildGranted() {
		return TRUE;
	}

	function getNaviItem(): ?\htmlElement {
		return null;
	}

	function getDefaultState() {
		$state = parent::getDefaultState();
		$state->hijackerId = NULL;
		return $state;
	}

	function toNavi() {
		$fakeRole = count($this->fakeRoles);
		return implode([
					\html::create('li.menu_item' . ($this->inPath() ? '.selected' : ''))->doc('menu_item')
					->append('a.ajax')->href($this->href)->append('i.far.fa-user')->class($fakeRole ? 'fakeRole' : NULL)
					->end(),
					\html::create('li.menu_item' . ($this->inPath() ? '.selected' : ''))
					->append('a.ajax')->href($this->href . 'logout')->append('i.far.fa-sign-out')
					->end()
				])
		;
	}

	/**
	 * The gravatar of an address, or FALSE if it has none. Asks gravatar.com
	 * (a HEAD request), so a template calls it once.
	 */
	static function get_gravatar($email, $s = 40, $d = '404', $r = 'g') {
		$url = 'https://www.gravatar.com/avatar/';
		$url .= md5(strtolower(trim($email ?? '')));
		$url .= "?s=$s&d=$d&r=$r";
		$response = @get_headers($url);
		if ($response && str_contains($response[0], '200')) {
			return $url;
		}
		return FALSE;
	}
}
