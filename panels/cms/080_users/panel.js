/* global sys,c,p,t,l */
(function () {
	var setup = {
		info: 'Users Panel extends cms Panel', // debug,
		route: 'panels.cms.users',
		extends: 'panels.cms',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		var users = this;

		l('con ' + setup.info);
		users.onUpdateRoles = doc => {
			for (var name in doc) {
				sys.editable.create({
					element: doc.name,
					maxlength: 100,
					allowedChars: /[a-z]/,
					onconfirm: 'updateRoleName',
				});
			}
		}

		var initDomEditables = doc => {
			l('initDomEditables');
			for (var name in doc) {
				if (doc[name].dataset.type) {
					sys.editable.create({
						element: doc[name],
						onconfirm: doc[name].dataset.onconfirm,
					});

				}
			}
		}

		users.onUpdateUser = initDomEditables;
		users.onUpdateRole = initDomEditables;
		users.onUpdateGroup = initDomEditables;
		users.onUpdateLightbox = initDomEditables;

		users.filterGroups = event => {
			var query = String(event.element.value || '').trim().toLowerCase();
			var table = event.element.closest('.groupList');
			if (!table) {
				return;
			}
			table.querySelectorAll('tbody tr').forEach(row => {
				row.hidden = query !== '' && !row.textContent.toLowerCase().includes(query);
			});
		};

		users.filterRoles = event => {
			var query = String(event.element.value || '').trim().toLowerCase();
			var table = event.element.closest('.roleList');
			if (!table) {
				return;
			}
			table.querySelectorAll('tbody tr').forEach(row => {
				row.hidden = query !== '' && !row.textContent.toLowerCase().includes(query);
			});
		};

	});
})();
