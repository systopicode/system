<?php

/** @var \Systopic\System\Panels\Root\Cms\Settings\Tags\Panel $this */

use Systopic\System\Queries\TagGroups\TagGroups;
use Systopic\System\Queries\TagLinks\TagLinks;
use Systopic\System\Tables\Tags\Model as Tag;
use Systopic\System\Tables\TagGroups\Operator as TagGroupOperator;

$repack = function (int $groupId): void {
	$group = TagGroups::byId($this->scope, $groupId);
	if ($group === null) {
		return;
	}
	$tags = $group->tags;
	usort($tags, static fn(Tag $a, Tag $b): int => [$a->ordering, (int) $a->id] <=> [$b->ordering, (int) $b->id]);
	$step = 0;
	$changed = [];
	foreach ($tags as $tag) {
		$step += 2;
		if ($tag->ordering !== $step) {
			$tag->ordering = $step;
			$changed[] = $tag;
		}
	}
	if ($changed !== []) {
		$this->scope->save(...$changed);
	}
};

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
			$max = 0;
			foreach ($this->group->tags as $existing) {
				$max = max($max, $existing->ordering);
			}
			[$tag->name, $tag->taggroup, $tag->ordering] = [$name, $this->group, $max + 2];
			$this->scope->save($tag);
			$this->updateView('main');
		}
		break;

	case 'insertIntoGroup':
		if ($this->draggedTag && $this->group) {
			$max = 0;
			foreach ($this->group->tags as $existing) {
				$max = max($max, $existing->ordering);
			}
			$oldGroupId = (int) ($this->draggedTag->taggroup?->id ?? 0);
			$this->draggedTag->taggroup = $this->group;
			$this->draggedTag->ordering = $max + 2;
			$this->scope->save($this->draggedTag);
			$repack((int) $this->group->id);
			if ($oldGroupId > 0 && $oldGroupId !== (int) $this->group->id) {
				$repack($oldGroupId);
			}
			$this->updateView('main');
		}
		break;

	case 'insertTagBefore':
	case 'insertTagAfter':
		$drag = $this->draggedTag;
		$drop = $this->tag;
		if ($drag === null || $drop === null || $drag->id === $drop->id || $drop->taggroup === null) {
			break;
		}
		$oldGroupId = (int) ($drag->taggroup?->id ?? 0);
		$group = $drop->taggroup;
		$drag->taggroup = $group;
		$drag->ordering = (int) $drop->ordering + ($this->action === 'insertTagBefore' ? -1 : 1);
		$this->scope->save($drag);
		$repack((int) $group->id);
		if ($oldGroupId > 0 && $oldGroupId !== (int) $group->id) {
			$repack($oldGroupId);
		}
		$this->updateView('main');
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
