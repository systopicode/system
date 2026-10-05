<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Settings\Tags\Edit;

use Systopic\System\Client\ClientExport;
use Systopic\Db\Scope;
use Systopic\System\Panels\PanelNode;
use Systopic\System\Queries\TagLinks\TagLinks;
use Systopic\System\Queries\TagGroups\TagGroups;
use Systopic\System\Tables\Tags\Model as Tag;

/**
 * The lightbox of one tag (`?id=`): its name, to rename, and how many nodes
 * carry it.
 */
class Panel extends PanelNode {

	public Scope $scope {
		get => Scope::default();
	}

	public ?Tag $tag {
		get => ($id = (int) (\http::dataset('tag_id') ?: \http::get('id'))) > 0 ? TagGroups::tagById($this->scope, $id) : NULL;
	}

	/** @var list<int> the nodes carrying the tag, published or not */
	public array $nodeIds {
		get => $this->tag ? TagLinks::nodeIdsOf($this->scope, (int) $this->tag->id) : [];
	}

	function getDefaultState() {
		return (object) ['hiddenViews' => []];
	}

	/** panel.js reads the tag's id from `__data.tag` to rename it. */
	public function exportClientData(): ClientExport {
		$export = parent::exportClientData();
		if ($this->tag) {
			$export->data->tag = ['id' => (int) $this->tag->id, 'name' => $this->tag->name];
		}
		return $export;
	}
}
