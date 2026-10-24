<?php

/** @var \Systopic\System\Panels\PanelNode $this */

use Systopic\System\Auth\LoginLog;

$path = LoginLog::file();

switch ($this->action) {
	case 'downloadLog':
		if (is_file($path)) {
			header('Content-Type: text/plain; charset=utf-8');
			header('Content-Disposition: attachment; filename="login.log"');
			header('Content-Length: ' . (string) filesize($path));
			readfile($path);
			exit;
		}
		break;
	case 'clearLog':
		if (is_file($path)) {
			file_put_contents($path, '');
		}
		$this->updateView('main');
		break;
}
