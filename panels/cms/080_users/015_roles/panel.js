/* global sys */
(function () {
	var setup = {
		info: 'Roles',
		route: 'panels.cms.users.roles',
		extends: 'panels.cms.users',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
	});
})();
