<?php

/** @var \Systopic\System\Panels\PanelNode $this */

switch ($this->action) {
	case 'clear':
		if (\Systopic\System\Auth\Session::isSuperuser()) {
			fs::unlinkFile($this->parent->logfile->filepath);
			http::redirect('./');
		} else {
            \message::error('Only a superuser can clear logs.');
        }
}