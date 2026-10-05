<?php

/**
 * Editing a role — included by the edit panels of users/ and permissions/,
 * which both have `$role`.
 *
 * @var \Systopic\System\Panels\Root\Cms\Users\Users\Edit\Panel|\Systopic\System\Panels\Root\Cms\Users\Permissions\Edit\Panel $this
 */

use Systopic\System\Tables\Roles\Operator as RoleOperator;

$role = $this->role;
if ($role === NULL) {
	return;
}
switch ($this->action) {
	case 'updateRoleName':
	case 'updateRoleDescription':
	case 'updateRoleColor':
		$this->scope->save(RoleOperator::assign($role, strtolower(substr($this->action, 10)), (string) \http::values('value')));
		$this->parent->updateView();
		break;
}
