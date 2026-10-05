<?php

/** @var \Systopic\System\Panels\Root\Cms\User\Panel $this */

use Systopic\System\Tables\Users\Operator as UserOperator;

switch ($this->action) {
	case 'logout':
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

	case 'unsetFakeRoles':
		\Systopic\System\Auth\Session::current()->state->fakeRoles = [];
		\http::redirect('./');
		break;

	case 'ransomUser':
		// Back from cms/users' hijackUser to the superuser who hijacked.
		if ($this->state->hijackerId) {
			$hijacker = (int) $this->state->hijackerId;
			$this->state->hijackerId = NULL;
			\Systopic\System\Auth\Session::logout();
			\Systopic\System\Auth\Session::login($hijacker);
			\http::refresh('./');
		}
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
