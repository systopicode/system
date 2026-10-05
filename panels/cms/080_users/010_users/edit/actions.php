<?php

/** @var \Systopic\System\Panels\Root\Cms\Users\Users\Edit\Panel $this */

use Systopic\System\Tables\Users\Operator as UserOperator;

include '../../child.role.actions.php';

$user = $this->user;
if ($user === NULL) {
	return;
}
switch ($this->action) {
	case 'updateUserName':
	case 'updateUserFullname':
	case 'updateUserEmail':
		$this->scope->save(UserOperator::assign($user, strtolower(substr($this->action, 10)), (string) \http::values('value')));
		$this->parent->updateView('main');
		break;

	case 'resendActivationEmail':
		$this->scope->save(UserOperator::newActivationKey($user));
		$this->mailActivation($user);
		\message::confirm('New activation Mail was sent.');
		$this->updateView('lightbox');
		break;
}
