<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Settings\Tags;

use Systopic\Db\Scope;
use Systopic\System\Tables\Tags\Model as Tag;
use Systopic\System\Tables\TagGroups\Model as TagGroup;
use Systopic\System\Queries\TagGroups\TagGroups;
use Systopic\System\Panels\PanelNode;

/**
 * cms/settings/tags — tag groups and their tags.
 *
 * What an action works on comes from here, looked up in the groups the
 * request has loaded once (`TagGroups::load()`); a commit throws them away,
 * and the next access reads them afresh.
 */
class Panel extends PanelNode {

	public function __construct($name, $parent) {
		parent::__construct($name, $parent);
		$this->label = 'Tags';
	}

	public Scope $scope {
		get => Scope::default();
	}

	/** @var list<TagGroup> every group, with `$tags` filled in */
	public array $groups {
		get => TagGroups::load($this->scope);
	}

	/**
	 * The group the action refers to: `dataset.group_id` from the widget,
	 * `put.group_id` when renaming (the heading does not sit in the widget's
	 * dataset there).
	 */
	public ?TagGroup $group {
		get => ($id = (int) (\http::dataset('group_id') ?: \http::put('group_id'))) > 0 ? TagGroups::byId($this->scope, $id) : NULL;
	}

	/** The tag the action refers to — `dataset.id`. */
	public ?Tag $tag {
		get => ($id = (int) \http::dataset('id')) > 0 ? TagGroups::tagById($this->scope, $id) : NULL;
	}

	/** The tag being dragged — `dragdata.id`. */
	public ?Tag $draggedTag {
		get => ($id = (int) \http::dragdata('id')) > 0 ? TagGroups::tagById($this->scope, $id) : NULL;
	}

	function getDefaultState() {
		return (object) [
					'hiddenViews' => ['lightbox'],
		];
	}
}
