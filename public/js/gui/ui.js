
// A thumb that does not load shows the crossed-out icon it names
// (data-broken="image-slash") instead of the browser's broken picture. Its
// parent gets .thumbMissing. Images that failed before this listener existed,
// or before a render inserted them, are caught by the sweep.
function markBrokenThumb(img) {
	var tile = img.parentElement;
	if (!tile || tile.classList.contains('thumbMissing')) {
		return;
	}
	var icon = document.createElement('i');
	icon.className = 'cmsIcon cmsIcon-' + img.dataset.broken + ' thumbBroken';
	icon.title = 'The picture cannot be shown';
	tile.classList.add('thumbMissing');
	img.after(icon);
}
document.addEventListener('error', function (event) {
	if (event.target.tagName === 'IMG' && event.target.dataset.broken) {
		markBrokenThumb(event.target);
	}
}, true);
document.addEventListener('load', function (event) {
	var img = event.target;
	if (!img || img.tagName !== 'IMG' || !img.dataset.broken || !img.naturalWidth) {
		return;
	}
	var tile = img.parentElement;
	if (!tile) {
		return;
	}
	tile.classList.remove('thumbMissing');
	var icon = tile.querySelector(':scope > i.thumbBroken');
	if (icon) {
		icon.remove();
	}
}, true);
function sweepBrokenThumbs() {
	document.querySelectorAll('img[data-broken]').forEach(function (img) {
		if (img.complete && !img.naturalWidth) {
			markBrokenThumb(img);
		}
	});
}
document.addEventListener('DOMContentLoaded', sweepBrokenThumbs);
document.addEventListener('cms:rendered', sweepBrokenThumbs);

// Transient information dialogs keep their content when dismissed. Reuse it
// on repeat activation instead of navigating to the already-rendered route.
document.addEventListener('click', function (event) {
    const link = event.target.closest?.('a[href]');
    if (!link) return;
    const path = new URL(link.href, location.href).pathname;
    const kind = path.endsWith('/defaultMeta/') ? 'metaDefaults' : path.endsWith('/relationsHelp/') ? 'relationsHelp' : '';
    const box = kind && document.querySelector('.cmslightbox[data-transient-info].' + kind);
    if (!box?.hidden) return;
    event.preventDefault(); event.stopImmediatePropagation();
    box.hidden = false;
}, true);

/****************************** KEEPRESS FUNCTIONSs ***************************/
document.addEventListener('click', function (event) {
	var open = event.target.closest ? event.target.closest('[data-open-box]') : null;
	if (open) {
		var box = document.querySelector(open.getAttribute('data-open-box'));
		if (box) {
			box.hidden = false;
			// Add device: a phone opens the camera from this click. A desktop
			// stays on the QR. The click is the gesture the prompt needs.
			var phone = window.matchMedia('(max-width: 720px), (hover: none) and (pointer: coarse)').matches;
			if (phone && box.classList.contains('remoteLoginBox') && window.QrScan) {
				box.classList.add('cmsOpening');
				var scanHost = box.querySelector('[data-qr-host="profile"]');
				if (scanHost) window.QrScan.start(scanHost);
			}
		}
		return;
	}
	var shut = event.target.closest ? event.target.closest('[data-box-close]') : null;
	if (shut) {
		var host = shut.closest('.cmslightbox');
		if (host) host.hidden = true;
	}
});

document.addEventListener('click', function (event) {
	var button = event.target.closest ? event.target.closest('[data-copy]') : null;
	if (!button || !navigator.clipboard) {
		return;
	}
	var text = button.getAttribute('data-copy') || '';
	navigator.clipboard.writeText(text).then(function () {
		button.classList.add('is-copied');
	});
});

function copyToClipboard(e) {
	$(e.target).addClass('confirm');
	if ($(e.target).prop('tagName') === 'A') {
		e.preventDefault();
		var text = $(e.target).attr('href');
	} else if ($(e.delegateTarget).prop('tagName') === 'A') {
		var text = $(e.delegateTarget).attr('href');
	} else {
		var text = $(e.target).html();
	}
	if (typeof $(e.target).attr('data-copy') !== 'undefined') {
		var text = $(e.target).attr('data-copy');
	}
	if ((typeof text) === 'undefined') {
		var text = e.element.innerText;
		if (typeof $(e.element).attr('data-copy') !== 'undefined') {
			var text = $(e.element).attr('data-copy');
		}
	}
	if (navigator.clipboard) {
		navigator.clipboard.writeText(text);
	}
}

$(document).keydown(function (event) {
	if (event.ctrlKey || event.metaKey) {
		switch (String.fromCharCode(event.which).toLowerCase()) {
			case 's':
				event.preventDefault();
				break;
			case 'p':
				event.preventDefault();
				if ($("body").hasClass('pages')) {
					var url = settings.siteRoot + "?id=" + userState.pages.id + "&preview=1";
					var popup = window.open(url, '_newtab');
					if (!popup) {
						alert("Your Browser is blocking PopUps for this Website.\nPlease check your Browser settings.");
						$("a.previewLink").click().addClass("keypressHighlight");
					} else {
						popup.focus();

					}
					;

				}
				break;
			case 'i':
				event.preventDefault();
				$("input[type='search']").focus();
				$("input.searchinput").focus();
				break;
		}
	}
});
// Informational sheets close through their regular, history-free navigation.
document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape' || event.defaultPrevented) return;
    var boxes = Array.from(document.querySelectorAll('.cmslightbox[data-transient-info]')).filter(box => box.getClientRects().length);
    var close = boxes.at(-1)?.querySelector('header [data-box-close],header a[data-history="false"]');
    if (!close) return;
    event.preventDefault();
    close.click();
});

