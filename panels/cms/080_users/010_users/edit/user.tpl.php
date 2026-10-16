<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Users\Edit\Panel $this */

use Systopic\System\Panels\Root\Cms\Users\Panel as Users;

$user = $this->user;
// one-time codes and devices, where the instance has auth.secret - else the
// activation mail of before (projects whose login knows no codes yet)
$codes    = \Systopic\System\Auth\LoginCodes::available();
$isScreen = $user->type === \Systopic\System\Tables\Users\Model::SCREEN;
$devices  = $codes ? $this->devices : [];
$issued   = $this->issuedCode;
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$when = static fn(?DateTimeImmutable $d): string => $d?->format('d.m.Y H:i:s') ?? '';
$field = static function (string $label, string $value, ?string $action = NULL, string $type = 'text') use ($e): string {
	$input = $action === NULL
			? "<input type='$type' readonly value='{$e($value)}' />"
			: "<input type='$type' autocapitalize='off' name='value' data-on_blur='$action' value='{$e($value)}' required spellcheck='off' />";
	return "<div class='inputGroup'>$input<span class='bar'></span><label>$label</label></div>";
};
?>
<header>
	<h1><?= $e(trim((string) $user->fullname) !== '' ? $user->fullname : $user->name) ?></h1>
	<ul class="barmenu">
		<li>
			<a class="ajax" href="../"><i class="fa-light fa-xmark-large"></i></a>
		</li>
	</ul>
</header>
<main>
	<div class="layout">
		<div class='row'>
			<?= \message::flush() ?>
		</div>
		<div class="row layout">
			<div class="halfColumn">
				<?= $field('Username', (string) $user->name, 'updateUserName') ?>
				<?= $field('Fullname', (string) $user->fullname, 'updateUserFullname') ?>
				<?= $field('E-Mail', (string) $user->email, 'updateUserEmail', 'email') ?>
				<?= $field('Last Login', $when($user->lastLogin)) ?>
				<?= $field('First Login', $when($user->firstLogin)) ?>
				<?php if (\Systopic\System\Auth\Session::isSuperuser()) { ?>
					<div class="inputGroup">
						<?php if ($user->isSuperuser) { ?>
							<button type="button" class="submit" data-on_click="../revokeSuperuser" data-user_id="<?= $user->id ?>" <?= (int) $user->id === (int) \Systopic\System\Auth\Session::id() ? 'disabled' : '' ?>>
								<i class="fas fa-spider"></i> Revoke Superuser
							</button>
						<?php } else { ?>
							<button type="button" class="submit" data-on_click="../makeSuperuser" data-user_id="<?= $user->id ?>">
								<i class="fas fa-spider"></i> Make Superuser
							</button>
						<?php } ?>
					</div>
				<?php } ?>
			</div>
			<div class="halfColumn">
				<?= $field('User Created at', $when($user->createDate)) ?>
				<?= $field('User Created by', Users::userLabel($this->createdBy)) ?>
				<?php if (!$codes) { ?>
				<?= $field('Activation Key', (string) $user->activationKey) ?>
				<?= $field('Activation Key Expire', $when($user->activationKeyExpire)) ?>
				<br><br>
				<button class='submit' data-on_click='resendActivationEmail'><i class="far fa-envelope"></i>
					<?= $user->firstLogin ? 'Send new activation E-Mail' : 'Send activation E-Mail' ?>
				</button>
				<?php } ?>
			</div>
		</div>

		<?php if ($codes) { ?>
		<div class="row layout userAccess">
			<div class="halfColumn">
				<h2>Anmeldung</h2>
				<div class="inputGroup">
					<select name="value" data-on_change="updateUserType">
						<option value="person" <?= $isScreen ? '' : 'selected' ?>>Person</option>
						<option value="screen" <?= $isScreen ? 'selected' : '' ?>>Screen (Kiosk, ohne Mensch)</option>
					</select>
					<span class="bar"></span><label>Konto-Art</label>
				</div>
				<?php if ($isScreen) { ?>
					<p>Ein Screen hat kein Passwort. Öffne auf ihm <b>auth/screen/</b> und gib den Code ein, den er zeigt:</p>
					<div class="inputGroup">
						<input type="text" name="value" autocapitalize="characters" spellcheck="off" maxlength="12" data-on_change="approveScreen" placeholder=" "/>
						<span class="bar"></span><label>Code vom Screen</label>
					</div>
				<?php } else { ?>
					<button type="button" class="submit" data-on_click="inviteMail" <?= (string) $user->email === '' ? 'disabled' : '' ?>><i class="far fa-envelope"></i> Einladung per Mail (24 h)</button>
					<button type="button" class="submit" data-on_click="inviteCode"><i class="far fa-qrcode"></i> Einladung als Code (24 h)</button>
					<button type="button" class="submit" data-on_click="recoverCode"><i class="far fa-key"></i> Wiederherstellungs-Code (10 min)</button>
				<?php } ?>
				<?php if ($issued !== NULL) {
					$url = \http::$siteUrl . 'auth/?code=' . rawurlencode($issued['code']);
				?>
					<div class="issuedCode">
						<p><?= $issued['purpose'] === 'invite' ? 'Einladung' : 'Wiederherstellung' ?> – gültig bis <?= $e((new DateTimeImmutable('@' . $issued['expires']))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m. H:i')) ?> Uhr, nur einmal:</p>
						<div class="issuedCodeValue"><?= $e($issued['code']) ?></div>
						<?= class_exists('qr') ? \qr::svgMarkup(\qr::payload('url', ['url' => $url]), 4, 'M', 'qr-code') : '' ?>
						<p>Auf dem Gerät des Benutzers die App öffnen und den Code eingeben – oder den QR-Code scannen.</p>
					</div>
				<?php } ?>
			</div>
			<div class="halfColumn">
				<h2>Geräte</h2>
				<?php if ($devices === []) { ?>
					<p>Noch auf keinem Gerät angemeldet.</p>
				<?php } ?>
				<?php foreach ($devices as $device) { ?>
					<div class="userDevice">
						<span>
							<b><?= $e($device->name) ?></b> · <?= $device->client === 'app' ? 'App' : 'Browser' ?><br>
							<small>zuletzt <?= $e($when($device->lastSeen?->setTimezone(new DateTimeZone('Europe/Berlin')))) ?> · seit <?= $e($device->createdAt?->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y')) ?></small>
						</span>
						<button type="button" class="submit" data-on_click="revokeDevice" data-device_id="<?= (int) $device->id ?>"><i class="far fa-ban"></i> Sperren</button>
					</div>
				<?php } ?>
			</div>
		</div>
		<?php } ?>
	</div>
</main>
