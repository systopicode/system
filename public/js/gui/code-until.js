/**
 * A shown one-time code (data-code-until, unix seconds). While it is valid
 * the block [data-code-live] stays. After that it is hidden and
 * [data-code-expired] is shown. This covers a lightbox that just sits there.
 *
 * data-code-left counts from the seconds the server gave, so a wrong clock on
 * a screen does no harm. [data-code-countdown] shows what is left. With
 * data-code-renew replaces only the visible QR block; a hidden one
 * leaves that to the poll, once it is back.
 */
(function () {
    // Schedule navigation first: optional success UI must never strand a signed-in browser.
    window.finishDeviceSignIn = function (block, answer) {
        if (!block.isConnected || block.dataset.signInFinishing === '1') return;
        block.dataset.signInFinishing = '1';
        window.setTimeout(function () { location.href = answer.redirect; }, 2000);
        try { window.sys?.passkeys?.showSignedIn?.(block, answer.profile, answer.redirect); }
        catch (error) { console.error('Sign-in feedback could not be displayed', error); }
    };
    var renewal = null;
    window.renewDeviceCode = function () {
        if (renewal) return renewal;
        var block = document.querySelector('[data-device-code]');
        if (!block) return Promise.resolve();
        renewal = fetch(location.pathname + location.search, {credentials: 'same-origin', cache: 'no-store', headers: {'X-Device-Code-Renew': '1'}})
            .then(function (response) {
                if (!response.ok) throw new Error('Code renewal failed');
                return response.json();
            }).then(function (answer) {
                if (!block.isConnected) return;
                if (answer.paired) { window.finishDeviceSignIn(block, answer); return; }
                var next = new DOMParser().parseFromString(answer.html || '', 'text/html').querySelector('[data-device-code]');
                if (!next) throw new Error('Code missing from renewal');
                block.replaceWith(next);
            }).finally(function () { renewal = null; });
        return renewal;
    };
	function retire(node) {
		node.querySelectorAll('[data-code-live]').forEach(function (part) {
			part.hidden = true;
		});
		var gone = node.querySelector('[data-code-expired]');
		if (gone) gone.hidden = false;
	}

	function watch(node) {
        var watchKey = node.getAttribute('data-code-until') + ':' + node.getAttribute('data-code-left');
        if (node.dataset.codeWatch === watchKey) return;
        node.dataset.codeWatch = watchKey;
		var until = parseInt(node.getAttribute('data-code-until'), 10);
		if (!until) return;
		var left = parseInt(node.getAttribute('data-code-left'), 10);
		var end = left > 0 ? Date.now() + left * 1000 : until * 1000;
		var clock = node.querySelector('[data-code-countdown]');
		var go = function () {
			if (!node.isConnected || node.dataset.codeWatch !== watchKey || node.dataset.codeComplete === '1') return;
			var wait = end - Date.now();
			if (clock) {
				var rest = Math.max(0, Math.ceil(wait / 1000));
				clock.textContent = Math.floor(rest / 60) + ':' + String(rest % 60).padStart(2, '0');
			}
			if (wait > 0) {
				window.setTimeout(go, clock ? Math.min(wait, 1000) : wait + 250);
				return;
			}
            if (node.hasAttribute('data-code-renew')) {
                if (node.offsetParent && document.visibilityState === 'visible') {
                    window.renewDeviceCode().catch(function () {
                        if (node.isConnected) window.setTimeout(go, 3000);
                    });
                }
                return;
            }
            retire(node);
		};
		go();
	}

	function bind(root) {
		(root || document).querySelectorAll('[data-code-until]').forEach(watch);
	}

	var queued = false;
	function schedule() {
		if (queued) return;
		queued = true;
		window.setTimeout(function () {
			queued = false;
			bind(document);
		}, 0);
	}

	new MutationObserver(schedule).observe(document.documentElement, {childList: true, subtree: true});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { bind(document); });
	} else {
		bind(document);
	}

	// A lightbox that is showing a one-time code. Once it has been scanned
	// the box closes. On the account page the offer is drawn again, so the
	// next open is a new code rather than the one that was just spent.
	function watchSpent(node) {
		var code = node.getAttribute('data-code-value') || '';
        if (!code || node.dataset.spentWatch === code) return;
        if (node._codeInterval) clearInterval(node._codeInterval);
        node.dataset.spentWatch = code;
        delete node.dataset.codeComplete;
		var busy = false;
        var kind = node.getAttribute('data-device-kind');
        var profileFlow = ['self', 'screen', 'person'].includes(kind);
        var deadline = Date.now() + 15 * 60 * 1000;
		function tick() {
            // ORM updates may replace the profile while the target finishes signing in.
            // Keep this completion check, but never reopen or write into a new dialog.
            if (Date.now() > deadline || (!node.isConnected && !profileFlow)) { clearInterval(node._codeInterval); return; }
			if (node.hidden || node.closest('.cmslightbox')?.hidden || busy) return;
			busy = true;
			var body = new URLSearchParams();
			body.set('code', code);
			fetch(location.pathname + location.search, {
				method: 'POST',
				headers: {
					'X-Code-Live': '1',
					'Content-Type': 'application/x-www-form-urlencoded'
				},
				body: body.toString(),
				credentials: 'same-origin',
				cache: 'no-store'
			}).then(function (response) {
				return response.json();
			}).then(function (data) {
				busy = false;
				if (!data || data.alive !== false || node.getAttribute('data-code-value') !== code || node.closest('.cmslightbox')?.hidden) return;
                clearInterval(node._codeInterval);
                if (!node.isConnected && data.used !== true) return;
                var feedback = node.querySelector('[data-code-feedback], [data-remote-feedback]');
                if (!feedback) {
                    feedback = document.createElement('div');
                    (node.querySelector('main') || node).prepend(feedback);
                }
                feedback.className = 'cms message';
                var message = document.createElement('div');
                message.className = data.used === true ? 'confirm' : 'error';
                var text = document.createElement('p');
                text.textContent = data.used === true ? 'Device signed in successfully.' : 'This code has expired. Create a new code to try again.';
                message.append(text); feedback.replaceChildren(message);
                if (data.used === true) {
                    var box = node.closest('.cmslightbox') || node;
                    box.dataset.codeComplete = '1';
                    box.querySelectorAll('[data-code-live], [data-code-form], [data-qr-host], .message.is-stay').forEach(function (part) { part.hidden = true; });
                    var refresh = node.getAttribute('data-code-refresh');
                    if (refresh && window.sys && sys.core && sys.core.server) {
                        var refreshList = function () {
                            if (!document.querySelector('[data-own-devices], [data-paired-screen-devices]')) return;
                            var call = new sys.core.server.call({method: 'put'});
                            call.background = true;
                            call.send(refresh);
                        };
                        refreshList();
                        // Authentication precedes the target's websocket connection.
                        window.setTimeout(refreshList, 2000);
                        window.setTimeout(refreshList, 5000);
                    }
                }
			}).catch(function () {
				busy = false;
			});
		}
		node._codeTick = tick;
        node._codeInterval = window.setInterval(function () {
            tick();
        }, 3000);
	}

	function bindSpent(root) {
		(root || document).querySelectorAll('[data-code-value]').forEach(watchSpent);
	}

	new MutationObserver(function () {
		bindSpent(document);
	}).observe(document.documentElement, { childList: true, subtree: true });
	bindSpent(document);

	document.addEventListener('click', function (event) {
		var open = event.target.closest && event.target.closest('[data-open-box]');
		if (!open) return;
		var box = document.querySelector(open.getAttribute('data-open-box'));
		if (box && box._codeTick) window.setTimeout(box._codeTick, 400);
	});
})();
