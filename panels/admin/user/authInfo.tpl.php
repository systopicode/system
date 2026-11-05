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
<div class="cms message is-stay"><div class="info">
	<?php if ($infoScan) { ?>
		<p>By entering or scanning the code, you authorize the device to be signed in as '<span data-identify-name><?= $e($infoName) ?></span>'.</p>
	<?php } else { ?>
		<p>By creating the auth code, you allow any person who gets to know it to sign in as '<span data-identify-name><?= $e($infoName) ?></span>' on one device.</p>
	<?php } ?>
	<p>Your choice is documented in the system's sign-in log.</p>
</div></div>
