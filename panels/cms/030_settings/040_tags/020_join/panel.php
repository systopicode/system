<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Settings\Tags\Join;

use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Queries\TagGroups\TagGroups;
use Systopic\System\Queries\TagLinks\TagLinks;
use Systopic\System\Tables\Tags\Model as Tag;

/**
 * The lightbox merging one tag into another: a tag dropped on a tag opens it
 * with `?id=` (the dropped one) and `?into=` (the one it lands on).
 */
class Panel extends PanelNode {

	public Scope $scope {
		get => Scope::default();
	}

	public ?Tag $source {
		get => ($id = (int) \http::get('id')) > 0 ? TagGroups::tagById($this->scope, $id) : NULL;
	}

	public ?Tag $target {
		get => ($id = (int) \http::get('into')) > 0 ? TagGroups::tagById($this->scope, $id) : NULL;
	}

	/** How many nodes carry the source, and the target. */
	public int $sourcePages {
		get => $this->source ? count(TagLinks::nodeIdsOf($this->scope, (int) $this->source->id)) : 0;
	}

	public int $targetPages {
		get => $this->target ? count(TagLinks::nodeIdsOf($this->scope, (int) $this->target->id)) : 0;
	}

	function onLoad() {
		$this->loadJS();
	}
}
