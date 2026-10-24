/* global sys */
(function () {
	var setup = {
		info: 'PHP log',
		route: 'panels.cms.tools.logs.php',
		extends: 'panels.cms',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		this.filterLog = event => {
			var query = String(event.element.value || '').trim().toLowerCase();
			var card = event.element.closest('.logList');
			if (!card) {
				return;
			}
			card.querySelectorAll('tbody tr').forEach(row => {
				row.hidden = query !== '' && !row.textContent.toLowerCase().includes(query);
			});
		};
	});
})();
