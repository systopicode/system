/* global sys */
(function () {
	var setup = {
		info: 'Environment',
		route: 'panels.cms.settings.environment',
		extends: 'panels.cms',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
        this.saveMatch = event => {
            var field = event.sourceElement;
            if (field && typeof field.checkValidity === 'function' && !field.checkValidity()) {
                field.setAttribute('aria-invalid', 'true');
                field.reportValidity();
                return;
            }
            if (field) field.removeAttribute('aria-invalid');
            sys.core.server.sendEventCall(event);
        };
        this.addAddress = event => {
            var file = event.data.dataset.file;
            sys.core.server.sendCall({dataset: {file: file}}, 'addMatch').done(() => {
                requestAnimationFrame(() => {
                    var card = Array.from(document.querySelectorAll('.envFile')).find(el => el.dataset.env === file);
                    var field = card?.querySelector('.projectRows tbody tr:last-child input[data-field="serverName"]');
                    if (!field) return;
                    field.focus({preventScroll: true});
                    for (var box = field.parentElement; box; box = box.parentElement) {
                        if (getComputedStyle(box).overflowY === 'auto' && box.scrollHeight > box.clientHeight) {
                            var top = box.scrollTop + field.getBoundingClientRect().top - box.getBoundingClientRect().top - box.clientHeight / 2;
                            sys.core.server.revealScroll(box, Math.max(0, top));
                            break;
                        }
                    }
                });
            });
        };
		this.showEnvironment = event => {
			var name = String(event.element.value || '');
			document.querySelectorAll('.projectFiles .envFile').forEach(card => {
				card.hidden = card.getAttribute('data-env') !== name;
			});
		};
	});
})();

// Clear the visible error as soon as the address has been corrected.
document.addEventListener('input', event => {
    var field = event.target;
    if (field.matches?.('.envFile input[data-field="serverIp"]') && field.checkValidity()) field.removeAttribute('aria-invalid');
});
