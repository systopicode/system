/* global sys */
(function () {
	var setup = {
		info: 'Screen list',
		route: 'panels.cms.access.screens',
		extends: 'panels.cms.access',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
	});
})();
