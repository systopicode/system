<?php
/**
 * The plain code mail (Auth\CodeMail) - a project brings its own as
 * panels/auth/emailCode.tpl.php.
 *
 * @var \Systopic\System\Tables\Users\Model $user
 * @var string $purpose  invite | recover
 * @var string $code     K7Q4-M2XD
 * @var string $link
 * @var string $expires  "24 Stunden"
 * @var string $appName
 */
$invite = $purpose === 'invite';
?>
<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title><?= htmlspecialchars($appName) ?></title></head>
<body style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;color:#1a1a1a;">
	<p>Hallo <?= htmlspecialchars($user->fullname ?: $user->name ?: (string) $user->email) ?>,</p>
	<p><?= $invite ? 'du bist eingeladen. Gib in der App diesen Code ein:' : 'mit diesem Code kommst du wieder in die App:' ?></p>
	<p style="font-family:Menlo,Consolas,monospace;font-size:28px;letter-spacing:0.15em;font-weight:600;"><?= htmlspecialchars($code) ?></p>
	<p>Gültig <?= htmlspecialchars($expires) ?>, nur einmal. <a href="<?= htmlspecialchars($link) ?>">Im Browser öffnen</a></p>
	<p style="font-size:12px;color:#777;">Du hast das nicht angefordert? Dann ignoriere diese Mail.</p>
</body>
</html>
