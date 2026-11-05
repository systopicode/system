<?php
/**
 * The camera and the eight boxes of a device code.
 *
 * Manual entry is submitted with the check button; a camera hit uses
 * the existing scanner submission path.
 *
 * @var string $scanHost profile | approve, what `qr-scan.js` does with a hit
 * @var string $scanAction where the form goes
 * @var string $scanLead
 * @var array<string, int|string> $scanFields further hidden fields
 * @var bool $scanAuto the box comes in by ajax, no click opens the camera
 */
	$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<div class="qrScan" data-qr-host="<?= $e($scanHost) ?>" data-base="<?= $e($this->href) ?>" data-kind="<?= $e($scanFields['kind'] ?? '') ?>"<?= !empty($scanAuto) ? ' data-qr-auto' : '' ?> hidden>
	<div class="qrScanStage">
		<video playsinline muted autoplay disablepictureinpicture disableremoteplayback></video>
		<div class="qrScanFrame" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
		<button type="button" class="qrScanTorch" data-qr-torch hidden aria-label="Light"><i class="cmsIcon cmsIcon-bolt"></i></button>
	</div>
	<p class="qrScanHint" data-qr-hint>Hold the QR code steady and well lit in the frame.</p>
		<div class="remoteMethods">
			<button type="button" class="button" data-remote-code><i class="cmsIcon cmsIcon-input-numeric"></i> Type code</button>
		</div>
	<div class="cms message qrScanMsg" data-qr-msg hidden></div>
	<div class="qrScanDim remoteAbort" data-qr-dim hidden>
		<button type="button" class="button submit" data-qr-again><i class="cmsIcon cmsIcon-arrows-rotate"></i> <span data-qr-label>Try again</span></button>
	</div>
</div>
<form class="ajax remoteCode" data-feedback="parameters" data-code-form method="put" action="<?= $e($scanAction) ?>">
    <div class="remoteEntryFields">
        <p class="tagsHint cameraStatus" data-qr-status role="status" hidden></p>
	<p class="cmsBoxLead"><?= $e($scanLead) ?></p>
    <input type="hidden" name="scanInstance" value="<?= $e($scanInstance ?? '') ?>">
	<?php foreach ($scanFields as $field => $value) { ?>
		<input type="hidden" name="<?= $e($field) ?>" value="<?= $e($value) ?>">
	<?php } ?>
	<div class="remoteCodeEntry">
		<div class="authCodeSlots" data-code-slots>
			<?php for ($slot = 0; $slot < 8; $slot++) {
				if ($slot === 4) {
					echo '<span class="authCodeDash" aria-hidden="true">-</span>';
				}
			?>
			<input required pattern="[A-Za-z0-9]" data-slot="<?= $slot ?>" inputmode="text" autocapitalize="characters" spellcheck="false" autocomplete="<?= $slot === 0 ? 'one-time-code' : 'off' ?>" aria-label="Character <?= $slot + 1 ?>">
			<?php } ?>
			<input type="hidden" name="deviceCode" value="">
		</div>
		<button class="button submit" type="submit" aria-label="Continue" title="Continue"><i class="cmsIcon cmsIcon-check"></i></button>
	</div>
    </div>
		<div class="remoteSwitch">
			<button type="button" class="button" data-qr-start><i class="cmsIcon cmsIcon-object-scan"></i> Scan QR code</button>
		</div>
</form>
