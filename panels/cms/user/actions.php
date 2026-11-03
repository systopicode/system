<?php

/** @var \Systopic\System\Panels\Root\Cms\User\Panel $this */

use Systopic\System\Auth\Devices;
use Systopic\System\Auth\LoginCodes;
use Systopic\System\Auth\Portrait;
use Systopic\System\Auth\Session;
use Systopic\System\Queries\Devices\Devices as DeviceQuery;
use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\LoginCodes\Operator as Codes;
use Systopic\System\Tables\Users\Model as User;
use Systopic\System\Tables\Users\Operator as UserOperator;

if (str_starts_with((string) $this->action, 'passkey')) {
    \Systopic\System\Auth\Passkeys::respond($this->action, false);
}

switch ($this->action) {
	case 'logout':
		// signed out as a taken-on user: no way back for whoever signs in next
		$this->state->hijackerId = NULL;
		$this->state->hijackedId = NULL;
		\Systopic\System\Auth\LoginLog::write(\Systopic\System\Auth\Session::current()->name, 'logged out', \Systopic\System\Auth\LoginLog::LOGOUT_BUTTON);
		\Systopic\System\Auth\Session::logout();
		// A full document, so the sign-in page brings its own styles.
		// An ajax answer only swaps the markup and leaves the auth sheet out.
        if (!\http::ajax()) \http::redirect(\http::$root . 'cms/auth/logout');
        // Legacy AJAX callers retain the full-document transition.
        \http::refresh(\http::$root . 'cms/auth/logout', '', [], 0, 'native');
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
		// Back from cms/users' hijackUser to the superuser who hijacked.
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
			\Systopic\System\Auth\LoginLog::write(Session::current()->name, 'logged in', \Systopic\System\Auth\LoginLog::SWITCH);
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

	case 'checkRemote':
		$input = (string) \http::post('screen', '');
		if (!LoginCodes::available() || LoginCodes::peek($input, [Codes::PAIR_USER, Codes::PAIR_SCREEN]) === null) {
			\message::error('That code is wrong or has expired.')->target('remoteLogin');
			\clientDocument::replaceInner('.remoteLoginBox [data-remote-feedback]', \message::flush('remoteLogin'));
		} else {
			$this->updateView('main');
		}
		break;

	case 'approveRemote':
		$me = $this->me;
		$input = (string) \http::post('code', '');
		$ok = false;
		$told = false;
		if (!$me || !LoginCodes::available()) {
			\message::error('Codes are not set up on this server.')->target('remoteLogin');
			$told = true;
		} else {
			$code = LoginCodes::peek($input, [Codes::PAIR_USER, Codes::PAIR_SCREEN]);
			if ($code === null) {
				\message::error(LoginCodes::$error === LoginCodes::BLOCKED
					? 'Too many attempts. Please try again in 15 minutes.'
					: 'That code is wrong or has expired.')->target('remoteLogin');
				$told = true;
			} elseif ($code->purpose === Codes::PAIR_SCREEN) {
				$allowed = Session::hasRole('cmsAdmin') || \Systopic\System\Auth\SignInRights::allows($this->scope, $me, \Systopic\System\Auth\SignInRights::SCREENS);
				$screen = $allowed ? UserLookup::byId($this->scope, (int) \http::post('screen', 0)) : null;
				$screenOk = $screen !== null && $screen->type === User::SCREEN && $screen->isActive && (int) $screen->id > 1;
				if (!$allowed) {
					\message::error('Signing in a screen takes the authScreens permission.')->target('remoteLogin');
					$told = true;
				} elseif (!$screenOk) {
					\message::error('That account cannot be signed in.')->target('remoteLogin');
					$told = true;
				} else {
					$ok = LoginCodes::approveScreen($input, $screen, (int) Session::id());
				}
			} else {
				$posted = (int) \http::post('person', 0);
				$target = $me;
				if ($posted > 0 && $posted !== (int) $me->id) {
					$allowed = \Systopic\System\Auth\SignInRights::allows($this->scope, $me, \Systopic\System\Auth\SignInRights::PERSONS);
					$other = $allowed ? UserLookup::byId($this->scope, $posted) : null;
					$personOk = $other !== null
						&& (int) $other->id > 1
						&& $other->isActive
						&& ($other->type === null || $other->type === '' || $other->type === User::PERSON)
						&& (!$other->isSuperuser || $me->isSuperuser);
					if (!$allowed) {
						\message::error('You can only sign this device in as yourself.')->target('remoteLogin');
						$told = true;
					} elseif ($other !== null && $other->isSuperuser && !$me->isSuperuser) {
						\message::error('Only a superuser can sign in as a superuser.')->target('remoteLogin');
						$told = true;
					} elseif (!$personOk) {
						\message::error('That account cannot be signed in.')->target('remoteLogin');
						$told = true;
					} else {
						$target = $other;
					}
				}
				if (!$told) {
					$ok = LoginCodes::approvePerson($input, $target, $me);
				}
			}
		}
		if ($ok) {
			\message::confirm('Allowed. The other device signs in within a few seconds.')->target('devices');
			\http::redirect($this->href);
			break;
		}
		if (!$told) {
			\message::error(LoginCodes::$error === LoginCodes::BLOCKED
				? 'Too many attempts. Please try again in 15 minutes.'
				: 'That code is wrong or has expired.')->target('remoteLogin');
		}
		\clientDocument::replaceInner('.remoteLoginBox [data-remote-feedback]', \message::flush('remoteLogin'));
		break;

	case 'revokeOwnDevice':
		$device = DeviceQuery::byId($this->scope, (int) \http::dataset('device_id'));
		if ($device !== null && $this->me && (int) $device->relationId('user') === (int) $this->me->id) {
			Devices::revoke($device, 'user');
			\message::confirm('Device signed out.')->target('devices');
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
