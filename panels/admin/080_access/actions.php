<?php

/** @var \Systopic\System\Panels\Root\Admin\Access\Panel $this */

switch ($this->action) {
	case 'hijackUser':
		// A superuser signs in as someone else to see what they see; admin/user's
		// "Back to my account" (state hijackerId) returns. Not as another
		// superuser: there is nothing to see that a superuser does not.
		$target = \Systopic\System\Auth\Session::isSuperuser(true)
			? \Systopic\System\Queries\UserLookup\UserLookup::byId($this->scope, (int) \http::dataset('user_id'))
			: NULL;
		if ($target !== NULL && !$target->isSuperuser) {
			$actor = trim((string) \Systopic\System\Auth\Session::current()->name);
			$user = $this->parent->child('user');
			$user->state->hijackerId = \Systopic\System\Auth\Session::id();
			$user->state->hijackedId = (int) $target->id;
			\Systopic\System\Auth\Session::logout(false);   // the device stays the superuser's
			\Systopic\System\Auth\Session::login($target);
			\Systopic\System\Auth\SignInLog::write($actor, 'switched to ' . $target->name, \Systopic\System\Auth\SignInLog::SWITCH);
			\Systopic\System\Auth\SignInLog::write((string) $target->name, 'signed in', \Systopic\System\Auth\SignInLog::SWITCH);
			\http::refresh('./');
		}
		break;
}
