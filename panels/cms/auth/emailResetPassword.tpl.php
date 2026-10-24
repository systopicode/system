<?php
/** @var \Systopic\System\Tables\Users\Model $user */

use Systopic\System\Mail\Letter;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$link = \http::$siteUrl . 'cms/auth/reset?key=' . rawurlencode((string) $user->activationKey);
$expires = $user->activationKeyExpire?->format('j M Y, H:i') ?? '';
echo Letter::render('Reset your password',
	'<p style="margin:0 0 12px;">Hello ' . $e($user->name) . ',</p>'
	. '<p style="margin:0;">Follow the link to choose a new password.</p>'
	. Letter::button($link, 'Reset your password')
	. Letter::note('Or copy this code:<br><b style="letter-spacing:0.04em;">' . $e($user->activationKey) . '</b>')
	. Letter::note('This link works once.' . ($expires !== '' ? ' It expires at ' . $e($expires) . '.' : ''))
	. Letter::note('If you did not ask for this, you can ignore it.')
);
