<?php

/** @var \Systopic\System\Panels\Root\Cms\Users\Users\Edit\Panel $this */

use Systopic\System\Auth\CodeMail;
use Systopic\System\Auth\Devices;
use Systopic\System\Auth\LoginCodes;
use Systopic\System\Queries\Devices\Devices as DeviceQuery;
use Systopic\System\Tables\LoginCodes\Operator as Codes;
use Systopic\System\Tables\Users\Model as User;
use Systopic\System\Tables\Users\Operator as UserOperator;

include '../../child.role.actions.php';

$user = $this->user;
if ($user === NULL) {
	return;
}
switch ($this->action) {
	case 'updateUserName':
	case 'updateUserFullname':
	case 'updateUserEmail':
		$this->scope->save(UserOperator::assign($user, strtolower(substr($this->action, 10)), (string) \http::values('value')));
		$this->parent->updateView('main');
		break;

	case 'resendActivationEmail':
		$this->scope->save(UserOperator::newActivationKey($user));
		$this->mailActivation($user);
		\message::confirm('New activation Mail was sent.');
		$this->updateView('lightbox');
		break;

	// ---------------------------------------- codes and devices (Auth\LoginCodes, Auth\Devices)

	case 'updateUserType':
		$type = (string) \http::values('value');
		if (in_array($type, [User::PERSON, User::SCREEN], TRUE)) {
			$user->type = $type;
			if ($type === User::SCREEN) {
				$user->passwordHash = NULL;   // a screen is paired, never logs in with a password
			}
			$this->scope->save($user);
		}
		$this->updateView('lightbox');
		break;

	case 'inviteCode':
	case 'inviteMail':
	case 'recoverCode':
		$purpose = $this->action === 'recoverCode' ? Codes::RECOVER : Codes::INVITE;
		if ($user->type === User::SCREEN || !LoginCodes::available()) {
			break;
		}
		$code = LoginCodes::create($purpose, $user, \Systopic\System\Auth\Session::id());
		if ($this->action === 'inviteMail') {
			$this->accessNote = match (TRUE) {
				(string) $user->email === ''         => 'Keine E-Mail-Adresse - zeig den Code oder den QR-Code vor.',
				CodeMail::send($user, $purpose, $code) => 'Einladung an ' . $user->email . ' geschickt.',
				default                              => 'Die Mail ging nicht raus - zeig den Code oder den QR-Code vor.',
			};
		}
		$this->issuedCode = ['purpose' => $purpose, 'code' => $code, 'expires' => time() + Codes::LIFETIMES[$purpose]];
		$this->updateView('lightbox');
		break;

	case 'approveScreen':
		if ($user->type === User::SCREEN) {
			$ok = LoginCodes::approveScreen((string) \http::values('value'), $user, (int) \Systopic\System\Auth\Session::id());
			$this->accessNote = $ok ? 'Screen gekoppelt - er meldet sich in wenigen Sekunden an.'
				: (LoginCodes::$error === LoginCodes::BLOCKED ? 'Zu viele falsche Codes von hier. Bitte in 15 Minuten noch einmal.' : 'Der Code stimmt nicht oder ist abgelaufen.');
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
