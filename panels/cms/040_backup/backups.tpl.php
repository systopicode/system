<?php
if (empty($this->backupfilestable)) {
	echo html::section()->class('cms')->h1('No Backups created.');
	return;
}
?>
<section class="cms">
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
				<th>Files</th>
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
</section>
