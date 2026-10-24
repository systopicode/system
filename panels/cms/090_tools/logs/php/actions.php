<?php

/** @var \Systopic\System\Panels\PanelNode $this */

$path = rtrim((string) fs::$root, '/\\') . '/var/log/PHP_errors.log';

switch ($this->action) {
	case 'downloadLog':
		if ($path !== '' && is_file($path)) {
			header('Content-Type: text/plain; charset=utf-8');
			header('Content-Disposition: attachment; filename="PHP_errors.log"');
			header('Content-Length: ' . (string) filesize($path));
			readfile($path);
			exit;
		}
		break;
	case 'clearLog':
		if ($path !== '' && is_file($path)) {
			file_put_contents($path, '');
		}
		$this->updateView('main');
		break;
}
