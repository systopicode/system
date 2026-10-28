<?php

/** @var \Systopic\System\Panels\Root\Cms\Settings\Tags\Edit\Panel $this */

use Systopic\System\Queries\TagLinks\TagLinks;

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

	case 'detach':
		$nodeId = (int) (\http::dataset('node_id') ?: \http::put('node_id'));
		if ($this->tag && $nodeId > 0) {
			foreach (TagLinks::linksOf($this->scope, (int) $this->tag->id) as $link) {
				if ((int) $link->relationId('node') === $nodeId) {
					$this->scope->remove($link);
				}
			}
			$this->updateView('lightbox');
			$this->parent->updateView('main');
		}
		break;
}
