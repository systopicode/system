/* global sys */
(function () {
	var setup = {
		info: 'Environment',
		route: 'panels.admin.settings.environment',
		extends: 'panels.admin',
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
        // a row with its own fields: what they hold goes along with the call
        var sendRow = (event, action, names) => {
            var row = (event.sourceElement || event.element)?.closest('tr');
            var dataset = Object.assign({}, event.data.dataset);
            for (var name of names) {
                var field = row?.querySelector('input[name="' + name + '"]');
                if (!field) continue;
                if (typeof field.checkValidity === 'function' && !field.checkValidity()) {
                    field.reportValidity();
                    return;
                }
                dataset[name] = field.value.trim();
            }
            if (!dataset[names[0]]) return;
            sys.core.server.sendCall({dataset: dataset}, action);
        };
        this.addEntry = event => sendRow(event, 'addEntry', ['name']);
        this.addFree = event => sendRow(event, 'addFree', ['key', 'value']);
	});
})();

// Check in the header follows the config shown: its link and its count.
function syncCheck(name) {
    document.querySelectorAll('.envCheckButton').forEach(button => {
        var counts = {};
        try { counts = JSON.parse(button.dataset.checks || '{}'); } catch (error) {}
        var url = new URL(button.getAttribute('href'), location.href);
        url.searchParams.set('file', name);
        button.setAttribute('href', url.pathname + url.search);
        var badge = button.querySelector('.envBadge');
        if (badge) {
            badge.textContent = String(counts[name] || 0);
            badge.hidden = !counts[name];
        }
    });
}

// The page script may run more than once: the listeners on document only once.
if (!window.cmsEnvironmentBound) {
window.cmsEnvironmentBound = true;

// The config in the header: shows its card, the server keeps it for the next visit.
document.addEventListener('change', event => {
    var select = event.target.closest?.('.envPick select');
    if (!select) return;
    var name = String(select.value || '');
    document.querySelectorAll('.projectFiles .envFile').forEach(card => { card.hidden = card.getAttribute('data-env') !== name; });
    syncCheck(name);
    sys.core.server.sendCall({dataset: {pick: name}}, 'pick');
});

// Main sections open and close: a click on the header or its chevron, all
// at once from the header bar. Every config shows the same ones open; the
// server keeps them, so a redrawn page opens them again.
function foldKeys() {
    var keys = new Set();
    document.querySelectorAll('.envSettings .envFold.is-open[data-fold]').forEach(section => keys.add(section.dataset.fold));
    return Array.from(keys);
}
function setFold(key, open) {
    document.querySelectorAll('.envSettings .envFold[data-fold]').forEach(section => {
        if (key !== null && section.dataset.fold !== key) return;
        section.classList.toggle('is-open', open);
        section.querySelector(':scope > header .envFoldToggle')?.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
}
function sendFolds() {
    sys.core.server.sendCall({dataset: {open: foldKeys().join(',')}}, 'fold');
}
document.addEventListener('click', event => {
    var all = event.target.closest?.('.envFoldAll');
    if (all) {
        setFold(null, all.dataset.open === '1');
        sendFolds();
        return;
    }
    var head = event.target.closest?.('.envSettings .envFold > header');
    if (!head) return;
    var toggle = event.target.closest('.envFoldToggle');
    // buttons, links and fields in the header do their own thing
    if (!toggle && event.target.closest('a, button, input, select, label, .envInfo')) return;
    var section = head.parentElement;
    setFold(section.dataset.fold, !section.classList.contains('is-open'));
    sendFolds();
});

// (i): what a value or block is. A tip of its own on <body>, so a scrolling
// table cannot cut it off; hover, focus or a tap show it. Where the (i) is a
// link, a click opens the definition in syscoder; on touch the first tap
// shows the tip, the second one opens.
var envTip = null;
var envPointer = 'mouse';
document.addEventListener('pointerdown', event => { envPointer = event.pointerType; }, true);
function showTip(icon) {
    var text = icon.getAttribute('data-tip') || '';
    // a title the system copies from aria-label would show as a second tip
    icon.removeAttribute('title');
    var src = icon.getAttribute('data-src') || '';
    // where the value lies in a file: packages.system.constants.X
    var key = icon.getAttribute('data-key') || '';
    if (!text && !src && !key) return;
    envTip ??= Object.assign(document.createElement('div'), {className: 'envTip', role: 'tooltip'});
    envTip.textContent = text;
    if (key) {
        envTip.append(Object.assign(document.createElement('code'), {textContent: key}));
    }
    if (src) {
        var where = Object.assign(document.createElement('small'), {textContent: (text ? '' : 'Definition: ') + src});
        envTip.append(where);
    }
    document.body.append(envTip);
    var box = icon.getBoundingClientRect();
    var width = Math.min(320, innerWidth - 32);
    envTip.style.maxWidth = width + 'px';
    var tip = envTip.getBoundingClientRect();
    var left = Math.max(16, Math.min(box.left + box.width / 2 - tip.width / 2, innerWidth - tip.width - 16));
    var top = box.bottom + 6;
    if (top + tip.height > innerHeight - 8) top = Math.max(8, box.top - tip.height - 6);
    envTip.style.left = left + 'px';
    envTip.style.top = top + 'px';
    envTip.cmsFor = icon;
    envTip.cmsShown = Date.now();
}
function hideTip(icon) {
    if (envTip && (!icon || envTip.cmsFor === icon)) {
        envTip.remove();
        envTip.cmsFor = null;
    }
}
document.addEventListener('pointerover', event => {
    var icon = event.target.closest?.('.envInfo');
    if (icon && event.pointerType !== 'touch') showTip(icon);
});
document.addEventListener('pointerout', event => {
    var icon = event.target.closest?.('.envInfo');
    if (icon && !icon.contains(event.relatedTarget) && document.activeElement !== icon) hideTip(icon);
});
document.addEventListener('focusin', event => {
    var icon = event.target.closest?.('.envInfo');
    icon ? showTip(icon) : hideTip();
});
document.addEventListener('focusout', event => {
    if (event.target.closest?.('.envInfo')) hideTip(event.target.closest('.envInfo'));
});
document.addEventListener('click', event => {
    var icon = event.target.closest?.('.envInfo');
    if (!icon) return;
    var shown = envTip?.cmsFor === icon && envTip.isConnected && Date.now() - envTip.cmsShown > 400;
    // a link: the mouse opens it, a tap opens it once the tip is there
    if (icon.matches('a[href]') && (envPointer !== 'touch' || shown)) {
        event.stopPropagation();
        hideTip(icon);
        return;
    }
    event.preventDefault();
    event.stopPropagation();
    // a tap focuses first: the tip it opened stays
    shown ? hideTip(icon) : showTip(icon);
}, true);
document.addEventListener('keydown', event => { if (event.key === 'Escape') hideTip(); });
addEventListener('scroll', () => hideTip(), true);

}

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
