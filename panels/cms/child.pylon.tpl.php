<?php

/** @var \Systopic\System\Panels\PanelNode $this */

$pylonWidth = $this->state->pylonWidth ?? 200;
if ($this->childSelected && isset($this->childSelected->state->pylonWidth)) {
	$pylonWidth = $this->childSelected->state->pylonWidth;
}
?>
<div class="pylon cms" style="max-width: <?= $pylonWidth ?>px;">
	<header></header>
	<nav><?= $this->getNavi() ?></nav>
	<div data-drag_type='none' data-on_draginit='draginitPylonwidth'></div>
</div>