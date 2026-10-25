<?php
/** Dashboard: versions, stage and disk space. @var \Systopic\System\Panels\PanelNode $this */

use Systopic\System\Queries\SystemStatus\SystemStatus;

$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$free = @disk_free_space((string) \fs::$siteRoot);
$total = @disk_total_space((string) \fs::$siteRoot);
$rows = [
	'fluxo cms' => 'GUI ' . array_key_first(\Systopic\System\Sys\Release::notes()),
	'PHP' => PHP_VERSION,
	'Database' => SystemStatus::databaseVersion(\Systopic\Db\Scope::default()),
	'Stage' => (string) STAGE,
	'Debug' => DEBUG() ? 'On' : 'Off',
];
$used = $free !== false && $total ? 1 - $free / $total : null;
?>
<header><h2><i class="fa-regular fa-server"></i>System</h2></header>
<main>
	<dl class="dashFacts">
		<?php foreach ($rows as $label => $value) { ?>
			<dt><?= $e($label) ?></dt><dd><?= $e($value) ?></dd>
		<?php } ?>
		<?php if ($used !== null) { ?>
			<dt>Disk</dt>
			<dd><?= $e(\str::formatbytes((int) $free)) ?> free of <?= $e(\str::formatbytes((int) $total)) ?></dd>
		<?php } ?>
	</dl>
	<?php if ($used !== null) { ?>
		<div class="dashMeter<?= $used > 0.9 ? ' dashWarn' : '' ?>"><span style="width:<?= round($used * 100) ?>%"></span></div>
	<?php } ?>
</main>
