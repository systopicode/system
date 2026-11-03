/* global sys */
(function () {
	var setup = {
		info: 'Roles',
		route: 'panels.cms.access.roles',
		extends: 'panels.cms.access',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
	});
})();
