<?php
if ($this->withChildSelected('controls')) return;
?>
<div class="cmsPanelActions">
<div class="rightAlign">
	<?php
	if (\Systopic\System\Auth\Session::hasRole('recovery') && \Systopic\System\Auth\Session::isRecovery()) {
		echo '<button type="button" data-cms-action data-on_click="createDatabase" class="button"><span>Create Database "' . DB_NAME . '"</span></button>';
	}
	if (\Systopic\System\Controlmap\Access::action($this, 'clearTempdirs')) {
		echo '<a href="log/" class="ajax button"><i class="cmsIcon cmsIcon-file-lines"></i><span>Log</span></a>';
		echo '<button type="button" data-cms-action data-on_click="clearTempdirs" class="button"><i class="cmsIcon cmsIcon-broom"></i><span>Clear temporary files</span></button>';
	}
	if (\Systopic\System\Controlmap\Access::action($this, 'upload')) {
		echo '<label class="button backupUpload"><i class="cmsIcon cmsIcon-arrow-up-from-bracket"></i><span>Upload backup</span>';
		echo '<input type="file" accept=".zip,application/zip" multiple data-on_change="upload">';
		echo '</label>';
	}
	if (\Systopic\System\Auth\Session::hasOneRole('cmsAdmin', 'cmsEditor')) {
		echo '<a href="create/" class="ajax button submit"><i class="cmsIcon cmsIcon-plus"></i><span>New backup</span></a>';
	}
	?>
</div>

</div>
