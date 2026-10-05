<?php

/** @var \Systopic\System\Panels\Root\Cms\Users\Users\Panel $this */

use Systopic\System\Tables\Roles\Operator as RoleOperator;
use Systopic\System\Tables\Users\Operator as UserOperator;
use Systopic\System\Tables\UsersAtRoles\Model as UserAtRole;

switch ($this->action) {
	case 'addUser':
		$rows = UserOperator::create(\Systopic\System\Auth\Session::id(), $this->admin->roleNamed('user'));
		$this->scope->save(...$rows);
		$this->scope->save(UserOperator::named($rows[0]));
		\http::redirect("edit/?user_id={$rows[0]->id}");
		break;

	case 'addRole':
		$this->scope->save($role = RoleOperator::create());
		$this->scope->save(RoleOperator::named($role));
		$this->updateView('main');
		break;

	case 'addRoleToUser':
		if ($this->user && $this->role && !$this->admin->assignment($this->user, $this->role)) {
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

	case 'makeSuperuser':
	case 'revokeSuperuser':
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
