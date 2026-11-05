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
        if (trim((string) \http::posted('deviceCode', '')) === '') break;
        $feedbackTarget = '.deviceBox';
        $instance = (string) \http::posted('scanInstance', '');
        if (preg_match('/^[a-f0-9]{16,64}$/D', $instance)) $feedbackTarget .= '[data-scan-instance="' . $instance . '"]';
        $feedbackTarget .= ':not([hidden]) [data-remote-feedback]';
		if ($account !== null && SignInCodes::approve((string) \http::posted('deviceCode', ''), $account, $me)) {
            \message::confirm('Allowed. Waiting for the screen to sign in.');
            $watchCode = htmlspecialchars((string) \http::posted('deviceCode', ''), ENT_QUOTES);
            \clientDocument::replaceInner($feedbackTarget, '<div data-code-approved data-code-value="' . $watchCode . '"><div data-code-feedback>' . \message::flush() . '</div></div>');
			break;
		}
		\message::error(SignInCodes::$error === SignInCodes::BLOCKED
			? 'Too many wrong codes from here. Try again in 15 minutes.'
			: 'That code is wrong or has expired.');
		\clientDocument::replaceInner($feedbackTarget, \message::flush());
		break;
}
