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
$screenCode = preg_replace('~[^0-9A-Za-z\-]~', '', (string) \http::post('screen', \http::get('screen', '')));
$pending = ($codesOn && $screenCode !== '') ? LoginCodes::peek($screenCode, [Codes::PAIR_USER, Codes::PAIR_SCREEN]) : null;
$mayPersons = $me && \Systopic\System\Auth\SignInRights::allows($this->scope, $me, \Systopic\System\Auth\SignInRights::PERSONS);
$mayScreens = $me && (Session::hasRole('cmsAdmin') || \Systopic\System\Auth\SignInRights::allows($this->scope, $me, \Systopic\System\Auth\SignInRights::SCREENS));
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
$when = static fn($time): string => \Systopic\System\Sys\LocalTime::format($time, \Systopic\System\Sys\LocalTime::DATETIME);
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$devicesShown = array_slice($ownDevices, 0, 3);
$deviceRow = static function ($device) use ($e, $when, $here, $me): void {
	[$presence, $presenceTitle] = Presence::deviceMark((int) $device->id);
	?>
	<div class="userDevice">
		<i class="<?= $e(DeviceName::icon((string) $device->userAgent)) ?>" aria-hidden="true"></i>
		<span class="presence<?= $presence !== '' ? ' ' . $presence : '' ?>" title="<?= $e($presenceTitle) ?>" role="img" aria-label="<?= $e($presenceTitle) ?>"></span>
		<span>
			<b><?= $e($device->name) ?></b>
			<?= $device->client === 'app' ? 'App' : 'Browser' ?>
			<small><?php if ($here && (int) $here->id === (int) $device->id) { ?>This device · <?php } ?>Last seen <?= $e($when($device->lastSeen)) ?><?php
				$paired = $me ? LoginLog::paired(trim((string) ($me->name ?: $me->email)), $device->createdAt) : '';
				if ($paired !== '') { ?><br><?= $e($paired) ?><?php } ?></small>
		</span>
		<?php if (!$here || (int) $here->id !== (int) $device->id) { ?>
			<button type="button" class="button transparent icononly" data-on_click="userConfirm" data-action="revokeOwnDevice" data-device_id="<?= (int) $device->id ?>" data-confirm="Sign this device out?" aria-label="Sign out device: <?= $e((string) $device->name) ?>; last seen <?= $e($when($device->lastSeen)) ?>" title="Sign out device"><i class="<?= $e(DeviceName::slash((string) $device->userAgent)) ?>" aria-hidden="true"></i></button>
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
    <h1 class="cmsA11yStatus">Account</h1>
	<?php $this->render("actions-bar.tpl.php"); ?>
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
					<input type="file" aria-label="Upload profile picture" title="Upload profile picture" name="portrait" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" onchange="this.form.requestSubmit()">
				</form>
				<?php if ($me && Portrait::hasFile((int) $me->id)) { ?>
					<button type="button" class="portraitRemove" data-on_click="removePortrait" title="Remove photo"><i class="cmsIcon cmsIcon-trash"></i></button>
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
				<p><i class="cmsIcon cmsIcon-user-clock"></i>Signed in <?= $e(\Systopic\System\Sys\LocalTime::format($me->lastLogin, \Systopic\System\Sys\LocalTime::DATETIME)) ?></p>
			<?php } ?>
			<ul class="profileRoles">
				<?php if (\Systopic\System\Auth\Session::isSuperuser(true)) { ?>
					<li class="is-super"><i class="cmsIcon cmsIcon-user-shield"></i>Superuser</li>
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
		<p class="profilePreview"><i class="cmsIcon cmsIcon-eye"></i>Viewing the System as <?= $e(implode(', ', $preview)) ?>. <button type="button" data-cms-action data-on_click="unsetFakeRoles">Stop</button></p>
	<?php } ?>
	<div class="profileGrid widgets">
		<?php if ($me) { ?>
			<section class="widget">
				<header><h2><i class="cmsIcon cmsIcon-id-card"></i>General</h2></header>
				<main class="fieldGrid">
					<?= \message::flush('account') ?>
					<?= $field('Username', 'text', 'updateUserName', $me->name, 'off') ?>
					<?= $field('Full name', 'text', 'updateUserFullname', $me->fullname) ?>
					<?= $field('Email', 'email', 'updateUserEmail', $me->email, 'off') ?>
				</main>
				<details class="cmsBox profilePassword"<?= $this->action === 'changePassword' ? ' open' : '' ?>>
				<summary><h3><i class="cmsIcon cmsIcon-input-password"></i>Change password</h3><span class="button transparent icononly summaryToggle" aria-hidden="true"><i class="cmsIcon cmsIcon-chevron-down"></i></span></summary>
				<form method="post" action="changePassword" data-password-check data-password-strict="<?= STAGE === 'LIVE' ? 'true' : 'false' ?>">
					<main class="fieldGrid">
						<?= \message::flush('password') ?>
						<label class="field"><span>New password</span><input type="password" name="password" autocomplete="new-password" required></label>
						<label class="field"><span>Repeat new password</span><input type="password" name="password_repeat" autocomplete="new-password" required></label>
					</main>
					<footer><button class="button submit" type="submit" disabled><i class="cmsIcon cmsIcon-check"></i>Save password</button></footer>
				</form>
				</details>
			</section>
		<?php } ?>
		<?php if ($me) { echo \Systopic\System\Auth\Passkeys::render($me, 'widget'); } ?>
		<section class="widget">
			<header>
				<h2><i class="cmsIcon cmsIcon-phone-laptop"></i>Trusted devices</h2>
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
					<button type="button" class="button submit" data-open-box=".remoteLoginBox"><i class="cmsIcon cmsIcon-laptop-mobile"></i> Add device</button>
				</footer>
			<?php } ?>
		</section>
		<?php if ($me) {
			$appearance = (string) ($me->appearance ?? '');
			if (!in_array($appearance, ['light', 'dark', 'system'], true)) {
				$appearance = 'system';
			}
		?>
		<section class="widget">
			<header><h2><i class="cmsIcon cmsIcon-sun"></i>Appearance</h2></header>
			<main>
				<p class="tagsHint">System follows this device unless you choose Light or Dark.</p>
				<div class="appearanceChoices" role="radiogroup" aria-label="Appearance">
					<?php foreach (['light' => ['cmsIcon cmsIcon-sun', 'Light', 'Light background.'], 'dark' => ['cmsIcon cmsIcon-moon', 'Dark', 'Dark background.'], 'system' => ['cmsIcon cmsIcon-circle-half-stroke', 'System', 'Matches this device.']] as $value => $choice) { ?>
						<button type="button" role="radio" tabindex="<?= $appearance === $value ? '0' : '-1' ?>" data-on_click="updateAppearance" data-value="<?= $value ?>" aria-checked="<?= $appearance === $value ? 'true' : 'false' ?>"><i class="<?= $choice[0] ?>"></i><span><?= $choice[1] ?></span><small><?= $choice[2] ?></small></button>
					<?php } ?>
				</div>
			</main>
			<footer>
				<?= \message::flush('layout') ?>
				<p class="tagsHint">Puts every module back to its defaults: open panes, folded trees, filters, sort orders.</p>
				<button type="button" class="button submit" data-on_click="userConfirm" data-action="resetState" data-confirm="Reset the layout of every module to its defaults?"><i class="cmsIcon cmsIcon-arrow-rotate-left"></i>Reset layout</button>
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
				<li><button type="button" data-box-close aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button></li>
			</ul>
		</header>
		<main>
			<?php foreach ($ownDevices as $device) { $deviceRow($device); } ?>
		</main>
	</section>
</div>
<?php } ?>
<?php if ($codesOn) {
	$remoteOpen = ($screenCode !== '' && $pending === null) || $this->action === 'approveRemote';
	$offer = $me ? LoginCodes::shown($me) : null;
	$offerUrl = $offer ? \Systopic\System\Auth\CodeMail::link($offer['code']) : '';
	$offerSvg = ($offerUrl !== '' && class_exists('qr')) ? \qr::svgMarkup(\qr::payload('url', ['url' => $offerUrl]), 4, 'M', 'qr-code') : '';
?>
<div class="cms cmslightbox remoteLoginBox" data-feedback="parameters"<?= $remoteOpen ? '' : ' hidden' ?><?= $offer ? ' data-code-value="' . $e($offer['code']) . '"' : '' ?>>
	<section>
		<header>
			<h1>Remote login</h1>
			<ul class="barmenu">
				<li><button type="button" data-box-close data-qr-close aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button></li>
			</ul>
		</header>
		<main class="remoteLogin">
			<div data-remote-feedback><?= \message::flush('remoteLogin') ?>
			<?php if ($remoteOpen && $this->action !== 'approveRemote') { ?>
				<div class="cms message"><div class="error"><p>That code is wrong or has expired.</p></div></div>
			<?php } ?></div>
			<?php if ($offerSvg !== '') { ?>
				<div class="remoteOffer">
					<div class="remoteQr"><?= $offerSvg ?></div>
					<p class="remoteCodeLine"><?= $e($offer['code']) ?></p>
					<p class="tagsHint">Anyone who can see this can sign in as this user. Close it when you are done.</p>
					<div class="remoteOfferCopy">
						<p class="cmsBoxLead">Scan this on the new device.</p>
						<p class="remoteExpiry">Valid for 10 minutes.</p>
						<button type="button" class="button" data-qr-start><i class="cmsIcon cmsIcon-camera"></i> Scan QR code</button>
						<button type="button" class="button" data-remote-code><i class="cmsIcon cmsIcon-input-numeric"></i> Use code</button>
					</div>
				</div>
			<?php } ?>
			<div class="qrScan" data-qr-host="profile" data-base="<?= $e($this->href) ?>" hidden>
				<div class="qrScanStage">
					<video playsinline muted autoplay disablepictureinpicture disableremoteplayback></video>
					<div class="qrScanFrame" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
					<button type="button" class="qrScanTorch" data-qr-torch hidden aria-label="Light"><i class="cmsIcon cmsIcon-bolt"></i></button>
				</div>
				<p class="qrScanHint" data-qr-hint>Hold the QR code steady and well lit in the frame.</p>
				<div class="remoteMethods">
					<?php if ($offerSvg !== '') { ?>
						<button type="button" class="button" data-remote-offer><i class="cmsIcon cmsIcon-qrcode"></i> Show QR</button>
					<?php } ?>
					<button type="button" class="button" data-remote-code><i class="cmsIcon cmsIcon-input-numeric"></i> Use code</button>
				</div>
				<div class="cms message qrScanMsg" data-qr-msg hidden></div>
				<div class="qrScanDim remoteAbort" data-qr-dim hidden>
					<button type="button" class="button submit" data-qr-again><i class="cmsIcon cmsIcon-arrows-rotate"></i> <span data-qr-label>Try again</span></button>
				</div>
			</div>
			<form class="remoteCode" data-feedback="parameters" data-code-form method="post" action="<?= $e($this->href . 'checkRemote') ?>"<?= ($remoteOpen || $offerSvg === '') ? '' : ' hidden' ?>>
				<div class="remoteCodeEntry">
					<div class="authCodeSlots" data-code-slots>
						<?php for ($slot = 0; $slot < 8; $slot++) {
							if ($slot === 4) {
								echo '<span class="authCodeDash" aria-hidden="true">-</span>';
							}
						?>
						<input required pattern="[A-Za-z0-9]" data-slot="<?= $slot ?>" inputmode="text" autocapitalize="characters" spellcheck="false" autocomplete="<?= $slot === 0 ? 'one-time-code' : 'off' ?>" aria-label="Character <?= $slot + 1 ?>">
						<?php } ?>
						<input type="hidden" name="screen" value="">
					</div>
					<button class="button submit" type="submit" aria-label="Continue" title="Continue"><i class="cmsIcon cmsIcon-check"></i></button>
				</div>
				<div class="remoteSwitch">
					<?php if ($offerSvg !== '') { ?>
						<button type="button" class="button" data-remote-offer><i class="cmsIcon cmsIcon-qrcode"></i> Show QR</button>
					<?php } ?>
					<button type="button" class="button" data-qr-start><i class="cmsIcon cmsIcon-camera"></i> Scan QR code</button>
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
				<li><a class="ajax" href="<?= $e($this->href) ?>" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a></li>
			</ul>
		</header>
		<main>
			<?php if (!$asScreen) { ?>
				<form data-feedback="parameters" method="post" action="approveRemote">
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
						<button class="button submit" type="submit"><i class="cmsIcon cmsIcon-check"></i>Allow</button>
					</div>
				</form>
			<?php } elseif (!$mayScreens) { ?>
				<p class="cmsBoxLead">Signing in a screen takes the authScreens permission.</p>
			<?php } elseif ($screens === []) { ?>
				<p class="cmsBoxLead">There is no screen account yet.</p>
			<?php } else { ?>
				<form data-feedback="parameters" method="post" action="approveRemote">
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
						<button class="button submit" type="submit"><i class="cmsIcon cmsIcon-check"></i>Allow</button>
					</div>
				</form>
			<?php } ?>
		</main>
	</section>
</div>
<?php } ?>
