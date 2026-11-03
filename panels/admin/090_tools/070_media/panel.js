/* global sys */
(function () {
	var setup = {
		info: 'Media',
		route: 'panels.admin.tools.media',
		extends: 'panels.admin',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		this.cropAction = event => {
			var select = event.element.closest('.widget').querySelector('select');
			if (!select || select.value === '') {
				alert('Choose a format first.');
				return;
			}
			if (!confirm(event.data.dataset.confirm || 'Continue?')) {
				return;
			}
			event.data.dataset.value = select.value;
			event.executeAction(event.data.dataset.action);
		};
	});
})();
