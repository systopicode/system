<?php

/**
 * Editing a role — included by the edit panels of users/ and permissions/,
 * which both have `$role`.
 *
 * @var \Systopic\System\Panels\Root\Cms\Users\Users\Edit\Panel|\Systopic\System\Panels\Root\Cms\Users\Permissions\Edit\Panel $this
 */

use Systopic\System\Tables\Roles\Operator as RoleOperator;

$role = $this->role;
if ($role !== null) {
	switch ($this->action) {
		case 'updateRoleName':
		case 'updateRoleDescription':
		case 'updateRoleColor':
		case 'updateRoleKey':
			$field = strtolower(substr($this->action, 10));
			$before = $field === 'key' || $field === 'name' ? $role->$field : NULL;
			try {
				$this->scope->save(RoleOperator::assign($role, $field, (string) \http::values('value')));
			} catch (\InvalidArgumentException $e) {
				\message::error(ucfirst($e->getMessage()) . '.');
			} catch (\PDOException $e) {
				// name and key are unique
				$role->$field = $before;
				\message::error("Another role has this $field.");
			}
			$this->updateView('lightbox');
			$this->parent->updateView('main');
			break;
	}
}
