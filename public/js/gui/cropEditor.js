/* global sys, document, window, fetch, ResizeObserver, confirm */

/**
 * The image editor of master and ratios - the browser's side of
 * Media\CropEditorEndpoint (cropState, saveMasterCrop, saveRatioCrops,
 * savePois).
 *
 * Two modes, two tabs (in the header of the window):
 *
 *   master   shows the original. The frame is the master crop: any ratio
 *            (the slider moves freely, a click on a ratio sets that one),
 *            turned, mirrored, with its look.
 *   crops    shows the master. The frame is the crop of one declared ratio;
 *            a click on the slider picks the ratio. Going from ratio to
 *            ratio keeps what was changed (this.pending); the crops nobody
 *            set by hand are worked out again from it as the server would.
 *
 * Where a control is says what it acts on: left the slider - what is
 * edited; in the middle the picture, and its corner acts on the picture on
 * screen only (back to automatic, or keep a computed crop as it is); right
 * the tools, and below them Apply and Discard for everything of the mode
 * that waits. Only leaving - another mode, closing, a link out - asks first.
 *
 * **The frame stands still, the picture moves under it**: drag to move,
 * wheel or two fingers to zoom. What is stored is how the frame is aligned
 * in the picture and how far it is zoomed in - over "fill in" or over
 * "fit in", by the crop's basis (Media\CropBox): the first of the tools
 * switches it. In 'fill' the edge of the picture stays outside
 * the frame, in 'fit' background may show. Switching starts the frame
 * afresh: centred at scale 1, "fill in" or "fit in".
 *
 * Pan and zoom are a choice of two, pan the one in the hand at first. Two
 * tools work around a **fix point** (a cross on the stage):
 *
 *   zoom     drag anywhere: the picture grows and shrinks around the fix
 *            point, and the place that was grabbed stays under the pointer -
 *            the two-finger zoom, done with a mouse. The pointer is an arrow
 *            away from the fix point, in steps of 45 degrees. The cross can
 *            be put anywhere; it snaps to the frame's corners, the middles of
 *            its edges and its middle - shown as circles while it is dragged.
 *   turn     (master) a drag turns freely around the middle of the frame -
 *            that cross stays where it is. With two fingers: turn them.
 *            Beside it the angle, with the way back to none; the 90 degree
 *            tool turns on to the next right angle, the turns that lose
 *            nothing.
 *
 * With either tool, Shift held is the hand: dragging moves the picture.
 * The wheel zooms freely; with Ctrl it goes in steps of editor.step
 * (1, 1.05, 1.1 ...).
 *
 * Turned without a crop of its own, the master is the largest frame inside
 * the turned picture (sys.cropMath.largest), its ratio following the angle;
 * a frame at "fill in" stays at "fill in" while it turns.
 *
 * A click on the cross puts the tool away: pan again.
 *
 * **Points of interest** (faces, objects) are set with a third tool: a click
 * puts one down, dragging moves it, the bar below weighs or removes the
 * chosen one. They are stored at once - they belong to the picture, not to
 * the crop on screen - and every crop nobody set by hand follows them.
 * Not offered for now (editor.pois = false): the tool and the marks are
 * left out; stored points still count on the server and here.
 *
 * Everything drawn while the pointer moves is worked out here
 * (sys.cropMath); what is stored is worked out again by the server, and the
 * editor redraws from the server's answer.
 *
 *   sys.cropEditor.open({stage: element, url: '…/admin/pages/editor/file/image/', nodeId: 169, mode: 'crops', ratio: '16:9'})
 *
 * The modes are tabs in the header of the window, each an address of its
 * own (cms: admin/pages/editor/file/image/master|crops/) - the panel writes
 * them, a tab opens the editor anew in its mode. Leaving with changes not
 * applied asks first, as any link out does.
 */