/** Rename administrative roles/groups using the same compact header as tags. */
function renameUserAdminTitle(button) {
    var heading = button.closest('header').querySelector('h1');
    if (heading.isContentEditable) { heading.blur(); return; }
    var previous = heading.textContent;
    var cancelled = false;
    heading.dataset.pencilField = '';
    heading.setAttribute('contenteditable', 'plaintext-only');
    var finish = function () {
        heading.removeEventListener('blur', finish);
        heading.removeEventListener('keydown', keydown);
        clearInlineTitleSelection(heading);
        heading.removeAttribute('contenteditable');
        var value = heading.textContent.replace(/\s+/g, ' ').trim();
        if (cancelled || !value || value === previous) { window.cmsFeedback?.cancel(heading); heading.textContent = previous; return; }
        heading.textContent = value;
        if (value !== previous) {
            var values = {};
            values[button.dataset.renameField || 'value'] = value;
            sys.core.server.sendCall({values: values}, button.dataset.renameUrl, heading);
        }
    };
    var keydown = function (event) {
        if (event.key !== 'Enter' && event.key !== 'Escape') return;
        event.preventDefault();
        event.stopPropagation();
        cancelled = event.key === 'Escape';
        heading.blur();
    };
    heading.addEventListener('blur', finish);
    heading.addEventListener('keydown', keydown);
    heading.focus();
    selectElementContents(heading);
}

// Autosaved technical keys use native validity without sending invalid changes.
['input', 'change', 'blur', 'focusout'].forEach(function (type) {
    document.addEventListener(type, function (event) {
        var input = event.target;
        if (!input.matches || !input.matches('input[data-setting-key]')) return;
        var valid = input.checkValidity();
        input.setAttribute('aria-invalid', valid ? 'false' : 'true');
        if (!valid) {
            event.stopImmediatePropagation();
            if (type !== 'input') input.reportValidity();
        }
    }, true);
});

