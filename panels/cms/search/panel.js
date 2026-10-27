/* global sys */
(function () {
	var setup = {
		info: 'Search',
		route: 'panels.cms.search',
		extends: 'panels.cms',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		// A full load. Taking the dialog out by hand leaves the client thinking
		// the search page is still on screen, so the next open does nothing.
		this.closeSearch = function (event) {
			var href = event.element.getAttribute('href');
			if (href) location.href = href;
		};
	});
})();
