<main class="backupProgress">
	<?php include __DIR__ . '/../progress.bar.tpl.php'; ?>
	<?= message::flush(); ?>
</main>
<footer>
	<?php if ($this->action !== 'finishBackup') { ?>
		<a href="cancel" class="button submit ajax"><span>Cancel</span></a>
	<?php } else { ?>
		<a href="../" class="button ajax submit"><span>Backup finished</span></a>
	<?php } ?>
</footer>
