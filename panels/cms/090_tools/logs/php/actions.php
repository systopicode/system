<?php

/** @var \Systopic\System\Panels\PanelNode $this */

switch ($this->action) {
	case 'clearLog':
		$path = defined('PHPLOGFILE') ? PHPLOGFILE : (string) ini_get('error_log');
		if ($path !== '' && is_file($path)) {
			file_put_contents($path, '');
		}
		$this->updateView('main');
		break;
}
