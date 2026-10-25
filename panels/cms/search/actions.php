<?php

/** @var \Systopic\System\Panels\Root\Cms\Search\Panel $this */

switch ($this->action) {
	case 'search':
		$this->state->query = mb_substr(trim((string) \http::values('query')), 0, 100);
		$this->updateView('results');
		break;
}
