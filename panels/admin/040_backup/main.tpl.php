<?php
$this->withView('backups', function ($panel) {
	if ($panel->isSelected()) {
		echo message::flush();
	}
	$panel->render('backups.tpl.php');
});