(function () {
	'use strict';

	const editor = {};
	const ICON = name => '<i class="cmsIcon cmsIcon-' + name + '" aria-hidden="true"></i>';
	const esc = text => String(text).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'})[c]);
	/**
	 * The basis, drawn: dashed the picture (16:9), solid the crop (1:1). Fill: the
	 * crop inside the picture; fit: the picture inside the crop. The crop stays,
	 * the picture is scaled - hovering shows the other basis (14_cropEditor.css).
	 */
	const BASIS = '<svg class="ceBasisIcon" viewBox="0 0 60 44" aria-hidden="true">' +
		'<rect class="cePicture" x="3.333" y="7" width="53.333" height="30" rx="1.5"/>' +
		'<rect class="ceCrop" x="15" y="7" width="30" height="30" rx="1"/></svg>';
	/**
	 * The pointer of the zoom: an arrow away from the fix point, one for every
	 * 45 degrees clockwise from the right; the system's resize pointer where an
	 * image cannot be one.
	 */
	const ARROWS = ['e', 'se', 's', 'sw', 'w', 'nw', 'n', 'ne'].map((fallback, step) => 'url("data:image/svg+xml,' + encodeURIComponent(
		'<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"><path transform="rotate(' + step * 45 + ' 16 16)" ' +
		'd="M6 13.5h12v-5l9 7.5-9 7.5v-5H6z" fill="#000" stroke="#fff" stroke-width="1.5" stroke-linejoin="round"/></svg>') + '") 16 16, ' + fallback + '-resize');
	const SOURCE = {manual: 'set by hand', interpolated: 'computed between its neighbours', poi: 'computed from the points of interest', anchor: 'computed from the nearest crop', centered: 'automatic: centred'};

	/** The frame keeps this much of the stage free around it. */
	editor.margin = 0.1;
	/** Screen pixels within which the fix point snaps to a point of the frame. */
	editor.snap = 12;
	/** The tool for points of interest - not offered for now. */
	editor.pois = false;
	/** Ctrl + wheel: the scale goes in steps of this (1, 1.05, 1.1 ...). */
	editor.step = 0.05;

	/**
	 * options: stage (the element it takes the place of), url (where its
	 * requests go, ending in /), nodeId, mode ('master' | 'crops' | 'formats'
	 * - the tab the address chose), ratio (the ratio, or the fixed format,
	 * to show first).
	 * A new editor puts away the one before it, whose page went with the tab.
	 */
	editor.open = function (options) {
		if (editor.current) {
			editor.current.close(true);
		}
		const session = editor.current = new Session(options);
		session.start();
		return session;
	};

	function Session(options) {
		this.options = options;
		this.math = sys.cropMath;
		this.mode = options.mode === 'crops' || options.mode === 'formats' ? 'crops' : 'master';   // the tab: an address of its own (Editor\File\Image\Panel)
		this.fixed = options.mode === 'formats';   // crops of fixed formats: each its own, no interpolation, none an anchor
		this.ratioName = options.ratio || null;   // crops mode: the ratio (or the fixed format) on screen
		this.work = null;           // what is being edited
		this.saved = null;          // the same as last stored
		this.pending = new Map();   // crops mode: ratio name -> a crop changed, or {auto: true}, not stored yet
		this.revision = 0;          // counts the changes of this.ratios
		this.busy = false;
		this.pointers = new Map();
		this.tool = null;           // 'zoom' | 'turn': dragging works around the fix point; 'poi': points of interest
		this.pivot = null;          // the fix point, on the stage
		this.pois = [];             // points of interest, fractions of the original
		this.poi = -1;              // the chosen one
	}

	/** An angle between -180 (not included) and 180 degrees. */
	const degrees = angle => {
		angle = angle % 360;
		return angle > 180 ? angle - 360 : (angle <= -180 ? angle + 360 : angle);
	};

	// ------------------------------------------------------------------ start

	Session.prototype.start = function () {
		const stage = this.options.stage;
		const el = this.el = document.createElement('div');
		el.className = 'cropEditor';
		el.innerHTML =
			'<div class="ceSide">' +
				'<div class="ceSlider"><div class="ceTrack"><span class="ceKnob"></span></div><ol class="ceGhosts" aria-hidden="true" hidden></ol><ol class="ceRatios"></ol></div>' +
			'</div>' +
			'<div class="ceStage">' +
				'<img class="ceUnder" alt="" draggable="false">' +
				'<div class="ceFrame"><img class="ceInner" alt="" draggable="false"></div>' +
				'<div class="cePois"></div>' +
				'<div class="cePoiBar" hidden>' +
					'<button type="button" class="button" data-ce-poi="lighter" title="Less important" aria-label="Less important">' + ICON('minus') + '</button>' +
					'<output title="Weight"></output>' +
					'<button type="button" class="button" data-ce-poi="heavier" title="More important" aria-label="More important">' + ICON('plus') + '</button>' +
					'<button type="button" class="button" data-ce-poi="remove" title="Remove this point" aria-label="Remove this point">' + ICON('trash') + '</button>' +
				'</div>' +
				'<div class="ceSnaps" hidden aria-hidden="true">' + '<span></span>'.repeat(9) + '</div>' +
				'<button type="button" class="cePivot" hidden title="Fix point - drag to move it, click to put the tool away" aria-label="Fix point"></button>' +
				'<button type="button" class="button ceMore" data-ce-more title="Tools" aria-label="Tools" aria-expanded="false">' + ICON('grip-lines') + '</button>' +
				// The corner acts on what the stage shows: back to automatic, or keep a computed crop as it is.
				'<div class="ceCorner">' +
					'<button type="button" class="button" data-ce-auto title="Back to automatic" aria-label="Back to automatic" hidden>' + ICON('arrow-rotate-left') + '</button>' +
					'<button type="button" class="button" data-ce-pin title="Keep this crop as it is: set by hand, its neighbours no longer move it" aria-label="Keep this crop as it is" hidden>' + ICON('pin') + '</button>' +
				'</div>' +
				'<p class="ceStatus" role="status"></p>' +
			'</div>' +
			'<div class="ceRight">' +
				'<div class="ceTools" role="toolbar" aria-label="Image tools">' +
					'<button type="button" class="button ceWide" data-ce-basis aria-label="Fit in: background may show" aria-pressed="false">' + BASIS + '</button>' +
					'<button type="button" class="button" data-ce-tool="pan" title="Move the picture - the wheel zooms" aria-label="Move the picture" aria-pressed="true">' + ICON('hand-stop') + '</button>' +
					'<button type="button" class="button" data-ce-tool="zoom" title="Zoom around a fix point - Shift: move the picture" aria-label="Zoom around a fix point" aria-pressed="false">' + ICON('resize') + '</button>' +
					'<button type="button" class="button" data-ce-flip title="Mirror" aria-label="Mirror" aria-pressed="false">' + ICON('reflect-horizontal') + '</button>' +
					'<button type="button" class="button" data-ce-turn title="Turn freely: drag around the middle - Shift: move the picture" aria-label="Turn freely" aria-pressed="false">' + ICON('rotate') + '</button>' +
					'<button type="button" class="button" data-ce-quarter title="Turn to the next right angle - turns that lose nothing" aria-label="Turn to the next right angle">' + ICON('file-orientation') + '</button>' +
					(editor.pois ? '<button type="button" class="button" data-ce-tool="poi" title="Points of interest: what must stay in every crop" aria-label="Points of interest" aria-pressed="false">' + ICON('location-dot') + '</button>' : '') +
					'<button type="button" class="button ceWide" data-ce-reset="geometry" title="Back to automatic: the whole picture, not turned, not mirrored">' + ICON('arrow-rotate-left') + '<span>Reset</span></button>' +
					'<button type="button" class="button ceWide" data-ce-reset="crops" title="All crops back to automatic: computed again">' + ICON('arrow-rotate-left') + '<span>Reset all</span></button>' +
					'<hr data-ce-look>' +
					'<button type="button" class="button" data-ce-level="b" title="Brightness" aria-label="Brightness">' + ICON('sun') + '</button>' +
					'<button type="button" class="button" data-ce-level="c" title="Contrast" aria-label="Contrast">' + ICON('contrast') + '</button>' +
					'<button type="button" class="button" data-ce-level="s" title="Saturation" aria-label="Saturation">' + ICON('droplet-half-2') + '</button>' +
					'<button type="button" class="button ceWide" data-ce-reset="look" title="Brightness, contrast and saturation unchanged">' + ICON('arrow-rotate-left') + '<span>Reset</span></button>' +
					'<div class="ceLevel" hidden><output>0</output><input type="range" min="0" max="200" value="100" aria-label="Value">' +
						'<button type="button" class="button" data-ce-level-reset title="Back to unchanged" aria-label="Back to unchanged">' + ICON('arrow-rotate-left') + '</button></div>' +
				'</div>' +
				// For everything of the mode that is not applied yet.
				'<div class="ceCommit">' +
					'<button type="button" class="button" data-ce-cancel title="Discard all changes not applied yet" hidden>Discard</button>' +
					'<button type="button" class="button submit" data-ce-save title="Apply all changes" hidden>Apply</button>' +
				'</div>' +
			'</div>';
		// The modes are tabs of the window's header, each an address (the panel writes them).
		el.classList.add('is-headed');
		stage.classList.add('cropEditing');
		stage.append(el);
		this.stageEl = el.querySelector('.ceStage');
		this.frameEl = el.querySelector('.ceFrame');
		this.under = el.querySelector('.ceUnder');
		this.inner = el.querySelector('.ceInner');
		this.bind();
		this.request('cropState').then(data => {
			if (!data || !data.master) {
				this.status('This picture cannot be cropped.');
				return;
			}
			this.take(data);
		});
	};

	Session.prototype.close = function (force) {
		if (!force && this.dirty() && !confirm('Discard the changes of this picture?')) {
			return false;
		}
		this.unguard();
		if (this.resize) {
			this.resize.disconnect();
		}
		if (this.drawing) {
			window.cancelAnimationFrame(this.drawing);
			this.drawing = 0;
		}
		this.el.remove();
		this.options.stage.classList.remove('cropEditing');
		if (editor.current === this) {
			editor.current = null;
		}
		if (this.options.onClose) {
			this.options.onClose(this.stored);
		}
		return true;
	};

	// ----------------------------------------------------------------- server

	Session.prototype.request = function (action, params) {
		const query = new URLSearchParams(Object.assign({target_id: this.options.nodeId}, params || {}));
		this.busy = true;
		this.el.classList.add('is-busy');
		this.notify(null);
		let reason = null;
		return fetch(this.options.url + action + '?' + query.toString(), {credentials: 'same-origin'})
			.then(response => {
				if (response.ok) {
					return response.json();
				}
				// Refused: the server says why ({error}) - or at least how.
				return response.json().catch(() => null).then(body => {
					reason = (body && body.error) || 'The server refused it (HTTP ' + response.status + ').';
					return null;
				});
			})
			.catch(() => {
				reason = reason || 'No answer from the server - is the connection there?';
				return null;
			})
			.then(data => {
				this.busy = false;
				this.el.classList.remove('is-busy');
				if (!data) {
					this.notify(reason || 'That did not work. Please try again.', 'error');
					this.status('Not applied.', true);
				}
				return data;
			});
	};

	/** Takes what the server says the medium is now - nothing is pending any more - and shows the current mode from it. */
	Session.prototype.take = function (data) {
		this.data = data;
		this.base = (this.fixed ? data.formats || [] : data.ratios).map(r => Object.assign({}, r, {ratio: this.math.ratio(r.w, r.h), basis: r.basis || 'fill'}));
		if (!this.base.length) {
			this.status('There are no fixed formats.');
			return;
		}
		this.pending.clear();
		this.recompute();
		if (!this.ratioName || !this.ratios.some(r => r.name === this.ratioName)) {
			const square = this.ratios.find(r => r.name === '1:1');
			this.ratioName = (square || this.ratios[0] || {}).name || null;
		}
		this.load();
	};

	/**
	 * The crop of every declared ratio as it would be with what is pending:
	 * the server's, or - once something is pending - worked out here by the
	 * server's rules (sys.cropMath.resolve), from the crops set by hand and
	 * the points of interest.
	 */
	Session.prototype.recompute = function () {
		this.revision++;
		if (!this.pending.size) {
			this.ratios = this.base.map(r => Object.assign({}, r));
			return;
		}
		if (this.fixed) {
			// A fixed format's crop changes that format only: its own, or back to the one computed from the ratios.
			this.ratios = this.base.map(r => {
				const changed = this.pending.get(r.name);
				if (!changed) {
					return Object.assign({}, r);
				}
				return Object.assign({}, r, changed.auto ? r.auto : {ax: changed.ax, ay: changed.ay, scale: changed.scale, basis: changed.basis, source: 'manual'});
			});
			return;
		}
		const m = this.data.master;
		const manual = [];
		for (const r of this.base) {
			const changed = this.pending.get(r.name);
			const crop = changed ? (changed.auto ? null : changed) : (r.source === 'manual' ? r : null);
			if (crop) {
				manual.push({ratio: r.ratio, ax: crop.ax, ay: crop.ay, scale: crop.scale, basis: crop.basis});
			}
		}
		const declared = this.base.map(r => r.ratio);
		const pois = this.poisInMaster();
		this.ratios = this.base.map(r => {
			const crop = this.math.resolve(r.ratio, m.width, m.height, manual, pois, declared, m.basis);
			return Object.assign({}, r, {ax: crop.ax, ay: crop.ay, scale: crop.scale, basis: crop.basis, source: crop.source});
		});
	};

	/** The points of interest in fractions of the master, as Media\MasterGeometry::poisToMaster() gives them to the resolver. */
	Session.prototype.poisInMaster = function () {
		const m = this.data.master;
		const o = this.data.original;
		const g = this.math.master(o.width, o.height, {ratio: this.math.ratio(m.ratio), ax: m.ax, ay: m.ay, scale: m.scale, basis: m.basis}, m.angle, m.flip);
		const inMaster = [];
		for (const poi of this.data.pois || []) {
			if (poi.x === undefined || poi.y === undefined) {
				continue;
			}
			const halfW = (poi.w || 0) / 2;
			const halfH = (poi.h || 0) / 2;
			const xs = [];
			const ys = [];
			for (const [sx, sy] of [[-1, -1], [1, -1], [1, 1], [-1, 1]]) {
				const [x, y] = g.toMaster((poi.x + sx * halfW) * o.width, (poi.y + sy * halfH) * o.height);
				xs.push(x / g.frameWidth);
				ys.push(y / g.frameHeight);
			}
			const middleX = (Math.min(...xs) + Math.max(...xs)) / 2;
			const middleY = (Math.min(...ys) + Math.max(...ys)) / 2;
			if (middleX < 0 || middleX > 1 || middleY < 0 || middleY > 1) {
				continue;
			}
			const [left, right] = [Math.max(0, Math.min(...xs)), Math.min(1, Math.max(...xs))];
			const [top, bottom] = [Math.max(0, Math.min(...ys)), Math.min(1, Math.max(...ys))];
			inMaster.push(Object.assign({}, poi, {x: (left + right) / 2, y: (top + bottom) / 2, w: right - left, h: bottom - top}));
		}
		return inMaster;
	};

	/**
	 * A crop - or the master - as it goes to the server: within what its
	 * basis allows. Moving to the edge stops a hair beyond it (math.furthest;
	 * the master half a pixel), in 'fill' a scale of 0.9998 - the server
	 * takes no scale under 1.
	 */
	Session.prototype.toStore = function (w) {
		const crop = this.math.clamped({ax: w.ax, ay: w.ay, scale: w.scale, basis: w.basis});
		return {ax: crop.ax, ay: crop.ay, scale: crop.scale, basis: w.basis};
	};

	/** Crops mode: what was changed on the ratio on screen goes with the others still to store. */
	Session.prototype.stash = function () {
		if (this.mode !== 'crops' || !this.changed()) {
			return;
		}
		const w = this.work;
		this.pending.set(this.ratioName, this.toStore(w));
		this.recompute();
		this.saved = JSON.stringify(this.work);
	};

	/** Forgets everything not stored. */
	Session.prototype.discard = function () {
		this.pending.clear();
		this.recompute();
		this.load();
	};

	/** Starts editing the current mode's subject from the server's state. */
	Session.prototype.load = function () {
		const data = this.data;
		if (this.mode === 'master') {
			const m = data.master;
			this.work = {ratio: this.math.ratio(m.ratio), ax: m.ax, ay: m.ay, scale: m.scale, basis: m.basis || 'fill', angle: m.angle, flip: m.flip,
				look: Object.assign({}, m.look), auto: !m.manual, freeRatio: false};
			this.show(data.original.url);
		} else {
			const r = this.ratios.find(item => item.name === this.ratioName);
			this.work = {ratio: r.ratio, ax: r.ax, ay: r.ay, scale: r.scale, basis: r.basis || 'fill'};
			this.show(this.masterUrl());
		}
		this.saved = JSON.stringify(this.work);
		this.pois = (data.pois || []).map(poi => Object.assign({}, poi));
		if (this.poi >= this.pois.length) {
			this.poi = -1;
		}
		this.hideLevel();
		// Points are stored one by one, each answer comes through here: that tool stays in the hand.
		this.setTool(this.tool === 'poi' ? 'poi' : null);
		this.guard();
	};

	/**
	 * While something is not stored: leaving asks first - a link or a close
	 * button outside the editor (the tabs in the window's header too), or
	 * the page itself. Inside it, the tool for points of interest (leave()).
	 */
	Session.prototype.guard = function () {
		if (this.guarding) {
			return;
		}
		this.guarding = true;
		this.onLeave = event => {
			if (!this.el.isConnected) {
				this.unguard();
				return;
			}
			const out = event.target.closest && event.target.closest('a[href], [data-box-close], [data-lightbox-close]');
			if (!out || this.el.contains(out) || !this.dirty()) {
				return;
			}
			if (confirm('Discard the changes of this picture?')) {
				this.pending.clear();
				this.saved = JSON.stringify(this.work);
				return;
			}
			event.preventDefault();
			event.stopImmediatePropagation();
		};
		this.onUnload = event => {
			if (this.el.isConnected && this.dirty()) {
				event.preventDefault();
				event.returnValue = '';
			}
		};
		// Shift is the hand while a tool is in use: the pointer shows it.
		this.onKey = event => {
			if (event.key === 'Shift' || event.type === 'blur') {
				this.el.classList.toggle('is-hand', event.type === 'keydown');
			}
		};
		document.addEventListener('click', this.onLeave, true);
		window.addEventListener('beforeunload', this.onUnload);
		window.addEventListener('keydown', this.onKey);
		window.addEventListener('keyup', this.onKey);
		window.addEventListener('blur', this.onKey);
	};

	Session.prototype.unguard = function () {
		if (!this.guarding) {
			return;
		}
		this.guarding = false;
		document.removeEventListener('click', this.onLeave, true);
		window.removeEventListener('beforeunload', this.onUnload);
		window.removeEventListener('keydown', this.onKey);
		window.removeEventListener('keyup', this.onKey);
		window.removeEventListener('blur', this.onKey);
	};

	/** The master in a size that has the pixels for the stage. */
	Session.prototype.masterUrl = function () {
		const sources = this.data.master.sources;
		const need = Math.max(this.stageEl.clientWidth, this.stageEl.clientHeight) * (window.devicePixelRatio || 1);
		const pick = sources.find(s => s.width >= need) || sources[sources.length - 1];
		return pick.url;
	};

	Session.prototype.show = function (url) {
		if (this.under.getAttribute('src') !== url) {
			this.under.src = url;
			this.inner.src = url;
		}
	};

	// --------------------------------------------------------------- geometry

	Session.prototype.masterOf = function (work) {
		const o = this.data.original;
		return this.math.master(o.width, o.height, work, work.angle, work.flip);
	};

	/** Is this state allowed by its basis? 'fill': the edge of the picture stays outside the frame; 'fit': anything that shows some picture. */
	Session.prototype.allowed = function (work) {
		if (work.basis === 'fit') {
			return work.scale > 0.05 && work.ax >= 0 && work.ax <= 1 && work.ay >= 0 && work.ay <= 1;
		}
		return this.mode === 'master'
			? this.masterOf(work).insideOriginal(0.5)
			: this.math.inside(work, this.data.master.width, this.data.master.height, 1e-6);
	};

	/** Goes from the current state towards a wanted one, as far as the rule allows. */
	Session.prototype.move = function (wanted) {
		const from = this.work;
		const make = t => Object.assign({}, wanted, {
			ax: from.ax + (wanted.ax - from.ax) * t,
			ay: from.ay + (wanted.ay - from.ay) * t,
			scale: from.scale * Math.pow(wanted.scale / from.scale, t)
		});
		const work = this.allowed(from) ? this.math.furthest(make, work => this.allowed(work)) : this.settled(wanted);
		// Nothing moved (an axis the frame fills in 'fill'): nothing changed, nothing to apply.
		if (work.ax !== from.ax || work.ay !== from.ay || work.scale !== from.scale || work.ratio !== from.ratio) {
			this.work = work;
		}
		this.render();
	};

	/** The nearest allowed state to any state - after the ratio, the angle or the basis changed. */
	Session.prototype.settled = function (work) {
		if (this.allowed(work)) {
			return work;
		}
		// Most often only zoomed out too far for 'fill': the alignment as it is, at "fill in".
		const near = this.math.clamped(work);
		if (this.allowed(near)) {
			return near;
		}
		work = near;
		// Fill in, centred, is always allowed; from there as far towards the wanted state as it goes.
		const safe = Object.assign({}, work, {ax: 0.5, ay: 0.5, scale: Math.max(1, work.scale)});
		const make = t => Object.assign({}, work, {
			ax: safe.ax + (work.ax - safe.ax) * t,
			ay: safe.ay + (work.ay - safe.ay) * t,
			scale: safe.scale * Math.pow(work.scale / safe.scale, t)
		});
		return this.math.furthest(make, state => this.allowed(state));
	};

	/**
	 * What is on the stage: the frame, and the picture under it.
	 * k is screen pixels per pixel of the picture shown.
	 */
	Session.prototype.layout = function () {
		const {w: sw, h: sh} = this.stageSize();
		const r = this.math.value(this.work.ratio);
		const room = 1 - 2 * editor.margin;
		const fw = Math.max(1, Math.min(sw * room, sh * room * r));
		const fh = fw / r;
		const frame = {x: (sw - fw) / 2, y: (sh - fh) / 2, w: fw, h: fh};
		const centre = {x: sw / 2, y: sh / 2};
		if (this.mode === 'master') {
			const g = this.masterOf(this.work);
			const k = fw / g.frameWidth;
			// The picture's middle, seen from the point of it that sits in the frame's middle.
			let vx = g.originalWidth / 2 - g.centreX;
			const vy = g.originalHeight / 2 - g.centreY;
			if (g.flip) {
				vx = -vx;
			}
			const rad = g.angle * Math.PI / 180;
			return {frame: frame, k: k, g: g, refW: g.referenceWidth, refH: g.referenceHeight, tilted: Math.abs(g.angle % 90) > 1e-9,
				frameW: g.frameWidth / g.referenceWidth, frameH: g.frameHeight / g.referenceHeight,
				image: {w: g.originalWidth * k, h: g.originalHeight * k,
					cx: centre.x + (vx * Math.cos(rad) - vy * Math.sin(rad)) * k, cy: centre.y + (vx * Math.sin(rad) + vy * Math.cos(rad)) * k,
					angle: g.angle, flip: g.flip}};
		}
		const m = this.data.master;
		const rect = this.math.rect(this.work, m.width, m.height);
		const k = fw / (rect.w * m.width);
		return {frame: frame, k: k, refW: m.width, refH: m.height, tilted: false, frameW: rect.w, frameH: rect.h,
			image: {w: m.width * k, h: m.height * k, cx: frame.x + (0.5 - rect.x) * m.width * k, cy: frame.y + (0.5 - rect.y) * m.height * k, angle: 0, flip: false}};
	};

	// ----------------------------------------------------------------- render

	/**
	 * The stage's size. Read from the page only when nothing is known yet:
	 * the ResizeObserver keeps it, so the pointer's way across the stage
	 * never makes the browser lay out the page in between.
	 */
	Session.prototype.stageSize = function () {
		return this.size || {w: this.stageEl.clientWidth, h: this.stageEl.clientHeight};
	};

	/**
	 * Works out what is on the stage now (this.view - the tools go on from
	 * it at once) and draws it with the next frame: however many pointer
	 * events come in between, the page is written once per frame.
	 */
	Session.prototype.render = function () {
		if (!this.work || !this.stageSize().w) {
			return;
		}
		const view = this.view = this.layout();
		// Turning goes around the middle of the frame, wherever the stage puts it; zooming around where the cross was put.
		if ((this.tool === 'zoom' && !this.pivot) || this.tool === 'turn') {
			this.pivot = {x: view.frame.x + view.frame.w / 2, y: view.frame.y + view.frame.h / 2};
		}
		if (typeof window.requestAnimationFrame !== 'function') {
			this.draw();
		} else if (!this.drawing) {
			this.drawing = window.requestAnimationFrame(() => {
				this.drawing = 0;
				this.draw();
			});
		}
	};

	Session.prototype.draw = function () {
		const view = this.view;
		if (!view) {
			return;
		}
		const px = value => value.toFixed(2) + 'px';
		const f = view.frame;
		const set = (el, styles) => {
			// Only what changed: the same value written again still costs the browser a look at the element.
			const was = el.ceStyles || (el.ceStyles = {});
			for (const key in styles) {
				if (was[key] !== styles[key]) {
					el.style[key] = was[key] = styles[key];
				}
			}
		};
		set(this.frameEl, {left: px(f.x), top: px(f.y), width: px(f.w), height: px(f.h)});
		const look = this.mode === 'master' ? this.work.look : null;
		const filter = !look || (look.b === 100 && look.c === 100 && look.s === 100)
			? 'none'
			: 'brightness(' + look.b + '%) contrast(' + look.c + '%) saturate(' + look.s + '%)';
		// Size by width and height (sharp at any zoom), place by transform (moving needs no new layout).
		const place = (img, ox, oy) => {
			const i = view.image;
			set(img, {
				width: px(i.w), height: px(i.h),
				transform: 'translate(' + px(i.cx - i.w / 2 - ox) + ',' + px(i.cy - i.h / 2 - oy) + ') rotate(' + i.angle + 'deg) scaleX(' + (i.flip ? -1 : 1) + ')',
				filter: filter
			});
		};
		place(this.under, 0, 0);
		place(this.inner, f.x, f.y);
		this.renderPois();
		const pivot = this.el.querySelector('.cePivot');
		const around = this.tool === 'zoom' || this.tool === 'turn';
		pivot.hidden = !around;
		if (around && this.pivot) {
			set(pivot, {left: px(this.pivot.x), top: px(this.pivot.y)});
		}
		// Where the cross snaps to, while it is dragged.
		const snaps = this.el.querySelector('.ceSnaps');
		snaps.hidden = !(around && this.pivot && this.gesture && this.gesture.tool === 'pivot' && this.gesture.moved);
		if (!snaps.hidden) {
			this.snapPoints().forEach((point, index) => {
				const span = snaps.children[index];
				set(span, {left: px(point.x), top: px(point.y)});
				span.classList.toggle('is-snapped', Math.abs(point.x - this.pivot.x) < 0.01 && Math.abs(point.y - this.pivot.y) < 0.01);
			});
		}
		this.renderSide();
	};

	/** Is the subject on screen changed - the master, or the crop of the ratio on screen? */
	Session.prototype.changed = function () {
		return this.work !== null && JSON.stringify(this.work) !== this.saved;
	};

	/** Is anything not stored - on screen, or (crops) on another ratio? */
	Session.prototype.dirty = function () {
		return this.changed() || this.pending.size > 0;
	};

	/**
	 * Crops mode: the crop on screen is computed and untouched - the pin
	 * keeps it as it is, set by hand, so it stays when its neighbours change.
	 * A change like any other: applied with the others.
	 */
	Session.prototype.pinnable = function () {
		if (this.mode !== 'crops' || this.work === null || this.sliding || this.changed() || this.pending.has(this.ratioName)) {
			return false;
		}
		const shown = this.ratios.find(r => r.name === this.ratioName);
		return !!shown && shown.source !== 'manual';
	};

	Session.prototype.pin = function () {
		if (!this.pinnable()) {
			return;
		}
		const w = this.work;
		this.pending.set(this.ratioName, this.toStore(w));
		this.recompute();
		this.render();
	};

	/** How many crops (or the master: 1) apply would store. */
	Session.prototype.waiting = function () {
		if (this.mode === 'master') {
			return this.changed() ? 1 : 0;
		}
		return this.pending.size + (this.changed() && !this.pending.has(this.ratioName) ? 1 : 0);
	};

	Session.prototype.renderSide = function () {
		const el = this.el;
		const master = this.mode === 'master';
		const dirty = this.dirty();
		el.dataset.mode = this.mode;
		el.toggleAttribute('data-fixed', !!this.fixed);
		// What cannot be used is not shown.
		el.querySelectorAll('[data-ce-level],[data-ce-turn],[data-ce-quarter],[data-ce-flip],[data-ce-look],[data-ce-reset="geometry"],[data-ce-reset="look"]').forEach(b => { b.hidden = !master; });
		const resetCrops = el.querySelector('[data-ce-reset="crops"]');
		resetCrops.hidden = master;
		resetCrops.disabled = master || this.busy || !!this.sliding || !this.anyCrop();
		const flip = el.querySelector('[data-ce-flip]');
		flip.classList.toggle('selected', master && this.work.flip);
		flip.setAttribute('aria-pressed', String(master && this.work.flip));
		// The master's resets: below their group, there at the defaults too, but not to be pressed.
		if (master) {
			el.querySelector('[data-ce-reset="geometry"]').disabled = this.busy || this.geometryUnchanged();
			el.querySelector('[data-ce-reset="look"]').disabled = this.busy || this.lookUnchanged();
		}
		// Pan or zoom: one of them is in the hand unless another tool is.
		const inHand = this.tool || 'pan';
		el.querySelectorAll('[data-ce-tool]').forEach(b => {
			b.classList.toggle('selected', inHand === b.dataset.ceTool);
			b.setAttribute('aria-pressed', String(inHand === b.dataset.ceTool));
		});
		el.querySelector('[data-ce-turn]').classList.toggle('selected', this.tool === 'turn');
		el.querySelector('[data-ce-turn]').setAttribute('aria-pressed', String(this.tool === 'turn'));
		el.dataset.tool = this.tool || '';
		// One toggle, its icon the basis on screen: "fill in" or "fit in".
		const basis = el.querySelector('[data-ce-basis]');
		const fit = this.work.basis === 'fit';
		if (basis.dataset.shows !== this.work.basis) {
			basis.dataset.shows = this.work.basis;
			basis.title = fit
				? 'Fit in: background may show, scale 1 shows the whole picture - click for fill in'
				: 'Fill in: the edge of the picture stays outside, scale 1 fills the frame - click for fit in';
			basis.setAttribute('aria-pressed', String(fit));
		}
		// Apply and discard: for all that waits, shown only when something does.
		const save = el.querySelector('[data-ce-save]');
		const cancel = el.querySelector('[data-ce-cancel]');
		const waiting = this.waiting();
		save.hidden = cancel.hidden = !dirty;
		save.textContent = master || waiting < 2 ? 'Apply' : 'Apply (' + waiting + ')';
		save.title = master ? 'Apply the changes of the master' : (waiting === 1 ? 'Apply the changed crop' : 'Apply the ' + waiting + ' changed crops');
		save.disabled = cancel.disabled = this.busy || !!this.sliding;

		// The slider: every declared ratio at its place, in one line; they overlap, the middle (1:1) lies
		// furthest back, the ends always in front. Hovered or chosen comes to the front (CSS).
		const list = el.querySelector('.ceRatios');
		if (list.dataset.for !== this.data.version + this.mode + this.revision) {
			list.dataset.for = this.data.version + this.mode + this.revision;
			const square = this.ratios.find(r => r.name === '1:1');
			const middle = square ? square.position : 0.5;
			list.innerHTML = this.ratios.map(r => {
				const box = r.w >= r.h ? [1, r.h / r.w] : [r.w / r.h, 1];
				const name = this.fixed ? r.label || r.name : r.name;
				const title = this.fixed ? name + ' (' + r.width + ' x ' + r.height + ') - ' + this.sourceOf(r) : r.name + (master ? '' : ' - ' + this.sourceOf(r));
				return '<li style="--p:' + r.position.toFixed(5) + ';z-index:' + (1 + Math.round(Math.abs(r.position - middle) * 100)) + '"><span class="ceRatioName" aria-hidden="true">' + esc(name) + '</span>' +
					'<button type="button" data-ce-ratio="' + esc(r.name) + '" title="' + esc(title) + '" aria-label="' + esc(name) + '"' +
					(!master && r.source === 'manual' ? ' class="is-manual"' : '') + '><span style="width:' + (box[0] * 1.05).toFixed(3) + 'rem;height:' + (box[1] * 1.05).toFixed(3) + 'rem"></span></button></li>';
			}).join('');
			// Fixed formats: the declared ratios greyed behind them, to see where they lie - not to be picked.
			const ghosts = el.querySelector('.ceGhosts');
			ghosts.hidden = !this.fixed;
			ghosts.innerHTML = !this.fixed ? '' : this.data.ratios.map(r => {
				const box = r.w >= r.h ? [1, r.h / r.w] : [r.w / r.h, 1];
				return '<li style="--p:' + r.position.toFixed(5) + '"><span class="ceRatioName">' + esc(r.name) + '</span><span class="ceGhostBox"><span style="width:' + (box[0] * 1.05).toFixed(3) + 'rem;height:' + (box[1] * 1.05).toFixed(3) + 'rem"></span></span></li>';
			}).join('');
		}
		// Names that would cover each other: the one further in front is written, the other only on hover.
		// The list's length comes from the ResizeObserver - measured again only when it changed.
		const length = this.listLength || Math.max(list.clientHeight, list.clientWidth);
		if (length && list.dataset.laid !== list.dataset.for + length) {
			list.dataset.laid = list.dataset.for + length;
			const items = Array.from(list.querySelectorAll('li'));
			const written = [];
			items.map((li, index) => ({li: li, at: this.ratios[index].position * length, z: Number(li.style.zIndex)}))
				.sort((a, b) => b.z - a.z)
				.forEach(item => {
					const name = item.li.firstChild;
					const room = list.clientHeight >= list.clientWidth ? name.offsetHeight : name.offsetWidth + 6;
					const free = written.every(at => Math.abs(at - item.at) >= room);
					item.li.classList.toggle('is-quiet', !free);
					if (free) {
						written.push(item.at);
					}
				});
		}
		const shown = this.sliding ? this.nearest(this.sliding.position) : null;
		const current = shown ? shown.ratio : (master ? this.work.ratio : this.ratios.find(r => r.name === this.ratioName).ratio);
		list.querySelectorAll('[data-ce-ratio]').forEach(b => {
			b.classList.toggle('selected', b.dataset.ceRatio === current.name);
			b.parentNode.classList.toggle('is-selected', b.dataset.ceRatio === current.name);
		});
		el.querySelector('.ceKnob').style.setProperty('--p', (this.sliding ? this.sliding.position : this.positionOf(current)).toFixed(5));

		// The corner of the stage: back to automatic - or, for a computed crop, keep it.
		const auto = el.querySelector('[data-ce-auto]');
		const pin = el.querySelector('[data-ce-pin]');
		const picked = master ? null : this.ratios.find(r => r.name === this.ratioName);
		auto.hidden = master || !!this.sliding || (picked.source !== 'manual' && !this.changed());
		auto.title = auto.ariaLabel = 'Crop back to automatic: computed again';
		pin.hidden = !this.pinnable();
		auto.disabled = pin.disabled = this.busy;

		if (this.sliding && !master) {
			this.statusEl().textContent = this.fixed ? this.sliding.between : 'Between ' + this.sliding.between + ' - let go: ' + current.name;
		} else if (!this.message) {
			const m = this.data.master;
			const shown = picked;
			const hint = this.tool === 'zoom' ? ' - drag to zoom around the cross, click the cross to finish'
				: (this.tool === 'turn' ? ' - drag to turn around the cross, click the cross to finish'
				: (this.tool === 'poi' ? ' - ' + this.pois.length + (this.pois.length === 1 ? ' point' : ' points') + ' of interest: click to set one, drag to move it' : ''));
			const turned = master && this.work.angle ? ', turned ' + (Math.round(this.work.angle * 10) / 10) + ' degrees' : '';
			const others = master ? 0 : Array.from(this.pending.keys()).filter(name => name !== this.ratioName).length;
			const open = master ? '' : (others ? ' - ' + others + (others === 1 ? ' other crop' : ' other crops') + ' not applied yet' : '');
			const name = this.fixed ? (shown.label || shown.name) + ' ' + shown.width + ' x ' + shown.height : this.ratioName;
			this.statusEl().textContent = this.tool ? (master ? 'Master ' + this.work.ratio.name + turned : name) + hint : master
				? 'Master ' + this.work.ratio.name + turned + ' - ' + (this.work.auto && !dirty ? 'the whole picture' : 'cropped') + (dirty ? ' - not applied yet' : '')
				: name + ' - ' + (this.changed() || this.pending.has(this.ratioName) ? 'not applied yet' : this.sourceOf(shown)) + open
					+ (this.fixed ? this.enlarged(shown) : ' (master ' + m.width + ' x ' + m.height + ')');
		}
	};

	/**
	 * A message of the system (div.cms.message, as the server writes them) on
	 * the stage: what went wrong, in words. It goes by itself after a while
	 * (ui.js); null takes it away at once.
	 */
	Session.prototype.notify = function (text, type) {
		const stage = this.stageEl;
		if (!stage || typeof stage.querySelector !== 'function') {
			return;
		}
		let host = stage.querySelector('.ceMessages');
		if (!text) {
			if (host) {
				host.replaceChildren();
			}
			return;
		}
		if (!host) {
			host = document.createElement('div');
			host.className = 'cms message ceMessages';
			host.setAttribute('data-system-messages', '');
			host.setAttribute('role', 'status');
			host.setAttribute('aria-live', 'polite');
			stage.appendChild(host);
		}
		const row = document.createElement('div');
		row.className = type || 'info';
		const p = document.createElement('p');
		p.textContent = text;
		row.appendChild(p);
		host.replaceChildren(row);
	};

	/** Where a crop on the slider comes from, in words. */
	Session.prototype.sourceOf = function (r) {
		return this.fixed && r.source !== 'manual' ? 'computed from the crops of the ratios' : SOURCE[r.source];
	};

	/** A fixed format whose crop has fewer pixels than the format: says so - the picture is enlarged. */
	Session.prototype.enlarged = function (format) {
		const m = this.data.master;
		const work = this.ratioName === format.name && this.work ? this.work : format;
		const [w] = this.math.size(format.ratio, work.scale, m.width, m.height, work.basis);
		const pixels = Math.round(w * m.width);
		return pixels < format.width ? ' - only ' + pixels + ' px wide here, enlarged to ' + format.width : '';
	};

	Session.prototype.statusEl = function () {
		return this.el.querySelector('.ceStatus');
	};

	Session.prototype.status = function (text, isError) {
		this.message = text;
		const el = this.statusEl();
		el.textContent = text;
		el.classList.toggle('is-error', !!isError);
		clearTimeout(this.messageTimer);
		this.messageTimer = setTimeout(() => {
			this.message = null;
			el.classList.remove('is-error');
			if (this.work) {
				this.renderSide();
			}
		}, 6000);
	};

	/** Where a ratio sits on the slider: 0 the widest declared one, 1 the narrowest. */
	Session.prototype.positionOf = function (ratio) {
		const [first, last] = this.ends();
		return Math.min(1, Math.max(0, 1 - this.math.position(ratio, last, first)));
	};

	/** The widest and the narrowest declared ratio: the ends of the slider, in every mode. */
	Session.prototype.ends = function () {
		const declared = this.data.ratios;
		return [this.math.ratio(declared[0].w, declared[0].h), this.math.ratio(declared[declared.length - 1].w, declared[declared.length - 1].h)];
	};

	/** The ratio at a place on the slider - for the master, whose ratio is free. */
	Session.prototype.ratioAt = function (position) {
		const [widest, narrowest] = this.ends();
		const first = Math.log(this.math.value(widest));
		const last = Math.log(this.math.value(narrowest));
		return this.math.ratio(Math.round(Math.exp(first + (last - first) * position) * 1000), 1000);
	};

	/** The declared ratio nearest to a place on the slider. */
	Session.prototype.nearest = function (position) {
		return this.ratios.reduce((best, r) => Math.abs(r.position - position) < Math.abs(best.position - position) ? r : best);
	};

	/**
	 * The crop at any place of the slider (crops mode): between the two
	 * declared ratios around it, as the resolver interpolates
	 * (sys.cropMath.between). At a declared ratio it is that ratio's crop, so
	 * letting go there does not jump.
	 */
	Session.prototype.cropAt = function (position) {
		if (this.fixed) {
			// Fixed formats have nothing between them: the nearest one.
			const r = this.nearest(position);
			return {crop: {ratio: r.ratio, ax: r.ax, ay: r.ay, scale: r.scale, basis: r.basis || 'fill'}, between: r.label || r.name};
		}
		let below = this.ratios[0];
		let above = this.ratios[this.ratios.length - 1];
		for (const r of this.ratios) {
			if (r.position <= position && r.position >= below.position) {
				below = r;
			}
			if (r.position >= position && r.position <= above.position) {
				above = r;
			}
		}
		const m = this.data.master;
		const crop = r => ({ratio: r.ratio, ax: r.ax, ay: r.ay, scale: r.scale, basis: r.basis || 'fill'});
		const span = above.position - below.position;
		const at = span > 0 ? this.math.between(this.ratioAt(position), crop(below), crop(above), m.width, m.height, (position - below.position) / span) : crop(below);
		return {
			crop: {ratio: at.ratio, ax: at.ax, ay: at.ay, scale: at.scale, basis: at.basis},
			between: below.name + ' and ' + above.name
		};
	};

	/** Crops mode: the knob at any place, the frame and its crop following. */
	Session.prototype.slideTo = function (position) {
		const at = this.cropAt(position);
		this.sliding = {position: position, between: at.between};
		this.work = at.crop;
		this.render();
	};

	/**
	 * Crops mode: the knob glides to a declared ratio, the frame following
	 * on the way; there that ratio is opened.
	 */
	Session.prototype.glide = function (target) {
		const from = this.sliding ? this.sliding.position : this.positionOf(this.ratios.find(r => r.name === this.ratioName).ratio);
		const done = () => {
			this.sliding = null;
			this.ratioName = target.name;
			this.load();
		};
		const distance = Math.abs(target.position - from);
		if (typeof window.requestAnimationFrame !== 'function' || distance < 1e-4 || this.fixed) {
			done();
			return;
		}
		const duration = 120 + 260 * Math.min(1, distance);
		const start = window.performance.now();
		const step = now => {
			const t = Math.min(1, (now - start) / duration);
			const eased = 1 - Math.pow(1 - t, 3);
			if (t >= 1) {
				done();
				return;
			}
			this.slideTo(from + (target.position - from) * eased);
			window.requestAnimationFrame(step);
		};
		this.slideTo(from);
		window.requestAnimationFrame(step);
	};

	// ----------------------------------------------------------------- tools

	/**
	 * Width and height of the frame as fractions of what it is cut from (the
	 * box around the turned original, or the master) - what turns the
	 * frame's middle into its alignment and back.
	 */
	Session.prototype.lengths = function (work) {
		if (this.mode === 'master') {
			const g = this.masterOf(work);
			return [g.frameWidth / g.referenceWidth, g.frameHeight / g.referenceHeight];
		}
		const m = this.data.master;
		return this.math.size(work.ratio, work.scale, m.width, m.height, work.basis);
	};

	/** The frame's middle, fractions of what it is cut from. */
	Session.prototype.centreOf = function (work) {
		const [w, h] = this.lengths(work);
		return [this.math.centreOf(work.ax, w, work.basis), this.math.centreOf(work.ay, h, work.basis)];
	};

	/**
	 * The state with its frame's middle at a place - the moving and zooming
	 * work with the middle, what is kept is the alignment. In 'fill', along
	 * an axis the frame fills completely, the alignment stays what it was.
	 */
	Session.prototype.centredAt = function (work, cx, cy) {
		const [w, h] = this.lengths(work);
		return Object.assign({}, work, {ax: this.math.alignOf(cx, w, work.ax, work.basis), ay: this.math.alignOf(cy, h, work.ay, work.basis)});
	};

	/** Moves the picture by screen pixels - the frame's middle moves the other way in the picture. */
	Session.prototype.pan = function (dx, dy) {
		const v = this.view;
		const [cx, cy] = this.centreOf(this.work);
		this.move(this.centredAt(Object.assign({}, this.work, {auto: false}), cx - dx / (v.k * v.refW), cy - dy / (v.k * v.refH)));
	};

	/**
	 * Ctrl + wheel: one notch is one step of editor.step on the scale, to the
	 * next multiple of it (1.37 -> 1.4 -> 1.5). A touchpad's pinch comes as
	 * many small turns with Ctrl held: they add up to a notch first.
	 */
	Session.prototype.zoomStep = function (event, at) {
		const delta = event.deltaY * (event.deltaMode === 1 ? 40 : (event.deltaMode === 2 ? 800 : 1));
		this.wheelSum = Math.abs(delta) >= 50 ? delta : (this.wheelSum || 0) + delta;
		if (Math.abs(this.wheelSum) < 50) {
			return;
		}
		const up = this.wheelSum < 0;
		this.wheelSum = 0;
		const step = editor.step;
		const now = this.work.scale / step;
		const next = (up ? Math.floor(now + 1e-6) + 1 : Math.ceil(now - 1e-6) - 1) * step;
		if (next >= step) {
			this.zoom(next / this.work.scale, at.x, at.y);
		}
	};

	/** Zooms by a factor; the point of the picture under (x, y) on the stage stays where it is. */
	Session.prototype.zoom = function (factor, x, y) {
		const v = this.view;
		const qx = x - (v.frame.x + v.frame.w / 2);
		const qy = y - (v.frame.y + v.frame.h / 2);
		const scale = Math.min(100, Math.max(0.05, this.work.scale * factor));
		const real = scale / this.work.scale;
		const [cx, cy] = this.centreOf(this.work);
		this.move(this.centredAt(Object.assign({}, this.work, {scale: scale, auto: false}),
			cx + qx / v.k * (1 - 1 / real) / v.refW,
			cy + qy / v.k * (1 - 1 / real) / v.refH));
	};

	/**
	 * The master turned or mirrored. The place of the picture under the fix
	 * point (or under the frame's middle) stays there, the frame keeps its
	 * pixels - as far as the rule about the picture's edge allows.
	 */
	Session.prototype.turn = function (angle, flip, pivot) {
		const work = this.work;
		const o = this.data.original;
		if (work.auto) {
			// Nothing cropped yet: the frame of the largest area inside the turned picture, its ratio following the angle.
			const largest = this.math.largest(o.width, o.height, angle, flip);
			this.work = Object.assign({}, work, {ratio: largest.crop.ratio, ax: 0.5, ay: 0.5, scale: 1, basis: 'fill', angle: angle, flip: flip});
			this.render();
			return;
		}
		const view = this.view = this.layout();
		const before = view.g;
		// The fix point in the master's pixels, and the place of the original that lies there.
		const mx = pivot ? (pivot.x - view.frame.x) / view.k : before.frameWidth / 2;
		const my = pivot ? (pivot.y - view.frame.y) / view.k : before.frameHeight / 2;
		const held = before.toOriginal(mx, my);
		const next = Object.assign({}, work, {angle: angle, flip: flip});
		const probe = this.masterOf(Object.assign({}, next, {ax: 0.5, ay: 0.5, scale: 1}));
		const rad = angle * Math.PI / 180;
		const cos = Math.cos(rad);
		const sin = Math.sin(rad);
		// From the fix point back to the frame's middle, in the turned picture; then into the original.
		const fx = mx - before.frameWidth / 2;
		const fy = my - before.frameHeight / 2;
		let ux = fx * cos + fy * sin;
		const uy = -fx * sin + fy * cos;
		if (flip) {
			ux = -ux;
		}
		let dx = held[0] - ux - o.width / 2;
		const dy = held[1] - uy - o.height / 2;
		if (flip) {
			dx = -dx;
		}
		// A frame at scale 1 stays there: "fill in", the largest of its ratio inside the turned picture, or "fit in". Any other keeps its pixels.
		next.scale = Math.abs(work.scale - 1) < 1e-6 ? 1 : probe.scaleFor(before.frameHeight);
		this.work = this.settled(this.centredAt(next, 0.5 + (dx * cos - dy * sin) / probe.referenceWidth, 0.5 + (dx * sin + dy * cos) / probe.referenceHeight));
		this.render();
	};

	// ------------------------------------------------------ points of interest

	/** The master as it lies in the original for what is on the stage - to get from the stage to the picture and back. */
	Session.prototype.shown = function () {
		const view = this.view;
		if (this.mode === 'master') {
			return {g: view.g, left: view.frame.x, top: view.frame.y, k: view.k};
		}
		const m = this.data.master;
		const o = this.data.original;
		const g = this.math.master(o.width, o.height, {ratio: this.math.ratio(m.ratio), ax: m.ax, ay: m.ay, scale: m.scale, basis: m.basis}, m.angle, m.flip);
		return {g: g, left: view.image.cx - view.image.w / 2, top: view.image.cy - view.image.h / 2, k: view.k};
	};

	/** Where a point of the original (fractions) is on the stage. */
	Session.prototype.toStage = function (poi) {
		const shown = this.shown();
		const at = shown.g.toMaster(poi.x * shown.g.originalWidth, poi.y * shown.g.originalHeight);
		return {x: shown.left + at[0] * shown.k, y: shown.top + at[1] * shown.k};
	};

	/** The place of the original (fractions, kept inside it) under a place on the stage. */
	Session.prototype.fromStage = function (x, y) {
		const shown = this.shown();
		const at = shown.g.toOriginal((x - shown.left) / shown.k, (y - shown.top) / shown.k);
		const unit = value => Math.min(1, Math.max(0, value));
		return {x: unit(at[0] / shown.g.originalWidth), y: unit(at[1] / shown.g.originalHeight)};
	};

	Session.prototype.renderPois = function () {
		const layer = this.el.querySelector('.cePois');
		const active = this.tool === 'poi';
		layer.classList.toggle('is-active', active);
		if (!editor.pois || (!this.pois.length && !layer.firstChild && this.el.querySelector('.cePoiBar').hidden)) {
			return;     // nothing to draw, nothing to clear: no work in every frame of a drag
		}
		layer.innerHTML = this.pois.map((poi, index) => {
			const at = this.toStage(poi);
			const weight = poi.weight === undefined ? 1 : poi.weight;
			return '<button type="button" class="cePoi' + (active && index === this.poi ? ' is-selected' : '') + '" data-ce-point="' + index + '" style="left:' + at.x.toFixed(1) + 'px;top:' + at.y.toFixed(1) + 'px;--w:' + Math.min(9, weight) + '"' +
				' title="Point of interest, weight ' + weight + '" aria-label="Point of interest ' + (index + 1) + '"' + (active ? '' : ' tabindex="-1"') + '></button>';
		}).join('');
		const bar = this.el.querySelector('.cePoiBar');
		const chosen = active ? this.pois[this.poi] : null;
		bar.hidden = !chosen;
		if (chosen) {
			bar.querySelector('output').textContent = chosen.weight === undefined ? 1 : chosen.weight;
		}
	};

	/** Stores the points as they are now; the answer brings every crop that follows them. */
	Session.prototype.savePois = function (dropCrops) {
		const params = {pois: JSON.stringify(this.pois)};
		if (dropCrops) {
			params.drop_crops = 1;
		}
		return this.request('savePois', params).then(data => {
			if (data) {
				this.stored = true;
				this.take(data);
			}
		});
	};

	Session.prototype.addPoi = function (x, y) {
		// Crops set by hand go before points. With the first point: ask whether they should step aside.
		const byHand = this.ratios.filter(r => r.source === 'manual').map(r => r.name);
		const drop = !this.pois.length && byHand.length > 0
			&& confirm('Crops set by hand stay as they are (' + byHand.join(', ') + ') - points of interest only move the others.\n\nRemove the crops set by hand, so the points decide everywhere?');
		this.pois.push(Object.assign(this.fromStage(x, y), {source: 'manual'}));
		this.poi = this.pois.length - 1;
		this.savePois(drop);
	};

	/** Takes a tool up or puts it away. The fix point starts in the frame's middle. */
	Session.prototype.setTool = function (tool) {
		this.tool = tool;
		this.pivot = null;
		this.gesture = null;
		this.arrow(null);
		this.render();
	};

	/** The points of the frame the fix point snaps to: its corners, the middles of its edges, its middle. */
	Session.prototype.snapPoints = function () {
		const f = this.view.frame;
		const points = [];
		for (const x of [f.x, f.x + f.w / 2, f.x + f.w]) {
			for (const y of [f.y, f.y + f.h / 2, f.y + f.h]) {
				points.push({x: x, y: y});
			}
		}
		return points;
	};

	/** Puts the fix point at a place on the stage - on a point of the frame when it is near one. */
	Session.prototype.movePivot = function (x, y) {
		let best = null;
		for (const point of this.snapPoints()) {
			const distance = Math.hypot(x - point.x, y - point.y);
			if (distance <= editor.snap && (!best || distance < best.distance)) {
				best = {x: point.x, y: point.y, distance: distance};
			}
		}
		this.pivot = best ? {x: best.x, y: best.y} : {x: x, y: y};
		this.render();
	};

	/**
	 * The zoom's pointer at a place on the stage: the arrow away from the fix
	 * point, in steps of 45 degrees; none on the fix point itself (null: none at all).
	 */
	Session.prototype.arrow = function (at) {
		let step = -1;
		if (at && this.tool === 'zoom' && this.pivot) {
			const dx = at.x - this.pivot.x;
			const dy = at.y - this.pivot.y;
			step = Math.hypot(dx, dy) < 6 ? -1 : (Math.round(Math.atan2(dy, dx) / (Math.PI / 4)) + 8) % 8;
		}
		const style = this.stageEl && this.stageEl.style;
		if (style && step !== this.arrowStep) {
			this.arrowStep = step;
			if (step < 0) {
				style.removeProperty('--ceArrow');
			} else {
				style.setProperty('--ceArrow', ARROWS[step]);
			}
		}
	};

	/**
	 * A drag with a tool: from where it started (the state then, the place
	 * grabbed) to where the pointer is now, around the fix point.
	 */
	Session.prototype.dragTool = function (gesture, now) {
		const pivot = this.pivot;
		const from = {x: gesture.start.x - pivot.x, y: gesture.start.y - pivot.y};
		const to = {x: now.x - pivot.x, y: now.y - pivot.y};
		const reach = Math.hypot(from.x, from.y);
		if (reach < 6) {
			return;     // grabbed on the fix point itself: no direction, no distance to go by
		}
		this.work = gesture.work;
		this.view = this.layout();
		if (gesture.tool === 'zoom') {
			// As far from the fix point as the pointer is now: the grabbed place stays under it.
			this.zoom(Math.max(0.02, Math.hypot(to.x, to.y) / reach), pivot.x, pivot.y);
		} else {
			const delta = (Math.atan2(to.y, to.x) - Math.atan2(from.y, from.x)) * 180 / Math.PI;
			this.turn(degrees(gesture.work.angle + delta), gesture.work.flip, pivot);
		}
	};

	/**
	 * From 'fill' to 'fit' and back. The frame starts afresh - centred at
	 * scale 1, "fill in" or "fit in" - so what the mode means shows at once.
	 */
	Session.prototype.switchBasis = function () {
		let next = Object.assign({}, this.work, {ax: 0.5, ay: 0.5, scale: 1, basis: this.work.basis === 'fit' ? 'fill' : 'fit'});
		if (this.mode === 'master') {
			// Without a crop the master is 'fill' - 'fit' is a crop. Back again, it is automatic again if nothing else changed.
			const back = Object.assign({}, next, {auto: true});
			next = next.basis === 'fill' && JSON.stringify(back) === this.saved ? back : Object.assign(next, {auto: false});
		}
		this.work = this.settled(next);
		this.render();
	};

	Session.prototype.setRatio = function (ratio, free) {
		this.work = this.unchangedMaster(this.settled(Object.assign({}, this.work, {ratio: ratio, freeRatio: !!free, auto: false})));
		this.render();
	};

	/** The master as stored, automatic again, when a change ended where it started. */
	Session.prototype.unchangedMaster = function (work) {
		if (this.mode !== 'master' || !this.saved) {
			return work;
		}
		const stored = JSON.parse(this.saved);
		const same = this.math.compare(work.ratio, this.math.ratio(stored.ratio.w, stored.ratio.h)) === 0
			&& Math.abs(work.ax - stored.ax) < 1e-6 && Math.abs(work.ay - stored.ay) < 1e-6 && Math.abs(work.scale - stored.scale) < 1e-6
			&& work.basis === stored.basis && work.angle === stored.angle && work.flip === stored.flip
			&& JSON.stringify(work.look) === JSON.stringify(stored.look);
		return same ? stored : work;
	};

	Session.prototype.hideLevel = function () {
		this.el.querySelector('.ceLevel').hidden = true;
		this.el.querySelectorAll('[data-ce-level]').forEach(b => b.classList.remove('selected'));
	};

	// ------------------------------------------------------------------ store

	/** Stores everything changed: the master, or every crop changed in crops mode - one request. */
	Session.prototype.save = function () {
		if (!this.dirty() || this.busy) {
			return;
		}
		let work = this.work;
		let action = 'saveRatioCrops';
		let params = {};
		if (this.mode === 'crops') {
			this.stash();
			const key = this.fixed ? 'format' : 'ratio';
			action = this.fixed ? 'saveFormatCrops' : 'saveRatioCrops';
			params = {crops: JSON.stringify(Array.from(this.pending, ([name, crop]) => crop.auto
				? {[key]: name, auto: true}
				: {[key]: name, ax: crop.ax, ay: crop.ay, scale: crop.scale, basis: crop.basis}))};
		} else {
			action = 'saveMasterCrop';
			if (work.freeRatio) {
				// A freely set ratio is the fraction of the frame's whole pixels in the original.
				const g = this.masterOf(work);
				const ratio = this.math.ratio(g.width, g.height);
				const scale = this.masterOf(Object.assign({}, work, {ratio: ratio, scale: 1})).scaleFor(g.height);
				// The same frame, its middle where it was: the alignment follows the frame's new, whole-pixel length.
				work = this.centredAt(Object.assign({}, work, {ratio: ratio, scale: scale}), g.cx, g.cy);
			}
			params = Object.assign({ratio: work.ratio.name}, this.toStore(work));
			Object.assign(params, {brightness: work.look.b, contrast: work.look.c, saturation: work.look.s, angle: work.angle, flip: work.flip ? 1 : 0});
			if (work.auto) {
				Object.assign(params, {auto: 1});
			}
		}
		this.request(action, params).then(data => {
			if (!data) {
				this.renderSide();
				return;
			}
			this.stored = true;
			this.take(data);
			if (data.clipped && data.clipped.length) {
				this.status('Moved to stay inside the new master: ' + data.clipped.join(', ') + '. Please check ' + (data.clipped.length > 1 ? 'these crops.' : 'this crop.'));
			}
		});
	};

	/** Discard: everything not stored goes. */
	Session.prototype.cancel = function () {
		this.discard();
	};

	/** The master's geometry as it is without anything done: the whole picture, not turned, not mirrored. */
	Session.prototype.geometryUnchanged = function () {
		const w = this.work;
		return w.auto && !w.angle && !w.flip && w.basis === 'fill';
	};

	Session.prototype.lookUnchanged = function () {
		const look = this.work.look;
		return look.b === 100 && look.c === 100 && look.s === 100;
	};

	/**
	 * The master's geometry back: automatic - the whole picture -, not turned,
	 * not mirrored; its look kept. A change like any other, applied with the others.
	 */
	Session.prototype.resetGeometry = function () {
		const o = this.data.original;
		this.work = this.unchangedMaster(Object.assign({}, this.work, {ratio: this.math.ratio(o.width, o.height), ax: 0.5, ay: 0.5, scale: 1, basis: 'fill',
			angle: 0, flip: false, auto: true, freeRatio: false}));
		this.render();
	};

	/** The master's look back to unchanged; its geometry kept. */
	Session.prototype.resetLook = function () {
		this.hideLevel();
		this.work = this.unchangedMaster(Object.assign({}, this.work, {look: {b: 100, c: 100, s: 100}}));
		this.render();
	};

	/** The crop of the ratio on screen back to automatic: computed again - a change like any other. */
	Session.prototype.auto = function () {
		if (this.mode !== 'crops') {
			return;
		}
		const stored = this.base.find(r => r.name === this.ratioName);
		if (stored.source === 'manual') {
			this.pending.set(this.ratioName, {auto: true});
		} else {
			this.pending.delete(this.ratioName);
		}
		this.recompute();
		this.load();
	};

	/** Crops mode: is any crop set by hand - stored and not reset, waiting, or on screen? */
	Session.prototype.anyCrop = function () {
		return this.changed() || this.ratios.some(r => r.source === 'manual');
	};

	/** Every crop back to automatic: computed again - changes like any other, applied with the others. */
	Session.prototype.resetCrops = function () {
		if (this.mode !== 'crops') {
			return;
		}
		this.pending.clear();
		for (const stored of this.base) {
			if (stored.source === 'manual') {
				this.pending.set(stored.name, {auto: true});
			}
		}
		this.recompute();
		this.load();
	};

	/** Leaves the mode: asks when there is something to lose, and forgets it. */
	Session.prototype.leave = function () {
		if (!this.dirty()) {
			return true;
		}
		if (!confirm('Discard the changes that are not applied yet?')) {
			return false;
		}
		this.pending.clear();
		this.recompute();
		return true;
	};

	// ------------------------------------------------------------------ input

	Session.prototype.bind = function () {
		const el = this.el;
		const stage = this.stageEl;
		const on = (selector, handler) => el.querySelectorAll(selector).forEach(b => b.addEventListener('click', event => {
			if (!this.work || this.busy || this.sliding) {
				return;
			}
			handler(event.currentTarget, event);
		}));

		el.querySelector('[data-ce-more]').addEventListener('click', event => {
			event.currentTarget.setAttribute('aria-expanded', String(el.classList.toggle('is-open')));
		});
		on('[data-ce-save]', () => this.save());
		on('[data-ce-cancel]', () => this.cancel());
		on('[data-ce-auto]', () => this.auto());
		on('[data-ce-pin]', () => this.pin());
		on('[data-ce-reset]', button => {
			const reset = button.dataset.ceReset;
			reset === 'look' ? this.resetLook() : (reset === 'crops' ? this.resetCrops() : this.resetGeometry());
		});
		on('[data-ce-tool]', button => {
			// Pan and zoom: a choice, not a toggle. Points of interest: taken up and put away.
			const picked = button.dataset.ceTool;
			const tool = picked === 'pan' ? null : (picked === 'poi' && this.tool === 'poi' ? null : picked);
			if (tool === this.tool) {
				return;
			}
			if (tool === 'poi' && this.dirty()) {
				// Points are stored at once and redraw everything: what is not applied yet would be lost without a word.
				if (!this.leave()) {
					return;
				}
				this.tool = 'poi';
				this.load();
				return;
			}
			this.setTool(tool);
		});
		on('[data-ce-poi]', button => {
			const poi = this.pois[this.poi];
			if (!poi) {
				return;
			}
			if (button.dataset.cePoi === 'remove') {
				this.pois.splice(this.poi, 1);
				this.poi = -1;
			} else {
				const weight = (poi.weight === undefined ? 1 : poi.weight) + (button.dataset.cePoi === 'heavier' ? 1 : -1);
				poi.weight = Math.min(9, Math.max(1, weight));
			}
			this.savePois(false);
		});
		// The tool for turning freely: taken up and put away, nothing turns yet.
		on('[data-ce-turn]', () => this.setTool(this.tool === 'turn' ? null : 'turn'));
		// On to the next right angle clockwise - from 30.4 degrees to 90, not to 120.4.
		on('[data-ce-quarter]', () => {
			const quarter = degrees((Math.floor(this.work.angle / 90 + 1e-9) + 1) * 90);
			this.turn(quarter, this.work.flip, this.tool === 'turn' ? this.pivot : null);
		});
		on('[data-ce-flip]', () => this.turn(this.work.angle, !this.work.flip, this.pivot));
		// The basis: fill in or fit in, the frame starts afresh.
		on('[data-ce-basis]', () => this.switchBasis());
		// After a switch the icon shows the new basis; what hovering shows comes back once the pointer has left.
		const basisButton = el.querySelector('[data-ce-basis]');
		basisButton.addEventListener('click', () => basisButton.classList.add('is-switched'));
		basisButton.addEventListener('pointerleave', () => basisButton.classList.remove('is-switched'));
		basisButton.addEventListener('blur', () => basisButton.classList.remove('is-switched'));

		// Brightness, contrast, saturation: one slider, beside the tool that opened it (left of the tools).
		const level = el.querySelector('.ceLevel');
		const range = level.querySelector('input');
		on('[data-ce-level]', button => {
			const key = button.dataset.ceLevel;
			if (!level.hidden && level.dataset.key === key) {
				this.hideLevel();
				return;
			}
			this.hideLevel();
			button.classList.add('selected');
			level.dataset.key = key;
			level.hidden = false;
			level.style.top = button.offsetTop + 'px';
			range.value = this.work.look[key];
			level.querySelector('output').textContent = this.work.look[key] - 100;
		});
		range.addEventListener('input', () => {
			if (this.mode !== 'master' || !this.work) {
				return;
			}
			this.work = Object.assign({}, this.work, {look: Object.assign({}, this.work.look, {[level.dataset.key]: Number(range.value)})});
			level.querySelector('output').textContent = Number(range.value) - 100;
			this.render();
		});
		const unchanged = () => {
			range.value = 100;
			range.dispatchEvent(new Event('input'));
		};
		range.addEventListener('dblclick', unchanged);
		on('[data-ce-level-reset]', unchanged);

		// The slider of ratios: a click picks a declared one - in crops mode the knob glides there, the
		// frame following. The knob can be dragged too: the master's stays where it is let go, in crops
		// mode it glides to the nearest declared ratio.
		el.querySelector('.ceRatios').addEventListener('click', event => {
			const button = event.target.closest('[data-ce-ratio]');
			if (!button || !this.work || this.busy || this.sliding) {
				return;
			}
			const item = this.ratios.find(r => r.name === button.dataset.ceRatio);
			if (this.mode === 'master') {
				this.setRatio(item.ratio, false);
			} else if (item.name !== this.ratioName) {
				this.stash();
				this.glide(item);
			}
		});
		const track = el.querySelector('.ceTrack');
		const along = event => {
			const box = track.getBoundingClientRect();
			// Down on a wide screen, across on a phone.
			return Math.min(1, Math.max(0, box.width > box.height ? (event.clientX - box.left) / box.width : (event.clientY - box.top) / box.height));
		};
		const slide = event => {
			if (this.mode === 'master') {
				// A press is no change: the master's ratio follows only once the knob is moved.
				if (!track.moved && Math.hypot(event.clientX - track.from.x, event.clientY - track.from.y) < 4) {
					return;
				}
				track.moved = true;
				this.setRatio(this.ratioAt(along(event)), true);
			} else {
				this.slideTo(along(event));
			}
		};
		track.addEventListener('pointerdown', event => {
			if (!this.work || this.busy || this.sliding || event.button !== 0) {
				return;
			}
			event.preventDefault();
			this.stash();
			track.setPointerCapture(event.pointerId);
			track.held = true;
			track.moved = false;
			track.from = {x: event.clientX, y: event.clientY};
			slide(event);
		});
		track.addEventListener('pointermove', event => {
			if (track.held) {
				slide(event);
			}
		});
		const stop = () => {
			if (!track.held) {
				return;
			}
			track.held = false;
			if (this.mode !== 'master' && this.sliding) {
				this.glide(this.nearest(this.sliding.position));
			}
		};
		track.addEventListener('pointerup', stop);
		track.addEventListener('pointercancel', stop);

		// The stage: one pointer moves the picture, two zoom it; the wheel zooms too.
		const local = event => {
			const box = stage.getBoundingClientRect();
			return {x: event.clientX - box.left, y: event.clientY - box.top};
		};
		stage.addEventListener('pointerdown', event => {
			const onPivot = event.target.closest('.cePivot');
			const onPoint = this.tool === 'poi' ? event.target.closest('.cePoi') : null;
			if (!this.work || this.busy || this.sliding || (!onPivot && !onPoint && event.target.closest('button,.cePoiBar')) || (event.pointerType === 'mouse' && event.button !== 0)) {
				return;
			}
			event.preventDefault();
			stage.setPointerCapture(event.pointerId);
			const at = local(event);
			this.pointers.set(event.pointerId, at);
			stage.classList.add('is-moving');
			if (this.pointers.size > 1) {
				this.gesture = null;         // a second finger: two-finger handling takes over
			} else if (onPivot) {
				this.gesture = {tool: 'pivot', start: at, moved: false};
			} else if (onPoint) {
				this.gesture = {tool: 'point', index: Number(onPoint.dataset.cePoint), start: at, moved: false};
			} else if (this.tool === 'poi') {
				this.gesture = {tool: 'place', start: at, moved: false};
			} else if (this.tool && this.pivot) {
				this.gesture = {tool: this.tool, start: at, work: this.work};
			} else {
				this.gesture = null;
			}
		});
		stage.addEventListener('pointermove', event => {
			if (this.tool === 'zoom') {
				this.arrow(local(event));
			}
			const last = this.pointers.get(event.pointerId);
			if (!last) {
				return;
			}
			const now = local(event);
			const gesture = this.pointers.size === 1 ? this.gesture : null;
			if (gesture && gesture.tool === 'pivot') {
				// The fix point of turning stays in the middle of the frame; that of zooming goes where it is put.
				if (gesture.moved || Math.hypot(now.x - gesture.start.x, now.y - gesture.start.y) > 3) {
					gesture.moved = true;
					if (this.tool !== 'turn') {
						this.movePivot(now.x, now.y);
					}
				}
			} else if (gesture && (gesture.tool === 'zoom' || gesture.tool === 'turn') && event.shiftKey) {
				// Shift: the hand. The tool goes on from here once it is let go.
				this.pan(now.x - last.x, now.y - last.y);
				gesture.start = now;
				gesture.work = this.work;
			} else if (gesture && gesture.tool === 'point') {
				if (gesture.moved || Math.hypot(now.x - gesture.start.x, now.y - gesture.start.y) > 3) {
					gesture.moved = true;
					Object.assign(this.pois[gesture.index], this.fromStage(now.x, now.y));
					this.poi = gesture.index;
					this.renderPois();
				}
			} else if (gesture && gesture.tool === 'place') {
				// A drag on the bare picture moves it, as always; only a click sets a point.
				if (Math.hypot(now.x - gesture.start.x, now.y - gesture.start.y) > 4) {
					gesture.moved = true;
					this.pan(now.x - last.x, now.y - last.y);
				}
			} else if (gesture) {
				this.dragTool(gesture, now);
			} else if (this.pointers.size === 1) {
				this.pan(now.x - last.x, now.y - last.y);
			} else if (this.pointers.size === 2) {
				const other = Array.from(this.pointers.entries()).find(([id]) => id !== event.pointerId)[1];
				const before = Math.hypot(last.x - other.x, last.y - other.y);
				const after = Math.hypot(now.x - other.x, now.y - other.y);
				const middle = {x: (now.x + other.x) / 2, y: (now.y + other.y) / 2};
				this.pan((now.x - last.x) / 2, (now.y - last.y) / 2);
				if (before > 0 && after > 0) {
					this.zoom(after / before, middle.x, middle.y);
				}
				if (this.tool === 'turn' && this.mode === 'master') {
					const delta = (Math.atan2(now.y - other.y, now.x - other.x) - Math.atan2(last.y - other.y, last.x - other.x)) * 180 / Math.PI;
					this.turn(degrees(this.work.angle + delta), this.work.flip, middle);
				}
			}
			this.pointers.set(event.pointerId, now);
		});
		const release = event => {
			if (!this.pointers.has(event.pointerId)) {
				return;
			}
			this.pointers.delete(event.pointerId);
			if (!this.pointers.size) {
				stage.classList.remove('is-moving');
				// A click on the fix point, without moving it, puts the tool away.
				const gesture = this.gesture;
				const up = event.type === 'pointerup';
				this.gesture = null;
				if (gesture && gesture.tool === 'pivot' && !gesture.moved && up) {
					this.setTool(null);
				} else if (gesture && gesture.tool === 'pivot') {
					this.render();      // the snap points go
				} else if (gesture && gesture.tool === 'point' && up) {
					if (gesture.moved) {
						this.savePois(false);
					} else {
						this.poi = this.poi === gesture.index ? -1 : gesture.index;
						this.renderPois();
					}
				} else if (gesture && gesture.tool === 'place' && !gesture.moved && up) {
					const at = local(event);
					this.addPoi(at.x, at.y);
				}
			}
		};
		stage.addEventListener('pointerup', release);
		stage.addEventListener('pointercancel', release);
		stage.addEventListener('wheel', event => {
			if (!this.work || this.busy || this.sliding) {
				return;
			}
			event.preventDefault();
			const at = local(event);
			if (event.ctrlKey || event.metaKey) {
				this.zoomStep(event, at);
				return;
			}
			this.zoom(Math.exp(-Math.max(-100, Math.min(100, event.deltaY)) * 0.002), at.x, at.y);
		}, {passive: false});

		el.addEventListener('keydown', event => {
			if (event.key === 'Enter' && this.dirty() && !event.target.closest('input')) {
				event.preventDefault();
				this.save();
			} else if (event.key === 'Escape' && this.tool) {
				event.preventDefault();
				event.stopPropagation();
				this.setTool(null);
			}
		});

		if (typeof ResizeObserver === 'function') {
			// The sizes the drawing needs, kept here: read once per resize, not once per pointer event.
			const list = el.querySelector('.ceRatios');
			this.resize = new ResizeObserver(() => {
				this.size = {w: stage.clientWidth, h: stage.clientHeight};
				this.listLength = Math.max(list.clientHeight, list.clientWidth);
				this.render();
			});
			this.resize.observe(stage);
			this.resize.observe(list);
		}
	};

	editor.Session = Session;
	sys.cropEditor = editor;
})();
