<?php
/** @var \Systopic\System\Panels\Root\Cms\User\Panel $this */

use Systopic\Db\Schema\Schema;
use Systopic\System\Auth\DeviceName;
use Systopic\System\Auth\Devices;
use Systopic\System\Auth\LoginCodes;
use Systopic\System\Auth\LoginLog;
use Systopic\System\Auth\Presence;
use Systopic\System\Queries\Devices\Devices as DeviceQuery;
use Systopic\System\Auth\Portrait;
use Systopic\System\Auth\Session;
use Systopic\System\Tables\LoginCodes\Operator as Codes;
use Systopic\System\Tables\Users\Model as User;

$me = $this->me;
$ownDevices = $me ? DeviceQuery::ofUser($this->scope, (int) $me->id) : [];
$here = Devices::current();
$codesOn = LoginCodes::available();
$screenCode = preg_replace('~[^0-9A-Za-z\-]~', '', (string) \http::get('screen', ''));
$pending = ($codesOn && $screenCode !== '') ? LoginCodes::peek($screenCode, [Codes::PAIR_USER, Codes::PAIR_SCREEN]) : null;
$mayPersons = $me && \Systopic\System\Auth\SignInRights::allows($this->scope, $me, \Systopic\System\Auth\SignInRights::PERSONS);
$mayScreens = $me && \Systopic\System\Auth\SignInRights::allows($this->scope, $me, \Systopic\System\Auth\SignInRights::SCREENS);
$actorIsSuper = Session::isSuperuser(true);
$screens = [];
if ($pending && $pending->purpose === Codes::PAIR_SCREEN && $mayScreens) {
	try {
		$table = Schema::tableNameOf(User::class);
		$screens = $this->scope->connection->all(
			"SELECT user_id, user_name, user_fullname FROM $table WHERE user_type = ? AND user_id > 1 AND user_is_active = 1 ORDER BY user_name",
			[User::SCREEN],
		);
	} catch (\Throwable) {
		$screens = [];
	}
}
$people = [];
if ($pending && $pending->purpose === Codes::PAIR_USER && $mayPersons && $me) {
	try {
		$table = Schema::tableNameOf(User::class);
		$peopleSql = "SELECT user_id, user_name, user_fullname FROM $table WHERE user_id > 1 AND user_is_active = 1 AND (user_type = ? OR user_type IS NULL OR user_type = '')";
		$peopleArgs = [User::PERSON];
		if (!$actorIsSuper) {
			$peopleSql .= ' AND (user_id = ? OR user_is_superuser = 0 OR user_is_superuser IS NULL)';
			$peopleArgs[] = (int) $me->id;
		}
		$peopleSql .= ' ORDER BY user_name';
		$people = $this->scope->connection->all($peopleSql, $peopleArgs);
	} catch (\Throwable) {
		$people = [];
	}
	$mine = (int) $me->id;
	usort($people, static function (array $a, array $b) use ($mine): int {
		$aMine = (int) $a['user_id'] === $mine;
		$bMine = (int) $b['user_id'] === $mine;
		if ($aMine !== $bMine) {
			return $aMine ? -1 : 1;
		}
		return strcasecmp((string) $a['user_name'], (string) $b['user_name']);
	});
}
$when = static fn($time): string => \Systopic\System\Sys\LocalTime::format($time, 'j M Y, H:i');
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$devicesShown = array_slice($ownDevices, 0, 3);
$deviceRow = static function ($device) use ($e, $when, $here, $me): void {
	[$presence, $presenceTitle] = Presence::deviceMark((int) $device->id);
	?>
	<div class="userDevice">
		<i class="<?= $e(DeviceName::icon((string) $device->userAgent)) ?>" aria-hidden="true"></i>
		<span class="presence<?= $presence !== '' ? ' ' . $presence : '' ?>" title="<?= $e($presenceTitle) ?>"></span>
		<span>
			<b><?= $e($device->name) ?></b>
			<?= $device->client === 'app' ? 'App' : 'Browser' ?>
			<small><?php if ($here && (int) $here->id === (int) $device->id) { ?>This device · <?php } ?>Last seen <?= $e($when($device->lastSeen)) ?><?php
				$paired = $me ? LoginLog::paired(trim((string) ($me->name ?: $me->email)), $device->createdAt) : '';
				if ($paired !== '') { ?><br><?= $e($paired) ?><?php } ?></small>
		</span>
		<?php if (!$here || (int) $here->id !== (int) $device->id) { ?>
			<button type="button" class="button transparent icononly" data-on_click="userConfirm" data-action="revokeOwnDevice" data-device_id="<?= (int) $device->id ?>" data-confirm="Sign this device out?" aria-label="Revoke" title="Sign out"><i class="<?= $e(DeviceName::slash((string) $device->userAgent)) ?>" aria-hidden="true"></i></button>
		<?php } ?>
	</div>
	<?php
};