/** Quiet feedback for the existing request flow. No value is stored outside this tab. */
(function () {
    const originals = new WeakMap();
    const dirty = new Map();
    const pending = new Set();
    const retryFields = new Set();
    const failures = [];
    let message = '', state = '', timer, navigation, navigationTimer, statusBox = null;
    let notice = '', noticeTimer;
    const writesPending = () => [...pending].some(token => token.visible);
    function resumeNavigation() {
        clearTimeout(navigationTimer);
        navigationTimer = setTimeout(() => {
            if (!navigation || writesPending()) return;
            const next = navigation; navigation = null;
            if (!next.failed && api.canLeave(next.scope)) next.run();
        }, 0);
    }
    const editable = 'input:not([type="search"]):not([type="file"]):not([type="hidden"]),textarea,select,[contenteditable="true"],[contenteditable="plaintext-only"],[data-pencil-field]';
    const fieldValue = el => (el.isContentEditable || el.hasAttribute('data-pencil-field')) ? el.innerHTML : /^(checkbox|radio)$/.test(el.type) ? el.checked : el.value;
    const eligible = el => el?.matches?.(editable) && !el.closest('[data-feedback=none],[data-feedback=parameters]') && !el.closest('.authCard') && (el.hasAttribute('data-pencil-field') || el.closest('form:not([method="get"]),.ajax,[data-on_input],[data-on_change],[data-on_blur],[data-on_focusout]'));
    const feedbackFields = new Map();
    const fieldOwners = new WeakMap();
    let feedbackSequence = 0;
    const mobileFeedback = matchMedia('(max-width: 1024px), (orientation: portrait) and (max-width: 1400px)');
    const line = () => {
        if (statusBox && !statusBox.isConnected) {
            const title = statusBox.querySelector('header h1,header h2')?.textContent.trim();
            statusBox = [...document.querySelectorAll('.cmslightbox')].find(box => !box.hidden && box.className === statusBox.className && box.querySelector('header h1,header h2')?.textContent.trim() === title) || null;
        }
        if (!mobileFeedback.matches && statusBox?.isConnected) return statusBox.querySelector('footer.cmsDialogStatus .cmsSaveState');
        const lines = [...document.querySelectorAll('body > footer.cmsStatusbar .cmsSaveState, body > .cmsSaveState.is-mobile')];
        const current = lines.find(el => !el.hidden) || lines[0];
        lines.forEach(el => { if (el !== current) el.remove(); });
        return current;
    };
    function currentField(el) {
        if (el.isConnected) return el;
        const attrs = ['id','name','type','data-focus-id','data-on_blur','data-on_change','data-on_input','data-key','data-setting-key','data-field','data-group','data-file','data-id','data-index','data-setting_node_id','data-on_click','data-action','data-user_id','href'];
        const selector = el.tagName.toLowerCase() + attrs.filter(key => el.hasAttribute(key)).map(key => '[' + key + '="' + CSS.escape(el.getAttribute(key)) + '"]').join('');
        const scope = el.closest('.cmslightbox') ? [...document.querySelectorAll('.cmslightbox')].filter(box => !box.hidden).at(-1) : document;
        let candidates = scope ? [...scope.querySelectorAll(selector)] : [];
        const owner = fieldOwners.get(el);
        if (owner) {
            const parent = scope?.querySelector(owner);
            if (parent) candidates = [...parent.querySelectorAll(selector)];
        }
        if (candidates.length !== 1) return el;
        if (el.hasAttribute('data-pencil-field')) candidates[0].dataset.pencilField = '';
        return candidates[0];
    }
    function markField(el, next) {
        const feedback=line();
        if(feedback){
            if(!feedback.id)feedback.id='cms-save-feedback-'+(++feedbackSequence);
            const ids=new Set((el.getAttribute('aria-describedby')||'').split(/\s+/).filter(Boolean));
            if(next==='error')ids.add(feedback.id);else ids.delete(feedback.id);
            if(ids.size)el.setAttribute('aria-describedby',[...ids].join(' '));else el.removeAttribute('aria-describedby');
        }
        clearTimeout(feedbackFields.get(el));
        if (!next) { delete el.dataset.saveState; feedbackFields.delete(el); return; }
        el.dataset.saveState = next;
        if (el.matches('[data-feedback=none]')) { delete el.dataset.saveState; return; }
        // Failed saves stay marked until the field is edited, retried or discarded.
        if (next === 'saved' || (next === 'error' && !eligible(el))) feedbackFields.set(el, setTimeout(() => markField(el, ''), next === 'error' ? 6000 : 2500));
    }
    function paint() {
        if (!mobileFeedback.matches && statusBox?.isConnected) {
            const mobileLine = document.querySelector('body > .cmsSaveState.is-mobile');
            const footer = document.querySelector('body > footer.cmsStatusbar');
            if (mobileLine && footer) {
                mobileLine.hidden = true;
                mobileLine.classList.remove('is-mobile');
                footer.append(mobileLine);
            }
        }
        const el = line();
        if (!el) return;
        const visibleState = state || (notice ? 'saved' : '');
        const visibleMessage = [message, notice].filter(Boolean).join(' · ');
        el.dataset.state = visibleState;
        const topBox = [...document.querySelectorAll('.cmslightbox')].filter(box => !box.hidden && box.getClientRects().length).at(-1);
        const hidden = !visibleMessage || !!(topBox && topBox !== statusBox);
        if (el.hidden !== hidden) el.hidden = hidden;
        const host = mobileFeedback.matches ? document.body : (statusBox?.isConnected ? statusBox.querySelector('footer.cmsDialogStatus') : document.querySelector('body > footer.cmsStatusbar'));
        if (host && el.parentElement !== host) host.append(el);
        el.classList.toggle('is-mobile', mobileFeedback.matches);
        const icons = {saved:'circle-check',error:'circle-xmark',changed:'circle-exclamation',saving:'spinner',loading:'spinner'};
        let icon = el.querySelector('i'), text = el.querySelector('span');
        if (!icon || !text) {
            el.replaceChildren();
            icon = document.createElement('i'); icon.setAttribute('aria-hidden','true');
            text = document.createElement('span'); el.append(icon,text);
        }
        icon.className = state === 'saving' || state === 'loading' ? 'spin' : 'cmsIcon cmsIcon-' + (icons[visibleState] || 'circle-info');
        if (text.textContent !== visibleMessage) text.textContent = visibleMessage;
        let retry = el.querySelector('.cmsSaveRetry');
        const retryable = state === 'error' && [...retryFields].some(field => field.isConnected && dirty.has(field));
        if (retryable && !retry) {
            retry = document.createElement('button');
            retry.type = 'button'; retry.className = 'button transparent cmsSaveRetry';
            retry.innerHTML = '<i class="cmsIcon cmsIcon-arrows-rotate" aria-hidden="true"></i><span>Retry</span>';
            retry.onclick = () => {
                for (const field of [...retryFields]) {
                    if (!field.isConnected || !dirty.has(field)) { retryFields.delete(field); continue; }
                    // Re-run the field's own save with its current value. Never
                    // replay destructive actions, mail sends or uploads blindly.
                    field.dispatchEvent(new FocusEvent('blur'));
                }
            };
            el.append(retry);
        }
        if (retry && retry.hidden === retryable) retry.hidden = !retryable;

        dirty.forEach((value, field) => { if (field.isConnected && field.dataset.saveState !== 'error') markField(field, 'changed'); });
        pending.forEach(token => token.fields.forEach(([field, sent]) => {
            if (field.isConnected && dirty.get(field) === sent) markField(field, 'saving');
        }));
    }
    function status(next, text) { clearTimeout(timer); state = next; message = text; paint(); }
    function scopeFields(scope) { return [...dirty.keys()].filter(el => !scope || scope === el || scope.contains(el)); }
    const api = window.cmsFeedback = {
        begin(call, method) {
            const source = call.event?.target || call.event?.element;
            const form = source?.closest?.('form');
            const fields = source ? scopeFields(call.event?.type === 'submit' ? (form || source) : source) : [];
            fields.forEach(el => {
                const parent = el.parentElement?.closest('[data-group_id],[data-setting_node_id],[data-user_id],[id]');
                const key = parent && ['id','data-group_id','data-setting_node_id','data-user_id'].find(key => parent.hasAttribute(key));
                if (key) fieldOwners.set(el, '[' + key + '="' + CSS.escape(parent.getAttribute(key)) + '"]');
            });
            const token = {fields: fields.map(el => [el, dirty.get(el)]), write: method !== 'get', source, ok: false, url: location.href};
            // Programmatic background reads/writes and search do not claim to save edits.
            token.visible = token.write && !!source && !!(fields.length || source.closest?.('[data-action-feedback],[data-action],button[type=submit],.button.submit')) && source.type !== 'search' && !source.closest?.('[data-feedback=none]') && !(source.closest?.('[data-feedback=parameters]') && call.event?.type !== 'submit' && !source.closest?.('[data-action],[data-action-feedback],button[type=submit],.button.submit')) && !source.closest?.('.userTabs,.dashTabs,.sidebarTabs,[role=tablist],[role=tab]');
            pending.add(token);
            token.fields.forEach(([field]) => { retryFields.delete(field); markField(field, 'saving'); });
            if (token.visible) {
                statusBox = source.closest?.('.cmslightbox') || null;
                token.actionButton = source.closest?.('[data-action-feedback],[data-action],.button.submit,button[type=submit]');
                if (token.actionButton?.matches('[role=tab],.dashTabs *, .userTabs *, .sidebarTabs *')) token.actionButton = null;
                if (token.actionButton) markField(token.actionButton, 'saving');
                status('saving', token.fields.length ? 'Saving…' : 'Working…');
            }

            return token;
        },
        finish(token, ok) {
            if (!token || !pending.delete(token)) return;
            clearTimeout(token.slow);
            if (token.actionButton) markField(currentField(token.actionButton), ok ? 'saved' : 'error');
            if (!ok && document.querySelector('footer.cmsStatusbar') && !document.querySelector('.authCard')) {
                const url = new URL(token.url || location.href);
                // Store no payload, response body or authentication/query secrets.
                if (/^https?:$/.test(url.protocol)) {
                const safe = new URL(url.pathname, url.origin);
                for (const key of ['id','lang','target_id']) if (url.searchParams.has(key)) safe.searchParams.set(key, url.searchParams.get(key));
                failures.unshift({time: new Date().toISOString(), kind: token.write ? 'Save or action failed' : 'Page could not load', url: safe.href});
                failures.splice(20);
                document.dispatchEvent(new CustomEvent('cms:failures'));
                }
            }

            if (ok) token.fields.forEach(([el, sent]) => {
                const live = currentField(el);
                originals.set(el, sent);
                originals.set(live, sent);
                if (dirty.get(el) === sent) {
                    dirty.delete(el);
                    if (live !== el && dirty.has(live)) markField(live, 'changed');
                    else markField(live, 'saved');
                }
                else if (live !== el && dirty.has(el)) {
                    const value = dirty.get(el);
                    dirty.delete(el); dirty.set(live, value);
                    if (live.isContentEditable) live.innerHTML = value;
                    else if (/^(checkbox|radio)$/.test(live.type)) live.checked = value;
                    else live.value = value;
                    markField(live, 'changed');
                } else if (!dirty.has(el) && el.isConnected && fieldValue(el) !== sent) dirty.set(el, fieldValue(el));
            });
            if (!ok) token.fields.forEach(([field]) => {
                const live = currentField(field);
                if (live !== field && dirty.has(field)) {
                    // An error response may redraw the field with its old server value.
                    // Keep the user's latest local edit, including edits made in flight.
                    const value = dirty.has(live) ? dirty.get(live) : dirty.get(field);
                    dirty.delete(field); dirty.set(live, value);
                    if (live.isContentEditable) live.innerHTML = value;
                    else if (/^(checkbox|radio)$/.test(live.type)) live.checked = value;
                    else live.value = value;
                    if (originals.has(field)) originals.set(live, originals.get(field));
                }
                markField(live, 'error');
                if (live.matches('[data-on_blur]')) retryFields.add(live);
            });
            if (!ok && token.visible && (!token.serverError || ![...document.querySelectorAll('[data-system-messages] > .error')].some(el => !el.closest('[data-client-action-messages]') && el.getClientRects().length))) {
                let messages=document.querySelector('[data-client-action-messages]');
                if (!messages) {
                    messages=document.createElement('div');messages.className='cms message cmsActionMessages';
                    messages.dataset.systemMessages='';messages.dataset.clientActionMessages='';
                    messages.setAttribute('role','alert');document.body.append(messages);
                }
                const error=document.createElement('div');error.className='error';
                const text=document.createElement('p');text.className='message';
                text.textContent=navigator.onLine?'The action could not be completed. Your changes have not been confirmed. Please try again.':'Offline. Your changes could not be saved. Reconnect and try again.';
                error.append(text);messages.replaceChildren(error);
            }
            if (!ok && token.visible && navigation) navigation.failed = true;
            if (!ok && token.visible) status('error', token.fields.length ? 'Not saved. Check the field or try again.' : 'Action failed. See the message for details.');
            else if (!ok) {
                status('error', navigator.onLine ? 'Could not load. Please try again.' : 'Offline. Check your connection.');
                timer = setTimeout(() => {
                    if (dirty.size) status('changed', 'Unsaved changes');
                    else status('', '');
                }, 6000);
            }
            else if (![...pending].some(t => t.visible)) {
                if (dirty.size) { statusBox = [...dirty.keys()].at(-1)?.closest('.cmslightbox') || null; status('changed', 'Unsaved changes'); }
                else if (state !== 'error' || token.visible) {
                    status('saved', token.visible ? (token.fields.length ? 'Saved' : 'Done') : '');
                    timer = setTimeout(() => status('', ''), 2500);
                }
            }
            resumeNavigation();
        },
        navigate(run, scope) {
            if (!scopeFields(scope).length && !writesPending()) { run(); return; }
            navigation = {run, scope, failed:false};
            const active = document.activeElement;
            if (eligible(active)) active.blur();
            resumeNavigation();
        },
        canLeave(scope) {
            const fields = scopeFields(scope);
            const saving = [...pending].some(t => t.write && (scope ? t.source && scope.contains(t.source) : t.visible));
            if (!fields.length && !saving) return true;
            if (saving) { status('saving', 'Saving… Please wait before leaving.'); return false; }
            if (!window.confirm('There are unsaved changes. Discard them and leave?')) return false;
            fields.forEach(el => { dirty.delete(el); markField(el, ''); });
            status('', '');
            return true;
        },
        notice(text, box) {
            clearTimeout(noticeTimer);
            if (box && !message) statusBox = box;
            notice = text;
            paint();
            noticeTimer = setTimeout(() => { notice = ''; paint(); }, 5000);
        },
        refresh: paint,
        failures() { return failures.map(item => ({...item})); },
        hasChanges(scope) { return [...dirty.keys()].some(el => !scope || scope.contains(el)) || [...pending].some(t => t.visible && (!scope || t.fields.some(([el]) => scope.contains(el)))); },
        cancel(el) {
            if (originals.has(el)) {
                const before = originals.get(el);
                if (el.isContentEditable || el.hasAttribute('data-pencil-field')) el.innerHTML = before;
                else if (/^(checkbox|radio)$/.test(el.type)) el.checked = before;
                else el.value = before;
            }
            retryFields.delete(el); dirty.delete(el); markField(el, '');
            if (!dirty.size && ![...pending].some(t => t.visible)) status('', '');
        }
    };
    document.addEventListener('focusin', e => { if (eligible(e.target) && !dirty.has(e.target)) originals.set(e.target, fieldValue(e.target)); }, true);
    document.addEventListener('input', e => {
        const el = e.target;
        if (!eligible(el)) return;
        const value = fieldValue(el);
        if (originals.has(el) && value === originals.get(el) && ![...pending].some(t => t.fields.some(([field, sent]) => field === el && sent !== value))) dirty.delete(el);
        else dirty.set(el, value);
        markField(el, dirty.has(el) ? 'changed' : '');
        statusBox = el.closest('.cmslightbox');
        status('changed', dirty.size ? 'Unsaved changes' : '');
    }, true);
    document.addEventListener('change', e => {
        const el = e.target;
        if (!eligible(el)) return;
        const value = fieldValue(el);
        if (originals.has(el) && value === originals.get(el) && ![...pending].some(t => t.fields.some(([field, sent]) => field === el && sent !== value))) dirty.delete(el);
        else dirty.set(el, value);
        markField(el, dirty.has(el) ? 'changed' : '');
        statusBox = el.closest('.cmslightbox');
        status('changed', dirty.size ? 'Unsaved changes' : '');
    }, true);
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape' || e.defaultPrevented || e.isComposing) return;
        const el = e.target;
        // Rich-text editors and header renamers have their own menu/undo handling.
        if (!eligible(el) || el.isContentEditable || !originals.has(el)) return;
        e.preventDefault(); e.stopImmediatePropagation();
        api.cancel(el); el.cmsEditCancelled = true; el.blur();
    }, true);
    window.addEventListener('beforeunload', e => {
        if (!api.hasChanges() && !document.querySelector('.fileAdjust [data-apply-adjust]:not(:disabled)')) return;
        e.preventDefault(); e.returnValue = '';
    });
    // Native links and local dialogs need the same protection as AJAX navigation.
    document.addEventListener('click', e => {
        const a = e.target.closest?.('a[href]');
        if (!a || !a.hasAttribute('target') || a.closest('[contenteditable="true"],[contenteditable="plaintext-only"]') || a.matches('.ajax,[data-on_click]') || a.target === '_blank' || a.hasAttribute('download') || a.getAttribute('href').startsWith('#')) return;
        if (api.hasChanges()) {
            e.preventDefault(); e.stopImmediatePropagation();
            api.navigate(() => location.assign(a.href), a.closest('.cmslightbox') || undefined);
        }
    }, true);
    document.addEventListener('cms:rendered', paint);
    // The keyboard can pan iOS Safari's visual viewport within the layout viewport.
    const viewport = window.visualViewport;
    const pinStatus = () => {
        document.documentElement.style.setProperty('--cmsStatusViewportTop', (viewport?.offsetTop ?? 0) + 'px');
    };
    viewport?.addEventListener('scroll', pinStatus);
    viewport?.addEventListener('resize', pinStatus);
    pinStatus();
    mobileFeedback.addEventListener('change', paint);
    paint();
    window.addEventListener('offline', () => status('error', 'Offline. Changes cannot be saved.'));
    window.addEventListener('online', () => status('changed', dirty.size ? 'Connected. Check and save your changes.' : 'Connected'));
})();

