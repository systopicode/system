<?php
use Systopic\System\Auth\SignInCodes;
use Systopic\System\Auth\SignInGrant;
	$offer = SignInCodes::shown();
	$offerKind = '';
	$offerName = '';
	foreach ($accountsOf as $kind => $list) {
		foreach ($list as $account) {
			if ($offer && $account['id'] === (int) $offer['user']) {
				$offerKind = $kind;
				$offerName = $account['label'];
			}
		}
	}
	$offer = $offerName !== '' ? $offer : null;
	$offerUrl = $offer ? \Systopic\System\Auth\CodeMail::link($offer['code']) : '';
	$offerSvg = ($offerUrl !== '' && class_exists('qr')) ? \qr::svgMarkup(\qr::payload('url', ['url' => $offerUrl]), 4, 'M', 'qr-code') : '';
	$authKind = $this->authKind ?: 'self';
	$addKind = $this->action === 'createDeviceCode' && (int) \http::posted('account', 0) > 0 ? $authKind : '';
	$scanOpen = !\http::ajax() && !$this->deviceAllowed && (($deviceCode !== '' && $pending === null) || ($this->action === 'approveDeviceCode' && trim((string) \http::posted('deviceCode', '')) !== ''));
	$scanKind = $scanOpen ? $authKind : '';
	$meFull = trim((string) $me->fullname);
	$meLabel = $meFull !== '' ? $meFull : (string) $me->name;
	// Who the other device is: the one who signs it in says so, and is told it goes into the log.
	$chooser = static function (string $device, array $choices) use ($meLabel, $e): void {
		?>
        <?php $infoScan = true; $infoName = $choices[0]['label'] ?? ''; include __DIR__ . '/authInfo.tpl.php'; ?>
		<?php if (count($choices) > 1) { ?>
			<label class="field"><span>Account</span>
				<select name="account" required data-identify-select>
					<?php foreach ($choices as $account) { ?>
						<option value="<?= (int) $account['id'] ?>"><?= $e($account['label']) ?></option>
					<?php } ?>
				</select>
			</label>
		<?php } else { ?>
			<input type="hidden" name="account" value="<?= (int) ($choices[0]['id'] ?? 0) ?>">
		<?php }
	};
	foreach (['self', 'screen', 'person'] as $kind) {
		if ($accountsOf[$kind] === []) {
			continue;
		}
		$kindClass = 'kind' . ucfirst($kind);
		$boxOffer = $offerKind === $kind ? $offer : null;
		$own = $kind === 'self';
		if ($own || $boxOffer || $addKind === $kind) {
?>
<div class="cms cmslightbox deviceBox addDeviceBox <?= $kindClass ?>" data-device-kind="<?= $e($kind) ?>" data-feedback="parameters" data-code-refresh="<?= $e($this->href . 'refreshScreenDevices') ?>"<?= $addKind === $kind ? '' : ' hidden' ?><?= $boxOffer ? ' data-code-value="' . $e($boxOffer['code']) . '"' : '' ?>>
	<section class="is-expanded">
		<header>
			<h1>Create sign-in code</h1>
			<ul class="barmenu">
				<li><button type="button" data-box-close data-qr-close aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button></li>
			</ul>
		</header>
		<main class="deviceBoxMain">
			<div data-remote-feedback><?= $addKind === $kind ? \message::flush('addDevice') : '' ?></div>
			<?php if ($boxOffer && $offerSvg !== '') {
                $infoScan = false;
                $infoName = $offerName;
                include __DIR__ . '/authInfo.tpl.php';
                include __DIR__ . '/codeOffer.tpl.php';
            ?>
			<?php } ?>
		</main>
	</section>
</div>
<?php } ?>
<?php $scanInstance = substr(hash('sha256', session_id() . ':scan:' . $kind), 0, 16); ?>
<div class="cms cmslightbox deviceBox scanCodeBox <?= $kindClass ?>" data-scan-instance="<?= $scanInstance ?>" data-feedback="parameters"<?= $scanKind === $kind ? '' : ' hidden' ?>>
	<section class="is-expanded">
		<header>
			<h1>Scan sign-in code</h1>
			<ul class="barmenu">
				<li><button type="button" data-box-close data-qr-close aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button></li>
			</ul>
		</header>
		<main class="deviceBoxMain">
			<div data-remote-feedback><?php if ($scanKind === $kind) { ?><?= \message::flush('deviceBox') ?>
			<?php if ($this->action !== 'approveDeviceCode') { ?>
				<div class="cms message"><div class="error"><p>That code is wrong or has expired.</p></div></div>
			<?php } ?><?php } ?></div>
			<?php
            $infoScan = true;
            $infoName = $accountsOf[$kind][0]['label'];
            include __DIR__ . '/authInfo.tpl.php';
			$scanHost = 'approve';
			$scanAction = $this->href . 'approveDeviceCode';
			$scanLead = 'The other device shows a code on its sign-in page.';
			$scanFields = ['kind' => $kind, 'account' => $accountsOf[$kind][0]['id']];
			include __DIR__ . '/scanCode.tpl.php';
			?>
		</main>
	</section>
</div>
<?php } ?>
<?php if ($pending) { ?>
<?php $scanInstance = substr(hash('sha256', session_id() . ':allow:' . $deviceCode), 0, 16); ?>
<div class="cms cmslightbox deviceBox allowDeviceBox" data-scan-instance="<?= $scanInstance ?>">
	<section class="is-expanded">
		<header>
			<h1>Allow this device?</h1>
			<ul class="barmenu">
				<li><button type="button" data-box-close data-qr-close aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button></li>
			</ul>
		</header>
        <main class="deviceBoxMain">
            <div data-remote-feedback></div>
            <p class="cmsBoxLead">Confirm the device in front of you.</p>
            <?php include __DIR__ . '/requestDevice.tpl.php'; ?>
			<form class="ajax" data-feedback="parameters" method="put" action="<?= $e($this->href . 'approveDeviceCode') ?>">
				<input type="hidden" name="feedback" value="allow">
                <input type="hidden" name="scanInstance" value="<?= $scanInstance ?>">
                <input type="hidden" name="deviceCode" value="<?= $e($deviceCode) ?>">
				<input type="hidden" name="kind" value="<?= $e($this->authKind) ?>">
				<?php $chooser('this device', $accountsOf[$this->authKind] ?? $accounts); ?>
				<div class="cmsBoxActions">
					<button class="button submit" type="submit"><i class="cmsIcon cmsIcon-check"></i>Allow</button>
				</div>
			</form>
		</main>
	</section>
</div>
<?php } ?>
