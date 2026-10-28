<?php
switch ($this->action) {
	default:
		$include = 'confirm.tpl.php';
		$title = 'Restore backup';
		$close = TRUE;
		break;
	case 'createTaskList':
		if (($this->parent->state->restoreNotice ?? '') === 'nothing') {
			$this->parent->state->restoreNotice = '';
			$include = 'confirm.tpl.php';
			$title = 'Restore backup';
			$close = TRUE;
			break;
		}
	case 'extractMysqldump':
	case 'extractSettings':
	case 'extractEnvironment':
	case 'extractMediaDir':
		$title = 'Restoring';
		$include = 'progress.tpl.php';
		$close = FALSE;
		break;
	case 'finishRestore':
		$title = 'Restore complete';
		$include = 'progress.tpl.php';
		$close = FALSE;
		break;
	case 'clearTempfiles':
		$title = 'Restore complete';
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
						<a class="ajax" href="../" aria-label="Close"><i class="fa-regular fa-xmark"></i></a>
					</li>
				</ul>
			<?php } ?>
		</header>
		<?php
		include $include;
		?>
    </section>
</div>