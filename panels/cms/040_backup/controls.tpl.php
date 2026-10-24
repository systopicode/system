<?php
if ($this->withChildSelected('controls')) return;
?>
<div class="rightAlign">
	<?php
	if (\Systopic\System\Auth\Session::hasRole('recovery') && \Systopic\System\Auth\Session::isRecovery()) {
		echo '<a data-on_click="createDatabase" class="button"><span>Create Database "' . DB_NAME . '"</span></a>';
	}
	if (\Systopic\System\Auth\Session::hasRole('admin')) {
		echo '<a href="log/" class="ajax button"><i class="fa-regular fa-file-lines"></i><span>Log</span></a>';
		echo '<a data-on_click="clearTempdirs" class="button"><i class="fa-regular fa-broom"></i><span>Clear temporary files</span></a>';
		echo '<label class="button backupUpload"><i class="fa-regular fa-arrow-up-from-bracket"></i><span>Upload backup</span>';
		echo '<input type="file" accept=".zip,application/zip" multiple data-on_change="upload">';
		echo '</label>';
	}
	if (\Systopic\System\Auth\Session::hasOneRole('admin', 'editor', 'textEditor')) {
		echo '<a href="create/" class="ajax button submit"><i class="fa-regular fa-plus"></i><span>New backup</span></a>';
	}
	?>
</div>
