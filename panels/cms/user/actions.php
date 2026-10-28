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
		\Systopic\System\Auth\LoginLog::write(\Systopic\System\Auth\Session::current()->name, 'logged out', \Systopic\System\Auth\LoginLog::LOGOUT_BUTTON);
		\Systopic\System\Auth\Session::logout();
		// A full document, so the sign-in page brings its own styles.
		// An ajax answer only swaps the markup and leaves the auth sheet out.
		\http::refresh(\http::$root . 'cms/auth/logout', '', [], 0, 'native');
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
		$role = (string) \http::dataset('role_name');
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

	case 'unsetFakeRoles':
		\Systopic\System\Auth\Session::current()->state->fakeRoles = [];
		\http::redirect('./');
		break;

	case 'approveRemote':
		$me = $this->me;
		$input = (string) \http::post('code', '');
		$ok = false;
		$told = false;
		if (!$me || !LoginCodes::available()) {
			\message::error('Codes are not set up on this server.')->target('devices');
			$told = true;
		} else {
			$code = LoginCodes::peek($input, [Codes::PAIR_USER, Codes::PAIR_SCREEN]);
			if ($code === null) {
				\message::error(LoginCodes::$error === LoginCodes::BLOCKED
					? 'Too many attempts. Please try again in 15 minutes.'
					: 'That code is wrong or has expired.')->target('devices');
				$told = true;
			} elseif ($code->purpose === Codes::PAIR_SCREEN) {
				$allowed = \Systopic\System\Auth\SignInRights::allows($this->scope, $me, \Systopic\System\Auth\SignInRights::SCREENS);
				$screen = $allowed ? UserLookup::byId($this->scope, (int) \http::post('screen', 0)) : null;
				$screenOk = $screen !== null && $screen->type === User::SCREEN && $screen->isActive && (int) $screen->id > 1;
				if (!$allowed) {
					\message::error('Signing in a screen takes the authScreens permission.')->target('devices');
					$told = true;
				} elseif (!$screenOk) {
					\message::error('That account cannot be signed in.')->target('devices');
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
						\message::error('You can only sign this device in as yourself.')->target('devices');
						$told = true;
					} elseif ($other !== null && $other->isSuperuser && !$me->isSuperuser) {
						\message::error('Only a superuser can sign in as a superuser.')->target('devices');
						$told = true;
					} elseif (!$personOk) {
						\message::error('That account cannot be signed in.')->target('devices');
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
				: 'That code is wrong or has expired.')->target('devices');
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
