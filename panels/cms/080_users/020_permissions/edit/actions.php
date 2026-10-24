<?php

/** @var \Systopic\System\Panels\Root\Cms\Users\Permissions\Edit\Panel $this */

use Systopic\System\Tables\Groups\Operator as GroupOperator;
use Systopic\System\Tables\RolesOnGroups\Operator as PermissionOperator;

include '../../child.role.actions.php';

$group = $this->group;
if ($group === NULL) {
	return;
}
$refresh = function () {
	$this->updateView('lightbox');
	$this->parent->updateView('main');
};
switch ($this->action) {
	case 'updateGroupName':
	case 'updateGroupDescription':
		$this->scope->save(GroupOperator::assign($group, strtolower(substr($this->action, 11)), (string) (\http::values('value') ?? \http::put('value') ?? '')));
		$refresh();
		break;
	case 'updateGroupColor':
		$this->scope->save(GroupOperator::assign($group, 'color', (string) (\http::values('value') ?? \http::values('color') ?? '')));
		$refresh();
		break;
	case 'resetGroupColor':
		$this->scope->save(GroupOperator::assign($group, 'color', ''));
		$refresh();
		break;
	case 'removeGroup':
		if ((int) $group->id === 1) {
			break;
		}
		$gone = [];
		foreach ($this->admin->roles as $role) {
			$link = $this->admin->permission($group, $role);
			if ($link !== null) {
				$gone[] = $link;
			}
		}
		$gone[] = $group;
		try {
			$this->scope->remove(...$gone);
		} catch (\Throwable $e) {
			\message::error('This group is still in use.');
			$this->updateView('lightbox');
			break;
		}
		\http::redirect('../');
		break;

	case 'toggleGroupRole':
		$right = (string) \http::dataset('right');
		$role = $this->admin->role((int) \http::dataset('role_id'));
		if ($role !== null && ($right === 'read' || $right === 'write')) {
			$link = $this->admin->permission($group, $role);
			$on = (bool) ($link?->$right ?? false);
			$this->scope->write(...PermissionOperator::permit($link, $group, $role, $right, !$on));
		}
		$refresh();
		break;
}
