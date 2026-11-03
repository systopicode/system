<?php

/** @var \Systopic\System\Panels\Root\Cms\Users\Users\Panel $this */

use Systopic\System\Tables\Roles\Operator as RoleOperator;
use Systopic\System\Tables\Users\Operator as UserOperator;
use Systopic\System\Tables\UsersAtRoles\Model as UserAtRole;

switch ($this->action) {
	case 'addUser':
		$rows = UserOperator::create(\Systopic\System\Auth\Session::id(), $this->admin->roleNamed('user'));
		$screen = ($this->accountKind ?? '') === \Systopic\System\Tables\Users\Model::SCREEN;
		if ($screen) {
			$rows[0]->type = \Systopic\System\Tables\Users\Model::SCREEN;
			$rows[0]->isActive = true;
		}
		$this->scope->save(...$rows);
		$this->scope->save(UserOperator::named($rows[0]));
		if ($screen) {
			$rows[0]->name = '##New Screen #' . $rows[0]->id;
			$this->scope->save($rows[0]);
		}
		$editState = $this->child('edit')?->state;
		if ($editState === null) {
			$node = \Systopic\System\Auth\Session::state();
			foreach ($this->path as $seg) {
				if (!isset($node->children)) {
					$node->children = new \stdClass();
				}
				if (!isset($node->children->$seg)) {
					$node->children->$seg = new \stdClass();
				}
				$node = $node->children->$seg;
			}
			if (!isset($node->children)) {
				$node->children = new \stdClass();
			}
			if (!isset($node->children->edit)) {
				$node->children->edit = new \stdClass();
			}
			if (!isset($node->children->edit->data)) {
				$node->children->edit->data = new \stdClass();
			}
			$editState = $node->children->edit->data;
		}
		$editState->userTab = 'general';
		$editState->signInsAll = false;
		$editState->devicesAll = false;
		\http::redirect("edit/?user_id={$rows[0]->id}");
		break;

	case 'addRole':
		$this->scope->save($role = RoleOperator::create());
		$this->scope->save(RoleOperator::named($role));
		$this->updateView('main');
		break;

	case 'addRoleToUser':
		if ($this->user && $this->role && !$this->role->isBuiltin && !$this->admin->assignment($this->user, $this->role)) {
			$link = new UserAtRole();
			[$link->user, $link->role] = [$this->user, $this->role];
			$this->scope->save($link);
		}
		$this->updateView('main');
		break;

	case 'removeRoleFromUser':
		if ($this->user && $this->role) {
			$this->scope->remove(...array_filter([$this->admin->assignment($this->user, $this->role)]));
		}
		$this->updateView('main');
		break;

	case 'removeUser':
		if ($this->user === null) {
			break;
		}
		if ((int) $this->user->id === (int) \Systopic\System\Auth\Session::id()) {
			\message::error('You cannot delete your own account.');
			break;
		}
		$gone = [];
		foreach ($this->roles as $role) {
			$link = $this->admin->assignment($this->user, $role);
			if ($link !== null) {
				$gone[] = $link;
			}
		}
		foreach (\Systopic\System\Queries\Devices\Devices::ofUser($this->scope, (int) $this->user->id) as $device) {
			$gone[] = $device;
		}
		$gone[] = $this->user;
		$this->scope->remove(...$gone);
		$this->updateView('main');
		break;

	case 'makeSuperuser':
	case 'revokeSuperuser':
		if ($this->action === 'makeSuperuser' && $this->user?->type === \Systopic\System\Tables\Users\Model::SCREEN) {
			\message::error('A screen cannot be a superuser.');
			break;
		}
		if (\Systopic\System\Auth\Session::isSuperuser() && $this->user) {
			$this->scope->save(...UserOperator::superuser($this->user, $this->action === 'makeSuperuser', \Systopic\System\Auth\Session::id()));
		}
		$this->updateView('main');
		$this->child('edit')?->updateView('lightbox');
		break;

	case 'sortUsers':
		$key = (string) \http::dataset('sortby', 'id');
		if (!isset(UserOperator::SORTABLE[$key])) {
			break;
		}
		if ($this->state->sortBy === $key) {
			$this->state->sortDesc = !$this->state->sortDesc;
		} else {
			[$this->state->sortBy, $this->state->sortDesc] = [$key, FALSE];
		}
		$this->updateView('main');
		break;
}
