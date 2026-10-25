<?php
/** Shared notification for account security changes. Never receives passwords or credential data. */
use Systopic\System\Mail\Letter;
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$body = '<p>Hello ' . $e($user->fullname ?: $user->name ?: $user->email) . ',</p>'
    . '<p>' . $e($title) . ' in ' . $e($appName) . '.</p>'
    . '<p>Time: ' . $e($when) . '</p>';
if ($event === 'email') {
    $body .= '<p>Previous address: ' . $e($details['oldEmail'] ?? '') . '<br>New address: ' . $e($details['newEmail'] ?? '') . '</p>';
} elseif (isset($details['name'])) {
    $body .= '<p>Passkey: ' . $e($details['name']) . '</p>';
}
$body .= '<p>If you did not make this change, contact your administrator immediately and secure your account.</p>'
    . Letter::note('This is a security notification. It contains no password or sign-in link.');
echo Letter::render($title, $body);
