<?php
/** @var \Systopic\System\Panels\Root\Cms\Users\Users\Edit\Panel $this */

use Systopic\System\Auth\DeviceName;
use Systopic\System\Auth\LoginLog;
use Systopic\System\Auth\Portrait;
use Systopic\System\Auth\Presence;
use Systopic\System\Panels\Root\Cms\Users\Panel as Users;

$user = $this->user;
$codes = \Systopic\System\Auth\LoginCodes::available();
$isScreen = $user->type === \Systopic\System\Tables\Users\Model::SCREEN;
$isMe = (int) $user->id === (int) \Systopic\System\Auth\Session::id();
$devices = $this->devices;
$viewer = \Systopic\System\Auth\Session::isSuperuser();
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$when = static fn(?DateTimeImmutable $d): string => \Systopic\System\Sys\LocalTime::format($d, 'j M Y, H:i');
$field = static function (string $label, string $value, ?string $action = null, string $type = 'text', string $class = '') use ($e): string {
	$input = $action === null
		? '<input type="' . $type . '" readonly value="' . $e($value) . '">'
		: '<input type="' . $type . '" autocapitalize="off" name="value" data-on_blur="' . $action . '" value="' . $e($value) . '" autocomplete="off" data-1p-ignore data-lpignore="true" readonly onpointerdown="this.readOnly=false" onkeydown="this.readOnly=false" spellcheck="off">';
	return '<label class="field' . ($class !== '' ? ' ' . $class : '') . '"><span>' . $label . '</span>' . $input . '</label>';
};
$logged = static fn(string $date): string => ($d = DateTimeImmutable::createFromFormat('d.m.Y-H:i:s', $date)) ? \Systopic\System\Sys\LocalTime::format($d, 'j M Y, H:i') : $date;
$tabs = ['general' => 'General', 'roles' => 'Roles', 'access' => 'Access', 'signins' => 'Sign-ins', 'maintenance' => 'Maintenance'];
$tab = (string) ($this->state->userTab ?? 'general');
if (!isset($tabs[$tab])) {
	$tab = 'general';
}
$signInAll = $tab === 'signins' ? LoginLog::entries([(string) $user->name, (string) $user->email]) : [];
$signInTotal = count($signInAll);
$signInsAll = !empty($this->state->signInsAll);
$signIns = $signInsAll ? $signInAll : array_slice($signInAll, 0, 3);
$devicesAll = !empty($this->state->devicesAll);
$devicesShown = $devicesAll ? $devices : array_slice($devices, 0, 3);
$heading = trim((string) $user->fullname);
if ($heading === '') {
	$heading = trim((string) $user->name);
}
if ($heading === '') {
	$heading = trim((string) $user->email);
}
?>
<header>
	<h1><?= $e($heading) ?></h1>
	<ul class="barmenu">
		<li><a class="ajax" href="../" aria-label="Close"><i class="fa-regular fa-xmark"></i></a></li>
	</ul>
</header>
<nav class="dashTabs userTabs" role="tablist">
	<?php foreach ($tabs as $key => $label) { ?>
		<button type="button" role="tab" data-on_click="userTab" data-tab="<?= $key ?>" aria-selected="<?= $key === $tab ? 'true' : 'false' ?>"><?= $label ?></button>
	<?php } ?>
