<?php

/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */

use Systopic\System\Auth\LoginCodes;
use Systopic\System\Auth\ScreenPair;
use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\LoginCodes\Operator as Codes;
use Systopic\System\Tables\Users\Model as User;
use Systopic\System\Tables\Users\Operator as UserOperator;

if (str_starts_with((string) $this->action, 'passkey')) {
    \Systopic\System\Auth\Passkeys::respond($this->action, !\Systopic\System\Auth\Session::active());
}

switch ($this->action) {
    case 'continueWithoutPasskey':
        if (\Systopic\System\Auth\Session::active()) {
            \http::redirect(\Systopic\System\Auth\Passkeys::completeSetup());
        }
        $this->form = 'login';
        $this->updateView('main');
        break;

	case 'register':
	case 'login':
	case 'resetPassword':
	case 'license':
	case 'imprint':
	case 'privacy':
	case 'releases':
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
		UserOperator::newActivationKey($user);
		if (!$this->sendResetPassword($user)) {
			$this->refuse('The email could not be sent. Try again in a moment.', 'resetPassword');
			break;
		}
		$this->scope->save($user);
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
		$this->signIn($user, \Systopic\System\Auth\LoginLog::EMAIL_LINK);
		\message::confirm('Your new password has been set');
		$this->enterCms(5);
		$this->updateView('main');
		break;

	case 'loginMethod':
		$method = (string) \http::dataset('method');
		$this->state->loginMethod = in_array($method, ['code', 'device', 'passkey', 'password', 'choose'], true) ? $method : 'choose';
		$this->form = 'login';
		if ($this->loginMethod() === 'device' && ScreenPair::current() === null) {
			$screen = (string) \http::get('pair') === 'screen';
			ScreenPair::begin(
				$screen ? \http::$root : \http::$root . 'cms/',
				$screen ? Codes::PAIR_SCREEN : Codes::PAIR_USER
			);
		}
		$this->updateView('main');
		break;

	case 'redeemCode':
		$code = LoginCodes::redeem((string) (\http::posted('code') ?: \http::post('code', '')), [Codes::INVITE, Codes::RECOVER, Codes::PAIR]);
		$user = $code !== null ? UserLookup::byId($this->scope, (int) $code->relationId('user')) : null;
		if ($user === null || $user->type === User::SCREEN) {
			$this->refuse(match (LoginCodes::$error) {
				LoginCodes::BLOCKED => 'Too many attempts. Please try again in 15 minutes.',
				LoginCodes::UNAVAILABLE => 'Codes are not set up on this server.',
				default => 'That code is wrong or has expired.',
			});
			break;
		}
		if ($code->purpose === Codes::INVITE || !$user->isActive) {
			$this->scope->save(UserOperator::joined($user));
		}
		$this->signIn($user, match ($code->purpose) {
			Codes::INVITE => \Systopic\System\Auth\LoginLog::INVITATION,
			Codes::RECOVER => \Systopic\System\Auth\LoginLog::RECOVERY,
			Codes::PAIR => \Systopic\System\Auth\LoginLog::DEVICE,
			default => \Systopic\System\Auth\LoginLog::EMAIL_CODE,
		});
		$this->form = 'signedIn';
		$this->updateView('main');
		$this->enterCms(2);
		break;

	case 'doLogin':
		$user = \Systopic\System\Auth\Session::check((string) \http::posted('username', ''), (string) \http::posted('password', ''));
		if ($user === NULL) {
			\Systopic\System\Auth\LoginLog::write((string) \http::posted('username', ''), 'wrong username/password', \Systopic\System\Auth\LoginLog::PASSWORD);
			$this->refuse("We don't know this combination");
			break;
		}
		$this->signIn($user, \Systopic\System\Auth\LoginLog::PASSWORD);
		if (\Systopic\System\Auth\Passkeys::offerSetup($user)) {
			$this->form = 'passkeySetup';
			$this->updateView('main');
		} else {
			$this->form = 'signedIn';
			$this->updateView('main');
			$this->enterCms(2);
		}
		break;

	case 'logout':
		$this->form = 'loggedOut';
		break;
}
