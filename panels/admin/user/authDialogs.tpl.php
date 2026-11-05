<?php
use Systopic\System\Auth\SignInCodes;
use Systopic\System\Auth\SignInGrant;
	$fixed = $fixedDialog ?? null;
	$dialogBase = $fixed['base'] ?? $this->href;
	$dialogRefresh = $fixed['refresh'] ?? ($this->href . 'refreshScreenDevices');
	$offer = $fixed ? null : SignInCodes::shown();
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
	$authKind = $fixed['kind'] ?? ($this->authKind ?: 'self');
	$addKind = $fixed ? ($fixed['mode'] === 'create' ? $authKind : '') : ($this->action === 'createDeviceCode' && (int) \http::posted('account', 0) > 0 ? $authKind : '');
	$scanOpen = $fixed ? $fixed['mode'] === 'scan' : !\http::ajax() && !$this->deviceAllowed && (($deviceCode !== '' && $pending === null) || ($this->action === 'approveDeviceCode' && trim((string) \http::posted('deviceCode', '')) !== ''));
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
		if ($accountsOf[$kind] !== []) {
?>
<div data-mobile-dialog="sheet" class="cms cmslightbox deviceBox addDeviceBox <?= $kindClass ?>" data-device-kind="<?= $e($kind) ?>" data-feedback="parameters" data-code-refresh="<?= $e($dialogRefresh) ?>"<?= $addKind === $kind ? '' : ' hidden' ?><?= $boxOffer ? ' data-code-value="' . $e($boxOffer['code']) . '"' : '' ?>>
	<section>
		<header>
			<h1>Create sign-in code</h1>
			<ul class="barmenu">
				<li><?php if (!empty($fixed['close'])) { ?><a class="ajax" data-qr-close href="<?= $e($fixed['close']) ?>" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a><?php } else { ?><button type="button" data-box-close data-qr-close aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button><?php } ?></li>
			</ul>
		</header>
		<main class="deviceBoxMain">
			<div data-remote-feedback><?= $addKind === $kind ? \message::flush('addDevice') : '' ?></div>
            <div data-create-gate<?= $addKind === $kind && $boxOffer ? ' hidden' : '' ?>>
                <?php $infoScan = false; $infoName = $accountsOf[$kind][0]['label']; include __DIR__ . '/authInfo.tpl.php'; ?>
                <div class="remoteOffer">
                    <div class="codeGateStage"><div class="codeGateCover" aria-hidden="true"><i class="cmsIcon cmsIcon-qrcode"></i></div></div>
                    <form class="ajax cmsBoxActions" data-feedback="parameters" method="put" action="<?= $e($dialogBase . 'createDeviceCode') ?>">
                        <input type="hidden" name="kind" value="<?= $e($kind) ?>">
                        <input type="hidden" name="account" value="<?= (int) $accountsOf[$kind][0]['id'] ?>">
                        <button type="submit" class="button submit" data-focus-id="device-ack-<?= $e($kind) ?>"><i class="cmsIcon cmsIcon-check"></i>I understand</button>
                    </form>
                </div>
            </div>
            <div data-create-result<?= $addKind === $kind && $boxOffer ? '' : ' hidden' ?>>
			<?php if ($boxOffer && $offerSvg !== '') {
                $infoScan = false;
                $infoName = $offerName;
                include __DIR__ . '/createResult.tpl.php';
            ?>
			<?php } ?>
            </div>
		</main>
	</section>
</div>
<?php } ?>
<?php $scanInstance = substr(hash('sha256', session_id() . ':scan:' . $kind), 0, 16); ?>
<div data-mobile-dialog="sheet" class="cms cmslightbox deviceBox scanCodeBox <?= $kindClass ?>" data-code-refresh="<?= $e($dialogRefresh) ?>" data-scan-instance="<?= $scanInstance ?>" data-feedback="parameters"<?= $scanKind === $kind ? '' : ' hidden' ?>>
	<section>
		<header>
			<h1>Scan sign-in code</h1>
			<ul class="barmenu">
				<li><?php if (!empty($fixed['close'])) { ?><a class="ajax" data-qr-close href="<?= $e($fixed['close']) ?>" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a><?php } else { ?><button type="button" data-box-close data-qr-close aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button><?php } ?></li>
			</ul>
		</header>
		<main class="deviceBoxMain">
			<div data-remote-feedback><?php if ($scanKind === $kind) { ?><?= \message::flush('deviceBox') ?>
			<?php if (!$fixed && $this->action !== 'approveDeviceCode') { ?>
				<div class="cms message"><div class="error"><p>That code is wrong or has expired.</p></div></div>
			<?php } ?><?php } ?></div>
			<?php
            $infoScan = true;
            $infoName = $accountsOf[$kind][0]['label'];
            include __DIR__ . '/authInfo.tpl.php';
			?>
            <div class="deviceScanGate" data-scan-gate data-gated>
                <div data-scan-content inert aria-hidden="true">
            <?php
			$scanHost = 'approve';
			$scanAction = $dialogBase . 'approveDeviceCode';
			$scanLead = 'Enter the code shown on the other device’s sign-in page.';
			$scanFields = ['kind' => $kind, 'account' => $accountsOf[$kind][0]['id']];
			include __DIR__ . '/scanCode.tpl.php';
			?>
                </div>
                <div class="deviceGatePlaceholder" data-scan-placeholder>
                    <div class="deviceGateDrawing" aria-hidden="true"><i class="cmsIcon cmsIcon-object-scan"></i></div>
                    <button type="button" class="button submit" data-scan-understood><i class="cmsIcon cmsIcon-check"></i>I understand</button>
                </div>
            </div>
		</main>
	</section>
</div>
<?php } ?>
<?php if ($pending) { ?>
<?php $scanInstance = substr(hash('sha256', session_id() . ':allow:' . $deviceCode), 0, 16); ?>
<div data-mobile-dialog="sheet" class="cms cmslightbox deviceBox allowDeviceBox" data-scan-instance="<?= $scanInstance ?>">
	<section>
		<header>
			<h1>Allow this device?</h1>
			<ul class="barmenu">
				<li><?php if (!empty($fixed['close'])) { ?><a class="ajax" data-qr-close href="<?= $e($fixed['close']) ?>" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a><?php } else { ?><button type="button" data-box-close data-qr-close aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></button><?php } ?></li>
			</ul>
		</header>
        <main class="deviceBoxMain">
            <div data-remote-feedback></div>
            <p class="cmsBoxLead">Check that this is the device you want to sign in.</p>
            <?php include __DIR__ . '/requestDevice.tpl.php'; ?>
			<form class="ajax" data-feedback="parameters" method="put" action="<?= $e($dialogBase . 'approveDeviceCode') ?>">
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
