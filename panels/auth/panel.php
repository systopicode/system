<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Auth;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\Users\Model as User;

/**
 * auth — login, registration, password reset.
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
    public ?string $successTarget = null;

	/** '' follows the config. choose is the method list. */
	public function getDefaultState() {
		return ['signInMethod' => ''];
	}

	/** '' follows the config. A code in the address opens the invitation view. */
	public function signInMethod(): string {
		$chosen = (string) ($this->state->signInMethod ?? '');
		$code = preg_replace('~[^0-9A-Za-z\-]~', '', (string) \http::get('code', ''));
		$get = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') && !isset($_SERVER['HTTP_X_DEVICE_CODE_POLL']);
		$opening = $code !== '' && $get;
		$action = (string) $this->action;
		$fresh = $get && ($action === '' || $action === 'login');
		if ($opening) {
			$this->state->signInMethod = 'code';
			return 'code';
		}
		// The address is the history. Back returns to the previous method.
		$asked = (string) \http::get('method', '');
		if ($get && in_array($asked, ['choose', 'code', 'device_code', 'passkey', 'password'], true)) {
			$this->state->signInMethod = $asked;
			return \Systopic\System\Auth\AuthConfig::signInMethod($asked);
		}
		if ($get && (string) \http::get('choose', '') !== '') {
			$this->state->signInMethod = 'choose';
			return '';
		}
		$chosen = \Systopic\System\Auth\AuthConfig::openingChoice($chosen, $fresh, $opening);
		$this->state->signInMethod = $chosen;
		return \Systopic\System\Auth\AuthConfig::signInMethod($chosen === '' ? null : $chosen);
	}

	function onLoadThisClass() {
		// A query change stays on this panel. The diff keeps the card on
		// screen unless the view is marked, and the browser's back button
		// is one of those changes.
		$get = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') && !isset($_SERVER['HTTP_X_DEVICE_CODE_POLL']);
		$view = (string) \http::get('view', '');
		if ($get && in_array($view, ['login', 'resetPassword', 'resetCode', 'activationCode', 'setPassword', 'license', 'imprint', 'privacy', 'releases'], true)) {
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
		$atLogin = $path === ['auth'];
        if ($atLogin && (isset($_SERVER['HTTP_X_DEVICE_CODE_POLL']) || isset($_SERVER['HTTP_X_DEVICE_CODE_RENEW']))) {
            \Systopic\System\Auth\DeviceCode::handle();
        }
		if (!$atLogin || $this->form !== 'login' || \Systopic\System\Auth\Session::active()) {
			return;
		}
		if ($action !== '' && $action !== 'login') {
			return;
		}
		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && !isset($_SERVER['HTTP_X_DEVICE_CODE_POLL'])) {
			return;
		}
		if ($this->signInMethod() !== 'device_code' && !isset($_SERVER['HTTP_X_DEVICE_CODE_POLL'])) {
			return;
		}
		try {
			\Systopic\System\Auth\DeviceCode::handle();
		} catch (\Throwable $e) {
			error_log('device code: ' . $e->getMessage());
		}
	}

	public Scope $scope {
		get => Scope::default();
	}

	/** The user named by the key of an activation or reset mail. */
	public ?User $keyUser {
		get => UserLookup::byActivationKey($this->scope, (string) (\http::posted('key') ?: \http::get('key')));
	}

	/**
	 * Everybody may open the sign-in (panel.config.json: `everyone`, and
	 * which actions a mail or another site may link to). What happens to
	 * whoever is signed in already is this panel's business, not access:
	 * sent on to where they came from, or to admin/ - unless a passkey is
	 * to be set up, or the request pairs a device (validateQr) or skips
	 * the passkey.
	 */
	function onBuild() {
		if ((\app::request()->modulePath[0] ?? '') !== 'auth') {
			return;
		}
		$action = (string) \app::request()->action;
		if (($action === 'validateQr' && \http::$method === 'PUT') || $action === 'continueWithoutPasskey') {
			return;
		}
		if (\Systopic\System\Auth\Passkeys::setupPending()) {
			$this->form = 'passkeySetup';
			return;
		}
		if (\Systopic\System\Auth\Session::active()) {
			$back = \Systopic\System\Auth\Session::takeReturn();
			\http::redirect(is_string($back) && $back !== '' ? $back : \http::$root . 'admin/');
		}
	}

	function toNavi() {
		if (!\Systopic\System\Auth\Session::known()) {
			return \html::create('li.menu_item')->doc('menu_item')
							->append('a.ajax')->href($this->href)->append('i.cmsIcon.cmsIcon-user')->end()
			;
		}
	}

	/**
	 * Hand a user to the session service: from here on they are logged in.
	 * With a device, as a login in the app has one: without it the session
	 * is in no list, and removing a device ("Meine Geräte", admin/access) never
	 * reaches it - the app shares this session.
	 */
	function signIn(User $user, string $method): void {
		\Systopic\System\Auth\Session::login($user);
		\Systopic\System\Auth\Devices::issue($user);
		\Systopic\System\Auth\SignInLog::write((string) $user->name, 'signed in', $method);
	}

	/**
	 * Into the CMS with a full load: the login's own css would stay in the
	 * document. Back to the page that sent here, if one did.
	 */
	function enterCms(float $delay = 0, ?string $target = null): void {
        $this->successTarget = $target ?? \Systopic\System\Auth\Session::takeReturn() ?? \http::$root . 'admin/';
		\client::addCommand('redirect')
			->data(['path' => $this->successTarget, 'action' => '', 'get' => [], 'method' => 'load'])
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
