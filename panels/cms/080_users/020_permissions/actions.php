<?php

/** @var \Systopic\System\Panels\Root\Cms\Users\Permissions\Panel $this */

use Systopic\System\Tables\Groups\Operator as GroupOperator;
use Systopic\System\Tables\Roles\Operator as RoleOperator;
use Systopic\System\Tables\RolesOnGroups\Operator as PermissionOperator;

switch ($this->action) {
	case 'addGroup':
		$this->scope->save($group = GroupOperator::create());
		$this->scope->save(GroupOperator::named($group));
		$this->updateView('main');
		break;

	case 'addRole':
		$this->scope->save($role = RoleOperator::create());
		$this->scope->save(RoleOperator::named($role));
		$this->updateView('main');
		break;

	case 'addReadRoleToGroup':
	case 'addWriteRoleToGroup':
	case 'removeReadRoleFromGroup':
	case 'removeWriteRoleFromGroup':
		if ($this->group && $this->role) {
			$right = str_contains($this->action, 'Read') ? 'read' : 'write';
			$this->scope->write(...PermissionOperator::permit($this->admin->permission($this->group, $this->role), $this->group, $this->role, $right, str_starts_with($this->action, 'add')));
		}
		$this->updateView('main');
		break;
}
