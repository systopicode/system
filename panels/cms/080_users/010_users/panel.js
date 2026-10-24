/* global sys */
(function () {
	var setup = {
		info: 'User list',
		route: 'panels.cms.users.users',
		extends: 'panels.cms.users',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		var users = this;

		users.filterUsers = event => {
			var query = String(event.element.value || '').trim().toLowerCase();
			var table = event.element.closest('.userList');
			if (!table) {
				return;
			}
			table.querySelectorAll('tbody tr').forEach(row => {
				row.hidden = query !== '' && !row.textContent.toLowerCase().includes(query);
			});
		};
	});
})();
