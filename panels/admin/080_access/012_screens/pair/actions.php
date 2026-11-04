<?php

/** @var \Systopic\System\Panels\Root\Admin\Access\Screens\Pair\Panel $this */

use Systopic\System\Auth\Session;
use Systopic\System\Auth\SignInCodes;
use Systopic\System\Auth\SignInGrant;
use Systopic\System\Queries\UserLookup\UserLookup;
use Systopic\System\Tables\SignInCodes\Operator as Codes;

$screen = $this->screen;
$me = UserLookup::byId($this->scope, (int) Session::id());
if ($screen === null || $me === null) {
	return;
}
$account = SignInGrant::account($this->scope, $me, $this, (int) $screen->id);

switch ($this->action) {

	case 'createCode':
		if ($account === null || !SignInCodes::available()) {
			\message::error('This screen cannot be signed in.');
		} else {
			$this->issuedCode = ['code' => SignInCodes::create($account, (int) $me->id, SignInCodes::DEVICE), 'expires' => time() + Codes::LIFETIME];
		}
		$this->updateView('lightbox');
		break;

	case 'approveDeviceCode':
		if ($account !== null && SignInCodes::approve((string) \http::post('deviceCode', ''), $account, $me)) {
			\message::confirm('Screen paired. It signs in within a few seconds.');
			\http::redirect($this->parent->href);
			break;
		}
		\message::error(SignInCodes::$error === SignInCodes::BLOCKED
			? 'Too many wrong codes from here. Try again in 15 minutes.'
			: 'That code is wrong or has expired.');
		$this->updateView('lightbox');
		break;
}
