<?php

/** @var \Systopic\System\Panels\Root\Admin\Settings\Tags\Join\Panel $this */

use Systopic\System\Queries\TagLinks\TagLinks;
use Systopic\System\Tables\Tags\Operator as TagOperator;

switch ($this->action) {
	case 'join':
		[$source, $target] = [$this->source, $this->target];
		if ($source && $target && $source !== $target) {
			$this->scope->write(...TagOperator::join($source, $target, TagLinks::linksOf($this->scope, (int) $source->id), TagLinks::nodeIdsOf($this->scope, (int) $target->id)));
			$this->parent->updateView('main');
			\http::redirect("../edit/?id={$target->id}");
			break;
		}
		$this->parent->updateView('main');
		\http::redirect('../');
		break;
}
