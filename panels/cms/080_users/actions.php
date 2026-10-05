<?php

/** @var \Systopic\System\Panels\Root\Cms\Users\Panel $this */

switch ($this->action) {
	case 'hijackUser':
		// Log in as someone else; cms/user's "back" button (state hijackerId)
		// returns. Session handling is the legacy auth service's.
		$target = \Systopic\System\Auth\Session::isSuperuser()
			? \Systopic\System\Queries\UserLookup\UserLookup::byId($this->scope, (int) \http::dataset('user_id'))
			: NULL;
		if ($target !== NULL && !$target->isSuperuser) {
			$this->parent->child('user')->state->hijackerId = \Systopic\System\Auth\Session::id();
			\Systopic\System\Auth\Session::logout();
			\Systopic\System\Auth\Session::login($target);
			\http::refresh('./');
		}
		break;
}
