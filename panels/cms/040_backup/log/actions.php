<?php

/** @var \Systopic\System\Panels\PanelNode $this */

switch ($this->action) {
	case 'clear':
		if (\Systopic\System\Auth\Session::hasRole('cmsAdmin')) {
			fs::unlinkFile($this->parent->logfile->filepath);
			http::redirect('./');
		}
}