<?php
/** @var \Systopic\System\Panels\Root\Cms\Auth\Panel $this */
$authMethod = $this->loginMethod();
$authPasskey = \Systopic\System\Auth\Passkeys::available();
$authCodes = \Systopic\System\Auth\LoginCodes::available();
$authScreen = (string) \http::get('pair') === 'screen';
if ($authMethod === 'device' && $authCodes && \Systopic\System\Auth\ScreenPair::current() === null) {
	try {
		\Systopic\System\Auth\ScreenPair::begin(
			$authScreen ? \http::$root : \http::$root . 'cms/',
			$authScreen ? \Systopic\System\Tables\LoginCodes\Operator::PAIR_SCREEN : \Systopic\System\Tables\LoginCodes\Operator::PAIR_USER
		);
	} catch (\Throwable $e) {
		error_log('screen pairing: ' . $e->getMessage());
	}
}
$authPairTitle = $authScreen ? 'Screen' : 'Remote login';
$authPairLead = $authScreen
	? 'On a signed-in phone, scan this in the account or open the link, then choose the screen.'
	: 'Scan this in the account on the phone that is already signed in, or open the link there.';
$authPair = $authMethod === 'device' ? \Systopic\System\Auth\ScreenPair::current() : null;
$authPairUrl = is_array($authPair) ? \Systopic\System\Auth\ScreenPair::url((string) $authPair['code']) : '';
$code = preg_replace('~[^0-9A-Za-z\-]~', '', (string) \http::get('code', ''));
$pending = $code !== '' ? \Systopic\System\Auth\LoginCodes::peek($code, ['invite', 'recover', 'pair']) : null;
$authCode = $code;
$authAuto = $pending !== null && (bool) \http::get('auto');
$authUsername = (string) \http::put('username', '');
$authForgot = '?view=resetPassword';
$authRegister = null;
$authForgotClass = 'ajax';
$authEndpoint = \http::$root . 'cms/auth/';
include \fs::toNative((string) \fs::$sysRoot . 'src/Auth/login.card.tpl.php');
