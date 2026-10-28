<?php

/** @var \Systopic\System\Panels\Root\Cms\Users\Users\Edit\Panel $this */

use Systopic\System\Auth\CodeMail;
use Systopic\System\Auth\Devices;
use Systopic\System\Auth\LoginCodes;
use Systopic\System\Auth\Portrait;
use Systopic\System\Queries\Devices\Devices as DeviceQuery;
use Systopic\System\Tables\LoginCodes\Operator as Codes;
use Systopic\System\Tables\Users\Model as User;
use Systopic\System\Tables\Users\Operator as UserOperator;

$user = $this->user;
if ($user === NULL) {
	return;
}
$refreshSignet = function () use ($user): void {
	if ((int) $user->id !== (int) \Systopic\System\Auth\Session::id()) {
		return;
	}
	$node = $this;
	while ($node !== null) {
		$account = $node->child('user');
		if ($account !== null) {
			$account->updateView('signetmenu');
			return;
		}
		$node = $node->parent;
	}
};
switch ($this->action) {
	case 'userTab':
		$tab = (string) \http::dataset('tab');
		if (in_array($tab, ['general', 'roles', 'access', 'signins', 'maintenance'], true)) {
			$this->state->userTab = $tab;
		}
		$this->updateView('user');
		break;

	case 'moreSignIns':
		$this->state->userTab = 'signins';
		$this->state->signInsAll = true;
		$this->updateView('user');
		break;

	case 'showAllDevices':
		$this->state->userTab = 'access';
		$this->state->devicesAll = true;
		$this->updateView('user');
		break;

	case 'updateUserName':
	case 'updateUserFullname':
	case 'updateUserEmail':
		$field = strtolower(substr($this->action, 10));
		$value = (string) \http::values('value');
		$problem = UserOperator::conflict($this->scope, $user, $field, $value);
		if ($problem !== null) {
			\message::error($problem)->target('account');
		} else {
			$this->scope->save(UserOperator::assign($user, $field, $value));
		}
		$this->updateView('user');
		$this->parent->updateView('main');
		break;

	case 'makeSuperuser':
	case 'revokeSuperuser':
		if (\Systopic\System\Auth\Session::isSuperuser()) {
			$this->scope->save(...UserOperator::superuser($user, $this->action === 'makeSuperuser', \Systopic\System\Auth\Session::id()));
		}
		$this->updateView('lightbox');
		$this->parent->updateView('main');
		break;

	case 'toggleUserRole':
		$role = $this->admin->role((int) \http::dataset('role_id'));
		if ($role !== null) {
			$link = $this->admin->assignment($user, $role);
			if ($link !== null) {
				$this->scope->remove($link);
			} else {
				$link = new \Systopic\System\Tables\UsersAtRoles\Model();
				[$link->user, $link->role] = [$user, $role];
				$this->scope->save($link);
			}
		}
		$this->updateView('lightbox');
		$this->parent->updateView('main');
		break;

	case 'uploadPortrait':
		$error = Portrait::store((int) $user->id);
		if ($error !== null) {
			\message::error($error)->target('account');
		}
		$this->updateView('user');
		$refreshSignet();
		break;

	case 'removePortrait':
		Portrait::remove((int) $user->id);
		$this->updateView('user');
		$refreshSignet();
		break;

	case 'resetState':
		if ((int) $user->id === (int) \Systopic\System\Auth\Session::id()) {
			\Systopic\System\Auth\Session::resetState();
			\http::refresh($this->href . '?user_id=' . (int) $user->id);
			break;
		}
		$this->scope->save(\Systopic\System\Auth\Session::requestStateReset($user));
		\message::confirm('The layout resets at the next click of this user.');
		$this->updateView('lightbox');
		break;

	case 'removeUser':
		if ((int) $user->id === (int) \Systopic\System\Auth\Session::id()) {
			\message::error('You cannot delete your own account.');
			break;
		}
		$gone = [];
		foreach ($this->admin->roles as $role) {
			$link = $this->admin->assignment($user, $role);
			if ($link !== null) {
				$gone[] = $link;
			}
		}
		foreach ($this->devices as $device) {
			$gone[] = $device;
		}
		foreach (\Systopic\System\Queries\Passkeys\Passkeys::ofUser($this->scope, (int) $user->id) as $passkey) {
			$gone[] = $passkey;
		}
		$gone[] = $user;
		$this->scope->remove(...$gone);
		\http::redirect('../');
		break;

	case 'resendActivationEmail':
		if ((string) $user->email === '') {
			$this->accessNote = 'This account has no email address.';
			\message::error($this->accessNote);
			$this->updateView('lightbox');
			break;
		}
		$this->scope->save(UserOperator::newActivationKey($user));
		try {
			$sent = (bool) $this->mailActivation($user);
		} catch (\Throwable $e) {
			$sent = false;
			$this->accessNote = 'The email could not be sent. ' . $e->getMessage();
		}
		if ($sent) {
			$this->accessNote = 'Activation email sent to ' . $user->email . '.';
			\message::confirm($this->accessNote);
		} elseif ($this->accessNote === null) {
			$this->accessNote = 'The email could not be sent.';
			\message::error($this->accessNote);
		} else {
			\message::error($this->accessNote);
		}
		$this->updateView('lightbox');
		break;

	// ---------------------------------------- codes and devices (Auth\LoginCodes, Auth\Devices)

	case 'updateUserType':
		$type = (string) \http::values('value');
		$this->state->userTab = 'access';
		if (in_array($type, [User::PERSON, User::SCREEN], TRUE) && $type !== (string) $user->type) {
			$user->type = $type;
			if ($type === User::SCREEN) {
				$user->passwordHash = NULL;   // a screen is paired, never logs in with a password
			}
			$this->scope->save($user);
		}
		$this->updateView('lightbox');
		break;

	case 'createAuth':
		$this->state->userTab = 'access';
		if (!\Systopic\System\Auth\Session::hasOneRole('admin')) {
			\message::error('Only an administrator can create the sign-in key.');
		} elseif (!\Systopic\System\Settings\EnvironmentFile::createAuth((string) \http::dataset('file'))) {
			\message::error('The sign-in key was not created.');
		} else {
			$file = (string) \http::dataset('file');
			$written = \Systopic\System\Settings\EnvironmentFile::read($file);
			$auth = is_array($written['auth'] ?? null) ? $written['auth'] : [];
			\Systopic\System\Auth\AuthConfig::rememberSecret((string) ($auth['secret'] ?? ''));
			\message::confirm('Sign-in key created.');
		}
		$this->updateView('lightbox');
		break;

	case 'clearSignIns':
		$this->state->userTab = 'signins';
		if (!\Systopic\System\Auth\Session::isSuperuser()) {
			\message::error('Only a superuser can delete a sign-in log.');
		} else {
			\Systopic\System\Auth\LoginLog::forget([(string) $user->name, (string) $user->email]);
			\message::confirm('Sign-in log deleted.');
		}
		$this->updateView('lightbox');
		break;

	case 'inviteCode':
	case 'inviteMail':
	case 'recoverCode':
		$purpose = $this->action === 'inviteMail' ? Codes::INVITE : Codes::RECOVER;
		if ($user->type === User::SCREEN || !LoginCodes::available()) {
			break;
		}
		$this->state->userTab = 'access';
		if ($this->action === 'inviteMail' && (string) $user->email === '') {
			\message::error('No email address. Create a code instead.');
			$this->updateView('lightbox');
			break;
		}
		$code = LoginCodes::create($purpose, $user, \Systopic\System\Auth\Session::id());
		if ($this->action === 'inviteMail') {
			if (CodeMail::send($user, $purpose, $code)) {
				\message::confirm('Invitation sent to ' . $user->email . '.');
			} else {
				\message::error('The email could not be sent. Create a code instead.');
			}
		} else {
			$this->issuedCode = ['purpose' => $purpose, 'code' => $code, 'expires' => time() + Codes::LIFETIMES[$purpose]];
		}
		$this->updateView('lightbox');
		break;

	case 'approveScreen':
		if ($user->type === User::SCREEN) {
			$ok = LoginCodes::approveScreen((string) \http::values('value'), $user, (int) \Systopic\System\Auth\Session::id());
			if ($ok) {
				\message::confirm('Screen paired. It signs in within a few seconds.');
			} else {
				\message::error(LoginCodes::$error === LoginCodes::BLOCKED
					? 'Too many wrong codes from here. Try again in 15 minutes.'
					: 'That code is wrong or has expired.');
			}
		}
		$this->updateView('lightbox');
		break;

	case 'revokeDevice':
		$device = DeviceQuery::byId($this->scope, (int) \http::dataset('device_id'));
		if ($device !== NULL && (int) $device->relationId('user') === (int) $user->id) {
			Devices::revoke($device, 'admin');
		}
		$this->updateView('lightbox');
		break;
}
