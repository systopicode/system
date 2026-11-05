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
$devicesShown = array_slice($ownDevices, 0, 5);
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
        <?php if ($kind === 'self') { ?>
            <input type="hidden" name="account" value="<?= (int) ($choices[0]['id'] ?? 0) ?>">
        <?php } else { ?>
            <input type="hidden" name="account" value="">
        <?php } ?>
		<div class="deviceWaysActions">
			<button type="button" class="button" <?= $kind !== 'self' ? 'disabled' : '' ?> data-open-box=".scanCodeBox.kind<?= ucfirst($kind) ?>"><i class="cmsIcon cmsIcon-object-scan"></i> Scan code</button>
			<button class="button submit" type="button" <?= $kind !== 'self' ? 'disabled' : '' ?> data-open-box=".addDeviceBox.kind<?= ucfirst($kind) ?>"><i class="cmsIcon cmsIcon-qrcode"></i> Create code</button>
		</div>
	</form>
	<?php
};

$accountChoices = static function (string $kind, array $choices) use ($e): void { ?>
    <input type="search" data-account-search aria-label="<?= $kind === 'screen' ? 'Search screens' : 'Search people' ?>" placeholder="<?= $kind === 'screen' ? 'Search screens' : 'Search people' ?>" autocomplete="off">
    <div class="cmsChoices accountChoices" role="group" aria-label="<?= $kind === 'screen' ? 'Screens' : 'People' ?>">
    <?php foreach ($choices as $account) { ?>
        <button type="button" class="cmsChoice" data-account-choice="<?= (int) $account['id'] ?>" data-account-label="<?= $e($account['label']) ?>" aria-pressed="false"><img class="userListAvatar" alt="" loading="lazy" src="<?= $e(Portrait::src((int) $account['id'], $account['email'] ?? null, $kind === 'screen')) ?>"><span><b><?= $e($account['label']) ?></b></span></button>
    <?php } ?>
    </div>
    <p class="tagsHint" data-account-empty hidden>No accounts found. Try another name.</p>
<?php };

$sections = [
    'profile' => ['Profile', 'Edit your photo and account details.', 'id-card'],
    'security' => ['Sign-in & Security', 'Manage how you sign in.', 'shield-check'],
    'devices' => ['My devices', 'Review your devices or add a new one.', 'phone-laptop'],
];
if ($codesOn) {
    $sections['pairing'] = ['Pairing & Authorization', 'Pair a screen or authorize another person.', 'qrcode'];
}
$sections['preferences'] = ['Preferences', 'Choose an appearance or reset your layout.', 'sliders'];
$area = (string) (\http::get('section') ?: ($this->state->accountSection ?? 'devices'));
if (!isset($sections[$area])) $area = 'devices';
if ($this->action === 'changePassword') $area = 'security';
$this->state->accountSection = $area;

$portrait =$me ? Portrait::src((int) $me->id, $me->email, $me->type === \Systopic\System\Tables\Users\Model::SCREEN) : '';
$field = static fn(string $label, string $type, string $action, ?string $value, string $caps = 'on'): string
	=> "<label class='field'><span>$label</span><input type='$type' autocapitalize='$caps' name='value' data-on_blur='$action' value='{$e($value)}' autocomplete='off' data-1p-ignore data-lpignore='true' readonly onpointerdown='this.readOnly=false' onkeydown='this.readOnly=false' spellcheck='off'></label>";
