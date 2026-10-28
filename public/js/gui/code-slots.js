/**
 * The eight boxes of a sign-in code. One binder for the login card, the
 * account lightbox and the screen approval. A full code with data-on_change
 * sends itself. data-auto-submit clicks the form once the page is ready.
 */
(function () {
	var letters = /[^0-9A-Z]/g;

	function bind(root) {
		(root || document).querySelectorAll('[data-code-slots]').forEach(function (slots) {
			if (slots.dataset.bound) return;
			slots.dataset.bound = '1';
			var boxes = Array.prototype.slice.call(slots.querySelectorAll('[data-slot]'));
			var hidden = slots.querySelector('input[type="hidden"]');
			var write = function () {
				var raw = boxes.map(function (box) { return box.value; }).join('');
				if (!hidden) return;
				hidden.value = raw.length > 4 ? raw.slice(0, 4) + '-' + raw.slice(4) : raw;
				if (!hidden.hasAttribute('data-on_change')) return;
				if (raw.length === 8 && hidden.dataset.sent !== raw) {
					hidden.dataset.sent = raw;
					hidden.dispatchEvent(new Event('change', { bubbles: true }));
				} else if (raw.length < 8) {
					delete hidden.dataset.sent;
				}
			};
			var put = function (start, text) {
				var chars = String(text || '').toUpperCase().replace(letters, '');
				if (chars.length > 4) start = 0;
				chars = chars.slice(0, 8 - start);
				if (start === 0) boxes.forEach(function (box) { box.value = ''; });
				for (var i = 0; i < chars.length; i++) boxes[start + i].value = chars.charAt(i);
				var next = boxes[Math.min(start + chars.length, boxes.length - 1)];
				if (chars.length && next) next.focus();
				write();
			};
			boxes.forEach(function (box, index) {
				box.addEventListener('keydown', function (event) {
					if (event.key === 'Backspace' && box.value === '' && index > 0) {
						boxes[index - 1].value = '';
						boxes[index - 1].focus();
						write();
					} else if (event.key === 'ArrowLeft' && index > 0) {
						boxes[index - 1].focus();
					} else if (event.key === 'ArrowRight' && index < boxes.length - 1) {
						boxes[index + 1].focus();
					}
				});
				box.addEventListener('input', function () {
					var value = box.value.toUpperCase().replace(letters, '');
					if (value.length > 1) {
						box.value = '';
						put(index, value);
						return;
					}
					box.value = value;
					if (value && index < boxes.length - 1) boxes[index + 1].focus();
					write();
				});
				box.addEventListener('paste', function (event) {
					var text = (event.clipboardData || window.clipboardData).getData('text');
					if (!text) return;
					event.preventDefault();
					put(index, text);
				});
				box.addEventListener('focus', function () { box.select(); });
			});
			var form = slots.closest('form');
			if (form) {
				form.addEventListener('submit', write);
				armAuto(form);
			}
			write();
		});
	}

	function armAuto(form) {
		if (form.dataset.autoArmed === '1' || !form.hasAttribute('data-auto-submit')) return;
		if (document.body.classList.contains('appBody')) return;
		form.dataset.autoArmed = '1';
		var tries = 0;
		var wait = setInterval(function () {
			var ready = window.sys && sys.lib && typeof sys.lib.parseQuery === 'function';
			if (ready || ++tries > 50) {
				clearInterval(wait);
				var btn = form.querySelector('button[type="submit"]');
				if (btn) btn.click();
			}
		}, 100);
	}

	var queued = false;
	new MutationObserver(function () {
		if (queued) return;
		queued = true;
		requestAnimationFrame(function () {
			queued = false;
			bind(document);
		});
	}).observe(document.documentElement, { childList: true, subtree: true });

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { bind(document); });
	} else {
		bind(document);
	}
})();
