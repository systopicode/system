<?php

/** @var \Systopic\System\Panels\Root\Cms\User\Panel $this */

use Systopic\System\Auth\Portrait;
use Systopic\System\Tables\Users\Operator as UserOperator;

switch ($this->action) {
	case 'logout':
		\Systopic\System\Auth\LoginLog::write(\Systopic\System\Auth\Session::current()->name, 'logged out');
		\Systopic\System\Auth\Session::logout();
		\http::refresh('../auth/logout');
		break;

	case 'changePassword':
		$problems = UserOperator::passwordProblems((string) \http::posted('password'), (string) \http::posted('password_repeat'), STAGE === 'LIVE');
		if ($this->me && $problems === []) {
			$this->scope->save(UserOperator::setPassword($this->me, (string) \http::posted('password')));
			\message::confirm('password has been changed.');
		} else {
			\message::warning(implode('<br>', $problems));
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

	case 'ransomUser':
		// Back from cms/users' hijackUser to the superuser who hijacked.
		// A real form post, not an ajax click: the browser must store the
		// new session cookie and only then follow the redirect. An ajax
		// navigation kept the ghost session.
		$hijacker = (int) ($this->state->hijackerId ?? 0);
		if ($hijacker > 0) {
			$this->state->hijackerId = NULL;
			\Systopic\System\Auth\Session::logout(false);   // back to the superuser, same device
			\Systopic\System\Auth\Session::login($hijacker);
			\Systopic\System\Auth\LoginLog::write(\Systopic\System\Auth\Session::current()->name, 'logged in', \Systopic\System\Auth\LoginLog::SWITCH);
			if (session_status() === PHP_SESSION_ACTIVE) {
				session_write_close();
			}
			\http::redirect($this->href);
		}
		break;

	case 'uploadPortrait':
		if ($this->me) {
			$error = Portrait::store((int) $this->me->id);
			$error === null ? \message::confirm('Photo updated.') : \message::error($error);
		}
		\http::redirect($this->href);
		break;

	case 'removePortrait':
		if ($this->me) {
			Portrait::remove((int) $this->me->id);
		}
		\http::redirect($this->href);
		break;

	case 'updateUserName':
	case 'updateUserFullname':
	case 'updateUserEmail':
		if ($this->me) {
			$this->scope->save(UserOperator::assign($this->me, strtolower(substr($this->action, 10)), (string) \http::values('value')));
		}
		$this->parent->updateView();
		break;
}
