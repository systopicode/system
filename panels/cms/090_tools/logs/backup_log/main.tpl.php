<?php
/** @var \Systopic\System\Panels\PanelNode $this */
?>
<h1>Backup log</h1>
<p class="tagsHint">What a backup writes while it runs. The same text as in Backup.</p>
<p class="tagsHint">Backup execution entries older than 90 days are eligible for automatic cleanup. Undated legacy records require administrator review.</p>
<section class="widget is-list logList">
	<header>
		<h2>Entries</h2>
		<a target="_self" href="<?= htmlspecialchars($this->getHref(\http::$root) . 'downloadLog') ?>" class="button"><i class="fa-regular fa-file-arrow-down"></i><span>Download</span></a>
		<?php if (\Systopic\System\Auth\Session::isSuperuser()) { ?>
		<a data-on_click="userConfirm" data-action="clearLog" data-confirm="Clear this log?" class="button"><i class="fa-regular fa-broom"></i><span>Clear</span></a>
		<?php } ?>
	</header>
	<?php include dirname(__DIR__, 3) . '/040_backup/log/text.inc.php'; ?>
</section>
