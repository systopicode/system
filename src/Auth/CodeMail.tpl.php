<?php
/**
 * The plain code mail. A project can replace it with panels/auth/emailCode.tpl.php.
 *
 * @var \Systopic\System\Tables\Users\Model $user
 * @var string $purpose  invite | recover
 * @var string $code
 * @var string $link
 * @var string $expires
 * @var string $appName
 */

use Systopic\System\Mail\Letter;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$invite = $purpose === 'invite';
$name = $user->fullname ?: $user->name ?: (string) $user->email;
echo Letter::render($invite ? 'Your invitation' : 'Your sign-in code',
	'<p style="margin:0 0 12px;">Hello ' . $e($name) . ',</p>'
	. '<p style="margin:0;">' . ($invite ? 'You are invited. Enter this code in the app:' : 'Use this code to sign in again:') . '</p>'
	. '<p style="margin:20px 0;text-align:center;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:28px;letter-spacing:0.14em;font-weight:700;">' . $e($code) . '</p>'
	. Letter::button($link, 'Open in the browser')
	. Letter::note('Valid ' . $e($expires) . ', once.')
	. Letter::note('If you did not ask for this, you can ignore it.')
);
