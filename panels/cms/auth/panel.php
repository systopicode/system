<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Auth;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\Users\Model as User;

/**
 * cms/auth — login, registration, password reset.
 *
 * The rows are read and written through the ORM; the session itself — who is
 * logged in, their roles, their panel state — is still the `\user` service,
 * and `signIn()` is the one place that hands a user over to it.
 */
class Panel extends PanelNode {

	/**
	 * Which form `main.tpl.php` shows: login, register, resetPassword,
	 * resetCode, activationCode, setPassword. Actions set it.
	 */
	public string $form = 'login';

	public Scope $scope {
		get => Scope::default();
	}

	/** The user named by the key of an activation or reset mail. */
	public ?User $keyUser {
		get => UserLookup::byActivationKey($this->scope, (string) (\http::posted('key') ?: \http::get('key')));
	}

	function buildGranted() {
		if (count(\app::request()->modulePath) > 1 && \app::request()->modulePath[1] === 'auth') {
			if (\Systopic\System\Auth\Session::active()) {
				if (\Systopic\System\Panels\Tree\PanelTree::getInstance()?->getRoot() && \Systopic\System\Panels\Tree\PanelTree::getInstance()?->getRoot()->hasChild('pages')) {
					\http::redirect('../pages/');
				} else {
					\http::redirect('../');
				}
			}
		}
		return !\Systopic\System\Auth\Session::active();
	}

	function actionGranted($action = NULL) {
		return TRUE;
	}

	function toNavi() {
		if (!\Systopic\System\Auth\Session::known()) {
			return \html::create('li.menu_item')->doc('menu_item')
							->append('a.ajax')->href($this->href)->append('i.far.fa-user')->end()
			;
		}
	}

	/** Hand a user to the session service: from here on they are logged in. */
	function signIn(User $user): void {
		\Systopic\System\Auth\Session::login($user);
	}

	/** A form went wrong: say why, and let the button spin no more. */
	function refuse(string $message, ?string $form = NULL): void {
		\message::warning($message);
		\client::removeClass('form.loading', 'loading');
		$this->form = $form ?? $this->form;
		$this->updateView('main');
	}

	function sendActivation(User $user) {
		return $this->sendEmail('emailActivation.tpl.php', 'YOUR ACTIVATION KEY', $user);
	}

	function sendResetPassword(User $user) {
		return $this->sendEmail('emailResetPassword.tpl.php', 'RESET PASSWORD', $user);
	}

	function sendEmail($template, $subject, User $user) {
		ob_start();
		include \fs::toNative(rtrim($this->absPath, '/') . '/' . $template);
		$body = ob_get_clean();

		return (new \emailSmtp())
						->To($user->email, $user->fullname)
						->Subject($subject)
						->Body($body)
						->send()
		;
	}
}
