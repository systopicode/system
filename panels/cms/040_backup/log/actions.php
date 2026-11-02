<?php

/** @var \Systopic\System\Panels\PanelNode $this */

switch ($this->action) {
	case 'clear':   // who may: ../panel.config.json (the controlmap)
		fs::unlinkFile($this->parent->logfile->filepath);
		http::redirect('./');
}