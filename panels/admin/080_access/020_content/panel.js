/* global sys */
(function () {
	var setup = {
		info: 'Permissions',
		route: 'panels.admin.access.content',
		extends: 'panels.admin.access',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
	});
})();
