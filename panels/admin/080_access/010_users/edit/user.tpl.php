<?php
/** @var \Systopic\System\Panels\Root\Admin\Access\Users\Edit\Panel $this */

use Systopic\System\Auth\DeviceName;
use Systopic\System\Auth\SignInLog;
use Systopic\System\Auth\Portrait;
use Systopic\System\Auth\Presence;
use Systopic\System\Panels\Root\Admin\Access\Panel as Users;

$user = $this->user;
$codes = \Systopic\System\Auth\SignInCodes::available();
$isScreen = $user->type === \Systopic\System\Tables\Users\Model::SCREEN;
$isMe = (int) $user->id === (int) \Systopic\System\Auth\Session::id();
$devices = $this->devices;
$viewer = \Systopic\System\Auth\Session::isSuperuser();
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$when = static fn(?DateTimeImmutable $d): string => \Systopic\System\Sys\LocalTime::format($d, \Systopic\System\Sys\LocalTime::DATETIME);
$field = static function (string $label, string $value, ?string $action = null, string $type = 'text', string $class = '') use ($e): string {
	$input = $action === null
		? '<input type="' . $type . '" readonly value="' . $e($value) . '">'
		: '<input type="' . $type . '" autocapitalize="off" name="value" data-on_blur="' . $action . '" value="' . $e($value) . '" autocomplete="off" data-1p-ignore data-lpignore="true" readonly onpointerdown="this.readOnly=false" onkeydown="this.readOnly=false" spellcheck="off">';
	return '<label class="field' . ($class !== '' ? ' ' . $class : '') . '"><span>' . $label . '</span>' . $input . '</label>';
};
$logged = static fn(string $date): string => ($d = DateTimeImmutable::createFromFormat('d.m.Y-H:i:s', $date)) ? \Systopic\System\Sys\LocalTime::format($d, \Systopic\System\Sys\LocalTime::DATETIME) : $date;
$accountLabel = trim((string) ($user->name ?: $user->email));
$mailNote = static function (string $kind) use ($accountLabel, $logged): string {
	$sent = $accountLabel === '' ? null : SignInLog::lastMailed($kind, $accountLabel);
	if ($sent === null) {
		return '';
	}
	$line = 'Last sent ' . $logged($sent[0]);
	if ($sent[1] !== '') {
		$line .= ' by ' . $sent[1];
	}
	return $line . '.';
};
$tabs = ['general' => 'General', 'roles' => 'Roles', 'access' => 'Access', 'signins' => 'Sign-ins', 'maintenance' => 'Maintenance'];
if ($viewer && !$isScreen) $tabs = array_slice($tabs, 0, 4, true) + ['email' => 'E-Mail Log'] + array_slice($tabs, 4, null, true);
$tab = (string) ($this->state->userTab ?? 'general');
if (!isset($tabs[$tab])) {
	$tab = 'general';
}
$signInAll = $tab === 'signins' ? SignInLog::entries([(string) $user->name, (string) $user->email]) : [];
$signInTotal = count($signInAll);
$signInsAll = !empty($this->state->signInsAll);
$signIns = $signInsAll ? $signInAll : array_slice($signInAll, 0, 3);
$devicesAll = !empty($this->state->devicesAll);
$devicesShown = $devicesAll ? $devices : array_slice($devices, 0, 3);
$heading = trim((string) ($isScreen ? $user->name : $user->fullname));
if ($heading === '') {
	$heading = trim((string) ($isScreen ? $user->fullname : $user->name));
}
if ($heading === '') {
	$heading = trim((string) $user->email);
}
?>
<header>
	<h1><?= $e($heading) ?></h1>
	<ul class="barmenu">
		<li><a class="ajax" href="../" aria-label="Close" data-focus-id="user-edit-close"><i class="cmsIcon cmsIcon-xmark"></i></a></li>
	</ul>
</header>
<nav class="dashTabs userTabs" role="tablist">
	<?php foreach ($tabs as $key => $label) { ?>
		<button type="button" role="tab" data-on_click="userTab" data-tab="<?= $key ?>" aria-selected="<?= $key === $tab ? 'true' : 'false' ?>"><?= $label ?></button>
	<?php } ?>
