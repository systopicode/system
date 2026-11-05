<?php
/**
 * What the one who authorizes is told. `ui.js` replaces the name when a widget
 * chose the account.
 *
 * @var bool $infoScan by a device code, else by a code that is made
 * @var string $infoName the account
 */
	$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<div class="cms message is-stay" data-auth-info><div class="info">
    <p data-auth-short hidden>Account: <strong data-identify-name><?= $e($infoName) ?></strong></p>
    <div data-auth-long>
	<?php if ($infoScan) { ?>
		<p>Scan or enter the code shown on the other device to sign it in as <strong data-identify-name><?= $e($infoName) ?></strong>.</p>
	<?php } else { ?>
		<p>Anyone with this code can sign in as <strong data-identify-name><?= $e($infoName) ?></strong> on one device.</p>
	<?php } ?>
	<p>This sign-in is recorded in the sign-in log.</p>
</div></div></div>
