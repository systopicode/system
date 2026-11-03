<?php

/** @var \Systopic\System\Panels\Root\Admin\Access\Users\Edit\Panel $this */

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
if ($user->isBlocked && in_array($this->action, ['makeSuperuser', 'inviteCode', 'inviteMail', 'resendActivationEmail'], true)) {
    \message::error('Unblock this account before granting access or sending an invitation.');
    return;
}
switch ($this->action) {
    case 'blockUser':
    case 'unblockUser':
        if (!\Systopic\System\Auth\Session::isSuperuser() || (int) $user->id <= 1 || (int) $user->id === (int) \Systopic\System\Auth\Session::id()) {
            \message::error('Only a superuser can block another account. You cannot block yourself.');
            break;
        }
        $user->isBlocked = $this->action === 'blockUser';
        if ($user->isBlocked) {
            \Systopic\System\Auth\AccountAccess::suspend($user);
        }
        $this->scope->save($user);
        if ($user->isBlocked) {
            Devices::revokeAllOf((int) $user->id, null, 'blocked');
            \Systopic\System\Queries\LoginCodes\LoginCodes::byHash($this->scope, str_repeat('0', 64));
            $this->scope->connection->run('UPDATE usm_logincodes SET logincode_used_at = CURRENT_TIMESTAMP WHERE logincode_user_id = ? AND logincode_used_at IS NULL', [(int) $user->id]);
        }
        \message::confirm($user->isBlocked ? 'Account blocked. Existing sessions and devices can no longer sign in.' : 'Account unblocked. Sign in again to reconnect devices.');
        $this->updateView('user');
        $this->parent->updateView('main');
        break;

	case 'userTab':
		$tab = (string) \http::dataset('tab');
		if (in_array($tab, ['general', 'roles', 'access', 'signins', 'maintenance', 'email'], true) && ($tab !== 'email' || \Systopic\System\Auth\Session::isSuperuser())) {
			$this->state->userTab = $tab;
		}
		$this->updateView('user');
		break;

    case 'moreEmails':
        if (!\Systopic\System\Auth\Session::isSuperuser()) break;
        $this->state->userTab = 'email';
        $this->state->emailsAll = empty($this->state->emailsAll);
        $this->updateView('user');
        break;

    case 'clearEmails':
        if (!\Systopic\System\Auth\Session::isSuperuser()) {
            \message::error('Only a superuser can delete email logs.');
            break;
        }
        $this->state->userTab = 'email';
        \Systopic\System\Maintenance\RetainedLogs::clearEmailsFor((string) $user->email);
        \message::confirm('Email log for this account deleted.');
        $this->updateView('user');
        break;

	case 'moreSignIns':
		$this->state->userTab = 'signins';
		$this->state->signInsAll = empty($this->state->signInsAll);
		$this->updateView('user');
		break;

	case 'showAllDevices':
		$this->state->userTab = 'access';
		$this->state->devicesAll = empty($this->state->devicesAll);
		$this->updateView('user');
		break;

	case 'updateUserName':
	case 'updateUserFullname':
	case 'updateUserEmail':
		$field = strtolower(substr($this->action, 10));
		if ($user->type === User::SCREEN && $field === 'email') {
			$this->updateView('user');
			break;
		}
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
		if ($this->action === 'makeSuperuser' && $user->type === User::SCREEN) {
			\message::error('A screen cannot be a superuser.')->target('account');
		} elseif (\Systopic\System\Auth\Session::isSuperuser()) {
			$this->scope->save(...UserOperator::superuser($user, $this->action === 'makeSuperuser', \Systopic\System\Auth\Session::id()));
		}
		$this->updateView('lightbox');
		$this->parent->updateView('main');
		break;

	case 'toggleUserRole':
		$role = $this->admin->role((int) \http::dataset('role_id'));
		if ($role !== null && !$role->isBuiltin) {
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
		$removedUserId = (int) $user->id;
        $this->scope->remove(...$gone);
        Portrait::remove($removedUserId);
		$this->parent->updateView('main');
		\http::redirect('../');
		break;

	case 'resendActivationEmail':
		if ($user->type === User::SCREEN) {
			\message::error('A screen has no email address.')->target('account');
			$this->updateView('lightbox');
			break;
		}
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
			\Systopic\System\Auth\LoginLog::mailed(
				\Systopic\System\Auth\LoginLog::MAILED_PASSWORD,
				trim((string) ($user->name ?: $user->email)),
			);
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
		$this->state->userTab = 'general';
		\message::error('The account type cannot be changed.')->target('account');
		$this->updateView('lightbox');
		break;

	case 'createAuth':
		$this->state->userTab = 'access';
		if (!\Systopic\System\Auth\Session::hasRole('cmsAdmin')) {
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
		if (!LoginCodes::available()) {
			break;
		}
		$this->state->userTab = 'access';
		if ($user->type !== User::SCREEN && (string) $user->email === '') {
			\message::error('Add an email address before creating a sign-in code.');
			$this->updateView('lightbox');
			break;
		}
		$code = LoginCodes::create($purpose, $user, \Systopic\System\Auth\Session::id());
		if ($this->action === 'inviteMail') {
			if (CodeMail::send($user, $purpose, $code)) {
				\Systopic\System\Auth\LoginLog::mailed(
					\Systopic\System\Auth\LoginLog::MAILED_INVITATION,
					trim((string) ($user->name ?: $user->email)),
				);
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
