/* global sys */
(function () {
	var setup = {
		info: 'Screen list',
		route: 'panels.cms.users.screens',
		extends: 'panels.cms.users',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
	});
})();
