/**
 * One camera for two places: the sign-in card (data-qr-host="login") and the
 * account lightbox (data-qr-host="profile"). It starts from a click. Texts on
 * the host can be replaced with data-hint, data-timeout-title,
 * data-timeout-text, data-denied-title, data-invalid, data-dark, data-again
 * and data-allow.
 */
(function () {
	if (window.QrScan) return;

	var scriptSrc = (document.currentScript && document.currentScript.src) || '';
	var libSrc = scriptSrc.replace(/js\/gui\/qr-scan\.js(?:\?.*)?$/, 'js/ext/jsQR.js');
	if (!libSrc || libSrc === scriptSrc) {
		libSrc = scriptSrc.replace(/\/[^\/?#]+(?:\?.*)?$/, '/../ext/jsQR.js');
	}
	var loading = null;
	var LIMIT = 30000;
	var STEP = 120;

	function loadJsQr() {
		if (window.jsQR) return Promise.resolve(window.jsQR);
		if (!loading) {
			loading = new Promise(function (resolve, reject) {
				var tag = document.createElement('script');
				tag.src = libSrc;
				tag.onload = function () { window.jsQR ? resolve(window.jsQR) : reject(new Error('jsQR')); };
				tag.onerror = function () { loading = null; reject(new Error('jsQR')); };
				document.head.appendChild(tag);
			});
		}
		return loading;
	}

	function clean(value) {
		return String(value || '').replace(/[^0-9A-Za-z\-]/g, '');
	}

	function parse(text) {
		var raw = String(text || '').trim();
		var screen = '';
		var code = '';
		if (/^https?:/i.test(raw) || raw.indexOf('?') !== -1) {
			try {
				var url = new URL(raw, location.href);
				screen = clean(url.searchParams.get('screen'));
				code = clean(url.searchParams.get('code'));
			} catch (e) {}
		}
		var bare = !screen && !code && /^[0-9A-Za-z]{4}-?[0-9A-Za-z]{4}$/.test(raw) ? raw : '';
		return { screen: screen, code: code, bare: bare };
	}

	function formOf(host) {
		var body = host.parentElement;
		return body ? body.querySelector('[data-code-form]') : null;
	}

	function hint(host, text) {
		var node = host.querySelector('[data-qr-hint]');
		if (node) node.textContent = text;
	}

	function escapeText(value) {
		return String(value || '').replace(/[&<>]/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c];
		});
	}

	function flash(host, kind, text) {
		var box = host.querySelector('[data-qr-msg]');
		var line = host.querySelector('[data-qr-hint]');
		if (!box) {
			hint(host, text);
			return;
		}
		box.hidden = false;
		box.innerHTML = '<div class="' + kind + '"><p>' + escapeText(text) + '</p></div>';
		if (line) line.hidden = true;
	}

	function clearFlash(host) {
		var box = host.querySelector('[data-qr-msg]');
		var line = host.querySelector('[data-qr-hint]');
		if (box) {
			box.hidden = true;
			box.innerHTML = '';
		}
		if (line) line.hidden = false;
	}

	function openCamera() {
		// The first call has to be the plain one, still inside the click.
		// A facingMode constraint that rejects afterwards is no longer a gesture,
		// and Safari then refuses the prompt.
		return navigator.mediaDevices.getUserMedia({ video: true, audio: false }).then(function (stream) {
			var track = stream.getVideoTracks()[0];
			var facing = track && track.getSettings ? track.getSettings().facingMode : '';
			if (facing === 'environment') return stream;
			return navigator.mediaDevices.getUserMedia({
				video: { facingMode: { exact: 'environment' } },
				audio: false
			}).then(function (rear) {
				stream.getTracks().forEach(function (item) { item.stop(); });
				return rear;
			}).catch(function () {
				return stream;
			});
		});
	}

	function steady(host) {
		return host.getAttribute('data-hint') || 'Hold the QR code steady and well lit in the frame.';
	}

	function go(base, code) {
		var join = base.indexOf('?') === -1 ? '?' : '&';
		location.href = base + join + 'screen=' + encodeURIComponent(code);
	}

	function fill(form, code) {
		var chars = String(code).toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 8);
		var boxes = form.querySelectorAll('[data-slot]');
		for (var i = 0; i < boxes.length; i++) {
			boxes[i].value = chars.charAt(i) || '';
			boxes[i].dispatchEvent(new Event('input', { bubbles: true }));
		}
		var hidden = form.querySelector('input[name="code"]');
		if (hidden) hidden.value = chars.length > 4 ? chars.slice(0, 4) + '-' + chars.slice(4) : chars;
	}

	function showFields(host, keep) {
		if (!keep) host.hidden = true;
		var form = formOf(host);
		if (form) form.hidden = false;
	}

	function showDim(host, mode) {
		var dim = host.querySelector('[data-qr-dim]');
		var title = host.querySelector('[data-qr-dim-title]');
		var text = host.querySelector('[data-qr-dim-text]');
		var again = host.querySelector('[data-qr-again]');
		if (!dim) return;
		host.hidden = false;
		host.classList.add('is-dim');
		host.classList.remove('is-on');
		dim.hidden = false;
		var denied = mode === 'denied';
		if (title) title.textContent = denied
			? (host.getAttribute('data-denied-title') || 'Camera access is required')
			: (host.getAttribute('data-timeout-title') || 'Scanning timed out');
		if (text) text.textContent = denied
			? ''
			: (host.getAttribute('data-timeout-text') || 'The camera stopped. You can try again.');
		if (again) {
			var words = denied
				? (host.getAttribute('data-allow') || 'Allow access')
				: (host.getAttribute('data-again') || 'Try again');
			var label = again.querySelector('[data-qr-label]');
			if (label) label.textContent = words;
			else again.textContent = words;
			var mark = again.querySelector('i');
			if (mark) mark.className = 'fa-regular ' + (denied ? 'fa-camera' : 'fa-rotate-right');
		}
		// The account lightbox already offers "Or use code" and keeps the QR.
		// Opening the form there hides that QR.
		if (denied && formOf(host) && !dim.querySelector('[data-remote-code]')) showFields(host, true);
		if (denied) whenAllowed(host);
	}

	function whenAllowed(host) {
		if (host._camWatch || !navigator.permissions || !navigator.permissions.query) return;
		host._camWatch = true;
		function look() {
			navigator.permissions.query({ name: 'camera' }).then(function (status) {
				host._camState = status.state;
				if (status.state === 'granted' && host.isConnected && host.classList.contains('is-dim')) {
					start(host);
				}
				if (!status._qrBound) {
					status._qrBound = true;
					status.onchange = look;
				}
			}).catch(function () {});
		}
		window.addEventListener('focus', function () {
			if (host.classList.contains('is-dim')) look();
		});
		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'visible' && host.classList.contains('is-dim')) look();
		});
		look();
	}

	function stop(host) {
		var live = host._qr;
		if (!live) return;
		live.done = true;
		if (live.onHide) document.removeEventListener('visibilitychange', live.onHide);
		if (live.timer) clearTimeout(live.timer);
		if (live.frame) clearTimeout(live.frame);
		if (live.stream) live.stream.getTracks().forEach(function (track) { track.stop(); });
		var video = host.querySelector('video');
		if (video) video.srcObject = null;
		host._qr = null;
		host.classList.remove('is-on');
	}

	function accept(host, run) {
		stop(host);
		flash(host, 'confirm', 'Code recognised.');
		setTimeout(run, 700);
		return true;
	}

	function take(host, text) {
		var found = parse(text);
		if (host.getAttribute('data-qr-host') === 'profile') {
			var token = found.screen || found.bare;
			if (!token) return false;
			return accept(host, function () { go(host.getAttribute('data-base') || '', token); });
		}
		if (found.screen) {
			return accept(host, function () { go(host.getAttribute('data-profile') || '', found.screen); });
		}
		var typed = found.code || found.bare;
		var form = formOf(host);
		if (!typed || !form) return false;
		return accept(host, function () {
			fill(form, typed);
			var button = form.querySelector('button[type="submit"]');
			if (button) button.click();
			else if (form.requestSubmit) form.requestSubmit();
			else form.submit();
		});
	}

	var BLOCKED = 'The browser will not ask for the camera again. Allow it for this site in the address bar, then try again.';

	function start(host, again) {
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
			showDim(host, 'denied');
			return;
		}
		// Ask in this turn. A constraint the laptop cannot meet must not
		// swallow the prompt; the plain request is the second try.
		var askedAt = again ? Date.now() : 0;
		var pendingCamera = openCamera();
		stop(host);
		host.hidden = false;
		host.classList.remove('is-dim');
		var dim = host.querySelector('[data-qr-dim]');
		if (dim) dim.hidden = true;
		var form = formOf(host);
		if (form) form.hidden = true;
		clearFlash(host);
		hint(host, steady(host));
		var live = { done: false, stream: null, timer: null, frame: null, mirror: true, darkAt: 0, bad: '' };
		host._qr = live;
		function onHide() {
			if (document.visibilityState !== 'hidden' || host._qr !== live) return;
			document.removeEventListener('visibilitychange', onHide);
			finish();
			showDim(host, 'timeout');
		}
		live.onHide = onHide;
		document.addEventListener('visibilitychange', onHide);
		var video = host.querySelector('video');
		if (video) video.disablePictureInPicture = true;
		var canvas = host._canvas || document.createElement('canvas');
		host._canvas = canvas;
		var ctx = canvas.getContext('2d', { willReadFrequently: true });
		var torch = host.querySelector('[data-qr-torch]');
		if (torch) torch.hidden = true;

		function finish() {
			if (live.done) return;
			live.done = true;
			document.removeEventListener('visibilitychange', onHide);
			if (live.timer) clearTimeout(live.timer);
			if (live.frame) clearTimeout(live.frame);
			if (live.stream) live.stream.getTracks().forEach(function (track) { track.stop(); });
			if (video) video.srcObject = null;
			if (host._qr === live) host._qr = null;
			host.classList.remove('is-on');
		}

		live.timer = setTimeout(function () {
			if (host._qr !== live) return;
			finish();
			showDim(host, 'timeout');
		}, LIMIT);

		function tick(now) {
			if (host._qr !== live || live.done) return;
			if (!document.contains(host)) { finish(); return; }
			var box = host.closest('.cmslightbox');
			if (box && box.hidden) { finish(); return; }
			if (document.visibilityState === 'hidden') {
				finish();
				showDim(host, 'timeout');
				return;
			}
			live.frame = setTimeout(function () { tick(performance.now()); }, STEP);
			if (!video || video.readyState < 2 || !video.videoWidth) return;
			var scale = Math.min(1, 640 / Math.max(video.videoWidth, video.videoHeight));
			canvas.width = Math.round(video.videoWidth * scale);
			canvas.height = Math.round(video.videoHeight * scale);
			ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
			var image = ctx.getImageData(0, 0, canvas.width, canvas.height);
			if (now - live.darkAt > 700) {
				live.darkAt = now;
				var sum = 0;
				var n = 0;
				var data = image.data;
				for (var i = 0; i < data.length; i += 64) {
					sum += data[i] * 0.3 + data[i + 1] * 0.59 + data[i + 2] * 0.11;
					n++;
				}
				hint(host, n && sum / n < 28 ? (host.getAttribute('data-dark') || 'Too dark. Use a better light.') : steady(host));
			}
			if (!window.jsQR) return;
			var hit = window.jsQR(image.data, image.width, image.height, { inversionAttempts: 'attemptBoth' });
			if (live.mirror && (!hit || !hit.data)) {
				// A front camera often shows the phone mirrored. The rear camera does not.
				ctx.save();
				ctx.translate(canvas.width, 0);
				ctx.scale(-1, 1);
				ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
				ctx.restore();
				image = ctx.getImageData(0, 0, canvas.width, canvas.height);
				hit = window.jsQR(image.data, image.width, image.height, { inversionAttempts: 'attemptBoth' });
			}
			if (!hit || !hit.data) return;
			if (hit.data === live.bad) return;
			if (take(host, hit.data)) {
				finish();
				if (navigator.vibrate) navigator.vibrate(40);
				return;
			}
			live.bad = hit.data;
			flash(host, 'error', host.getAttribute('data-invalid') || 'This code is not valid.');
			setTimeout(function () {
				if (live.bad === hit.data) live.bad = '';
				if (host._qr === live && !live.done) {
					clearFlash(host);
					hint(host, steady(host));
				}
			}, 1600);
		}

		Promise.all([
			loadJsQr(),
			pendingCamera
		]).then(function (result) {
			if (host._qr !== live) {
				result[1].getTracks().forEach(function (track) { track.stop(); });
				return;
			}
			live.stream = result[1];
			video.srcObject = live.stream;
			var playing = video.play();
			if (playing && playing.catch) playing.catch(function () {});
			host.classList.add('is-on');
			var track = live.stream.getVideoTracks()[0];
			var facing = track && track.getSettings ? track.getSettings().facingMode : '';
			live.mirror = facing !== 'environment';
			if (video) video.classList.toggle('is-mirror', live.mirror);
			var caps = track && track.getCapabilities ? track.getCapabilities() : {};
			if (torch && caps.torch) {
				torch.hidden = false;
				torch.onclick = function () {
					var on = torch.classList.toggle('is-on');
					track.applyConstraints({ advanced: [{ torch: on }] }).catch(function () {});
				};
			}
			tick(performance.now());
		}).catch(function (error) {
			if (host._qr !== live) return;
			var fast = askedAt && (Date.now() - askedAt < 300);
			finish();
			showDim(host, 'denied');
			var note = host.querySelector('[data-qr-dim-text]');
			if (note && error && error.name === 'NotFoundError') {
				note.textContent = 'No camera was found on this device.';
				return;
			}
			// A stored block and a dismissal Chrome will not show again both
			// reject in this turn. A prompt the user actually answered takes longer.
			if (fast && error && error.name === 'NotAllowedError') window.alert(BLOCKED);
		});
	}

	document.addEventListener('click', function (event) {
		if (event.target.closest && event.target.closest('[data-on_click="loginMethod"], a.authSwitch, a.authMethod')) {
			document.querySelectorAll('[data-qr-host]').forEach(stop);
		}
		var openScan = event.target.closest && event.target.closest('[data-qr-start]');
		if (openScan) {
			var scope = openScan.closest('.authBody, .remoteLogin') || openScan.parentElement;
			var scanHost = scope && scope.querySelector('[data-qr-host]');
			if (scanHost) start(scanHost);
			return;
		}
		var again = event.target.closest && event.target.closest('[data-qr-again]');
		if (again) {
			var againHost = again.closest('[data-qr-host]');
			if (!againHost) return;
			if (againHost._camState === 'denied') {
				window.alert(BLOCKED);
				return;
			}
			start(againHost, true);
			return;
		}
		var useCode = event.target.closest && event.target.closest('[data-remote-code]');
		if (useCode) {
			var scope = useCode.closest('.remoteLogin');
			var codeHost = scope && scope.querySelector('[data-qr-host]');
			if (codeHost) {
				stop(codeHost);
				codeHost.hidden = true;
				codeHost.classList.remove('is-dim', 'is-on');
				var dimmed = codeHost.querySelector('[data-qr-dim]');
				if (dimmed) dimmed.hidden = true;
			}
			var codeForm = scope && scope.querySelector('[data-code-form]');
			if (codeForm) {
				codeForm.hidden = false;
				var first = codeForm.querySelector('[data-slot]');
				if (first) first.focus();
			}
			return;
		}
		var close = event.target.closest && event.target.closest('[data-qr-close]');
		if (!close) return;
		var scanBox = close.closest('.cmslightbox');
		if (!scanBox) return;
		event.preventDefault();
		var closeHost = scanBox.querySelector('[data-qr-host]');
		if (closeHost) {
			stop(closeHost);
			clearFlash(closeHost);
			closeHost.hidden = true;
			closeHost.classList.remove('is-dim', 'is-on');
			var closedDim = closeHost.querySelector('[data-qr-dim]');
			if (closedDim) closedDim.hidden = true;
			var home = closeHost.closest('.remoteLogin');
			var homeForm = home && home.querySelector('[data-code-form]');
			if (home && home.querySelector('.remoteOffer') && homeForm) {
				homeForm.hidden = true;
			} else if (formOf(closeHost)) {
				showFields(closeHost, false);
			}
		}
		scanBox.hidden = true;
	}, true);

	window.QrScan = { start: start, stop: stop };
})();
