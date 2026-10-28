/**
 * A shown one-time code (data-code-until, unix seconds). While it is valid
 * the block [data-code-live] stays. After that it is hidden and
 * [data-code-expired] is shown. The login card reloads on its own when the
 * poll notices; this covers a lightbox that just sits there.
 */
(function () {
	function retire(node) {
		node.querySelectorAll('[data-code-live]').forEach(function (part) {
			part.hidden = true;
		});
		var gone = node.querySelector('[data-code-expired]');
		if (gone) gone.hidden = false;
	}

	function watch(node) {
		if (node.dataset.codeWatch === '1') return;
		node.dataset.codeWatch = '1';
		var until = parseInt(node.getAttribute('data-code-until'), 10);
		if (!until) return;
		var go = function () {
			if (Date.now() >= until * 1000) retire(node);
		};
		go();
		var wait = until * 1000 - Date.now();
		if (wait > 0) window.setTimeout(go, wait + 250);
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
		if (node.dataset.spentWatch === '1') return;
		var code = node.getAttribute('data-code-value') || '';
		if (!code) return;
		node.dataset.spentWatch = '1';
		var busy = false;
		function tick() {
			if (!document.contains(node) || node.hidden || busy) return;
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
				if (!data || data.alive !== false || !document.contains(node)) return;
				node.hidden = true;
				if (node.classList.contains('remoteLoginBox') && window.sys && sys.core && sys.core.server) {
					sys.core.server.sendGet(location.pathname + location.search);
				}
			}).catch(function () {
				busy = false;
			});
		}
		node._codeTick = tick;
		window.setInterval(tick, 3000);
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