/** Shared focus and keyboard behavior; existing close actions keep their own routes. */
(function () {
    const opened = new Map();
    let previous = document.activeElement;
    const visible = el => !el.hidden && !!el.getClientRects().length;
    const boxes = () => [...document.querySelectorAll('.cmslightbox')].filter(visible);
    const controls = box => [...box.querySelectorAll('a[href],button,input,textarea,select,[tabindex]')].filter(el => !el.disabled && el.tabIndex >= 0 && visible(el));
    const closeButton = box => box.querySelector('[data-box-close],[data-close-find],header a[aria-label="Close"],header button[aria-label="Close"],header a:has(.cmsIcon-xmark),header button:has(.cmsIcon-xmark),header a:has(.cmsIcon-times),header button:has(.cmsIcon-times)');
    function sync() {
        const active = boxes();
        document.body.classList.toggle('cms-dialog-open', active.length > 0);
        active.forEach(box => {
            if (!box.querySelector('footer.cmsDialogStatus')) {
                const footer = document.createElement('footer');
                footer.className = 'cmsStatusbar cmsDialogStatus cms';
                footer.innerHTML = '<span class="cmsSaveState" role="status" aria-live="polite" hidden></span>';
                (box.querySelector(':scope > section') || box).append(footer);
            }
        });
        document.querySelectorAll(':is(.widget,.cmsBox) > summary').forEach(summary => {
            if (summary.querySelector(':scope > .summaryToggle')) return;
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'button transparent icononly summaryToggle';
            toggle.dataset.feedback = 'none';
            toggle.tabIndex = -1; // The native summary is the keyboard toggle.
            toggle.setAttribute('aria-label', 'Toggle section');
            toggle.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                summary.parentElement.open = !summary.parentElement.open;
            });
            toggle.innerHTML = '<i class="cmsIcon cmsIcon-chevron-down"></i>';
            summary.append(toggle);
        });
        opened.forEach((opener, box) => {
            if (!active.includes(box)) {
                const replacement = active.find(next => !opened.has(next) && next.className === box.className && next.querySelector('header h1,header h2')?.textContent.trim() === box.querySelector('header h1,header h2')?.textContent.trim());
                opened.delete(box);
                if (replacement) opened.set(replacement, opener);
                else if (opener?.isConnected && (!document.activeElement || document.activeElement === document.body || box.contains(document.activeElement))) opener.focus({preventScroll:true});
            }
        });
        active.forEach(box => {
            box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true');
            if (opened.has(box)) return;
            opened.set(box, previous);
            const title = box.querySelector('header h1,header h2');
            if (title && !box.hasAttribute('aria-label') && !box.hasAttribute('aria-labelledby')) box.setAttribute('aria-label', title.textContent.trim());
            if (!box.contains(document.activeElement)) {
                box.tabIndex = -1;
                (box.querySelector('[autofocus]') || box).focus({preventScroll:true});
            }
            const query = box.matches('.cmsFind') && box.querySelector('input[name=query]');
            if (query) query.setSelectionRange(query.value.length, query.value.length);
        });
        previous = document.activeElement;
        window.cmsFeedback?.refresh();
    }
    document.addEventListener('focusin', () => { previous = document.activeElement; });
    document.addEventListener('click', e => {
        const close = e.target.closest?.('[data-box-close],[data-close-find],header a,header button');
        const box = close?.closest('.cmslightbox');
        if (!box || close !== closeButton(box)) return;
        if (close.cmsLeaveApproved) { delete close.cmsLeaveApproved; return; }
        if (window.cmsFeedback.hasChanges(box)) {
            e.preventDefault(); e.stopImmediatePropagation();
            window.cmsFeedback.navigate(() => { close.cmsLeaveApproved = true; close.click(); }, box);
        }
    }, true);
    document.addEventListener('keydown', e => {
        if (e.defaultPrevented || e.isComposing) return;
        const box = boxes().at(-1);
        if (!box) return;
        if (e.key === 'Tab') {
            const list = controls(box), first = list[0], last = list.at(-1);
            if (!first) { e.preventDefault(); return; }
            if (e.shiftKey && (document.activeElement === first || !box.contains(document.activeElement))) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && (document.activeElement === last || !box.contains(document.activeElement))) { e.preventDefault(); first.focus(); }
        } else if (e.key === 'Escape' && !e.target.closest('input,textarea,select,[contenteditable="true"],[contenteditable="plaintext-only"],.dropdownMenu')) {
            const close = closeButton(box);
            if (close) { e.preventDefault(); close.click(); }
        }
    });
    const start = () => {
        const observer = new MutationObserver(() => { clearTimeout(start.timer); start.timer = setTimeout(sync, 0); });
        observer.observe(document.body, {childList:true,subtree:true,attributes:true,attributeFilter:['hidden','open']});
        sync();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();

// Empty tables keep their existing headers/actions. Never infer a permission failure from an empty result.
(function () {
    function sync() {
        document.querySelectorAll('.tableScroll > table').forEach(table => {
            if (!table.getClientRects().length) return;
            const body = table.tBodies[0];
            if (!body) return;
            let empty = body.querySelector(':scope > [data-empty-state]');
            const rows = [...body.rows].filter(row => row !== empty);
            const hasRows = rows.some(row => !row.hidden && getComputedStyle(row).display !== 'none');
            if (hasRows) { if (empty) empty.hidden = true; return; }
            const container = table.closest('.widget,section');
            const query = container?.querySelector('input[type="search"],input.listSearch');
            const text = query?.value.trim() ? 'No matching entries. Try another search or clear the filter.' : 'No entries yet.';
            if (!empty) {
                empty = document.createElement('tr'); empty.dataset.emptyState = '';
                const cell = document.createElement('td');
                cell.colSpan = Math.max(1, [...(table.tHead?.rows[0]?.cells || [])].reduce((n,c) => n+c.colSpan, 0));
                cell.className = 'cmsEmptyState'; empty.append(cell); body.append(empty);
            }
            empty.hidden = false;
            if (empty.firstElementChild.textContent !== text) empty.firstElementChild.textContent = text;
        });
    }
    let queued;
    const schedule = () => { clearTimeout(queued); queued = setTimeout(sync, 50); };
    document.addEventListener('cms:rendered', schedule);
    document.addEventListener('toggle', schedule, true);
    document.addEventListener('input', e => { if (e.target.matches('input[type="search"],input.listSearch')) schedule(); });
    const start = () => { new MutationObserver(schedule).observe(document.body, {childList:true,subtree:true}); schedule(); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();


// Shared messages retire with a measured, smooth collapse instead of a height jump.
(function () {
    const watched=new WeakSet();
    function scan() {
        document.querySelectorAll('[data-system-messages] > div').forEach(row => {
            if (watched.has(row) || row.parentElement.classList.contains('is-stay') || row.querySelector('a,button')) return;
            watched.add(row);
            row.parentElement.getAnimations().forEach(animation => animation.cancel());
            let timer, animation;
            function stop() { clearTimeout(timer); animation?.cancel(); }
            function schedule() {
                stop(); timer=setTimeout(() => {
                    if (!row.isConnected || (row.matches(':focus-within') || (matchMedia('(hover: hover)').matches && row.matches(':hover')))) return;
                    const box=row.parentElement.children.length===1?row.parentElement:row;
                    const style=getComputedStyle(box);
                    animation=box.animate([
                        {height:box.getBoundingClientRect().height+'px',opacity:1,marginTop:style.marginTop,marginBottom:style.marginBottom,paddingTop:style.paddingTop,paddingBottom:style.paddingBottom},
                        {height:'0px',opacity:0,transform:'translateY(-.6rem) scale(.97)',filter:'blur(3px)',marginTop:'0px',marginBottom:'0px',paddingTop:'0px',paddingBottom:'0px'}
                    ],{duration:matchMedia('(prefers-reduced-motion: reduce)').matches?0:420,easing:'ease-in-out',fill:'forwards'});
                    box.style.overflow='hidden';
                    animation.finished.then(()=>{row.remove();animation.cancel();box.style.overflow='';}).catch(()=>{box.style.overflow='';});
                },10000);
            }
            row.addEventListener('pointerenter',event=>{if(event.pointerType==='mouse')stop();});row.addEventListener('pointerleave',event=>{if(event.pointerType==='mouse')schedule();});
            row.addEventListener('focusin',stop);row.addEventListener('focusout',schedule);schedule();
        });
    }
    function startMessages() { new MutationObserver(scan).observe(document.body,{childList:true,subtree:true});scan(); }
    if (document.body) startMessages();
    else document.addEventListener('DOMContentLoaded',startMessages,{once:true});
})();

(function () {
    function positionMessages() {
        const content=document.querySelector('main > .content');
        if (!content) return;
        const rect=content.getBoundingClientRect();
        document.documentElement.style.setProperty('--cms-message-left',Math.max(16,rect.left+16)+'px');
        document.documentElement.style.setProperty('--cms-message-right',Math.max(16,innerWidth-rect.right+16)+'px');
    }
    addEventListener('resize',positionMessages);
    document.addEventListener('cms:rendered',positionMessages);
    document.addEventListener('DOMContentLoaded',positionMessages);
})();


(function(){
    function previewReady(event){
        const media=event.target;
        if(!media?.matches?.('img,video,audio,iframe') || !media.closest('.cmslightbox .fileFrame'))return;
        media.dataset.previewReady='1';
        const spinner=media.closest('.fileFrame').querySelector('[data-preview-loading]');if(spinner)spinner.hidden=true;
    }
    ['load','loadeddata','error'].forEach(type=>document.addEventListener(type,previewReady,true));
    function watchPreviews(){document.querySelectorAll('.cmslightbox .fileFrame').forEach(frame=>{
        const media=frame.querySelector('img,video,audio,iframe');if(!media)return;
        let spinner=frame.querySelector('[data-preview-loading]');
        if(!spinner){spinner=document.createElement('i');spinner.className='cmsIcon cmsIcon-spinner cmsIcon-spin';spinner.dataset.previewLoading='';spinner.setAttribute('aria-label','Loading preview');spinner.setAttribute('role','status');frame.append(spinner);}
        const ready=()=>{media.dataset.previewReady='1';spinner.hidden=true;};
        if(!media.dataset.previewWatched){media.dataset.previewWatched='1';['load','loadeddata','error'].forEach(type=>media.addEventListener(type,ready));new MutationObserver(()=>{delete media.dataset.previewReady;spinner.hidden=false;}).observe(media,{attributes:true,attributeFilter:['src']});}
        let loaded=media.dataset.previewReady==='1';
        if(media.tagName==='IMG')loaded ||= media.complete;
        else if(media.tagName==='IFRAME'){try{loaded ||= media.contentDocument?.readyState==='complete';}catch{}}
        else loaded ||= media.readyState>=2;
        spinner.hidden=loaded;
    });}
    document.addEventListener('cms:rendered',watchPreviews);document.addEventListener('DOMContentLoaded',watchPreviews);
})();

// Two message placements: next to the item, or in one page-wide floating host.
(function () {
    let queued=false;
    let swipe;
    function dismiss(row) {
        if (!row || row.dataset.dismissing) return;
        row.dataset.dismissing='1';
        const box=row.parentElement.children.length===1?row.parentElement:row;
        box.getAnimations().forEach(animation=>animation.cancel());
        const style=getComputedStyle(box);
        const animation=box.animate([
            {height:box.getBoundingClientRect().height+'px',opacity:1,marginTop:style.marginTop,marginBottom:style.marginBottom,paddingTop:style.paddingTop,paddingBottom:style.paddingBottom},
            {height:'0px',opacity:0,transform:'translateY(-.6rem) scale(.97)',filter:'blur(3px)',marginTop:'0px',marginBottom:'0px',paddingTop:'0px',paddingBottom:'0px'}
        ],{duration:matchMedia('(prefers-reduced-motion: reduce)').matches?0:300,easing:'ease-in-out',fill:'forwards'});
        box.style.overflow='hidden';
        animation.finished.then(()=>{row.remove();animation.cancel();box.style.overflow='';}).catch(()=>{box.style.overflow='';});
    }
    const overlayRow=target=>target.closest('.cmsActionMessages > div');
    document.addEventListener('click',event=>{if(event.pointerType==='touch'||event.target.closest('a,button,input,select,textarea'))return;dismiss(overlayRow(event.target));});
    document.addEventListener('pointerdown',event=>{if(event.pointerType==='touch'&&!event.target.closest('a,button,input,select,textarea'))swipe={row:overlayRow(event.target),x:event.clientX,y:event.clientY,id:event.pointerId};});
    document.addEventListener('pointerup',event=>{if(swipe?.id===event.pointerId&&swipe.y-event.clientY>40&&Math.abs(swipe.x-event.clientX)<60)dismiss(swipe.row);swipe=null;});
    document.addEventListener('pointercancel',()=>{swipe=null;});
    function arrange(){
        queued=false;
        document.querySelectorAll('dialog[data-auto-open]:not([open])').forEach(dialog=>{dialog.showModal();dialog.addEventListener('close',()=>dialog.remove(),{once:true});});
        const pageMessages=[...document.querySelectorAll('[data-system-messages]')].filter(box=>box.children.length && !box.closest('.widget,.cmslightbox,dialog,.authCard,.sidebarItem,.field,form,.cmsBox,.passkeys'));
        if(!pageMessages.length)return;
        const host=pageMessages.find(box=>box.parentElement===document.body && box.classList.contains('cmsActionMessages')) || pageMessages[0];
        host.classList.add('cmsActionMessages');
        if(host.parentElement!==document.body)document.body.append(host);
        pageMessages.filter(box=>box!==host).forEach(box=>{host.append(...box.children);box.remove();});
    }
    function start(){new MutationObserver(()=>{if(!queued){queued=true;queueMicrotask(arrange);}}).observe(document.body,{childList:true,subtree:true});arrange();}
    if(document.body)start();else document.addEventListener('DOMContentLoaded',start,{once:true});
})();

// Existing compact, history-free setting help, also usable with touch/keyboard.
document.addEventListener('click',event=>{
 const button=event.target.closest('[data-setting-help]');if(!button)return;
 const box=document.createElement('dialog');box.className='cms cmslightbox';box.dataset.size='2';
 const section=document.createElement('section'),header=document.createElement('header'),title=document.createElement('h1'),close=document.createElement('button'),main=document.createElement('main');
 title.textContent=button.getAttribute('aria-label');close.type='button';close.className='button transparent icononly';close.setAttribute('aria-label','Close');close.innerHTML='<i class="cmsIcon cmsIcon-xmark"></i>';
 close.onclick=()=>box.close();main.textContent=button.dataset.settingHelp;header.append(title,close);section.append(header,main);box.append(section);document.body.append(box);box.addEventListener('close',()=>box.remove(),{once:true});box.showModal();
});

/** Accessible names and native keyboard behavior for CMS controls, including AJAX output. */
(function () {
    let queued=false, titleBase=document.title, errorId=0;
    const set=(el,key,value)=>{if(el.getAttribute(key)!==value)el.setAttribute(key,value);};
    function sync() {
        queued=false;
        if (!document.body.classList.contains('cms') || document.querySelector('.authCard')) return;
        const content=document.querySelector('body > main > .content');
        if(content){
            if(!content.id)content.id='cms-main';
            if(content.getAttribute('role')!=='dialog')set(content,'role','main');
            if(!content.hasAttribute('tabindex'))content.tabIndex=-1;
        }
        // Preserve existing tags/selectors and DOM-layer references while removing nested landmarks.
        document.querySelectorAll('body > main,body > main main,.cmslightbox main').forEach(el=>{if(!el.closest('article.cmsContent'))set(el,'role','presentation');});
        document.querySelectorAll('.pylon nav').forEach(el=>set(el,'aria-label','Section navigation'));
        document.querySelectorAll('.authFooter nav,.cmsStatusbar nav').forEach(el=>set(el,'aria-label','Help and legal information'));
        const heading=[...(content?.querySelectorAll('h1')||[])].find(el=>!el.closest('article.cmsContent,.cmslightbox'));
        const label=content?.querySelector('.dashboard') ? 'Dashboard' : content?.querySelector('.profile') ? 'Account' : heading?.textContent.trim() || document.querySelector('.main nav li.active > a')?.textContent.trim() || 'Dashboard';
        const stage=titleBase.match(/^[🔨🧪]/u)?.[0] || '';
        const title=stage+ (stage ? ' ' : '') +label+' · '+titleBase.replace(/^[🔨🧪]\s*/u,'');
        if(document.title!==title)document.title=title;
        document.querySelectorAll('.appearanceChoices [role="radio"]').forEach(el=>{el.tabIndex=el.getAttribute('aria-checked')==='true'?0:-1;});
        document.querySelectorAll('.treeMenu').forEach(el=>{
            const label=el.closest('li')?.querySelector(':scope > label > .label')?.textContent.trim();
            if(label){set(el,'aria-label','Menu for page: '+label);set(el,'title','Menu for page: '+label);}
        });
        // Add row context only to short, repeated action names; never use input values or credentials.
        document.querySelectorAll('.tableScroll tr').forEach(row=>{
            const cell=[...row.cells].find(c=>c.tagName==='TD'&&!c.classList.contains('icons')&&!c.classList.contains('userStatusCell')&&!c.classList.contains('pageSettingDrag'));
            const context=cell?.querySelector('span')?.firstChild?.textContent?.trim() || cell?.textContent?.trim();
            if(!context)return;
            row.querySelectorAll('a,button').forEach(el=>{
                const name=el.getAttribute('aria-label')||el.textContent.trim()||el.title;
                if(!/^(Edit|Delete|Restore|Preview|Clean up|Remove)$/i.test(name||''))return;
                set(el,'aria-label',name+': '+context);set(el,'title',name+': '+context);
            });
        });
    }
    const schedule=()=>{if(!queued){queued=true;queueMicrotask(sync);}};
    document.addEventListener('cms:rendered',schedule);
    document.addEventListener('click',event=>{
        if(event.target.closest?.('.cmsSkipLink')){
            const target=document.getElementById('cms-main');
            if(target){event.preventDefault();event.stopImmediatePropagation();target.focus({preventScroll:true});target.scrollIntoView({block:'start'});}
        }
    },true);
    document.addEventListener('keydown',event=>{
        const radio=event.target.closest?.('.appearanceChoices [role="radio"]');
        if(!radio||!['ArrowRight','ArrowDown','ArrowLeft','ArrowUp','Home','End'].includes(event.key))return;
        event.preventDefault();event.stopImmediatePropagation();
        const list=[...radio.parentElement.querySelectorAll('[role="radio"]:not([disabled])')],i=list.indexOf(radio);
        const index=event.key==='Home'?0:event.key==='End'?list.length-1:(i+(['ArrowLeft','ArrowUp'].includes(event.key)?list.length-1:1))%list.length;
        list.forEach((el,n)=>{el.tabIndex=n===index?0:-1;el.setAttribute('aria-checked',n===index?'true':'false');});
        list[index].focus();list[index].click();
    },true);
    // Native validation identifies the field. Network failure alone is never declared invalid input.
    document.addEventListener('invalid',event=>{
        const field=event.target;if(!field.closest?.('.cms'))return;
        let message=field.cmsValidationMessage;
        if(!message){message=document.createElement('span');message.className='cmsA11yStatus';message.id='cms-field-error-'+(++errorId);field.insertAdjacentElement('afterend',message);field.cmsValidationMessage=message;}
        message.textContent=field.validationMessage;set(field,'aria-invalid','true');
        const ids=new Set((field.getAttribute('aria-describedby')||'').split(/\s+/).filter(Boolean));ids.add(message.id);set(field,'aria-describedby',[...ids].join(' '));
    },true);
    document.addEventListener('input',event=>{
        const field=event.target,message=field.cmsValidationMessage;
        if(message&&field.validity?.valid){
            field.setAttribute('aria-invalid','false');field.setAttribute('aria-describedby',(field.getAttribute('aria-describedby')||'').split(/\s+/).filter(id=>id!==message.id).join(' '));message.remove();delete field.cmsValidationMessage;
        }
    },true);
    function start(){new MutationObserver(schedule).observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['hidden']});sync();}
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
})();

// Explain the shared new-password rule before a form can be submitted.
(function () {
    const validate = form => {
        const password = form.querySelector('[name="password"]');
        const repeat = form.querySelector('[name="password_repeat"]');
        const submit = form.querySelector('[type="submit"]');
        if (!password || !repeat || !submit) return;
        const value = password.value;
        const strong = Array.from(value).length >= 15;
        const repetitive = /^(.{1,6})\1+$/us.test(value.trim().toLowerCase());
        let hint = form.querySelector('[data-password-hint]');
        if (!hint) {
            hint = document.createElement('p'); hint.dataset.passwordHint = '';
            hint.className = 'tagsHint'; hint.setAttribute('aria-live', 'polite');
            (repeat.closest('label, .row') || repeat).after(hint);
        }
        const length = Array.from(value).length;
        const rules = [
            [strong, strong ? 'At least 15 characters.' : `Add ${15 - length} more character${15 - length === 1 ? '' : 's'} (minimum 15).`],
            ...(strong ? [[!repetitive, repetitive ? 'Avoid repeating the same characters or short pattern.' : 'No simple repeated pattern. Common passwords are checked when saving.']] : []),
            [!!repeat.value && value === repeat.value, !repeat.value ? 'Repeat your password.' : value !== repeat.value ? 'Repeat the same password in both fields.' : 'Passwords match.']
        ];
        hint.replaceChildren(...rules.map(([ok, text]) => {
            const row = document.createElement('span'); row.className = 'passwordRule';
            const icon = document.createElement('i'); icon.className = `cmsIcon cmsIcon-${ok ? 'circle-check' : 'circle-exclamation'}`;
            icon.style.color = `var(${ok ? '--cmsSuccess' : '--cmsWarning'})`; icon.setAttribute('aria-hidden', 'true');
            row.append(icon, document.createTextNode(text)); return row;
        }));
        repeat.setCustomValidity(repeat.value && value !== repeat.value ? "Passwords do not match." : '');
        submit.disabled = !value || !strong || repetitive || value !== repeat.value;
    };
    const init = () => document.querySelectorAll('form[data-password-check]').forEach(validate);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    document.addEventListener('cms:rendered', init);
    document.addEventListener('input', event => {
        const form = event.target.closest('form[data-password-check]');
        if (form) validate(form);
    });
})();