$preview = $this->fakeRoles;
?>
<?php
$accountDetail = $this->action !== 'showOverview' && ($this->action === 'showSection' || (string) \http::get('section', '') !== '');
$accountMotion = \http::ajax() ? ($this->action === 'showSection' ? 'forward' : ($this->action === 'showOverview' ? 'back' : '')) : '';
?>
<section class="cms profile" data-account-view="<?= $accountDetail ? 'detail' : 'overview' ?>" data-account-motion="<?= $accountMotion ?>">
    <h1 class="cmsA11yStatus">Account</h1>
    <div class="accountLayout">
    <div class="accountOverview">
	<div class="accountHeader">
	<?php $this->render("actions-bar.tpl.php"); ?>
	<div class="profileHead">
        <div class="signet accountAvatar"><span><?= $e($this->initials) ?></span><?php if ($portrait !== '') { ?><img referrerpolicy="no-referrer" src="<?= $e($portrait) ?>" alt=""><?php } ?></div>
		<div>
			<h1><?= $e($me?->fullname ?: $me?->name ?: 'Profile') ?></h1>
			<?php if ($me?->email) { ?>
				<p><?= $e($me->email) ?></p>
			<?php } ?>

            <?php if ($me?->lastSignIn) { ?>
                <p><i class="cmsIcon cmsIcon-user-clock"></i>Last signed in <?= $e(\Systopic\System\Sys\LocalTime::format($me->lastSignIn, \Systopic\System\Sys\LocalTime::DATETIME)) ?></p>
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
	</div>
	<?php if ($preview !== []) { ?>
		<p class="profilePreview"><i class="cmsIcon cmsIcon-eye"></i>Viewing the System as <?= $e(implode(', ', $preview)) ?>. <button type="button" data-cms-action data-on_click="unsetFakeRoles">Stop</button></p>
	<?php } ?>
        <nav class="accountNavigation" aria-label="Account sections">
            <section class="widget accountDesktopNavigation">
                <header><h2><i class="cmsIcon cmsIcon-user"></i>Account settings</h2></header>
                <main><?php $accountNavigationVariant = 'desktop'; include __DIR__ . '/navigation.tpl.php'; ?></main>
            </section>
        </nav>
    </div>
        <div class="accountContent" data-account-sheet data-account-open="<?= $accountDetail ? '1' : '0' ?>" data-mobile-dialog="sheet">
            <section class="accountSectionSurface profile">
                <header class="accountSheetHeader"><h1><?= $e($sections[$area][0]) ?></h1><ul class="barmenu"><li><a class="ajax button transparent icononly" href="<?= $e($this->href . 'showOverview') ?>" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a></li></ul></header>
                <main class="accountSectionBody">
            <div class="profileGrid" data-account-area="<?= $e($area) ?>">

		<?php if ($me && $area === 'profile') { ?>
			<section class="widget">
                <header><h2><i class="cmsIcon cmsIcon-id-card"></i>Profile</h2></header>
				<main class="accountProfileFields">
                    <?php include __DIR__ . '/portrait.tpl.php'; ?>
                    <div class="fieldGrid">
					<?= \message::flush('account') ?>
					<?= $field('Username', 'text', 'updateUserName', $me->name, 'off') ?>
					<?= $field('Full name', 'text', 'updateUserFullname', $me->fullname) ?>
					<?= $field('Email', 'email', 'updateUserEmail', $me->email, 'off') ?>
                    </div>
				</main>

			</section>
		<?php } ?>
		<?php if ($me && $area === 'security') { echo \Systopic\System\Auth\Passkeys::render($me, 'widget'); include __DIR__ . '/password.tpl.php'; } ?>
        <?php if ($area === 'devices') { ?>
		<section class="widget">
			<header>
				<h2><i class="cmsIcon cmsIcon-phone-laptop"></i>Connected devices</h2>
			</header>
			<main>
				<div data-device-feedback="self"><?= \message::flush('devices') ?></div>
				<?php if (!$codesOn) { ?>
					<p class="tagsHint">Device codes are disabled. You can still sign in with your password or a passkey.</p>
				<?php } else { ?>
					<p class="tagsHint">Devices with access to your account. Sign out any you no longer use.</p>
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
				</footer>
			<?php } ?>
		</section>
        <?php } ?>
        <?php if ($area === 'devices' && $codesOn && $me) { ?>
            <section class="widget">
                <header><h2><i class="cmsIcon cmsIcon-plus"></i>Connect a device</h2></header>
                <main><p class="tagsHint">Use a code to sign in to your account on another phone, tablet or computer.</p></main>
                <footer><?php $pick('self', '', $accountsOf['self']); ?></footer>
            </section>
        <?php } ?>
		<?php if ($area === 'pairing' && $codesOn && SignInGrant::mayScreens($this)) { ?>
			<section class="widget">
				<header>
					<h2><i class="cmsIcon cmsIcon-tv"></i>Pair a screen</h2>
				</header>
                <main><div data-device-feedback="screen"><?= \message::flush('devicesScreen') ?></div><p class="tagsHint">Choose the screen account to use on the display, then scan or create a code.</p><?php $accountChoices('screen', $accountsOf['screen']); ?></main>
				<?php if ($accountsOf['screen'] !== []) { ?>
					<footer>
						<?php $pick('screen', 'Screen', $accountsOf['screen']); ?>
					</footer>
				<?php } ?>
			</section>
		<?php } ?>
		<?php if ($area === 'pairing' && $codesOn && SignInGrant::mayPersons($this)) { ?>
			<section class="widget">
				<header>
					<h2><i class="cmsIcon cmsIcon-user-key"></i>Authorize a person</h2>
				</header>
				<main>
					<div data-device-feedback="person"><?= \message::flush('devicesPerson') ?></div>
					<p class="tagsHint">Choose the person to sign in on another device, then scan or create a code.</p>
                    <?php $accountChoices('person', $accountsOf['person']); ?>
					<?php if ($accountsOf['person'] === []) { ?>
						<p class="profileEmpty">No other people are available to authorize.</p>
					<?php } ?>
				</main>
				<?php if ($accountsOf['person'] !== []) { ?>
					<footer>
						<?php $pick('person', 'User', $accountsOf['person']); ?>
					</footer>
				<?php } ?>
			</section>
		<?php } ?>
        <?php if ($area === 'pairing' && $codesOn && SignInGrant::mayScreens($this)) { ?>
            <section class="widget accountWide">
                <header><h2><i class="cmsIcon cmsIcon-tv"></i>Connected screens</h2></header>
                <main><div data-paired-screen-devices><?php include __DIR__ . '/pairedDevices.tpl.php'; ?></div></main>
            </section>
        <?php } ?>
		<?php if ($me && $area === 'preferences') {
			$appearance = (string) ($me->appearance ?? '');
			if (!in_array($appearance, ['light', 'dark', 'system'], true)) {
				$appearance = 'system';
			}
		?>
		<section class="widget">
			<header><h2><i class="cmsIcon cmsIcon-sun"></i>Appearance</h2></header>
			<main>
				<p class="tagsHint">Choose Light or Dark, or use System to follow this device’s appearance.</p>
				<div class="appearanceChoices" role="radiogroup" aria-label="Appearance">
					<?php foreach (['light' => ['cmsIcon cmsIcon-sun', 'Light', 'Light background.'], 'dark' => ['cmsIcon cmsIcon-moon', 'Dark', 'Dark background.'], 'system' => ['cmsIcon cmsIcon-circle-half-stroke', 'System', 'Matches this device.']] as $value => $choice) { ?>
						<button type="button" role="radio" tabindex="<?= $appearance === $value ? '0' : '-1' ?>" data-on_click="updateAppearance" data-value="<?= $value ?>" aria-checked="<?= $appearance === $value ? 'true' : 'false' ?>"><i class="<?= $choice[0] ?>"></i><span><?= $choice[1] ?></span><small><?= $choice[2] ?></small></button>
					<?php } ?>
				</div>
			</main>
        </section>
        <section class="widget">
            <header><h2><i class="cmsIcon cmsIcon-arrow-rotate-left"></i>Reset layout</h2></header>
            <main>
                <?= \message::flush('layout') ?>
                <p class="tagsHint">Restore your default workspace: open panels, expanded trees, filters and sorting.</p>
            </main>
            <footer>
				<button type="button" class="button submit" data-on_click="userConfirm" data-action="resetState" data-confirm="Reset your layout, filters and sorting in all sections?"><i class="cmsIcon cmsIcon-arrow-rotate-left"></i>Reset layout</button>
			</footer>
		</section>
		<?php } ?>
	</div>
                </main>
            </section>
        </div>
    </div>
</section>
<?php if ($codesOn && count($ownDevices) > 5) { ?>
<div class="cms cmslightbox deviceListBox" data-mobile-dialog="sheet" hidden>
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
