/* global sys */
(function () {
	var setup = {
		info: 'Backup log',
		route: 'panels.admin.tools.logs.backup_log',
		extends: 'panels.admin',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
	});
})();
