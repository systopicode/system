<?php
/** @var \Systopic\System\Panels\Root\Cms\Backup\Panel $this */
$index = (int) $this->parent->state->taskListNextIndex;
$total = count($this->parent->state->taskList);
$progress = $total > 0 ? (int) round($index / $total * 100) : 0;
$info = $index . ' / ' . $total;
?>
<div class="bar">
	<b><?= htmlspecialchars($info, ENT_QUOTES) ?></b>
	<div style="width: <?= $progress ?>%"><b><?= htmlspecialchars($info, ENT_QUOTES) ?></b></div>
</div>
