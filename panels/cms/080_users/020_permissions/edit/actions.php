<?php

/** @var \Systopic\System\Panels\Root\Cms\Users\Permissions\Edit\Panel $this */

use Systopic\System\Tables\Groups\Operator as GroupOperator;

include '../../child.role.actions.php';

$group = $this->group;
if ($group === NULL) {
	return;
}
switch ($this->action) {
	case 'updateGroupName':
	case 'updateGroupDescription':
		$this->scope->save(GroupOperator::assign($group, strtolower(substr($this->action, 11)), (string) \http::put('value')));
		$this->parent->updateView('main');
		break;
	case 'updateGroupColor':
		$this->scope->save(GroupOperator::assign($group, 'color', (string) \http::values('color')));
		$this->parent->updateView('main');
		break;
	case 'resetGroupColor':
		$this->scope->save(GroupOperator::assign($group, 'color', ''));
		$this->parent->updateView('main');
		$this->updateView('lightbox');
		break;
}
