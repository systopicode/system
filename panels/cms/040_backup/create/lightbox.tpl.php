<?php
switch ($this->action) {
	default:
		$include = 'settings.tpl.php';
		$title = 'Create a new Backup';
		$close = TRUE;
		break;
	case 'createTaskList':
	case 'addMysqldump':
	case 'addMediaDir':
		$title = 'Backup running' . http::ajax();
		$include = 'progress.tpl.php';
		$close = FALSE;
		break;
	case 'finishBackup':
		$title = 'Backup completed.';
		$include = 'progress.tpl.php';
		$close = TRUE;
		break;
}
?>
<div class="cms cmslightbox" data-size="1">
    <section>
		<header>
			<h1><?= $title ?></h1>
			<?php if ($close) { ?>
				<ul class="barmenu">
					<li>
						<a class="ajax" href="../"><i class="fa-light fa-xmark-large"></i></a>
					</li>
				</ul>
			<?php } ?>
		</header>
		<?php
		include $include;
		?>
    </section>
</div>