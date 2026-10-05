<?php

/** @var \Systopic\System\Panels\Root\Cms\Settings\Tags\Panel $this */

use Systopic\System\Queries\TagLinks\TagLinks;
use Systopic\System\Tables\Tags\Model as Tag;
use Systopic\System\Tables\TagGroups\Operator as TagGroupOperator;

/*
 * Two actions deliberately do not redraw: when renaming and when picking a
 * colour the change is already on screen, and redrawing would take the
 * cursor out of the field.
 */
switch ($this->action) {
	case 'addGroup':
		$this->scope->save(TagGroupOperator::create($this->groups));
		$this->updateView('main');
		break;

	case 'updateGroupLabel':
		if ($this->group) {
			$this->group->name = (string) \http::put('name');
			$this->scope->save($this->group);
		}
		break;

	case 'updateColor':
		if ($this->group) {
			$values = (array) \http::put('values');
			$this->group->color = trim((string) reset($values), '#');
			$this->scope->save($this->group);
		}
		break;

	case 'deleteGroup':
		// Only an empty group: a tag without a group is shown nowhere.
		if ($this->group && $this->group->tags !== []) {
			\message::warning('The group still has tags - move or delete them first.');
		} elseif ($this->group) {
			$this->scope->remove($this->group);
		}
		$this->updateView('main');
		break;

	case 'enterNewTag':
		$name = trim((string) \http::put('value'));
		if ($name !== '' && $this->group) {
			$tag = new Tag();
			[$tag->name, $tag->taggroup] = [$name, $this->group];
			$this->scope->save($tag);
			$this->updateView('main');
		}
		break;

	case 'insertIntoGroup':
		if ($this->draggedTag && $this->group) {
			$this->draggedTag->taggroup = $this->group;
			$this->scope->save($this->draggedTag);
			$this->updateView('main');
		}
		break;

	case 'delete':
		// The links go with the tag, before it.
		if ($this->tag) {
			$this->scope->remove(...TagLinks::linksOf($this->scope, (int) $this->tag->id), ...[$this->tag]);
			$this->updateView('main');
		}
		break;

	case 'close':
		$this->hideView('lightbox');
		$this->updateView('lightbox');
		break;
}
