<?php

/** @var \Systopic\System\Panels\Root\Admin\Access\Users\Edit\Panel $this */

use Systopic\System\Auth\CodeMail;
use Systopic\System\Auth\Devices;
use Systopic\System\Auth\SignInCodes;
use Systopic\System\Auth\Portrait;
use Systopic\System\Queries\Devices\Devices as DeviceQuery;
use Systopic\System\Tables\SignInCodes\Operator as Codes;
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
    case 'refreshDevices':
        $this->updateView('user');
        break;

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
            \Systopic\System\Queries\SignInCodes\SignInCodes::byHash($this->scope, str_repeat('0', 64));
            $this->scope->connection->run('UPDATE usm_signincodes SET signincode_used_at = CURRENT_TIMESTAMP WHERE signincode_user_id = ? AND signincode_used_at IS NULL', [(int) $user->id]);
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

	// ---------------------------------------- codes and devices (Auth\SignInCodes, Auth\Devices)

	case 'updateUserType':
		$this->state->userTab = 'general';
		\message::error('The account type cannot be changed.')->target('account');
		$this->updateView('lightbox');
		break;

	case 'clearSignIns':
		$this->state->userTab = 'signins';
		if (!\Systopic\System\Auth\Session::isSuperuser()) {
			\message::error('Only a superuser can delete a sign-in log.');
		} else {
			\Systopic\System\Auth\SignInLog::forget([(string) $user->name, (string) $user->email]);
			\message::confirm('Sign-in log deleted.');
		}
		$this->updateView('lightbox');
		break;

	case 'inviteMail':
		if (!SignInCodes::available()) {
			break;
		}
		$this->state->userTab = 'access';
		if ((string) $user->email === '') {
			\message::error('Add an email address before creating a sign-in code.');
			$this->updateView('lightbox');
			break;
		}
		$by = \Systopic\System\Auth\Session::id();
		if ($this->action === 'inviteMail') {
			$code = SignInCodes::create($user, $by, SignInCodes::INVITATION, Codes::INVITATION_LIFETIME);
			if (CodeMail::send($user, $code)) {
				\Systopic\System\Auth\SignInLog::mailed(
					\Systopic\System\Auth\SignInLog::MAILED_INVITATION,
					trim((string) ($user->name ?: $user->email)),
				);
				\message::confirm('Invitation sent to ' . $user->email . '.');
			} else {
				\message::error('The email could not be sent. Create a code instead.');
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
