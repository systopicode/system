<main class="backupProgress">
	<?php include __DIR__ . '/../progress.bar.tpl.php'; ?>
	<?= message::flush(); ?>
</main>
<?php if ($this->action === 'clearTempfiles') { ?>
<footer>
	<a href="../" class="button ajax submit"><span>Restore finished</span></a>
</footer>
<?php } ?>
