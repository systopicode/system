<?php

/** @var \Systopic\System\Panels\Root\Cms\Settings\Tags\Join\Panel $this */

use Systopic\System\Queries\TagLinks\TagLinks;
use Systopic\System\Tables\Tags\Operator as TagOperator;

switch ($this->action) {
	case 'join':
		[$source, $target] = [$this->source, $this->target];
		if ($source && $target && $source !== $target) {
			$this->scope->write(...TagOperator::join($source, $target, TagLinks::linksOf($this->scope, (int) $source->id), TagLinks::nodeIdsOf($this->scope, (int) $target->id)));
			\http::redirect("../edit/?id={$target->id}");
		}
		break;
}
