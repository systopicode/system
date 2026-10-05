<?php
switch ($this->action) {
	default:
		$include = 'confirm.tpl.php';
		$title = 'Restore Backup';
		$close = TRUE;
		break;
	case 'createTaskList':
	case 'extractMysqldump':
	case 'extractMediaDir':
		$title = 'Restore running ...';
		$include = 'progress.tpl.php';
		$close = FALSE;
		break;
	case 'finishRestore':
		$title = 'Restore completed.';
		$include = 'progress.tpl.php';
		$close = FALSE;
		break;
	case 'clearTempfiles':
		$title = 'All Done';
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