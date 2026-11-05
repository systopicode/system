/* global sys */
(function () {
    function layoutRoles() {
        document.querySelectorAll('.userList .roles').forEach(function (roles) {
            var row = roles.closest('tr');
            var table = roles.closest('table');
            if (!row || !table || !roles.getClientRects().length) return;
            var viewport = table.closest('.tableScroll') || table.parentElement;
            var others = Array.from(row.cells).filter(cell => !cell.contains(roles)).reduce((width, cell) => width + cell.getBoundingClientRect().width, 0);
            var gap = parseFloat(getComputedStyle(roles).columnGap) || 0;
            var needed = Array.from(roles.children).reduce((width, role) => width + role.getBoundingClientRect().width, 0) + Math.max(0, roles.children.length - 1) * gap;
            var cell = roles.parentElement;
            var style = getComputedStyle(cell);
            var available = (window.matchMedia('(max-width: 767px)').matches ? cell.clientWidth : viewport.clientWidth - others) - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
            var rows = needed > available ? 'repeat(2, max-content)' : 'max-content';
            if (roles.style.getPropertyValue('--role-rows') !== rows) roles.style.setProperty('--role-rows', rows);
        });
    }
    document.addEventListener('cms:rendered', layoutRoles);
    window.addEventListener('resize', layoutRoles);
    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-table-fold]')) requestAnimationFrame(layoutRoles);
    });
	var setup = {
		info: 'User list',
		route: 'panels.admin.access.users',
		extends: 'panels.admin.access',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		var users = this;
        users.onUpdateMain = layoutRoles;
        requestAnimationFrame(layoutRoles);

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
