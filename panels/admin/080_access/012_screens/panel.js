/* global sys */
(function () {
	var setup = {
		info: 'Screen list',
		route: 'panels.admin.access.screens',
		extends: 'panels.admin.access',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
	});
})();
