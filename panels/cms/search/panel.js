/* global sys */
(function () {
	var setup = {
		info: 'Search',
		route: 'panels.cms.search',
		extends: 'panels.cms',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
	});
})();
