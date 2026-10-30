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
	 * Opened from outside (ActionGuard): the link of an activation or reset
	 * mail (the key authorises it), and the forms and legal pages a site
	 * links to. None of them changes anything by itself.
	 */
	public array $entryActions = ['activate', 'reset', 'login', 'register', 'resetPassword', 'license', 'imprint', 'privacy', 'releases'];

	/**
	 * Which form `main.tpl.php` shows: login, register, resetPassword,
	 * resetCode, activationCode, setPassword. Actions set it.
	 */
	public string $form = 'login';

	/** '' follows the config. choose is the method list. */
	public function getDefaultState() {
		return ['loginMethod' => ''];
	}

	/** '' follows the config. A code in the address opens the invitation view. */
	public function loginMethod(): string {
		$chosen = (string) ($this->state->loginMethod ?? '');
		$code = preg_replace('~[^0-9A-Za-z\-]~', '', (string) \http::get('code', ''));
		$get = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') && !isset($_SERVER['HTTP_X_SCREEN_POLL']);
		$opening = $code !== '' && $get;
		$action = (string) $this->action;
		$fresh = $get && ($action === '' || $action === 'login');
		if ((string) \http::get('pair') === 'screen' && $fresh) {
			$this->state->loginMethod = 'device';
			return 'device';
		}
		if ($opening) {
			$this->state->loginMethod = 'code';
			return 'code';
		}
		// The address is the history. Back returns to the previous method.
		$asked = (string) \http::get('method', '');
		if ($get && in_array($asked, ['choose', 'code', 'device', 'passkey', 'password'], true)) {
			$this->state->loginMethod = $asked;
			return \Systopic\System\Auth\AuthConfig::loginMethod($asked);
		}
		if ($get && (string) \http::get('choose', '') !== '') {
			$this->state->loginMethod = 'choose';
			return '';
		}
		$chosen = \Systopic\System\Auth\AuthConfig::openingChoice($chosen, $fresh, $opening);
		$this->state->loginMethod = $chosen;
		return \Systopic\System\Auth\AuthConfig::loginMethod($chosen === '' ? null : $chosen);
	}

	function onLoadThisClass() {
		// A query change stays on this panel. The diff keeps the card on
		// screen unless the view is marked, and the browser's back button
		// is one of those changes.
		$get = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') && !isset($_SERVER['HTTP_X_SCREEN_POLL']);
		$view = (string) \http::get('view', '');
		if ($get && in_array($view, ['login', 'resetPassword', 'license', 'imprint', 'privacy', 'releases'], true)) {
			$this->form = $view;
		}
		if ($get) {
			$this->updateView('main');
			$this->updateView('login');
		}
		$action = (string) $this->action;
		// selected() is still false while these hooks run: the tree reference
		// they read is installed afterwards. The path is already known.
		$path = \app::request()->modulePath;
		$atLogin = array_slice($path, -2) === ['cms', 'auth'];
		if (!$atLogin || $this->form !== 'login' || \Systopic\System\Auth\Session::active()) {
			return;
		}
		if ($action !== '' && $action !== 'login') {
			return;
		}
		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && !isset($_SERVER['HTTP_X_SCREEN_POLL'])) {
			return;
		}
		if ($this->loginMethod() !== 'device' && !isset($_SERVER['HTTP_X_SCREEN_POLL'])) {
			return;
		}
		$screen = (string) \http::get('pair') === 'screen';
		try {
			\Systopic\System\Auth\ScreenPair::handle(
				$screen ? \http::$root : \http::$root . 'cms/',
				$screen ? \Systopic\System\Tables\LoginCodes\Operator::PAIR_SCREEN : \Systopic\System\Tables\LoginCodes\Operator::PAIR_USER
			);
		} catch (\Throwable $e) {
			error_log('screen pairing: ' . $e->getMessage());
		}
	}

	public Scope $scope {
		get => Scope::default();
	}

	/** The user named by the key of an activation or reset mail. */
	public ?User $keyUser {
		get => UserLookup::byActivationKey($this->scope, (string) (\http::posted('key') ?: \http::get('key')));
	}

	function buildGranted() {
        if ((string) \app::request()->action === 'continueWithoutPasskey') return TRUE;
		if (\Systopic\System\Auth\Passkeys::setupPending()) {
			$this->form = 'passkeySetup';
			return TRUE;
		}
		if (count(\app::request()->modulePath) > 1 && \app::request()->modulePath[1] === 'auth') {
			if (\Systopic\System\Auth\Session::active()) {
				$back = \Systopic\System\Auth\Session::takeReturn();
				if (is_string($back) && $back !== '') {
					\http::redirect($back);
				}
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

	/**
	 * Hand a user to the session service: from here on they are logged in.
	 * With a device, as a login in the app has one: without it the session
	 * is in no list, and removing a device ("Meine Geräte", cms/users) never
	 * reaches it - the app shares this session.
	 */
	function signIn(User $user, string $method): void {
		\Systopic\System\Auth\Session::login($user);
		\Systopic\System\Auth\Devices::issue($user);
		\Systopic\System\Auth\LoginLog::write((string) $user->name, 'logged in', $method);
	}

	/**
	 * Into the CMS with a full load: the login's own css would stay in the
	 * document. Back to the page that sent here, if one did.
	 */
	function enterCms(int $delay = 0): void {
		\client::addCommand('redirect')
			->data(['path' => \Systopic\System\Auth\Session::takeReturn() ?? \http::$root . 'cms/', 'action' => '', 'get' => [], 'method' => 'load'])
			->delay($delay);
	}

	/** A form went wrong: say why, and let the button spin no more. */
	function refuse(string $message, ?string $form = NULL): void {
		\message::error($message);
		\client::removeClass('form.loading', 'loading');
		$this->form = $form ?? $this->form;
		$this->updateView('main');
	}

	function sendActivation(User $user) {
		return $this->sendEmail('emailActivation.tpl.php', 'Your activation key', $user);
	}

	function sendResetPassword(User $user) {
		return $this->sendEmail('emailResetPassword.tpl.php', 'Reset your password', $user);
	}

	/** @var array<string, array{date: string, new?: list<string>, fixed?: list<string>}> GUI versions, newest first */
	public array $releases {
		get => \Systopic\System\Sys\Release::notes();
	}

	public function revision(): string {
		return \Systopic\System\Sys\Release::revision();
	}

	function sendEmail($template, $subject, User $user) {
		try {
			ob_start();
			include \fs::toNative(rtrim($this->absPath, '/') . '/' . $template);
			$body = ob_get_clean();
			return (new \emailSmtp())
				->sensitive()
				->To($user->email, $user->fullname)
				->Subject($subject)
				->Body($body)
				->send();
		} catch (\Throwable $e) {
			error_log('[auth] mail to user ' . (int) $user->id . ' failed: ' . $e->getMessage());
			return false;
		}
	}
}