$portrait = $me ? Portrait::src((int) $me->id, $me->email, $me->type === \Systopic\System\Tables\Users\Model::SCREEN) : '';
$field = static fn(string $label, string $type, string $action, ?string $value, string $caps = 'on'): string
	=> "<label class='field'><span>$label</span><input type='$type' autocapitalize='$caps' name='value' data-on_blur='$action' value='{$e($value)}' autocomplete='off' data-1p-ignore data-lpignore='true' readonly onpointerdown='this.readOnly=false' onkeydown='this.readOnly=false' spellcheck='off'></label>";
$preview = $this->fakeRoles;
?>
<section class="cms profile">
	<div class="profileHead">
		<div class="profilePortrait">
			<div class="portraitFrame">
				<form class="ajax portraitDrop" method="post" enctype="multipart/form-data" action="<?= $e($this->href . 'uploadPortrait') ?>" data-drop-files data-upload="onsubmit">
					<div class="signet">
						<span><?= $e($this->initials) ?></span>
						<?php if ($portrait !== '') { ?>
							<img referrerpolicy="no-referrer" src="<?= $e($portrait) ?>" alt="">
						<?php } ?>
					</div>
					<input type="file" name="portrait" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" onchange="this.form.requestSubmit()">
				</form>
				<?php if ($me && Portrait::hasFile((int) $me->id)) { ?>
					<button type="button" class="portraitRemove" data-on_click="removePortrait" title="Remove photo"><i class="fa-regular fa-trash"></i></button>
				<?php } ?>
			</div>
			<span>Drop or click to replace</span>
			<?= \message::flush('portrait') ?>
		</div>
		<div>
			<h1><?= $e($me?->fullname ?: $me?->name ?: 'Profile') ?></h1>
			<?php if ($me?->email) { ?>
				<p><?= $e($me->email) ?></p>
			<?php } ?>
			<?php if ($me?->lastLogin) { ?>
				<p><i class="fa-regular fa-user-clock"></i>Signed in <?= $e(\Systopic\System\Sys\LocalTime::format($me->lastLogin, 'j M Y, H:i')) ?></p>
			<?php } ?>
			<ul class="profileRoles">
				<?php if (\Systopic\System\Auth\Session::isSuperuser(true)) { ?>
					<li class="is-super"><i class="fa-regular fa-user-shield"></i>Superuser</li>
				<?php } ?>
				<?php foreach (\Systopic\System\Auth\Session::current()->roles as $role) { ?>
					<li><?= $e($role->name) ?></li>
				<?php } ?>
				<?php if (!\Systopic\System\Auth\Session::isSuperuser(true) && \Systopic\System\Auth\Session::current()->roles === []) { ?>
					<li class="is-none">No role</li>
				<?php } ?>
			</ul>
		</div>
	</div>
	<?php if ($preview !== []) { ?>
		<p class="profilePreview"><i class="fa-regular fa-eye"></i>Viewing the CMS as <?= $e(implode(', ', $preview)) ?>. <a data-on_click="unsetFakeRoles">Stop</a></p>
	<?php } ?>
	<div class="profileGrid">
		<?php if ($me) { ?>
			<section class="widget">
				<header><h2><i class="fa-regular fa-id-card"></i>Account</h2></header>
				<main class="fieldGrid">
					<?= \message::flush('account') ?>
					<?= $field('Username', 'text', 'updateUserName', $me->name, 'off') ?>
					<?= $field('Full name', 'text', 'updateUserFullname', $me->fullname) ?>
					<?= $field('Email', 'email', 'updateUserEmail', $me->email, 'off') ?>
				</main>
			</section>
			<section class="widget">
				<header><h2><i class="fa-regular fa-lock"></i>Password</h2></header>
				<form method="post" action="changePassword">
					<main class="fieldGrid">
						<?= \message::flush('password') ?>
						<label class="field"><span>New password</span><input type="password" name="password" autocomplete="new-password" required></label>
						<label class="field"><span>Repeat new password</span><input type="password" name="password_repeat" autocomplete="new-password" required></label>
					</main>
					<footer><button class="button submit" type="submit"><i class="fa-regular fa-check"></i>Save password</button></footer>
				</form>
			</section>
		<?php } ?>
		<?php if ($me) { echo \Systopic\System\Auth\Passkeys::render($me, 'widget'); } ?>
		<section class="widget">
			<header>
				<h2><i class="fa-regular fa-phone-laptop"></i>Trusted devices</h2>
			</header>
			<main>
				<?= \message::flush('devices') ?>
				<?php if (!$codesOn) { ?>
					<p class="tagsHint">Devices that signed in with a code no longer count. Your password and a passkey still work.</p>
				<?php } else { ?>
					<p class="tagsHint">Devices that can access this account.</p>
					<?php if ($ownDevices === []) { ?>
						<p class="profileEmpty">No devices yet.</p>
					<?php } ?>
					<?php foreach ($devicesShown as $device) { $deviceRow($device); } ?>
				<?php } ?>
			</main>
			<?php if ($codesOn) { ?>
				<footer>
					<?php if (count($ownDevices) > count($devicesShown)) { ?>
						<button type="button" class="cmsBoxMore" data-open-box=".deviceListBox">Show all devices (<?= count($ownDevices) ?>)</button>
					<?php } ?>
					<button type="button" class="button submit" data-open-box=".remoteLoginBox"><i class="fa-regular fa-laptop-mobile"></i> Add device</button>
				</footer>
			<?php } ?>
		</section>
		<?php if ($me) {
			$appearance = (string) ($me->appearance ?? '');
			if (!in_array($appearance, ['light', 'dark', 'system'], true)) {
				$appearance = 'light';
			}
		?>
		<section class="widget">
			<header><h2><i class="fa-regular fa-sun"></i>Appearance</h2></header>
			<main>
				<p class="tagsHint">Light is used until Dark or System is chosen for this account.</p>
				<div class="appearanceChoices" role="radiogroup" aria-label="Appearance">
					<?php foreach (['light' => ['fa-regular fa-sun', 'Light', 'Light background.'], 'dark' => ['fa-regular fa-moon', 'Dark', 'Dark background.'], 'system' => ['fa-regular fa-circle-half-stroke', 'System', 'Matches this device.']] as $value => $choice) { ?>
						<button type="button" role="radio" data-on_click="updateAppearance" data-value="<?= $value ?>" aria-checked="<?= $appearance === $value ? 'true' : 'false' ?>"><i class="<?= $choice[0] ?>"></i><span><?= $choice[1] ?></span><small><?= $choice[2] ?></small></button>
					<?php } ?>
				</div>
			</main>
			<footer>
				<?= \message::flush('layout') ?>
				<p class="tagsHint">Puts every module back to its defaults: open panes, folded trees, filters, sort orders.</p>
				<button type="button" class="button submit" data-on_click="userConfirm" data-action="resetState" data-confirm="Reset the layout of every module to its defaults?"><i class="fa-regular fa-arrow-rotate-left"></i>Reset layout</button>
			</footer>
		</section>
		<?php } ?>
	</div>