</nav>
<main class="userEdit userTab-<?= $e($tab) ?>">
	<?= \message::flush() ?>
	<div class="userPane" data-user-pane="general"<?= $tab === 'general' ? '' : ' hidden' ?>>
	<section class="cmsBox userAccount">
		<header><div class="cmsBoxTitles"><h2>Account</h2><p class="cmsBoxLead"><?= $isScreen ? 'Name, description and the picture used for this screen.' : 'Name, email and the portrait used in the System.' ?></p></div></header>
		<div class="cmsBoxBody">
			<?= \message::flush('account') ?>
			<div class="accountPhoto">
				<form class="ajax portraitDrop" method="post" enctype="multipart/form-data" action="<?= $e($this->href . 'uploadPortrait?user_id=' . (int) $user->id) ?>" data-drop-files data-upload="onsubmit">
					<span class="portraitFace"><img referrerpolicy="no-referrer" src="<?= $e(Portrait::src((int) $user->id, $user->email, $isScreen)) ?>" alt=""></span>
					<span>Drop or click</span>
					<input type="file" aria-label="Upload profile picture" title="Upload profile picture" name="portrait" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" onchange="this.form.requestSubmit()">
				</form>
				<?php if (Portrait::hasFile((int) $user->id)) { ?>
					<button type="button" class="button" data-on_click="removePortrait" data-user_id="<?= (int) $user->id ?>"><i class="cmsIcon cmsIcon-trash"></i>Remove</button>
				<?php } ?>
			</div>
			<div class="accountFields">
				<div class="fieldGrid is-stack">
					<?php if ($isScreen) { ?>
						<?= $field('Name', (string) $user->name, 'updateUserName') ?>
						<?= $field('Description', (string) $user->fullname, 'updateUserFullname') ?>
					<?php } else { ?>
						<?= $field('Username', (string) $user->name, 'updateUserName') ?>
						<?= $field('Full name', (string) $user->fullname, 'updateUserFullname') ?>
						<?= $field('Email', (string) $user->email, 'updateUserEmail', 'email') ?>
					<?php } ?>
					<label class="field">
						<span>Account type</span>
						<input type="text" class="is-locked" value="<?= $isScreen ? 'Screen' : 'Person' ?>" disabled tabindex="-1" aria-disabled="true">
					</label>
				</div>
				<p class="accountLine">
					<span>Created <?= $e($when($user->createDate) ?: 'unknown') ?><?= $this->createdBy ? ' by ' . $e(Users::userLabel($this->createdBy)) : '' ?></span>
					<span>First sign-in <?= $e($when($user->firstSignIn) ?: 'not yet') ?></span>
				</p>
			</div>
		</div>
	</section>
	</div>
	<div class="userPane" data-user-pane="roles"<?= $tab === 'roles' ? '' : ' hidden' ?>>
			<section class="cmsBox">
				<header><div class="cmsBoxTitles"><h2>Roles</h2><p class="cmsBoxLead"><?= $user->isSuperuser ? 'A superuser has every role.' : 'Click a role to give it or take it away.' ?></p></div></header>
				<div class="cmsBoxBody">
					<?php
					// held above, the rest below: which one is on reads off its place, not its shade
					$held = [];
					$other = [];
					foreach ($this->admin->assignableRoles() as $role) {
						if ($user->isSuperuser || $this->admin->assignment($user, $role) !== null) {
							$held[] = $role;
						} else {
							$other[] = $role;
						}
					}
					$chip = static function ($role, bool $on) use ($e, $user): string {
						$color = ($role->color !== null && $role->color !== '') ? $role->color : '#8a8f93';
						$click = $user->isSuperuser ? '' : ' data-on_click="toggleUserRole" data-role_id="' . (int) $role->id . '"';
						$title = $user->isSuperuser ? '' : ' title="' . ($on ? 'Take away' : 'Give') . '"';
						$icon = $user->isSuperuser ? '' : '<i class="cmsIcon cmsIcon-' . ($on ? 'xmark' : 'plus') . '"></i>';
						return '<div class="tag' . ($on ? ' active' : '') . '"' . $click . $title . ' style="--tag-color:' . $e($color) . ';--tag-bg:' . $e($color) . ';border-color:' . $e($color) . '">' . $e($role->name) . $icon . '</div>';
					};
					?>
					<h3 class="picksLabel"><?= $user->isSuperuser ? 'Every role' : 'Has' ?></h3>
					<div class="picks rolesHeld">
						<?= $held === [] ? '<p class="tagsHint">No role yet.</p>' : implode('', array_map(static fn($r) => $chip($r, true), $held)) ?>
					</div>
					<?php if ($other !== []) { ?>
						<h3 class="picksLabel">Other roles</h3>
						<?php if (count($other) > 8) { ?>
							<input type="search" class="picksFilter" placeholder="Find a role" autocomplete="off"
								   oninput="const q = this.value.trim().toLowerCase(); this.nextElementSibling.querySelectorAll('.tag').forEach(t => t.hidden = q !== '' && !t.textContent.toLowerCase().includes(q))">
						<?php } ?>
						<div class="picks rolesOther">
							<?= implode('', array_map(static fn($r) => $chip($r, false), $other)) ?>
						</div>
					<?php } ?>
					<?php if ($viewer && $isScreen) { ?>
						<div class="userRow">
							<p class="tagsHint">A screen cannot be a superuser.</p>
							<?php if ($user->isSuperuser) { ?>
								<button type="button" class="button" data-on_click="userConfirm" data-action="revokeSuperuser" data-confirm="Revoke superuser access for this account?" data-user_id="<?= (int) $user->id ?>"><i class="cmsIcon cmsIcon-user-shield"></i>Revoke superuser</button>
							<?php } ?>
						</div>
					<?php } elseif ($viewer && !$user->isBlocked) { ?>
						<div class="userRow">
							<p class="tagsHint"><?= $user->isSuperuser ? 'Superuser: manages every user.' : 'A normal user.' ?><?= $isMe ? ' Not to be changed on your own account.' : '' ?></p>
							<?php if (!$isMe && $user->isSuperuser) { ?>
								<button type="button" class="button" data-on_click="userConfirm" data-action="revokeSuperuser" data-confirm="Revoke superuser access for this account?" data-user_id="<?= (int) $user->id ?>"><i class="cmsIcon cmsIcon-user-shield"></i>Revoke superuser</button>
							<?php } elseif (!$isMe) { ?>
								<button type="button" class="button" data-on_click="userConfirm" data-action="makeSuperuser" data-confirm="Give this account full superuser access?" data-user_id="<?= (int) $user->id ?>"><i class="cmsIcon cmsIcon-user-shield"></i>Make superuser</button>
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
					<p class="cmsBoxLead">Entries older than <?= (int) \Systopic\System\Maintenance\LogRetention::SIGN_IN_DAYS ?> days are eligible for automatic cleanup during site visits or scheduled runs. The retention period is a fixed system rule, not a project setting. Delete entries earlier when their security purpose ends. Review retention regularly and use Clean User Log when these entries are no longer required; include retained copies and backups in that process.</p>
					<?php if ($signIns === []) { ?>
						<p class="profileEmpty">No sign-ins logged yet.</p>
					<?php } else { ?>
						<ul class="signInList">
							<?php foreach ($signIns as $entry) { ?>
								<li>
									<span><?= $e($logged($entry[0])) ?></span>
									<span><?= $e($entry[2]) ?><?= $entry[4] !== '' ? ' · ' . $e(SignInLog::methodLabel($entry[4])) : '' ?></span>
									<small><?= $e($entry[3]) ?></small>
								</li>
							<?php } ?>
						</ul>
					<?php } ?>
					<?php
					$moreSignIns = $signInTotal > 3;
					$cleanLog = \Systopic\System\Auth\Session::isSuperuser() && $signInTotal > 0;
					if ($moreSignIns || $cleanLog) { ?>
						<div class="signInTools">
							<?php if ($moreSignIns) { ?>
								<button type="button" class="cmsBoxMore" data-on_click="moreSignIns"><?= $signInsAll ? 'Show less' : 'Show all sign-ins (' . (int) $signInTotal . ')' ?> <i class="cmsIcon cmsIcon-chevron-<?= $signInsAll ? 'up' : 'down' ?>"></i></button>
							<?php } ?>
							<?php if ($cleanLog) { ?>
								<button type="button" class="button" data-on_click="userConfirm" data-action="clearSignIns" data-confirm="Clean this user's sign-in log? Only this user's lines are removed." data-user_id="<?= (int) $user->id ?>"><i class="cmsIcon cmsIcon-broom"></i> Clean User Log</button>
							<?php } ?>
						</div>
					<?php } ?>
				</div>
			</section>
	</div>
    <?php if ($viewer && !$isScreen && $tab === 'email') { ?>
    <div class="userPane" data-user-pane="email">
        <section class="cmsBox">
            <header><div class="cmsBoxTitles"><h2>E-Mail Log</h2><p class="cmsBoxLead">Messages addressed to this account's current email address. Newest first. Technical log retention: 90 days. Code-message contents are omitted by the sender.</p></div></header>
            <div class="cmsBoxBody">
            <?php $emails = \Systopic\System\Maintenance\RetainedLogs::emailsFor((string) $user->email); ?>
            <?php if ($emails === []) { ?><p class="profileEmpty">No messages logged for this email address.</p><?php } ?>
            <?php $emailTotal = count($emails); $emailsAll = !empty($this->state->emailsAll); foreach ($emailsAll ? $emails : array_slice($emails, 0, 3) as $mailIndex => $mail) { ?>
                <div class="userMailEntry">
                    <div><i class="cmsIcon cmsIcon-envelope"></i> <?= $e($mail['subject'] ?? '') ?> <small><?= $e($mail['timestamp'] ?? '') ?> · <?= $e($mail['status'] ?? '') ?></small></div>
                    <div class="userMailContent"><button type="button" class="button transparent icononly" aria-label="Preview email" data-feedback="none" data-open-box="#userMailPreview<?= (int) $user->id ?>-<?= (int) $mailIndex ?>"><i class="cmsIcon cmsIcon-eye"></i></button></div>
                    <div class="cms cmslightbox cmsNestedDialog mailPreviewBox" data-mobile-dialog="static" id="userMailPreview<?= (int) $user->id ?>-<?= (int) $mailIndex ?>" data-size="1" data-feedback="none" hidden><section>
                        <header><h1><?= $e($mail['subject'] ?? 'Email') ?></h1><ul class="barmenu"><li><button type="button" data-box-close aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button></li></ul></header>
                        <main class="mailLog"><?php
                            $mailDetails = $mail;
                            include __DIR__ . '/../../../090_tools/logs/email/details.tpl.php';
                        ?></main>
                    </section></div>
                </div>
            <?php } ?>
            <?php if ($emailTotal > 0) { ?>
            <div class="signInTools">
                <?php if ($emailTotal > 3) { ?><button type="button" class="cmsBoxMore" data-feedback="none" data-on_click="moreEmails"><?= $emailsAll ? 'Show less' : 'Show all messages (' . $emailTotal . ')' ?> <i class="cmsIcon cmsIcon-chevron-<?= $emailsAll ? 'up' : 'down' ?>"></i></button><?php } ?>
                <button type="button" class="button" data-on_click="userConfirm" data-action="clearEmails" data-confirm="Clear email log entries addressed to this account only?" data-user_id="<?= (int) $user->id ?>"><i class="cmsIcon cmsIcon-broom"></i>Clear user log</button>
            </div>
            <?php } ?>
            </div>
        </section>
    </div>
    <?php } ?>
	<div class="userPane" data-user-pane="access"<?= $tab === 'access' ? '' : ' hidden' ?>>

<?php if (!$user->isBlocked): ?>
			<?php if (!$codes) { ?>
				<section class="cmsBox">
					<header>
						<div class="cmsBoxTitles">
							<h2>Sign-in key</h2>
						</div>
					</header>
					<div class="cmsBoxBody">
						<p class="cmsBoxLead">The sign-in key could not be written. One-time codes do not work until the config of this instance can be changed. Passwords, passkeys and trusted devices still do.</p>
					</div>
				</section>
			<?php } elseif ($isScreen) { ?>
				<section class="cmsBox userAccess">
					<header><div class="cmsBoxTitles"><h2>Pairing</h2><p class="cmsBoxLead">Pair this screen with the code button in the list of screens.</p></div></header>
				</section>
			<?php } elseif ((string) $user->email === '') { ?>
				<section class="cmsBox userAccess">
					<header><div class="cmsBoxTitles"><h2>Sign-in code</h2><p class="cmsBoxLead">Add an email address before creating a sign-in code.</p></div></header>
				</section>
			<?php } else {
				$hint = 'You, ' . trim((string) \Systopic\System\Auth\Session::current()->name) . ', are signing a device in as ' . trim((string) ($user->fullname ?: $user->name)) . '. This choice is written to the sign-in log.';
			?>
				<section class="cmsBox userAccess">
					<header><div class="cmsBoxTitles"><h2>Sign-in code</h2><p class="cmsBoxLead"><?php if (!$user->firstSignIn) { ?>This person has not signed in yet. A code is the first way in and makes the account active.<?php } else { ?>A code signs this person in on another device. A signed-in account can confirm that device instead.<?php } ?></p></div></header>
					<div class="accessRow">
						<div>
							<button type="button" class="button submit" data-feedback="none" data-on_click="userConfirm" data-action="createCode" data-confirm="<?= $e($hint) ?>"><i class="cmsIcon cmsIcon-qrcode"></i> Create code</button>
							<p class="cmsBoxLead">Valid for 10 minutes. Shown here.</p>
						</div>
						<div>
							<button type="button" class="button" data-on_click="userConfirm" data-action="inviteMail" data-confirm="<?= $e($hint) ?>"><i class="cmsIcon cmsIcon-paper-plane"></i> Send invitation code</button>
							<p class="cmsBoxLead">Valid for 24 hours.<?= ($note = $mailNote(SignInLog::MAILED_INVITATION)) !== '' ? ' ' . $e($note) : '' ?></p>
						</div>
					</div>
				</section>
			<?php } ?>
<?php endif; ?>
			<?php if ($codes) { ?>
			<section class="cmsBox">
				<header>
					<div class="cmsBoxTitles"><h2>Trusted devices</h2><p class="cmsBoxLead"><?= $isScreen ? 'Devices paired to this screen.' : 'Devices that can access this account.' ?> Expiry or revocation blocks access; it does not automatically erase the stored device record.</p></div>
				</header>
				<div class="cmsBoxBody">
					<?php if ($devices === []) { ?>
						<p class="profileEmpty">No devices yet.</p>
					<?php } ?>
					<?php foreach ($devicesShown as $device) { ?>
						<div class="userDevice">
							<i class="<?= $e(DeviceName::icon((string) $device->userAgent)) ?>" aria-hidden="true"></i>
							<?php [$presence, $presenceTitle] = Presence::deviceMark((int) $device->id); ?>
							<span class="presence<?= $presence !== '' ? ' ' . $presence : '' ?>" title="<?= $e($presenceTitle) ?>" role="img" aria-label="<?= $e($presenceTitle) ?>"></span>
							<span>
								<b><?= $e($device->name) ?></b>
								<?= $device->client === 'app' ? 'App' : 'Browser' ?>
								<?php $mine = \Systopic\System\Auth\Devices::current();
							$isMine = $mine && (int) $mine->id === (int) $device->id; ?>
							<small><?php if ($device->revokedAt) { ?>Access revoked · <?php } ?><?php if ($isMine) { ?>This device · <?php } ?>Last seen <?= $e($when($device->lastSeen)) ?><?php
								$paired = SignInLog::paired(trim((string) ($user->name ?: $user->email)), $device->createdAt);
								if ($paired !== '') { ?><br><?= $e($paired) ?><?php } ?></small>
							</span>
							<?php if (!$isMine && !$user->isBlocked) { ?>
								<button type="button" class="button transparent icononly" data-on_click="userConfirm" data-action="revokeDevice" data-device_id="<?= (int) $device->id ?>" data-confirm="Sign this device out?" aria-label="Sign out device: <?= $e((string) $device->name) ?>; last seen <?= $e($when($device->lastSeen)) ?>" title="Sign out device"><i class="<?= $e(DeviceName::slash((string) $device->userAgent)) ?>" aria-hidden="true"></i></button>
							<?php } ?>
						</div>
					<?php } ?>
					<?php if (count($devices) > 3) { ?>
						<button type="button" class="cmsBoxMore" data-on_click="showAllDevices"><?= $devicesAll ? 'Show less' : 'Show all devices (' . count($devices) . ')' ?> <i class="cmsIcon cmsIcon-chevron-<?= $devicesAll ? 'up' : 'down' ?>"></i></button>
					<?php } ?>
				</div>
			</section>
			<?php } ?>
			<?php if (!$isScreen) { echo \Systopic\System\Auth\Passkeys::render($user); } ?>
        <?php if ($viewer && !$isMe && (int) $user->id > 1): ?>
        <section class="cmsBox"><header><div class="cmsBoxTitles"><h2><?= $user->isBlocked ? 'Account blocked' : 'Block account' ?></h2><p class="cmsBoxLead">Prevents sign-in and revokes existing sessions and device access. Content, roles and account details are kept. Unblocking requires a fresh sign-in.</p></div></header><div class="cmsBoxActions"><button type="button" class="button" data-on_click="userConfirm" data-action="<?= $user->isBlocked ? 'unblockUser' : 'blockUser' ?>" data-confirm="<?= $user->isBlocked ? 'Allow this account to sign in again?' : 'Block this account and revoke all access?' ?>" data-user_id="<?= (int) $user->id ?>"><i class="cmsIcon cmsIcon-<?= $user->isBlocked ? 'user-unlock' : 'user-lock' ?>"></i><?= $user->isBlocked ? 'Unblock account' : 'Block account' ?></button></div></section>
        <?php endif; ?>
	</div>
	<div class="userPane" data-user-pane="maintenance"<?= $tab === 'maintenance' ? '' : ' hidden' ?>>

		<section class="cmsBox">
			<header>
				<div class="cmsBoxTitles">
					<h2>Reset layout</h2>
					<p class="cmsBoxLead">Resets the saved module layouts for this account on its next interaction.</p>
				</div>
			</header>
			<div class="cmsBoxActions">
				<button type="button" class="button" data-on_click="userConfirm" data-action="resetState" data-confirm="Reset the layout of every module to its defaults?" data-user_id="<?= (int) $user->id ?>"><i class="cmsIcon cmsIcon-arrow-rotate-left"></i> Reset layout</button>
			</div>
		</section>
		<?php if (!$isMe) { ?>
			<section class="cmsBox">
				<header>
					<div class="cmsBoxTitles">
					<h2><?= $isScreen ? 'Delete screen' : 'Delete user' ?></h2>
					<p class="cmsBoxLead"><?= $isScreen ? 'Removes the screen, its roles and its devices. This cannot be undone.' : 'Removes the account, its roles and its devices. This cannot be undone.' ?></p>
					</div>
				</header>
				<div class="cmsBoxActions">
					<button type="button" class="button" data-on_click="userConfirm" data-action="removeUser" data-confirm="<?= $isScreen ? 'Delete this screen? This cannot be undone.' : 'Delete this user? This cannot be undone.' ?>" data-user_id="<?= (int) $user->id ?>"><i class="cmsIcon cmsIcon-trash"></i> <?= $isScreen ? 'Delete screen' : 'Delete user' ?></button>
				</div>
			</section>
		<?php } ?>
	</div>
</main>
