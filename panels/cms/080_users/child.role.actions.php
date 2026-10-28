<?php

/**
 * Editing a role — included by the edit panels of users/ and permissions/,
 * which both have `$role`.
 *
 * @var \Systopic\System\Panels\Root\Cms\Users\Users\Edit\Panel|\Systopic\System\Panels\Root\Cms\Users\Permissions\Edit\Panel $this
 */

use Systopic\System\Tables\Roles\Operator as RoleOperator;
use Systopic\System\Tables\RolesOnGroups\Operator as PermissionOperator;

$role = $this->role;
if ($role !== null) {
	switch ($this->action) {
		case 'updateRoleName':
		case 'updateRoleDescription':
		case 'updateRoleColor':
			$this->scope->save(RoleOperator::assign($role, strtolower(substr($this->action, 10)), (string) \http::values('value')));
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
