/* global sys */
(function () {
	var setup = {
		info: 'Roles',
		route: 'panels.admin.access.roles',
		extends: 'panels.admin.access',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
	});
})();
