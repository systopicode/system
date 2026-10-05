<?php

/** @var \Systopic\System\Panels\Root\Cms\Auth\Root\Panel $this */

use Systopic\System\Tables\Users\Operator as UserOperator;

switch ($this->action) {
	case 'createFirstUser':
		$problems = $this->hasUsers ? ['user table is not empty'] : UserOperator::passwordProblems((string) \http::posted('password'), (string) \http::posted('password1'), FALSE);
		if ($problems !== []) {
			\message::error(implode('<br>', $problems));
			\http::redirect('./');
			break;
		}
		$this->scope->save($user = UserOperator::firstSuperuser((string) \http::posted('name'), (string) \http::posted('password')));
		\http::redirect(\http::$root . "cms/auth/?user=" . urlencode((string) $user->name));
		break;
}
