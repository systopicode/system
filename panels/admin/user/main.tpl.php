<?php
/** @var \Systopic\System\Panels\Root\Admin\User\Panel $this */

use Systopic\System\Auth\DeviceName;
use Systopic\System\Auth\Devices;
use Systopic\System\Auth\SignInCodes;
use Systopic\System\Auth\SignInGrant;
use Systopic\System\Auth\SignInLog;
use Systopic\System\Auth\Presence;
use Systopic\System\Queries\Devices\Devices as DeviceQuery;
use Systopic\System\Auth\Portrait;
use Systopic\System\Auth\Session;

$me = $this->me;
$ownDevices = $me ? DeviceQuery::ofUser($this->scope, (int) $me->id) : [];
$here = Devices::current();
$codesOn = SignInCodes::available();
$deviceCode = preg_replace('~[^0-9A-Za-z\-]~', '', (string) \http::posted('deviceCode', \http::get('deviceCode', '')));
$pending = ($codesOn && !$this->deviceAllowed && (!\http::ajax() || $this->action === 'checkDeviceCode') && $deviceCode !== '') ? SignInCodes::peekDeviceCode($deviceCode) : null;
$accounts = ($me && $codesOn) ? SignInGrant::accounts($this->scope, $me, $this) : [];
$accountsOf = [
	'self' => array_values(array_filter($accounts, static fn(array $account): bool => $account['self'])),
	'screen' => array_values(array_filter($accounts, static fn(array $account): bool => $account['screen'])),
	'person' => array_values(array_filter($accounts, static fn(array $account): bool => !$account['self'] && !$account['screen'])),
];
$when =static fn($time): string => \Systopic\System\Sys\LocalTime::format($time, \Systopic\System\Sys\LocalTime::DATETIME);
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$devicesShown = array_slice($ownDevices, 0, 3);
$deviceRow =static function ($device, ?array $screen = null) use ($e, $when, $here, $me): void {
    include __DIR__ . '/deviceRow.tpl.php';
};
// The two directions: this device reads the code of the other one, or makes a code for it.
// With other accounts the widget chooses first, so one lightbox is enough for either way.
$createUrl = $this->href . 'createDeviceCode';
$pick = static function (string $kind, string $label, array $choices) use ($e, $createUrl): void {
	?>
	<form class="ajax deviceWays" data-feedback="parameters" method="put" action="<?= $e($createUrl) ?>">
		<input type="hidden" name="kind" value="<?= $e($kind) ?>">
		<label class="field"><span><?= $e($label) ?></span>
			<select name="account" required>
				<?php foreach ($choices as $account) { ?>
					<option value="<?= (int) $account['id'] ?>"><?= $e($account['label']) ?></option>
				<?php } ?>
			</select>
		</label>
		<div class="deviceWaysActions">
			<button type="button" class="button" data-open-box=".scanCodeBox.kind<?= ucfirst($kind) ?>"><i class="cmsIcon cmsIcon-object-scan"></i> Scan code</button>
			<button class="button submit" type="submit"><i class="cmsIcon cmsIcon-qrcode"></i> Create code</button>
		</div>
	</form>
	<?php
};

$portrait =$me ? Portrait::src((int) $me->id, $me->email, $me->type === \Systopic\System\Tables\Users\Model::SCREEN) : '';
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
			<?php if ($me?->lastSignIn) { ?>
				<p><i class="cmsIcon cmsIcon-user-clock"></i>Signed in <?= $e(\Systopic\System\Sys\LocalTime::format($me->lastSignIn, \Systopic\System\Sys\LocalTime::DATETIME)) ?></p>
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
				<h2><i class="cmsIcon cmsIcon-phone-laptop"></i>My devices</h2>
			</header>
			<main>
				<div data-device-feedback="self"><?= \message::flush('devices') ?></div>
				<?php if (!$codesOn) { ?>
					<p class="tagsHint">Devices that signed in with a code no longer count. Your password and a passkey still work.</p>
				<?php } else { ?>
					<p class="tagsHint">Devices that can access this account.</p>
                    <div data-own-devices><?php include __DIR__ . '/ownDevices.tpl.php'; ?></div>
				<?php } ?>
			</main>
			<?php if ($codesOn) { ?>
				<footer class="deviceActionsFooter">
					<?php if (count($ownDevices) > count($devicesShown)) { ?>
						<button type="button" class="cmsBoxMore" data-open-box=".deviceListBox">Show all devices (<?= count($ownDevices) ?>)</button>
					<?php } ?>
					<?php if (count($ownDevices) > 1) { ?>
						<button type="button" class="button" data-on_click="userConfirm" data-action="revokeOtherDevices" data-confirm="Sign out all your other devices?"><i class="cmsIcon cmsIcon-right-from-bracket"></i> Sign out other devices</button>
					<?php } ?>
					<button type="button" class="button" data-open-box=".scanCodeBox.kindSelf"><i class="cmsIcon cmsIcon-object-scan"></i> Scan code</button>
                    <form class="ajax" data-feedback="parameters" method="put" action="<?= $e($createUrl) ?>">
                        <input type="hidden" name="kind" value="self">
                        <input type="hidden" name="account" value="<?= (int) $me->id ?>">
                        <button class="button submit" type="submit"><i class="cmsIcon cmsIcon-qrcode"></i> Create code</button>
                    </form>
				</footer>
			<?php } ?>
		</section>
		<?php if ($codesOn && SignInGrant::mayScreens($this)) { ?>
			<section class="widget">
				<header>
					<h2><i class="cmsIcon cmsIcon-tv"></i>Paired screens</h2>
				</header>
                <main><div data-device-feedback="screen"><?= \message::flush('devicesScreen') ?></div><div data-paired-screen-devices><?php include __DIR__ . '/pairedDevices.tpl.php'; ?></div></main>
				<?php if ($accountsOf['screen'] !== []) { ?>
					<footer>
						<?php $pick('screen', 'Screen', $accountsOf['screen']); ?>
					</footer>
				<?php } ?>
			</section>
		<?php } ?>
		<?php if ($codesOn && SignInGrant::mayPersons($this)) { ?>
			<section class="widget">
				<header>
					<h2><i class="cmsIcon cmsIcon-user-key"></i>Authorize other user</h2>
				</header>
				<main>
					<div data-device-feedback="person"><?= \message::flush('devicesPerson') ?></div>
					<p class="tagsHint">Sign another person in on a device. Your choice is documented in the system's sign-in log.</p>
					<?php if ($accountsOf['person'] === []) { ?>
						<p class="profileEmpty">There is nobody else to sign in.</p>
					<?php } ?>
				</main>
				<?php if ($accountsOf['person'] !== []) { ?>
					<footer>
						<?php $pick('person', 'User', $accountsOf['person']); ?>
					</footer>
				<?php } ?>
			</section>
		<?php } ?>
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
			<h1>My devices</h1>
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
<?php if ($codesOn && $me) { include __DIR__ . '/authDialogs.tpl.php'; } ?>
