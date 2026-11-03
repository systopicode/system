/* global sys,c,p,t,l,document */
(function () {
	var setup = {
		info: 'Join Tags class extends cmsPanel', // debug,
		route: 'panels.admin.settings.tags.join',
		extends: 'panels.admin',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		var join = this; // internal instance name
		
	});
})();
