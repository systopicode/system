<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Users\Edit\Panel $this */

use Systopic\System\Auth\Portrait;
use Systopic\System\Panels\Root\Cms\Users\Panel as Users;

$user = $this->user;
$codes = \Systopic\System\Auth\LoginCodes::available();
$isScreen = $user->type === \Systopic\System\Tables\Users\Model::SCREEN;
$devices = $codes ? $this->devices : [];
$issued = $this->issuedCode;
$viewer = \Systopic\System\Auth\Session::isSuperuser();
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$when = static fn(?DateTimeImmutable $d): string => $d?->format('j M Y, H:i') ?? '';
$field = static function (string $label, string $value, ?string $action = null, string $type = 'text', string $class = '') use ($e): string {
	$input = $action === null
		? '<input type="' . $type . '" readonly value="' . $e($value) . '">'
		: '<input type="' . $type . '" autocapitalize="off" name="value" data-on_blur="' . $action . '" value="' . $e($value) . '" autocomplete="off" spellcheck="off">';
	return '<label class="field' . ($class !== '' ? ' ' . $class : '') . '"><span>' . $label . '</span>' . $input . '</label>';
};
?>
<header>
	<h1><?= $e(trim((string) $user->fullname) !== '' ? $user->fullname : $user->name) ?></h1>
	<ul class="barmenu">
		<li><a class="ajax" href="../" title="Close"><i class="fa-regular fa-xmark"></i></a></li>
	</ul>
