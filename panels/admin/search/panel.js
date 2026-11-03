/* global sys */
(function () {
	var setup = {
		info: 'Search',
		route: 'panels.admin.search',
		extends: 'panels.admin',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
	});
})();
