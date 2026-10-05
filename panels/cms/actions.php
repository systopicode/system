<?php

/** @var \Systopic\System\Panels\Root\Cms\Panel $this */

use Systopic\System\Queries\UserAdmin\UserAdmin;
use Systopic\System\Tables\Users\Operator as UserOperator;

switch ($this->action) {
	case 'logout':
		$user = UserAdmin::of($this->scope)->user((int) \Systopic\System\Auth\Session::id());
		if ($user) {
			$this->scope->save(UserOperator::stamped($user));
		}
		\http::killSession();
		\http::redirect('./');
		exit;

	case 'notificationPushed':
		break;
}