</nav>
<main class="userEdit">
	<?= \message::flush() ?>
	<div class="userPane" data-user-pane="general"<?= $tab === 'general' ? '' : ' hidden' ?>>
	<section class="cmsBox userAccount">
		<header><div class="cmsBoxTitles"><h2>Account</h2><p class="cmsBoxLead">Name, email and the portrait used in the CMS.</p></div></header>
		<div class="cmsBoxBody">
			<?= \message::flush('account') ?>
			<div class="accountPhoto">
				<form class="ajax portraitDrop" method="post" enctype="multipart/form-data" action="<?= $e($this->href . 'uploadPortrait?user_id=' . (int) $user->id) ?>" data-drop-files data-upload="onsubmit">
					<span class="portraitFace"><img src="<?= $e(Portrait::src((int) $user->id, $user->email)) ?>" alt=""></span>
					<span>Drop or click</span>
					<input type="file" name="portrait" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" onchange="this.form.requestSubmit()">
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
				<p class="accountLine">
					<span>Created <?= $e($when($user->createDate) ?: 'unknown') ?><?= $this->createdBy ? ' by ' . $e(Users::userLabel($this->createdBy)) : '' ?></span>
					<span>First sign-in <?= $e($when($user->firstLogin) ?: 'not yet') ?></span>
				</p>
			</div>
		</div>
	</section>
	</div>
	<div class="userPane" data-user-pane="roles"<?= $tab === 'roles' ? '' : ' hidden' ?>>
			<section class="cmsBox">
				<header><div class="cmsBoxTitles"><h2>Roles</h2><p class="cmsBoxLead"><?= $user->isSuperuser ? 'A superuser has every role.' : 'Click a role to give it or take it away.' ?></p></div></header>
				<div class="cmsBoxBody">
					<div class="picks">
						<?php foreach ($this->admin->roles as $role) {
							$on = $user->isSuperuser || $this->admin->assignment($user, $role) !== null;
							$color = ($role->color !== null && $role->color !== '') ? $role->color : '#8a8f93';
							$click = $user->isSuperuser ? '' : ' data-on_click="toggleUserRole" data-role_id="' . (int) $role->id . '"';
							echo '<div class="tag' . ($on ? ' active' : '') . '"' . $click . ' style="--tag-color:' . $e($color) . ';--tag-bg:' . $e($color) . ';background:' . $e($color) . ';border-color:' . $e($color) . '">' . $e($role->name) . '</div>';
						} ?>
					</div>
					<?php if ($viewer) { ?>
						<div class="userRow">
							<p class="tagsHint"><?= $user->isSuperuser ? 'Superuser: manages every user.' : 'A normal user.' ?><?= $isMe ? ' Not to be changed on your own account.' : '' ?></p>
							<?php if (!$isMe && $user->isSuperuser) { ?>
								<button type="button" class="button" data-on_click="revokeSuperuser" data-user_id="<?= (int) $user->id ?>"><i class="fa-regular fa-user-shield"></i>Revoke superuser</button>
							<?php } elseif (!$isMe) { ?>
								<button type="button" class="button" data-on_click="makeSuperuser" data-user_id="<?= (int) $user->id ?>"><i class="fa-regular fa-user-shield"></i>Make superuser</button>
							<?php } ?>
						</div>
					<?php } ?>
				</div>
			</section>
	</div>
	<div class="userPane" data-user-pane="signins"<?= $tab === 'signins' ? '' : ' hidden' ?>>
			<section class="cmsBox">
				<header>
					<div class="cmsBoxTitles"><h2>Sign-ins</h2><p class="cmsBoxLead">Sign-ins, failed attempts and sign-outs. Newest first.</p></div>
				</header>
				<div class="cmsBoxBody">
					<?php if ($signIns === []) { ?>
						<p class="profileEmpty">No sign-ins logged yet.</p>
					<?php } else { ?>
						<ul class="signInList">
							<?php foreach ($signIns as $entry) { ?>
								<li>
									<span><?= $e($logged($entry[0])) ?></span>
									<span><?= $e($entry[2]) ?><?= $entry[4] !== '' ? ' · ' . $e(LoginLog::methodLabel($entry[4])) : '' ?></span>
									<small><?= $e($entry[3]) ?></small>
								</li>
							<?php } ?>
						</ul>
					<?php } ?>
					<?php
					$moreSignIns = !$signInsAll && $signInTotal > count($signIns);
					$cleanLog = $viewer && $signInTotal > 0;
					if ($moreSignIns || $cleanLog) { ?>
						<div class="signInTools">
							<?php if ($moreSignIns) { ?>
								<button type="button" class="cmsBoxMore" data-on_click="moreSignIns">Show all sign-ins (<?= (int) $signInTotal ?>) <i class="fa-regular fa-chevron-down"></i></button>
							<?php } ?>
							<?php if ($cleanLog) { ?>
								<button type="button" class="button" data-on_click="userConfirm" data-action="clearSignIns" data-confirm="Clean this user's sign-in log? Only this user's lines are removed." data-user_id="<?= (int) $user->id ?>"><i class="fa-regular fa-broom"></i> Clean User Log</button>
							<?php } ?>
						</div>
					<?php } ?>
				</div>
			</section>
	</div>
	<div class="userPane" data-user-pane="access"<?= $tab === 'access' ? '' : ' hidden' ?>>
			<?php
			$envFile = null;
			foreach (\Systopic\System\Settings\EnvironmentFile::files() as $info) {
				if ($info['current'] && $info['file'] !== 'config.local.json') {
					$envFile = $info['file'];
					break;
				}
			}
			$canAuth = \Systopic\System\Auth\Session::hasOneRole('admin');
			if (!$codes) { ?>
				<section class="cmsBox">
					<header>
						<div class="cmsBoxTitles">
							<h2>Sign-in key</h2>
						</div>
					</header>
					<div class="cmsBoxBody">
						<p class="cmsBoxLead">The sign-in key is missing. One-time codes no longer work, including codes already sent. Passwords, passkeys and trusted devices still do. Lifetimes stay in Environment.</p>
					</div>
					<?php if ($canAuth && $envFile !== null) { ?>
						<div class="cmsBoxActions">
							<button type="button" class="button" data-on_click="createAuth" data-file="<?= $e($envFile) ?>"><i class="fa-regular fa-key-skeleton"></i> Create sign-in key</button>
						</div>
					<?php } ?>
				</section>
			<?php } ?>
			<?php if ($codes) { ?>
				<section class="cmsBox userAccess">
					<div class="cmsBoxSplit">
						<div>
							<h2>Account type</h2>
							<p class="cmsBoxLead">A person signs in. A screen is a device that is paired.</p>
							<label class="field">
								<select name="value" data-on_change="updateUserType" aria-label="Account type">
									<option value="person" <?= $isScreen ? '' : 'selected' ?>>Person</option>
									<option value="screen" <?= $isScreen ? 'selected' : '' ?>>Screen</option>
								</select>
							</label>
							<?php if ($isScreen) { ?>
								<p class="cmsBoxLead">On a signed-in phone, scan the code in the account or open the link, then choose the screen.</p>
							<?php } else { ?>
								<p class="cmsBoxLead">Use this when the account has no signed-in device yet.</p>
							<?php } ?>
						</div>
						<div class="accessStack">
							<?php if ($isScreen) { ?>
								<div class="screenCode">
									<h2>Code from the screen</h2>
									<div class="cmsCodeSlots" data-code-slots>
										<?php for ($slot = 0; $slot < 8; $slot++) {
											if ($slot === 4) {
												echo '<span class="cmsCodeDash" aria-hidden="true">-</span>';
											}
										?>
										<input data-slot="<?= $slot ?>" inputmode="text" autocapitalize="characters" spellcheck="false" autocomplete="<?= $slot === 0 ? 'one-time-code' : 'off' ?>" aria-label="Character <?= $slot + 1 ?>" maxlength="8">
										<?php } ?>
										<input type="hidden" name="value" data-on_change="approveScreen" autocomplete="off">
									</div>
								</div>
							<?php } else { ?>
								<div>
									<button type="button" class="button submit" data-on_click="inviteCode"><i class="fa-regular fa-qrcode"></i> Create code</button>
									<p class="cmsBoxLead">The code is valid for 10 minutes.</p>
								</div>
								<div>
									<button type="button" class="button" data-on_click="inviteMail" <?= (string) $user->email === '' ? 'disabled' : '' ?>><i class="fa-regular fa-envelope"></i> Send invitation code</button>
									<p class="cmsBoxLead">The code in the email is valid for 24 hours.</p>
								</div>
								<?php if (!$user->firstLogin) { ?>
									<button type="button" class="button" data-on_click="resendActivationEmail" <?= (string) $user->email === '' ? 'disabled' : '' ?>><i class="fa-regular fa-paper-plane"></i> Email a password link</button>
								<?php } ?>
							<?php } ?>
						</div>
					</div>
				</section>
			<?php } elseif (!$isScreen) { ?>
				<section class="cmsBox">
					<header><div class="cmsBoxTitles"><h2>Password link</h2><p class="cmsBoxLead"><?php if ((string) $user->activationKey !== '') { ?>An activation link is out, valid until <?= $e($when($user->activationKeyExpire)) ?>.<?php } else { ?>Sends a link to set a password.<?php } ?></p></div></header>
					<div class="cmsBoxActions">
						<?php if ((string) $user->activationKey !== '') {
							$activationLink = \http::$siteUrl . 'cms/auth/reset?key=' . rawurlencode((string) $user->activationKey);
						?>
							<button type="button" class="button" data-copy="<?= $e($activationLink) ?>"><i class="fa-regular fa-copy"></i> Copy activation link</button>
						<?php } ?>
						<button type="button" class="button" data-on_click="resendActivationEmail" data-user_id="<?= (int) $user->id ?>" <?= (string) $user->email === '' ? 'disabled' : '' ?>><i class="fa-regular fa-paper-plane"></i> <?= $user->firstLogin ? 'Email a new password link' : 'Email a password link' ?></button>
					</div>
				</section>
			<?php } ?>
			<?php if ($codes) { ?>
			<section class="cmsBox">
				<header>
					<div class="cmsBoxTitles"><h2>Trusted devices</h2><p class="cmsBoxLead">Devices that can access this account.</p></div>
				</header>
				<div class="cmsBoxBody">
					<?php if ($devices === []) { ?>
						<p class="profileEmpty">No devices yet.</p>
					<?php } ?>
					<?php foreach ($devicesShown as $device) { ?>
						<div class="userDevice">
							<i class="<?= $e(DeviceName::icon((string) $device->userAgent)) ?>" aria-hidden="true"></i>
							<?php [$presence, $presenceTitle] = Presence::deviceMark((int) $device->id); ?>
							<span class="presence<?= $presence !== '' ? ' ' . $presence : '' ?>" title="<?= $e($presenceTitle) ?>"></span>
							<span>
								<b><?= $e($device->name) ?></b>
								<?= $device->client === 'app' ? 'App' : 'Browser' ?>
								<?php $mine = \Systopic\System\Auth\Devices::current();
							$isMine = $mine && (int) $mine->id === (int) $device->id; ?>
							<small><?php if ($isMine) { ?>This device · <?php } ?>Last seen <?= $e($when($device->lastSeen)) ?><?php
								$paired = LoginLog::paired(trim((string) ($user->name ?: $user->email)), $device->createdAt);
								if ($paired !== '') { ?><br><?= $e($paired) ?><?php } ?></small>
							</span>
							<?php if (!$isMine) { ?>
								<button type="button" class="button transparent icononly" data-on_click="userConfirm" data-action="revokeDevice" data-device_id="<?= (int) $device->id ?>" data-confirm="Sign this device out?" aria-label="Revoke" title="Sign out"><i class="<?= $e(DeviceName::slash((string) $device->userAgent)) ?>" aria-hidden="true"></i></button>
							<?php } ?>
						</div>
					<?php } ?>
					<?php if (!$devicesAll && count($devices) > count($devicesShown)) { ?>
						<button type="button" class="cmsBoxMore" data-on_click="showAllDevices">Show all devices (<?= count($devices) ?>) <i class="fa-regular fa-chevron-down"></i></button>
					<?php } ?>
				</div>
			</section>
			<?php } ?>
			<?= \Systopic\System\Auth\Passkeys::render($user) ?>
	</div>
	<div class="userPane" data-user-pane="maintenance"<?= $tab === 'maintenance' ? '' : ' hidden' ?>>
		<section class="cmsBox">
			<header>
				<div class="cmsBoxTitles">
					<h2>Reset layout</h2>
					<p class="cmsBoxLead">Puts every module back to its defaults for this user, at their next click.</p>
				</div>
			</header>
			<div class="cmsBoxActions">
				<button type="button" class="button" data-on_click="userConfirm" data-action="resetState" data-confirm="Reset the layout of every module to its defaults?" data-user_id="<?= (int) $user->id ?>"><i class="fa-regular fa-arrow-rotate-left"></i> Reset layout</button>
			</div>
		</section>
		<?php if (!$isMe) { ?>
			<section class="cmsBox">
				<header>
					<div class="cmsBoxTitles">
						<h2>Delete user</h2>
						<p class="cmsBoxLead">Removes the account, its roles and its devices. This cannot be undone.</p>
					</div>
				</header>
				<div class="cmsBoxActions">
					<button type="button" class="button" data-on_click="userConfirm" data-action="removeUser" data-confirm="Delete this user? This cannot be undone." data-user_id="<?= (int) $user->id ?>"><i class="fa-regular fa-trash"></i> Delete user</button>
				</div>
			</section>
		<?php } ?>
	</div>
</main>
