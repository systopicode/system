<?php

/** @var \Systopic\System\Panels\Root\Auth\Panel $this */

use Systopic\System\Auth\DeviceCode;
use Systopic\System\Auth\SignInCodes;
use Systopic\System\Auth\SignInLog;
use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\Users\Model as User;
use Systopic\System\Tables\Users\Operator as UserOperator;

if (str_starts_with((string) $this->action, 'passkey')) {
    \Systopic\System\Auth\Passkeys::respond($this->action, !\Systopic\System\Auth\Session::active());
}

switch ($this->action) {
    case 'validateQr':
        $renderStyle = 'none';
        \client::done(['valid' => SignInCodes::known((string) \http::put('code', ''))]);
        break;
    case 'continueWithoutPasskey':
        if (\Systopic\System\Auth\Session::active()) {
            $target = \Systopic\System\Auth\Passkeys::completeSetup();
            $this->form = 'signedIn';
            $this->updateView('main');
            $this->enterCms(2, $target);
            break;
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
		if ($whitelist === '' || !preg_match("~^[a-z0-9_.-]*($whitelist)$~i", $email)) {
			$this->refuse('The email address given is not whitelisted. <br>Please ask a user administrator to invite you.');
			break;
		}
		// The same answer whether or not the address has an account.
		if (\Systopic\System\Auth\Throttle::spend() && !UserLookup::byName($this->scope, $email)) {
			$user = UserOperator::register($email, UserLookup::byName($this->scope, strtolower(strstr($email, '@', TRUE) ?: $email)) !== NULL);
			$this->sendActivation($user);
			$this->scope->save($user);
		}
		\message::confirm('If this address can be registered, an activation code is on its way - please check your inbox');
		$this->form = 'activationCode';
		$this->updateView('main');
		break;

	case 'sendResetCode':
		// The same answer whether or not the address has an account.
		$user = \Systopic\System\Auth\Throttle::spend() ? UserLookup::byName($this->scope, (string) \http::posted('email_reset', '')) : NULL;
		if ($user !== NULL) {
			UserOperator::newActivationKey($user);
			if ($this->sendResetPassword($user)) {
				$this->scope->save($user);
			}
		}
		\message::confirm('If this address is registered, a code to reset your password is on its way - please check your inbox');
		$this->form = 'resetCode';
		$this->updateView('main');
		break;

	case 'activate':
	case 'reset':
		// The link of an activation or reset mail (GET, the page renders after).
        // Rendering checks the key before exposing password fields, including
        // expired, missing and already consumed links.
        $this->form = 'setPassword';
        $this->updateView('main');
		break;

	case 'setPassword':
		$user = $this->keyUser;
		if ($user === NULL || !UserOperator::keyValid($user)) {
			$this->form = 'setPassword';
            $this->updateView('main');
			break;
		}
		$this->form = 'setPassword';
		$problems = UserOperator::passwordProblems((string) \http::posted('password'), (string) \http::posted('password_repeat'), STAGE === 'LIVE');
		if ($problems !== []) {
			$this->refuse(implode('<br>', $problems));
			break;
		}
		$this->scope->save(UserOperator::activate($user, (string) \http::posted('password')));
		$this->signIn($user, \Systopic\System\Auth\SignInLog::EMAIL_LINK);
		\message::confirm('Your new password has been set');
		$this->form = 'signedIn';
		$this->enterCms(2);
		$this->updateView('main');
		break;

	case 'signInMethod':
		$method = (string) \http::dataset('method');
		$this->state->signInMethod = in_array($method, ['code', 'device_code', 'passkey', 'password', 'choose'], true) ? $method : 'choose';
		$this->form = 'login';
		if ($this->signInMethod() === 'device_code' && DeviceCode::current() === null) {
			DeviceCode::begin();
		}
		$this->updateView('main');
		break;

	case 'redeemCode':
        $this->state->signInMethod = 'code';
		$code = SignInCodes::redeem((string) (\http::posted('code') ?: \http::post('code', '')));
		$user = $code !== null ? UserLookup::byId($this->scope, (int) $code->relationId('user')) : null;
		if ($user === null) {
			$this->refuse(match (SignInCodes::$error) {
				SignInCodes::BLOCKED => 'Too many attempts. Please try again in 15 minutes.',
				SignInCodes::UNAVAILABLE => 'Codes are not set up on this server.',
				default => 'That code is wrong or has expired.',
			});
			break;
		}
		if (!$user->isActive) {
			$this->scope->save(UserOperator::joined($user));
		}
		// The code names the account. Who made it decided that, this device does not.
		$this->signIn($user, SignInLog::CODE);
		if ((string) \http::posted('passkey_supported', '') === '1' && $user->type !== User::SCREEN && \Systopic\System\Auth\Passkeys::offerSetup($user)) {
			$this->form = 'passkeySetup';
			$this->updateView('main');
			break;
		}
		$this->form = 'signedIn';
		$this->updateView('main');
		$this->enterCms(2);
		break;

	case 'doSignIn':
		$user = \Systopic\System\Auth\Session::check((string) \http::posted('username', ''), (string) \http::posted('password', ''));
		if ($user === NULL) {
			\Systopic\System\Auth\SignInLog::write((string) \http::posted('username', ''), 'wrong username/password', \Systopic\System\Auth\SignInLog::PASSWORD);
			$this->refuse("We don't know this combination");
			break;
		}
		$this->signIn($user, \Systopic\System\Auth\SignInLog::PASSWORD);
		if ((string) \http::posted('passkey_supported', '') === '1' && \Systopic\System\Auth\Passkeys::offerSetup($user)) {
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
