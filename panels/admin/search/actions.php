<?php

/** @var \Systopic\System\Panels\Root\Admin\Search\Panel $this */

switch ($this->action) {
	case 'open':
		// Drawn onto the page that is already open. A navigation would replace
		// that page and put another module behind the dialog.
		$renderStyle = 'none';
		ob_start();
		include __DIR__ . '/lightbox.tpl.php';
		client::append('body', ob_get_clean());
		break;
	case 'search':
		$this->state->query = mb_substr(trim((string) \http::values('query')), 0, 100);
		$renderStyle = 'none';
		ob_start();
		include __DIR__ . '/results.tpl.php';
		client::replace('.cmsFindResults', ob_get_clean());
		break;
}
