<?php

/** @var \Systopic\System\Panels\Root\Admin\User\Panel $this */

use Systopic\System\Auth\Devices;
use Systopic\System\Auth\Portrait;
use Systopic\System\Auth\Session;
use Systopic\System\Auth\SignInCodes;
use Systopic\System\Auth\SignInGrant;
use Systopic\System\Queries\Devices\Devices as DeviceQuery;
use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\Users\Operator as UserOperator;

if (str_starts_with((string) $this->action, 'passkey')) {
    \Systopic\System\Auth\Passkeys::respond($this->action, false);
}

switch ($this->action) {
	case 'logout':
		// signed out as a taken-on user: no way back for whoever signs in next
		$this->state->hijackerId = NULL;
		$this->state->hijackedId = NULL;
		\Systopic\System\Auth\SignInLog::write(\Systopic\System\Auth\Session::current()->name, 'signed out', \Systopic\System\Auth\SignInLog::SIGN_OUT_BUTTON);
		\Systopic\System\Auth\Session::logout();
		// A full document, so the sign-in page brings its own styles.
		// An ajax answer only swaps the markup and leaves the auth sheet out.
        if (!\http::ajax()) \http::redirect(\http::$root . 'auth/logout');
        // Legacy AJAX callers retain the full-document transition.
        \http::refresh(\http::$root . 'auth/logout', '', [], 0, 'native');
		break;

	case 'updateAppearance':
		$value = (string) \http::dataset('value');
		if ($this->me && in_array($value, ['light', 'dark', 'system'], true)) {
			$this->me->appearance = $value;
			$this->scope->save($this->me);
			client::addCommand('setAttribute')->selector('html')->attr('data-appearance')->value($value);
			foreach (['light', 'dark', 'system'] as $mode) {
				$selector = '.appearanceChoices [data-value="' . $mode . '"]';
				client::addCommand('setAttribute')->selector($selector)->attr('aria-checked')->value($mode === $value ? 'true' : 'false');
				client::addCommand('setAttribute')->selector($selector)->attr('tabindex')->value($mode === $value ? '0' : '-1');
			}
		}
		break;

	case 'changePassword':
		$problems = UserOperator::passwordProblems((string) \http::posted('password'), (string) \http::posted('password_repeat'), STAGE === 'LIVE');
		if ($this->me && $problems === []) {
			$this->scope->save(UserOperator::setPassword($this->me, (string) \http::posted('password')));
			\message::confirm('Password has been changed.')->target('password');
		} else {
			\message::warning(implode('<br>', $problems))->target('password');
		}
		$this->updateView('main');
		break;

	case 'toggleFakeRole':
		$role = (string) \http::dataset('role_key');
		if (in_array($role, $this->fakeRoles, TRUE)) {
			\Systopic\System\Auth\Session::removeFakeRole($role);
		} else {
			\Systopic\System\Auth\Session::addFakeRole($role);
		}
		\http::redirect('./');
		break;

	case 'resetState':
		\Systopic\System\Auth\Session::resetState();
		\http::refresh($this->href);
		break;

	case 'ransomUser':
		// Back from admin/access' hijackUser to the superuser who hijacked.
		// A real form post, not an ajax click: the browser must store the
		// new session cookie and only then follow the redirect. An ajax
		// navigation kept the ghost session.
		//
		// Only from the account that was taken on, and only to a superuser:
		// the state lives in the browser's session, and somebody signing in
		// there after a hijack must not inherit the way back.
		$hijacker = (int) ($this->state->hijackerId ?? 0);
		$hijacked = (int) ($this->state->hijackedId ?? 0);
		$this->state->hijackerId = NULL;
		$this->state->hijackedId = NULL;
		$back = $hijacker > 0 && $hijacked === (int) Session::id()
			? UserLookup::byId($this->scope, $hijacker)
			: NULL;
		if ($back !== NULL && $back->isSuperuser) {
			Session::logout(false);   // back to the superuser, same device
			Session::login($back);
			\Systopic\System\Auth\SignInLog::write(Session::current()->name, 'signed in', \Systopic\System\Auth\SignInLog::SWITCH);
			if (session_status() === PHP_SESSION_ACTIVE) {
				session_write_close();
			}
		}
		\http::redirect($this->href);
		break;

	case 'unsetFakeRoles':
		\Systopic\System\Auth\Session::current()->state->fakeRoles = [];
		\http::redirect('./');
		break;

	case 'createDeviceCode':
		$account = $this->me ? SignInGrant::account($this->scope, $this->me, $this, (int) \http::post('account', 0)) : null;
		if ($account === null || !SignInCodes::available()) {
			\message::error('That account cannot be signed in.')->target('addDevice');
		} else {
			SignInCodes::offer($account, (int) $this->me->id);
		}
		$this->updateView('main');
		break;

	case 'newDeviceCode':
		SignInCodes::dropOffer();
		$this->updateView('main');
		break;

	case 'checkDeviceCode':
		if (!SignInCodes::available() || SignInCodes::peekDeviceCode((string) \http::post('deviceCode', '')) === null) {
			\message::error('That code is wrong or has expired.')->target('deviceBox');
			\clientDocument::replaceInner($this->scanFeedback, \message::flush('deviceBox'));
		} else {
			$this->updateView('main');
		}
		break;

	case 'approveDeviceCode':
		$me = $this->me;
		$account = $me ? SignInGrant::account($this->scope, $me, $this, (int) \http::post('account', 0)) : null;
		if ($account === null) {
			\message::error('That account cannot be signed in.')->target('deviceBox');
		} elseif (SignInCodes::approve((string) \http::post('deviceCode', ''), $account, $me)) {
			$this->deviceAllowed = true;
			\message::confirm('Allowed. The other device signs in within a few seconds.')->target($this->deviceMessages);
			\http::redirect($this->href);
			break;
		} else {
			\message::error(SignInCodes::$error === SignInCodes::BLOCKED
				? 'Too many attempts. Please try again in 15 minutes.'
				: 'That code is wrong or has expired.')->target('deviceBox');
		}
		\clientDocument::replaceInner($this->scanFeedback, \message::flush('deviceBox'));
		break;

	case 'revokeOtherDevices':
		if ($this->me) {
			Devices::revokeAllOf((int) $this->me->id, Devices::current()?->id, 'user');
			\message::confirm('Your other devices are signed out.')->target('devices');
		}
		$this->updateView('main');
		break;

	case 'revokeOwnDevice':
		$device = DeviceQuery::byId($this->scope, (int) \http::dataset('device_id'));
		if ($device !== null && $this->me && (int) $device->relationId('user') === (int) $this->me->id) {
			Devices::revoke($device, 'user');
			\message::confirm('Device signed out.')->target('devices');
		}
		$this->updateView('main');
		break;

	case 'revokeScreenDevice':
		$device = DeviceQuery::byId($this->scope, (int) \http::dataset('device_id'));
		$screen = $device !== null && $this->me ? SignInGrant::account($this->scope, $this->me, $this, (int) $device->relationId('user')) : null;
		if ($screen?->type === \Systopic\System\Tables\Users\Model::SCREEN) {
			Devices::revoke($device, 'admin');
			\message::confirm('Device signed out.')->target('devicesScreen');
		}
		$this->updateView('main');
		break;

	case 'uploadPortrait':
		if ($this->me) {
			$error = Portrait::store((int) $this->me->id);
			if ($error !== null) {
				\message::error($error)->target('portrait');
			}
		}
		$this->updateView('main');
		$this->updateView('signetmenu');
		break;

	case 'removePortrait':
		if ($this->me) {
			Portrait::remove((int) $this->me->id);
		}
		$this->updateView('main');
		$this->updateView('signetmenu');
		break;

	case 'updateUserName':
	case 'updateUserFullname':
	case 'updateUserEmail':
		if ($this->me) {
			$field = strtolower(substr($this->action, 10));
			$value = (string) \http::values('value');
			$problem = UserOperator::conflict($this->scope, $this->me, $field, $value);
			if ($problem !== null) {
				\message::error($problem)->target('account');
			} else {
				$this->scope->save(UserOperator::assign($this->me, $field, $value));
			}
		}
		$this->updateView('main');
		break;
}
