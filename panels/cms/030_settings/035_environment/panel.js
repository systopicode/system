/* global sys */
(function () {
	var setup = {
		info: 'Environment',
		route: 'panels.cms.settings.environment',
		extends: 'panels.cms',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		this.showEnvironment = event => {
			var name = String(event.element.value || '');
			document.querySelectorAll('.projectFiles .envFile').forEach(card => {
				card.hidden = card.getAttribute('data-env') !== name;
			});
		};
	});
})();
