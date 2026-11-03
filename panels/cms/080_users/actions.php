<?php

/** @var \Systopic\System\Panels\Root\Cms\Users\Panel $this */

switch ($this->action) {
	case 'hijackUser':
		// A superuser signs in as someone else to see what they see; cms/user's
		// "Back to my account" (state hijackerId) returns. Not as another
		// superuser: there is nothing to see that a superuser does not.
		$target = \Systopic\System\Auth\Session::isSuperuser(true)
			? \Systopic\System\Queries\UserLookup\UserLookup::byId($this->scope, (int) \http::dataset('user_id'))
			: NULL;
		if ($target !== NULL && !$target->isSuperuser) {
			$user = $this->parent->child('user');
			$user->state->hijackerId = \Systopic\System\Auth\Session::id();
			$user->state->hijackedId = (int) $target->id;
			\Systopic\System\Auth\Session::logout(false);   // the device stays the superuser's
			\Systopic\System\Auth\Session::login($target);
			\Systopic\System\Auth\LoginLog::write((string) $target->name, 'logged in', \Systopic\System\Auth\LoginLog::SWITCH);
			\http::refresh('./');
		}
		break;
}
