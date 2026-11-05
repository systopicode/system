<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\User;

use Systopic\System\Panels\Root\Admin\Panel as CmsPanel;
use Systopic\System\Queries\UserAdmin\UserAdmin;
use Systopic\System\Tables\Roles\Model as Role;
use Systopic\System\Tables\Users\Model as User;
use Systopic\System\Queries\UserLookup\UserLookup;

/**
 * admin/user — the logged-in user's own profile, password and adopted roles.
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
	 * Initials for the signet, which is on every CMS page: from the session
	 * service, which has the user loaded anyway — reading the row here would
	 * cost a query per request.
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

	/** @var list<string> keys of the roles adopted right now */
	public array $fakeRoles {
		get => (array) (\Systopic\System\Auth\Session::current()->state->fakeRoles ?? []);
	}

	/**
	 * Which widget the device boxes of this request belong to: `self`, `screen`
	 * or `person`. '' when a code came in by link, then every account is offered.
	 */
	public string $authKind {
		get {
			$kind = (string) (\http::posted('kind') ?: \http::dataset('kind') ?: \http::get('kind'));
			return in_array($kind, ['self', 'screen', 'person'], true) ? $kind : '';
		}
	}

	/** The device code of this request went through: its box has nothing left to show. */
	public bool $deviceAllowed = false;

	/** The message target of the widget that asked. */
	public string $deviceMessages {
		get => in_array($this->authKind, ['screen', 'person'], true) ? 'devices' . ucfirst($this->authKind) : 'devices';
	}

	/** Where the answer to a scanned code goes. */
	public string $scanFeedback {
        get {
            $box = \http::posted('feedback') === 'allow' ? '.allowDeviceBox' : '.scanCodeBox.kind' . ucfirst($this->authKind ?: 'self');
            $instance = (string) \http::posted('scanInstance', '');
            if (preg_match('/^[a-f0-9]{16,64}$/D', $instance)) $box .= '[data-scan-instance="' . $instance . '"]';
            return $box . ':not([hidden]) [data-remote-feedback]';
        }
	}

	function getDefaultState() {
		$state = parent::getDefaultState();
		$state->hijackerId = NULL;   // set by admin/access' hijackUser, for "Back to my account"
		$state->hijackedId = NULL;
		return $state;
	}

	function getNaviItem(): ?\htmlElement {
		return null;
	}

	function toNavi() {
		$fakeRole = count($this->fakeRoles);
		return implode([
					\html::create('li.menu_item' . ($this->inPath() ? '.selected' : ''))->doc('menu_item')
					->append('a.ajax')->href($this->href)->append('i.cmsIcon.cmsIcon-user')->class($fakeRole ? 'fakeRole' : NULL)
					->end(),
					\html::create('li.menu_item' . ($this->inPath() ? '.selected' : ''))
					->append('a.ajax')->href($this->href . 'logout')->append('i.cmsIcon.cmsIcon-right-from-bracket')
					->end()
				])
		;
	}

}