</section>
<?php if ($codesOn && count($ownDevices) > 3) { ?>
<div class="cms cmslightbox deviceListBox" hidden>
	<section>
		<header>
			<h1>Trusted devices</h1>
			<ul class="barmenu">
				<li><button type="button" data-box-close aria-label="Close"><i class="fa-regular fa-xmark"></i></button></li>
			</ul>
		</header>
		<main>
			<?php foreach ($ownDevices as $device) { $deviceRow($device); } ?>
		</main>
	</section>
</div>
<?php } ?>
<?php if ($codesOn) {
	$remoteOpen = $screenCode !== '' && $pending === null;
	$offer = $me ? LoginCodes::shown($me) : null;
	$offerUrl = $offer ? \Systopic\System\Auth\CodeMail::link($offer['code']) : '';
	$offerSvg = ($offerUrl !== '' && class_exists('qr')) ? \qr::svgMarkup(\qr::payload('url', ['url' => $offerUrl]), 4, 'M', 'qr-code') : '';
?>
<div class="cms cmslightbox remoteLoginBox"<?= $remoteOpen ? '' : ' hidden' ?><?= $offer ? ' data-code-value="' . $e($offer['code']) . '"' : '' ?>>
	<section>
		<header>
			<h1>Remote login</h1>
			<ul class="barmenu">
				<li><button type="button" data-box-close data-qr-close aria-label="Close"><i class="fa-regular fa-xmark"></i></button></li>
			</ul>
		</header>
		<main class="remoteLogin">
			<?php if ($remoteOpen) { ?>
				<div class="cms message"><div class="error"><p>That code is wrong or has expired.</p></div></div>
			<?php } ?>
			<?php if ($offerSvg !== '') { ?>
				<div class="remoteOffer">
					<div class="remoteQr"><?= $offerSvg ?></div>
					<p class="remoteCodeLine"><?= $e($offer['code']) ?></p>
					<p class="tagsHint">Anyone who can see this can sign in as this user. Close it when you are done.</p>
					<div class="remoteOfferCopy">
						<p class="cmsBoxLead">Scan this on the new device.</p>
						<p class="remoteExpiry">Valid for 10 minutes.</p>
						<button type="button" class="button" data-qr-start><i class="fa-regular fa-camera"></i> Scan QR code</button>
						<button type="button" class="button" data-remote-code><i class="fa-regular fa-input-numeric"></i> Use code</button>
					</div>
				</div>
			<?php } ?>
			<div class="qrScan" data-qr-host="profile" data-base="<?= $e($this->href) ?>" hidden>
				<div class="qrScanStage">
					<video playsinline muted autoplay disablepictureinpicture disableremoteplayback></video>
					<div class="qrScanFrame" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
					<button type="button" class="qrScanTorch" data-qr-torch hidden aria-label="Light"><i class="fa-regular fa-bolt"></i></button>
				</div>
				<p class="qrScanHint" data-qr-hint>Hold the QR code steady and well lit in the frame.</p>
				<div class="remoteMethods">
					<?php if ($offerSvg !== '') { ?>
						<button type="button" class="button" data-remote-offer><i class="fa-regular fa-qrcode"></i> Show QR</button>
					<?php } ?>
					<button type="button" class="button" data-remote-code><i class="fa-regular fa-input-numeric"></i> Use code</button>
				</div>
				<div class="cms message qrScanMsg" data-qr-msg hidden></div>
				<div class="qrScanDim remoteAbort" data-qr-dim hidden>
					<button type="button" class="button submit" data-qr-again><i class="fa-regular fa-rotate-right"></i> <span data-qr-label>Try again</span></button>
				</div>
			</div>
			<form class="remoteCode" data-code-form method="get" action="<?= $e($this->href) ?>"<?= ($remoteOpen || $offerSvg === '') ? '' : ' hidden' ?>>
				<div class="remoteCodeEntry">
					<div class="authCodeSlots" data-code-slots>
						<?php for ($slot = 0; $slot < 8; $slot++) {
							if ($slot === 4) {
								echo '<span class="authCodeDash" aria-hidden="true">-</span>';
							}
						?>
						<input data-slot="<?= $slot ?>" inputmode="text" autocapitalize="characters" spellcheck="false" autocomplete="<?= $slot === 0 ? 'one-time-code' : 'off' ?>" aria-label="Character <?= $slot + 1 ?>">
						<?php } ?>
						<input type="hidden" name="screen" value="">
					</div>
					<button class="button submit" type="submit" aria-label="Continue" title="Continue"><i class="fa-regular fa-check"></i></button>
				</div>
				<div class="remoteSwitch">
					<?php if ($offerSvg !== '') { ?>
						<button type="button" class="button" data-remote-offer><i class="fa-regular fa-qrcode"></i> Show QR</button>
					<?php } ?>
					<button type="button" class="button" data-qr-start><i class="fa-regular fa-camera"></i> Scan QR code</button>
				</div>
			</form>
		</main>
	</section>
</div>
<?php } ?>
<?php if ($pending) {
	$asScreen = $pending->purpose === Codes::PAIR_SCREEN;
	$accountLabel = static function (array $row) use ($e): string {
		$full = trim((string) ($row['user_fullname'] ?? ''));
		$name = trim((string) ($row['user_name'] ?? ''));
		return $e($full !== '' ? $full : $name);
	};
	$meFull = $me ? trim((string) $me->fullname) : '';
	$meLabel = $me ? $e($meFull !== '' ? $meFull : (string) $me->name) : '';
	$otherPeople = $me ? array_values(array_filter($people, static fn(array $row): bool => (int) $row['user_id'] !== (int) $me->id)) : [];
	$offerPeople = !$asScreen && $otherPeople !== [];
?>
<div class="cms cmslightbox">
	<section>
		<header>
			<h1><?= $asScreen ? 'Screen' : 'Remote login' ?></h1>
			<ul class="barmenu">
				<li><a class="ajax" href="<?= $e($this->href) ?>" aria-label="Close"><i class="fa-regular fa-xmark"></i></a></li>
			</ul>
		</header>
		<main>
			<?php if (!$asScreen) { ?>
				<form method="post" action="approveRemote">
					<input type="hidden" name="code" value="<?= $e($screenCode) ?>">
					<?php if ($offerPeople) {
						$firstSelf = (int) $people[0]['user_id'] === (int) $me->id;
						$firstName = $accountLabel($people[0]);
					?>
						<p class="cmsBoxLead" data-identify-self<?= $firstSelf ? '' : ' hidden' ?>>Allow this device to sign in as you?</p>
						<div data-identify-other<?= $firstSelf ? ' hidden' : '' ?>>
							<p class="cmsBoxLead">You, <?= $meLabel ?>, are signing this device in. You identify the user on that device as <strong data-identify-name><?= $firstSelf ? '' : $firstName ?></strong>.</p>
							<p class="tagsHint">This choice is written to the login log.</p>
						</div>
						<label class="field"><span>Person</span>
							<select name="person" required data-identify-select>
								<?php foreach ($people as $person) { ?>
									<option value="<?= (int) $person['user_id'] ?>"<?= (int) $person['user_id'] === (int) $me->id ? ' data-self="1"' : '' ?>><?= $accountLabel($person) ?></option>
								<?php } ?>
							</select>
						</label>
					<?php } else { ?>
						<p class="cmsBoxLead">Allow this device to sign in as you?</p>
					<?php } ?>
					<div class="cmsBoxActions">
						<button class="button submit" type="submit"><i class="fa-regular fa-check"></i>Allow</button>
					</div>
				</form>
			<?php } elseif (!$mayScreens) { ?>
				<p class="cmsBoxLead">Signing in a screen takes the authScreens permission.</p>
			<?php } elseif ($screens === []) { ?>
				<p class="cmsBoxLead">There is no screen account yet.</p>
			<?php } else { ?>
				<form method="post" action="approveRemote">
					<input type="hidden" name="code" value="<?= $e($screenCode) ?>">
					<div data-identify-other>
						<p class="cmsBoxLead">You, <?= $meLabel ?>, are signing this device in. You identify the user on that device as <strong data-identify-name><?= $accountLabel($screens[0]) ?></strong>.</p>
						<p class="tagsHint">This choice is written to the login log.</p>
					</div>
					<label class="field"><span>Screen</span>
						<select name="screen" required data-identify-select>
							<?php foreach ($screens as $screen) { ?>
								<option value="<?= (int) $screen['user_id'] ?>"><?= $accountLabel($screen) ?></option>
							<?php } ?>
						</select>
					</label>
					<div class="cmsBoxActions">
						<button class="button submit" type="submit"><i class="fa-regular fa-check"></i>Allow</button>
					</div>
				</form>
			<?php } ?>
		</main>
	</section>
</div>
<?php } ?>
