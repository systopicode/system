<?php

/** @var \Systopic\System\Panels\Root\Cms\Settings\Tags\Edit\Panel $this */

switch ($this->action) {
	case 'rename':
		$name = trim((string) \http::put('name'));
		if ($this->tag && $name !== '') {
			$this->tag->name = $name;
			$this->scope->save($this->tag);
			\client::done($this->tag->name);
			$this->parent->updateView('main');
		}
		break;
}
