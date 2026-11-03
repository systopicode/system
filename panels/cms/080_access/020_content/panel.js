/* global sys */
(function () {
	var setup = {
		info: 'Permissions',
		route: 'panels.cms.access.content',
		extends: 'panels.cms.access',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
	});
})();
