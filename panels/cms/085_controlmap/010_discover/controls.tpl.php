<?php
/**
 * Show all: every panel in one table instead of the chosen level.
 * Discover: read the folders and the code again — what every view does
 * anyway; the button is for a change made meanwhile, without leaving.
 *
 * @var \Systopic\System\Panels\Root\Cms\Controlmap\Discover\Panel $this
 */

$panel = rawurlencode($this->parent->selectedPath);
?>
<div class="rightAlign">
	<?php if ($this->showAll) { ?>
		<a class="button ajax active" href="./?all=0" title="Back to the level chosen in the tree"><i class="fa-regular fa-list-tree"></i><span>Show all</span></a>
	<?php } else { ?>
		<a class="button ajax" href="./?all=1" title="Every panel in one table"><i class="fa-regular fa-list-tree"></i><span>Show all</span></a>
	<?php } ?>
	<a class="button ajax" href="./?<?= $this->showAll ? 'all=1' : 'panel=' . $panel ?>" title="Read the folders and the code again"><i class="fa-regular fa-magnifying-glass-arrows-rotate"></i><span>Discover</span></a>
</div>
