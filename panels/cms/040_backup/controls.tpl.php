<?php
if ($this->withChildSelected('controls')) return;
?>
<div class="rightAlign">
	<?php
	if (\Systopic\System\Auth\Session::hasRole('recovery') && \Systopic\System\Auth\Session::isRecovery()) {
		echo '<a data-on_click="createDatabase" class="button"><span>Create Database "' . DB_NAME . '"</span></a>';
	}
	if (\Systopic\System\Auth\Session::hasRole('admin')) {
		echo '<a href="log/" class="ajax button"><span>Logfile</span></a>';
		echo '<a data-on_click="clearTempdirs" class="button"><span>Clear Tempdirs</span></a>';
	}
	if (\Systopic\System\Auth\Session::hasOneRole('admin', 'editor', 'textEditor')) {
		echo '<a href="create/" class="ajax button submit"><i class="fa-light fa-circle-plus"></i><span>New Backup</span></a>';
	}
	?>
</div>
