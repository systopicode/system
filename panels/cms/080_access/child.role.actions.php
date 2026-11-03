<?php

/**
 * Editing a role — included by the edit panels of users/ and permissions/,
 * which both have `$role`.
 *
 * @var \Systopic\System\Panels\Root\Cms\Access\Users\Edit\Panel|\Systopic\System\Panels\Root\Cms\Access\Content\Edit\Panel $this
 */

use Systopic\System\Tables\Roles\Operator as RoleOperator;
use Systopic\System\Tables\RolesOnGroups\Operator as PermissionOperator;

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
		case 'toggleGroupRole':
			$right = (string) \http::dataset('right');
			$group = $this->admin->group((int) \http::dataset('group_id'));
			if ($group !== null && ($right === 'read' || $right === 'write')) {
				$link = $this->admin->permission($group, $role);
				$on = (bool) ($link?->$right ?? false);
				try {
					$this->scope->write(...PermissionOperator::permit($link, $group, $role, $right, !$on));
				} catch (\Throwable $e) {
					// A second click can still be on its way with the same old
					// state. Inserting the pair again is that race: the view
					// below shows what is stored.
					if (!str_contains($e->getMessage(), '1062') && !str_contains($e->getMessage(), 'Duplicate')) {
						throw $e;
					}
				}
			}
			$this->updateView('lightbox');
			$this->parent->updateView('main');
			break;
	}
}
