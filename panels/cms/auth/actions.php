<?php

/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */

use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\Users\Operator as UserOperator;

switch ($this->action) {
	case 'register':
	case 'login':
	case 'resetPassword':
		$this->form = $this->action;
		$this->updateView('main');
		break;

	case 'createAccount':
		$this->form = 'register';
		$email = (string) \http::posted('email_create', '');
		$whitelist = str_replace('.', '\.', implode('|', defined('ACCOUNT_WHITELIST') ? ACCOUNT_WHITELIST : []));
		if (UserLookup::byName($this->scope, $email)) {
			$this->refuse('The email address is already in use');
		} elseif ($whitelist === '' || !preg_match("~^[a-z0-9_.-]*($whitelist)$~i", $email)) {
			$this->refuse('The email address given is not whitelisted. <br>Please ask a user administrator to invite you.');
		} else {
			$user = UserOperator::register($email, UserLookup::byName($this->scope, strtolower(strstr($email, '@', TRUE) ?: $email)) !== NULL);
			$this->sendActivation($user);
			$this->scope->save($user);
			\message::confirm('An activation code has been sent to your email address - please check your inbox');
			$this->form = 'activationCode';
			$this->updateView('main');
		}
		break;

	case 'sendResetCode':
		$user = UserLookup::byName($this->scope, (string) \http::posted('email_reset', ''));
		if ($user === NULL) {
			$this->refuse('The email address is not registered', 'resetPassword');
			break;
		}
		$this->scope->save(UserOperator::newActivationKey($user));
		$this->sendResetPassword($user);
		\message::confirm('A code to reset your password has been sent to your email address - please check your inbox');
		$this->form = 'resetCode';
		$this->updateView('main');
		break;

	case 'activate':
	case 'reset':
		// The link of an activation or reset mail (GET, the page renders after).
		if ($this->keyUser === NULL) {
			$this->refuse('Activation key not found');
		} elseif (!UserOperator::keyValid($this->keyUser)) {
			$this->refuse('Activation key expired. Go and get a new one.');
		} else {
			\message::confirm($this->keyUser->firstLogin === NULL ? 'Your Activation key is valid.' : 'Your reset key is valid.');
			$this->form = 'setPassword';
		}
		break;

	case 'setPassword':
		$user = $this->keyUser;
		if ($user === NULL || !UserOperator::keyValid($user)) {
			$this->refuse('Your activation key was not found or is expired, please get a new one');
			break;
		}
		$this->form = 'setPassword';
		$problems = UserOperator::passwordProblems((string) \http::posted('password'), (string) \http::posted('password_repeat'), STAGE === 'LIVE');
		if ($problems !== []) {
			$this->refuse(implode('<br>', $problems));
			break;
		}
		$this->scope->save(UserOperator::activate($user, (string) \http::posted('password')));
		$this->signIn($user);
		\message::confirm('Your new password has been set');
		\http::refresh('./', [], 5);
		$this->updateView('main');
		break;

	case 'doLogin':
		$user = \Systopic\System\Auth\Session::check((string) \http::posted('username', ''), (string) \http::posted('password', ''));
		if ($user === NULL) {
			$this->refuse("We don't know this combination");
			break;
		}
		$this->signIn($user);
		\http::refresh('./');
		break;

	case 'logout':
		\message::confirm('Logged out - Bye bye ... see you later');
		break;
}
