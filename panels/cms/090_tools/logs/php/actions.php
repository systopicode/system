<?php

/** @var \Systopic\System\Panels\PanelNode $this */

$path = rtrim((string) fs::$root, '/\\') . '/var/log/PHP_errors.log';

switch ($this->action) {
	case 'downloadLog':
		if ($path !== '' && fs::isFile($path)) {
			header('Content-Type: text/plain; charset=utf-8');
			header('Content-Disposition: attachment; filename="PHP_errors.log"');
			header('Content-Length: ' . (string) fs::filesize($path));
			fpassthru(fs::fopen($path, 'rb'));
			exit;
		}
		break;
	case 'clearLog':
		if ($path !== '' && fs::isFile($path)) {
			fs::file_put_contents($path, '');
		}
		$this->updateView('main');
		break;
}
