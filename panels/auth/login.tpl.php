<?php
/** @var \Systopic\System\Panels\Root\Auth\Panel $this */
$authMethod = $this->signInMethod();
$authPasskey = \Systopic\System\Auth\Passkeys::available();
$authCodes = \Systopic\System\Auth\SignInCodes::available();
if ($authMethod === 'device_code' && $authCodes && \Systopic\System\Auth\DeviceCode::current() === null) {
	try {
		\Systopic\System\Auth\DeviceCode::begin();
	} catch (\Throwable $e) {
		error_log('device code: ' . $e->getMessage());
	}
}
$authPair = $authMethod === 'device_code' ? \Systopic\System\Auth\DeviceCode::current() : null;
$authPairUrl = is_array($authPair) ? \Systopic\System\Auth\DeviceCode::url((string) $authPair['code']) : '';
$code = preg_replace('~[^0-9A-Za-z\-]~', '', (string) \http::get('code', ''));
// A failed submission must retain the manual input, including on touch devices.
$authCode = (string) (\http::posted('code') ?: $code);
$authCodeAttempted = $this->action === 'redeemCode';
$authUsername = (string) \http::posted('username', '');
$authPassword = (string) \http::posted('password', '');
$authForgot = '?view=resetPassword';
$authRegister = null;
$authForgotClass = 'ajax';
$authEndpoint = \http::$root . 'auth/';
include \fs::toNative((string) \fs::$sysRoot . 'src/Auth/login.card.tpl.php');
