/* global sys */
(function () {
	var setup = {
		info: 'Environment',
		route: 'panels.cms.settings.environment',
		extends: 'panels.cms',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
        // Autofill must never silently rewrite environment settings on blur.
        this.save = event => {
            const field = event.sourceElement || event.element;
            if (!field) return;
            if (field.matches('input:not([type="checkbox"])') && field.value === field.defaultValue) return;
            if (field.matches('input:not([type="checkbox"])') && !field.cmsConnectionEdited) return;
            if (typeof field.checkValidity === 'function' && !field.checkValidity()) {
                field.reportValidity();
                return;
            }
            sys.core.server.sendEventCall(event);
        };
        this.saveMatch = event => {
            var field = event.sourceElement;
            if (field && !field.cmsConnectionEdited) return;
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

// Preserve Render/Time preferences while Debug is off; PHP error output is independent.
function refreshDebugControls(){
 document.querySelectorAll('.envFile').forEach(file=>{
  const refresh=()=>{
   const debug=file.querySelector('input[data-group="debug"][data-key="debug"]');
   if(!debug)return;
   file.querySelectorAll('input[data-group="debug"][data-key="render"],input[data-group="debug"][data-key="time"]').forEach(input=>{input.disabled=!debug.checked;});
  };
  if(!file.cmsDebugBound){
   file.cmsDebugBound=true;
   file.addEventListener('change',event=>{if(event.target.matches?.('[data-group="debug"][data-key="debug"]'))refresh();},true);
  }
  refresh();
 });
}
sys.onload(refreshDebugControls);
sys.onJSReady(refreshDebugControls);

// beforeinput covers typing, paste and mobile keyboards, unlike autofill on load.
document.addEventListener('beforeinput', event => {
    if (event.target.matches?.('.envFile input:not([type="checkbox"])')) event.target.cmsConnectionEdited = true;
}, true);