</header>
<main class="userEdit">
	<?= \message::flush() ?>
	<div class="widgets">
		<section class="widget userAccount is-wide">
			<header><h2><i class="fa-regular fa-id-card"></i>Account</h2></header>
			<main>
				<div class="accountPhoto">
					<form class="portraitDrop" method="post" enctype="multipart/form-data" target="_self" action="<?= $e($this->href . 'uploadPortrait?user_id=' . (int) $user->id) ?>">
						<span class="portraitFace"><img src="<?= $e(Portrait::src((int) $user->id, $user->email)) ?>" alt=""></span>
						<span>Drop or click</span>
						<input type="file" name="portrait" accept="image/jpeg,image/png,image/webp,image/gif" onchange="this.form.submit()">
					</form>
					<?php if (Portrait::hasFile((int) $user->id)) { ?>
						<button type="button" class="button" data-on_click="removePortrait" data-user_id="<?= (int) $user->id ?>"><i class="fa-regular fa-trash"></i>Remove</button>
					<?php } ?>
				</div>
				<div class="accountFields">
					<div class="fieldGrid is-stack">
						<?= $field('Username', (string) $user->name, 'updateUserName') ?>
						<?= $field('Full name', (string) $user->fullname, 'updateUserFullname') ?>
						<?= $field('Email', (string) $user->email, 'updateUserEmail', 'email') ?>
					</div>
					<div class="fieldGrid is-stack">
						<?= $field('Last sign-in', $when($user->lastLogin) !== '' ? $when($user->lastLogin) : 'Unknown') ?>
						<?= $field('First sign-in', $when($user->firstLogin) !== '' ? $when($user->firstLogin) : 'Unknown') ?>
						<?= $field('Created', trim($when($user->createDate) . ($this->createdBy ? ' by ' . Users::userLabel($this->createdBy) : ''))) ?>
					</div>
				</div>
			</main>
		</section>
		<section class="widget">
			<header><h2><i class="fa-regular fa-user-tag"></i>Roles</h2></header>
			<main>
				<p class="tagsHint"><?= $user->isSuperuser ? 'A superuser has every role.' : 'Click a role to give it or take it away.' ?></p>
				<div class="picks">
					<?php foreach ($this->admin->roles as $role) {
						$on = $user->isSuperuser || $this->admin->assignment($user, $role) !== null;
						$color = ($role->color !== null && $role->color !== '') ? $role->color : '#8a8f93';
						$click = $user->isSuperuser ? '' : ' data-on_click="toggleUserRole" data-role_id="' . (int) $role->id . '"';
						echo '<div class="tag' . ($on ? ' active' : '') . '"' . $click . ' style="--tag-color:' . $e($color) . ';--tag-bg:' . $e($color) . ';background:' . $e($color) . ';border-color:' . $e($color) . '">' . $e($role->name) . '</div>';
					} ?>
				</div>
			</main>
		</section>
		<?php if ($viewer) { ?>
			<section class="widget">
				<header><h2><i class="fa-regular fa-user-shield"></i>Superuser</h2></header>
				<main>
					<p class="tagsHint"><?= $user->isSuperuser ? 'This account can manage every user.' : 'This account is a normal user.' ?></p>
					<?php if ((int) $user->id === (int) \Systopic\System\Auth\Session::id()) { ?>
						<p class="profileEmpty">You cannot change this on your own account.</p>
					<?php } elseif ($user->isSuperuser) { ?>
						<button type="button" class="button" data-on_click="revokeSuperuser" data-user_id="<?= (int) $user->id ?>"><i class="fa-regular fa-user-shield"></i>Revoke superuser</button>
					<?php } else { ?>
						<button type="button" class="button submit" data-on_click="makeSuperuser" data-user_id="<?= (int) $user->id ?>"><i class="fa-regular fa-user-shield"></i>Make superuser</button>
					<?php } ?>
				</main>
			</section>
		<?php } ?>
		<?php if ($codes) { ?>
			<section class="widget userAccess">
				<header><h2><i class="fa-regular fa-envelope"></i>Access</h2></header>
				<main>
					<?php if ($this->accessNote !== null) { ?>
						<p class="accessNote"><?= $e($this->accessNote) ?></p>
					<?php } ?>
					<label class="field">
						<span>Account type</span>
						<select name="value" data-on_change="updateUserType">
							<option value="person" <?= $isScreen ? '' : 'selected' ?>>Person</option>
							<option value="screen" <?= $isScreen ? 'selected' : '' ?>>Screen</option>
						</select>
					</label>
					<?php if ($isScreen) { ?>
						<p class="tagsHint">A screen has no password. Open auth/screen/ on it and enter the code it shows.</p>
						<label class="field">
							<span>Code from the screen</span>
							<input type="text" name="value" autocapitalize="characters" spellcheck="off" maxlength="12" data-on_change="approveScreen" autocomplete="off">
						</label>
					<?php } else { ?>
						<button type="button" class="button" data-on_click="inviteMail" <?= (string) $user->email === '' ? 'disabled' : '' ?>><i class="fa-regular fa-envelope"></i>Email invite</button>
						<button type="button" class="button" data-on_click="inviteCode"><i class="fa-regular fa-qrcode"></i>Invite code</button>
						<button type="button" class="button" data-on_click="recoverCode"><i class="fa-regular fa-key"></i>Recovery code</button>
						<button type="button" class="button" data-on_click="resendActivationEmail" <?= (string) $user->email === '' ? 'disabled' : '' ?>><i class="fa-regular fa-envelope"></i><?= $user->firstLogin ? 'Resend activation email' : 'Send activation email' ?></button>
					<?php } ?>
					<?php if ($issued !== null) {
						$url = \http::$siteUrl . 'auth/?code=' . rawurlencode($issued['code']);
						$until = (new DateTimeImmutable('@' . $issued['expires']))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('j M, H:i');
					?>
						<div class="issuedCode">
							<p><?= $issued['purpose'] === 'invite' ? 'Invite' : 'Recovery' ?>, valid until <?= $e($until) ?>, once.</p>
							<div class="issuedCodeValue"><?= $e($issued['code']) ?></div>
							<?= class_exists('qr') ? \qr::svgMarkup(\qr::payload('url', ['url' => $url]), 4, 'M', 'qr-code') : '' ?>
						</div>
					<?php } ?>
				</main>
			</section>
		<?php } elseif (!$isScreen) { ?>
			<section class="widget">
				<header><h2><i class="fa-regular fa-envelope"></i>Access</h2></header>
				<main>
					<?php if ($this->accessNote !== null) { ?>
						<p class="accessNote"><?= $e($this->accessNote) ?></p>
					<?php } ?>
					<?php if ((string) $user->activationKey !== '') {
						$activationLink = \http::$siteUrl . 'cms/auth/reset?key=' . rawurlencode((string) $user->activationKey);
					?>
						<div class="accessBar">
							<?= $field('Activation key', (string) $user->activationKey) ?>
							<?= $field('Key expires', $when($user->activationKeyExpire)) ?>
							<div class="accessButtons">
								<button type="button" class="button" data-on_click="resendActivationEmail" data-user_id="<?= (int) $user->id ?>" <?= (string) $user->email === '' ? 'disabled' : '' ?>><i class="fa-regular fa-envelope"></i><?= $user->firstLogin ? 'Resend activation email' : 'Send activation email' ?></button>
								<button type="button" class="button" data-copy="<?= $e($activationLink) ?>"><i class="fa-regular fa-copy"></i>Copy activation link</button>
							</div>
						</div>
					<?php } else { ?>
						<p class="tagsHint">Sends a fresh activation link to this account.</p>
						<button type="button" class="button" data-on_click="resendActivationEmail" data-user_id="<?= (int) $user->id ?>" <?= (string) $user->email === '' ? 'disabled' : '' ?>><i class="fa-regular fa-envelope"></i><?= $user->firstLogin ? 'Resend activation email' : 'Send activation email' ?></button>
					<?php } ?>
				</main>
			</section>
		<?php } ?>
		<?php if ((int) $user->id !== (int) \Systopic\System\Auth\Session::id()) { ?>
			<section class="widget">
				<header><h2><i class="fa-regular fa-trash"></i>Delete user</h2></header>
				<main>
					<p class="tagsHint">Removes the account, its roles and its devices. This cannot be undone.</p>
					<button type="button" class="button" data-on_click="userConfirm" data-action="removeUser" data-confirm="Delete this user? This cannot be undone." data-user_id="<?= (int) $user->id ?>"><i class="fa-regular fa-trash"></i>Delete user</button>
				</main>
			</section>
		<?php } ?>
		<section class="widget">
			<header><h2><i class="fa-regular fa-key"></i>Passkeys</h2></header>
			<main>
				<p class="tagsHint">Sign in from a device without typing a password.</p>
				<p class="profileEmpty">No passkeys yet.</p>
			</main>
		</section>
		<section class="widget">
			<header><h2><i class="fa-regular fa-phone-laptop"></i>Devices</h2></header>
			<main>
				<p class="tagsHint">Browsers and phones that stay signed in.</p>
				<?php if ($devices === []) { ?>
					<p class="profileEmpty">No devices yet.</p>
				<?php } ?>
				<?php foreach ($devices as $device) { ?>
					<div class="userDevice">
						<span>
							<b><?= $e($device->name) ?></b>
							<?= $device->client === 'app' ? 'App' : 'Browser' ?>
							<small>Last seen <?= $e($when($device->lastSeen)) ?></small>
						</span>
						<button type="button" class="button" data-on_click="revokeDevice" data-device_id="<?= (int) $device->id ?>"><i class="fa-regular fa-xmark"></i>Revoke</button>
					</div>
				<?php } ?>
			</main>
		</section>
	</div>
</main>
