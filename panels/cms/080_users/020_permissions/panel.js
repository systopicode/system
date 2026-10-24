/* global sys */
(function () {
	var setup = {
		info: 'Permissions',
		route: 'panels.cms.users.permissions',
		extends: 'panels.cms.users',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
	});
})();
