<?php

/** @var \Systopic\System\Panels\Root\Cms\Users\Roles\Edit\Panel $this */

include '../../child.role.actions.php';

if ($this->action === 'removeRole' && $this->role !== null && (int) $this->role->id !== 1) {
	$role = $this->role;
	$gone = [];
	foreach ($this->admin->users as $user) {
		$link = $this->admin->assignment($user, $role);
		if ($link !== null) {
			$gone[] = $link;
		}
	}
	foreach ($this->admin->groups as $group) {
		$link = $this->admin->permission($group, $role);
		if ($link !== null) {
			$gone[] = $link;
		}
	}
	$gone[] = $role;
	try {
		$this->scope->remove(...$gone);
	} catch (\Throwable $e) {
		\message::error('This role is still in use.');
		$this->updateView('lightbox');
		return;
	}
	\http::redirect('../');
}
