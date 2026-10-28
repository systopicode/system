/* global sys */
(function () {
	var setup = {
		info: 'Backup log',
		route: 'panels.cms.tools.logs.backup_log',
		extends: 'panels.cms',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
	});
})();
