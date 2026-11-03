<?php
if (empty($this->backupfilestable)) {
    $this->render('actions-bar.tpl.php');
	echo html::section()->class('cms')->h1('No backups yet.');
    echo '<p class="tagsHint">No backup is available for restoring this installation.</p>';
	return;
}
?>
<section class="widget is-list">
    <header><h2>Backups</h2><?php $this->render('actions-bar.tpl.php'); ?></header>
    <p class="tagsHint">An archive marked complete has finished creation. Restore performs additional structure checks before replacement; a completed archive is not a tested recovery.</p>
	<div class="tableScroll">
		<table class="hover">
			<thead>
				<tr>
					<th>Date Started</th>
					<!--<th>Time Finished</th>-->
					<!--<th>Duration</th>-->
					<th>Size</th>
					<th>Stage</th>
					<th>Database Name</th>
					<th>User</th>
					<th>Note</th>
					<th>Contents</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ($this->backupfilestable as $key => $backup) {
					include 'backup.tpl.php';
				}
				?>
			</tbody>
		</table>
	</div>
</section>
