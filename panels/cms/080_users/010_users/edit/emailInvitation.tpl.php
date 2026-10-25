<?php
/** @var \Systopic\System\Tables\Users\Model $user */

use Systopic\System\Mail\Letter;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$link = \http::$siteUrl . 'cms/auth/reset?key=' . rawurlencode((string) $user->activationKey);
$expires = \Systopic\System\Sys\LocalTime::format($user->activationKeyExpire, 'j M Y, H:i');
echo Letter::render('Account activation',
	'<p style="margin:0 0 12px;">Hello ' . $e($user->name) . ',</p>'
	. '<p style="margin:0;">Welcome to the fluxo cms. Here is your access key. Follow the link to activate your account.</p>'
	. Letter::button($link, 'Activate your account')
	. Letter::note('Or copy this code:<br><b style="letter-spacing:0.04em;">' . $e($user->activationKey) . '</b>')
	. Letter::note('This link works once.' . ($expires !== '' ? ' It expires at ' . $e($expires) . '.' : ''))
	. Letter::note('If you did not expect this message, you can ignore it.')
);
