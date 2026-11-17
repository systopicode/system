/* global sys, document, window, fetch, ResizeObserver, confirm */

/**
 * The image editor of master and ratios - the browser's side of
 * Media\CropEdits (the file editor's actions cropState, saveMasterCrop,
 * saveRatioCrop, resetCrop, savePois).
 *
 * Two modes:
 *
 *   master   shows the original. The frame is the master crop: any ratio
 *            (the slider moves freely, a click on a ratio sets that one),
 *            turned, mirrored, with its look.
 *   crops    shows the master. The frame is the crop of one declared ratio;
 *            a click on the slider picks the ratio.
 *
 * **The frame stands still, the picture moves under it**: drag to move,
 * wheel or two fingers to zoom. What is stored is where the middle of the
 * frame lies in the picture and how far it is zoomed in over "fill in".
 *
 * Two tools work around a **fix point** (a cross on the stage, to be put
 * anywhere; it snaps to the frame's corners, the middles of its edges and
 * its middle):
 *
 *   zoom     drag anywhere: the picture grows and shrinks around the fix
 *            point, and the place that was grabbed stays under the pointer -
 *            the two-finger zoom, done with a mouse.
 *   turn     (master) a click turns by 90 degrees; a drag turns freely
 *            around the fix point. With two fingers: turn them.
 *
 * A click on the cross puts the tool away; dragging moves the picture again.
 *
 * **Points of interest** (faces, objects) are set with a third tool: a click
 * puts one down, dragging moves it, the bar below weighs or removes the
 * chosen one. They are stored at once - they belong to the picture, not to
 * the crop on screen - and every crop nobody set by hand follows them.
 *
 * Everything drawn while the pointer moves is worked out here
 * (sys.cropMath); what is stored is worked out again by the server, and the
 * editor redraws from the server's answer.
 *
 *   sys.cropEditor.open({stage: element, url: '…/admin/pages/editor/file/', nodeId: 169})
 */
(function () {
	'use strict';

	const editor = {};
	const ICON = name => '<i class="cmsIcon cmsIcon-' + name + '" aria-hidden="true"></i>';
	const SOURCE = {manual: 'set by hand', interpolated: 'computed between its neighbours', poi: 'computed from the points of interest', anchor: 'computed from the nearest crop', centered: 'computed: centred'};

	/** The frame keeps this much of the stage free around it. */
	editor.margin = 0.1;
	/** Screen pixels within which the fix point snaps to a point of the frame. */
	editor.snap = 12;

	editor.open = function (options) {
		const session = new Session(options);
		session.start();
		return session;
	};

	function Session(options) {
		this.options = options;
		this.math = sys.cropMath;
		this.mode = 'master';
		this.ratioName = null;      // crops mode: the ratio on screen
		this.work = null;           // what is being edited
		this.saved = null;          // the same as last stored
		this.free = false;          // background may show
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
			'<div class="ceTools" role="toolbar" aria-label="Image tools">' +
				'<button type="button" class="button" data-ce-mode="master" title="Master: turn, look and crop of the whole picture" aria-label="Master">' + ICON('image') + '</button>' +
				'<button type="button" class="button" data-ce-mode="crops" title="Crops: one per aspect ratio" aria-label="Crops">' + ICON('crop-simple') + '</button>' +
				'<hr>' +
				'<button type="button" class="button" data-ce-tool="zoom" title="Zoom around a fix point" aria-label="Zoom around a fix point" aria-pressed="false">' + ICON('magnifying-glass') + '</button>' +
				'<button type="button" class="button" data-ce-tool="poi" title="Points of interest: what must stay in every crop" aria-label="Points of interest" aria-pressed="false">' + ICON('location-dot') + '</button>' +
				'<button type="button" class="button" data-ce-level="c" title="Contrast" aria-label="Contrast">' + ICON('contrast') + '</button>' +
				'<button type="button" class="button" data-ce-level="b" title="Brightness" aria-label="Brightness">' + ICON('sun') + '</button>' +
				'<button type="button" class="button" data-ce-level="s" title="Saturation" aria-label="Saturation">' + ICON('droplet-half-2') + '</button>' +
				'<button type="button" class="button" data-ce-turn title="Turn by 90 degrees - then drag to turn freely around the fix point" aria-label="Turn by 90 degrees" aria-pressed="false">' + ICON('rotate-right') + '</button>' +
				'<button type="button" class="button" data-ce-flip title="Mirror" aria-label="Mirror">' + ICON('reflect-horizontal') + '</button>' +
				'<span class="ceSpace"></span>' +
				'<div class="ceLevel" hidden><output>0</output><input type="range" min="0" max="200" value="100" aria-label="Value"></div>' +
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
				'<button type="button" class="cePivot" hidden title="Fix point - drag to move it, click to put the tool away" aria-label="Fix point"></button>' +
				'<button type="button" class="button ceOld" data-ce-close title="The editor of the named formats">Old editor</button>' +
				'<button type="button" class="button ceMore" data-ce-more title="Tools" aria-label="Tools" aria-expanded="false">' + ICON('grip-lines') + '</button>' +
				'<div class="ceCommit">' +
					'<button type="button" class="button submit" data-ce-save title="Apply" aria-label="Apply" disabled>' + ICON('check') + '</button>' +
					'<button type="button" class="button" data-ce-cancel title="Discard changes" aria-label="Discard changes" disabled>' + ICON('xmark') + '</button>' +
				'</div>' +
				'<p class="ceStatus" role="status"></p>' +
			'</div>' +
			'<div class="ceSide">' +
				'<div class="ceButtons">' +
					'<button type="button" class="button" data-ce-source title="Back to the ratio of the picture" aria-label="Back to the ratio of the picture">' + ICON('aspect-ratio') + '</button>' +
					'<button type="button" class="button" data-ce-fit title="Fit in: the whole picture" aria-label="Fit in">' + ICON('expand') + '</button>' +
					'<button type="button" class="button" data-ce-fill title="Fill in: no background" aria-label="Fill in">' + ICON('frame') + '</button>' +
					'<span class="ceToggle" role="group" aria-label="Edge of the picture">' +
						'<button type="button" class="button" data-ce-free="0" title="The edge of the picture stays outside" aria-label="The edge of the picture stays outside">' + ICON('lock') + '</button>' +
						'<button type="button" class="button" data-ce-free="1" title="Background may show" aria-label="Background may show">' + ICON('lock-open') + '</button>' +
					'</span>' +
				'</div>' +
				'<div class="ceSlider"><div class="ceTrack"><span class="ceKnob"></span></div><ol class="ceRatios"></ol></div>' +
				'<button type="button" class="button" data-ce-auto title="Crop back to automatic" aria-label="Crop back to automatic">' + ICON('arrow-rotate-left') + '</button>' +
			'</div>';
		if (this.options.old === false) {
			// The named formats are aliases now: their editor would store crops nothing reads.
			el.querySelector('.ceOld').remove();
		}
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
		if (this.resize) {
			this.resize.disconnect();
		}
		this.el.remove();
		this.options.stage.classList.remove('cropEditing');
		if (this.options.onClose) {
			this.options.onClose(this.changed);
		}
		return true;
	};

	// ----------------------------------------------------------------- server

	Session.prototype.request = function (action, params) {
		const query = new URLSearchParams(Object.assign({target_id: this.options.nodeId}, params || {}));
		this.busy = true;
		this.el.classList.add('is-busy');
		return fetch(this.options.url + action + '?' + query.toString(), {credentials: 'same-origin'})
			.then(response => response.ok ? response.json() : null)
			.catch(() => null)
			.then(data => {
				this.busy = false;
				this.el.classList.remove('is-busy');
				if (!data) {
					this.status('That did not work. Please try again.', true);
				}
				return data;
			});
	};

	/** Takes what the server says the medium is now, and shows the current mode from it. */
	Session.prototype.take = function (data) {
		this.data = data;
		this.ratios = data.ratios.map(r => Object.assign({}, r, {ratio: this.math.ratio(r.w, r.h)}));
		if (!this.ratioName || !this.ratios.some(r => r.name === this.ratioName)) {
			const square = this.ratios.find(r => r.name === '1:1');
			this.ratioName = (square || this.ratios[0] || {}).name || null;
		}
		this.load();
	};

	/** Starts editing the current mode's subject from the server's state. */
	Session.prototype.load = function () {
		const data = this.data;
		if (this.mode === 'master') {
			const m = data.master;
			this.work = {ratio: this.math.ratio(m.ratio), cx: m.cx, cy: m.cy, scale: m.scale, angle: m.angle, flip: m.flip,
				look: Object.assign({}, m.look), auto: !m.manual, freeRatio: false};
			this.free = !this.masterOf(this.work).insideOriginal(1);
			this.show(data.original.url);
		} else {
			const r = this.ratios.find(item => item.name === this.ratioName);
			this.work = {ratio: r.ratio, cx: r.cx, cy: r.cy, scale: r.scale};
			this.free = !this.math.inside(this.work, data.master.width, data.master.height, 1e-6);
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

	/** Is this state allowed while "the edge stays outside" holds? */
	Session.prototype.allowed = function (work) {
		if (this.free) {
			return work.scale > 0.05;
		}
		return this.mode === 'master'
			? this.masterOf(work).insideOriginal(0.5)
			: this.math.inside(work, this.data.master.width, this.data.master.height, 1e-6);
	};

	/** Goes from the current state towards a wanted one, as far as the rule allows. */
	Session.prototype.move = function (wanted) {
		const from = this.work;
		const make = t => Object.assign({}, wanted, {
			cx: from.cx + (wanted.cx - from.cx) * t,
			cy: from.cy + (wanted.cy - from.cy) * t,
			scale: from.scale * Math.pow(wanted.scale / from.scale, t)
		});
		this.work = this.allowed(from) ? this.math.furthest(make, work => this.allowed(work)) : this.settled(wanted);
		this.render();
	};

	/** The nearest allowed state to any state - after the ratio, the angle or the rule changed. */
	Session.prototype.settled = function (work) {
		if (this.allowed(work)) {
			return work;
		}
		// Fill in, centred, is always allowed; from there as far towards the wanted state as it goes.
		const safe = Object.assign({}, work, {cx: 0.5, cy: 0.5, scale: Math.max(1, work.scale)});
		const make = t => Object.assign({}, work, {
			cx: safe.cx + (work.cx - safe.cx) * t,
			cy: safe.cy + (work.cy - safe.cy) * t,
			scale: safe.scale * Math.pow(work.scale / safe.scale, t)
		});
		return this.math.furthest(make, state => this.allowed(state));
	};

	/**
	 * What is on the stage: the frame, and the picture under it.
	 * k is screen pixels per pixel of the picture shown.
	 */
	Session.prototype.layout = function () {
		const sw = this.stageEl.clientWidth;
		const sh = this.stageEl.clientHeight;
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

	Session.prototype.render = function () {
		if (!this.work || !this.stageEl.clientWidth) {
			return;
		}
		const view = this.view = this.layout();
		const px = value => value.toFixed(2) + 'px';
		const f = view.frame;
		Object.assign(this.frameEl.style, {left: px(f.x), top: px(f.y), width: px(f.w), height: px(f.h)});
		const look = this.mode === 'master' ? this.work.look : {b: 100, c: 100, s: 100};
		const place = (img, ox, oy) => {
			const i = view.image;
			Object.assign(img.style, {
				width: px(i.w), height: px(i.h), left: px(i.cx - i.w / 2 - ox), top: px(i.cy - i.h / 2 - oy),
				transform: 'rotate(' + i.angle + 'deg) scaleX(' + (i.flip ? -1 : 1) + ')',
				filter: 'brightness(' + look.b + '%) contrast(' + look.c + '%) saturate(' + look.s + '%)'
			});
		};
		place(this.under, 0, 0);
		place(this.inner, f.x, f.y);
		this.renderPois();
		const pivot = this.el.querySelector('.cePivot');
		const around = this.tool === 'zoom' || this.tool === 'turn';
		pivot.hidden = !around;
		if (around) {
			this.pivot = this.pivot || {x: f.x + f.w / 2, y: f.y + f.h / 2};
			Object.assign(pivot.style, {left: px(this.pivot.x), top: px(this.pivot.y)});
		}
		this.renderSide();
	};

	Session.prototype.dirty = function () {
		return this.work !== null && JSON.stringify(this.work) !== this.saved;
	};

	Session.prototype.renderSide = function () {
		const el = this.el;
		const master = this.mode === 'master';
		const dirty = this.dirty();
		el.dataset.mode = this.mode;
		el.querySelectorAll('[data-ce-mode]').forEach(b => b.classList.toggle('selected', b.dataset.ceMode === this.mode));
		el.querySelectorAll('[data-ce-level],[data-ce-turn],[data-ce-flip]').forEach(b => { b.disabled = !master; });
		el.querySelector('[data-ce-tool="zoom"]').classList.toggle('selected', this.tool === 'zoom');
		el.querySelector('[data-ce-tool="zoom"]').setAttribute('aria-pressed', String(this.tool === 'zoom'));
		el.querySelector('[data-ce-tool="poi"]').classList.toggle('selected', this.tool === 'poi');
		el.querySelector('[data-ce-tool="poi"]').setAttribute('aria-pressed', String(this.tool === 'poi'));
		el.querySelector('[data-ce-turn]').classList.toggle('selected', this.tool === 'turn');
		el.querySelector('[data-ce-turn]').setAttribute('aria-pressed', String(this.tool === 'turn'));
		el.dataset.tool = this.tool || '';
		el.querySelector('[data-ce-source]').hidden = !master;
		el.querySelector('[data-ce-fit]').hidden = master;
		el.querySelector('[data-ce-fill]').hidden = master;
		el.querySelectorAll('[data-ce-free]').forEach(b => b.classList.toggle('selected', (b.dataset.ceFree === '1') === this.free));
		el.querySelector('[data-ce-save]').disabled = !dirty || this.busy;
		el.querySelector('[data-ce-cancel]').disabled = !dirty || this.busy;

		// The slider: every declared ratio at its place, the knob at the current one.
		const list = el.querySelector('.ceRatios');
		if (list.dataset.for !== this.data.version + this.mode) {
			list.dataset.for = this.data.version + this.mode;
			list.innerHTML = this.ratios.map(r => {
				const box = r.w >= r.h ? [1, r.h / r.w] : [r.w / r.h, 1];
				return '<li style="--p:' + r.position.toFixed(5) + '"><button type="button" data-ce-ratio="' + r.name + '" title="' + r.name + (master ? '' : ' - ' + SOURCE[r.source]) + '" aria-label="' + r.name + '"' +
					(!master && r.source === 'manual' ? ' class="is-manual"' : '') + '><span style="width:' + (box[0] * 1.05).toFixed(3) + 'rem;height:' + (box[1] * 1.05).toFixed(3) + 'rem"></span></button></li>';
			}).join('');
		}
		// Ratios that would cover each other take a second column (a second row, where the slider lies across).
		const height = Math.max(list.clientHeight, list.clientWidth);
		if (height && list.dataset.laid !== list.dataset.for + height) {
			list.dataset.laid = list.dataset.for + height;
			const free = [-Infinity, -Infinity];
			list.querySelectorAll('li').forEach((li, index) => {
				const y = this.ratios[index].position * height;
				const size = li.firstChild.offsetHeight + 3;
				const col = y - free[0] >= size ? 0 : (y - free[1] >= size ? 1 : (free[0] <= free[1] ? 0 : 1));
				li.dataset.col = col;
				free[col] = y;
			});
		}
		const current = master ? this.work.ratio : this.ratios.find(r => r.name === this.ratioName).ratio;
		list.querySelectorAll('[data-ce-ratio]').forEach(b => b.classList.toggle('selected', b.dataset.ceRatio === current.name));
		el.querySelector('.ceKnob').style.setProperty('--p', this.positionOf(current).toFixed(5));
		el.querySelector('.ceSlider').classList.toggle('is-free', master);

		const auto = el.querySelector('[data-ce-auto]');
		const shown = master ? null : this.ratios.find(r => r.name === this.ratioName);
		auto.disabled = this.busy || (master ? !this.data.master.manual : shown.source !== 'manual');

		if (!this.message) {
			const m = this.data.master;
			const hint = this.tool === 'zoom' ? ' - drag to zoom around the cross, click the cross to finish'
				: (this.tool === 'turn' ? ' - drag to turn around the cross, click the cross to finish'
				: (this.tool === 'poi' ? ' - ' + this.pois.length + (this.pois.length === 1 ? ' point' : ' points') + ' of interest: click to set one, drag to move it' : ''));
			const turned = master && this.work.angle ? ', turned ' + (Math.round(this.work.angle * 10) / 10) + ' degrees' : '';
			this.statusEl().textContent = this.tool ? (master ? 'Master ' + this.work.ratio.name + turned : this.ratioName) + hint : master
				? 'Master ' + this.work.ratio.name + turned + ' - ' + (this.work.auto && !dirty ? 'the whole picture' : 'cropped') + (dirty ? ' - not applied yet' : '')
				: this.ratioName + ' - ' + (dirty ? 'not applied yet' : SOURCE[shown.source]) + ' (master ' + m.width + ' x ' + m.height + ')';
		}
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
		const first = this.ratios[0].ratio;
		const last = this.ratios[this.ratios.length - 1].ratio;
		return Math.min(1, Math.max(0, 1 - this.math.position(ratio, last, first)));
	};

	/** The ratio at a place on the slider - for the master, whose ratio is free. */
	Session.prototype.ratioAt = function (position) {
		const first = Math.log(this.math.value(this.ratios[0].ratio));
		const last = Math.log(this.math.value(this.ratios[this.ratios.length - 1].ratio));
		return this.math.ratio(Math.round(Math.exp(first + (last - first) * position) * 1000), 1000);
	};

	// ----------------------------------------------------------------- tools

	/** Moves the picture by screen pixels - the frame's middle moves the other way in the picture. */
	Session.prototype.pan = function (dx, dy) {
		const v = this.view;
		this.move(Object.assign({}, this.work, {cx: this.work.cx - dx / (v.k * v.refW), cy: this.work.cy - dy / (v.k * v.refH), auto: false}));
	};

	/** Zooms by a factor; the point of the picture under (x, y) on the stage stays where it is. */
	Session.prototype.zoom = function (factor, x, y) {
		const v = this.view;
		const qx = x - (v.frame.x + v.frame.w / 2);
		const qy = y - (v.frame.y + v.frame.h / 2);
		const scale = Math.min(100, Math.max(0.05, this.work.scale * factor));
		const real = scale / this.work.scale;
		this.move(Object.assign({}, this.work, {
			scale: scale,
			cx: this.work.cx + qx / v.k * (1 - 1 / real) / v.refW,
			cy: this.work.cy + qy / v.k * (1 - 1 / real) / v.refH,
			auto: false
		}));
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
			// Nothing cropped yet: the frame that touches all four edges of the turned picture.
			const touching = this.math.touching(o.width, o.height, angle, flip);
			this.work = touching
				? Object.assign({}, work, {ratio: touching.crop.ratio, cx: 0.5, cy: 0.5, scale: 1, angle: angle, flip: flip})
				: this.settled(Object.assign({}, work, {angle: angle, flip: flip, cx: 0.5, cy: 0.5, scale: 1}));
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
		const probe = this.masterOf(Object.assign({}, next, {cx: 0.5, cy: 0.5, scale: 1}));
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
		next.cx = 0.5 + (dx * cos - dy * sin) / probe.referenceWidth;
		next.cy = 0.5 + (dx * sin + dy * cos) / probe.referenceHeight;
		next.scale = probe.fill / before.frameHeight;
		this.work = this.settled(next);
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
		const g = this.math.master(o.width, o.height, {ratio: this.math.ratio(m.ratio), cx: m.cx, cy: m.cy, scale: m.scale}, m.angle, m.flip);
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
				this.changed = true;
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
		this.render();
	};

	/** Puts the fix point at a place on the stage - on a point of the frame when it is near one. */
	Session.prototype.movePivot = function (x, y) {
		const f = this.view.frame;
		let best = null;
		for (const px of [f.x, f.x + f.w / 2, f.x + f.w]) {
			for (const py of [f.y, f.y + f.h / 2, f.y + f.h]) {
				const distance = Math.hypot(x - px, y - py);
				if (distance <= editor.snap && (!best || distance < best.distance)) {
					best = {x: px, y: py, distance: distance};
				}
			}
		}
		this.pivot = best ? {x: best.x, y: best.y} : {x: x, y: y};
		this.render();
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

	Session.prototype.setRatio = function (ratio, free) {
		this.work = this.settled(Object.assign({}, this.work, {ratio: ratio, freeRatio: !!free, auto: false}));
		this.render();
	};

	Session.prototype.hideLevel = function () {
		this.el.querySelector('.ceLevel').hidden = true;
		this.el.querySelectorAll('[data-ce-level]').forEach(b => b.classList.remove('selected'));
	};

	// ------------------------------------------------------------------ store

	Session.prototype.save = function () {
		if (!this.dirty() || this.busy) {
			return;
		}
		let work = this.work;
		let action = 'saveRatioCrop';
		const params = {ratio: work.ratio.name, cx: work.cx, cy: work.cy, scale: work.scale};
		if (this.mode === 'master') {
			action = 'saveMasterCrop';
			if (work.freeRatio) {
				// A freely set ratio is the fraction of the frame's whole pixels in the original.
				const g = this.masterOf(work);
				const ratio = this.math.ratio(g.width, g.height);
				const fill = this.masterOf(Object.assign({}, work, {ratio: ratio, scale: 1})).fill;
				work = Object.assign({}, work, {ratio: ratio, scale: fill / g.height});
				Object.assign(params, {ratio: ratio.name, scale: work.scale});
			}
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
			this.changed = true;
			this.take(data);
			if (data.clipped && data.clipped.length) {
				this.status('Moved to stay inside the new master: ' + data.clipped.join(', ') + '. Please check ' + (data.clipped.length > 1 ? 'these crops.' : 'this crop.'));
			}
		});
	};

	Session.prototype.cancel = function () {
		this.load();
	};

	Session.prototype.auto = function () {
		if (this.busy) {
			return;
		}
		this.request('resetCrop', {format: this.mode === 'master' ? 'original' : this.ratioName}).then(data => {
			if (data) {
				this.changed = true;
				this.take(data);
			}
		});
	};

	/** Leaves the current subject: asks when there is something to lose. */
	Session.prototype.leave = function () {
		return !this.dirty() || confirm('Discard the changes that are not applied yet?');
	};

	// ------------------------------------------------------------------ input

	Session.prototype.bind = function () {
		const el = this.el;
		const stage = this.stageEl;
		const on = (selector, handler) => el.querySelectorAll(selector).forEach(b => b.addEventListener('click', event => {
			if (!this.work || this.busy) {
				return;
			}
			handler(event.currentTarget, event);
		}));

		on('[data-ce-mode]', button => {
			if (button.dataset.ceMode !== this.mode && this.leave()) {
				this.mode = button.dataset.ceMode;
				this.load();
			}
		});
		el.querySelector('[data-ce-more]').addEventListener('click', event => {
			event.currentTarget.setAttribute('aria-expanded', String(el.classList.toggle('is-open')));
		});
		// Leaving works whatever state the editor is in - also when the picture could not be loaded.
		el.querySelectorAll('[data-ce-close]').forEach(button => button.addEventListener('click', () => this.close()));
		on('[data-ce-save]', () => this.save());
		on('[data-ce-cancel]', () => this.cancel());
		on('[data-ce-auto]', () => this.auto());
		on('[data-ce-tool]', button => {
			const tool = this.tool === button.dataset.ceTool ? null : button.dataset.ceTool;
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
		on('[data-ce-turn]', () => {
			// A click turns by a quarter, to the next right angle - and takes the tool up for turning freely.
			const quarter = degrees((Math.floor(this.work.angle / 90 + 1e-9) + 1) * 90);
			if (this.tool !== 'turn') {
				this.tool = 'turn';
				this.pivot = null;
			}
			this.turn(quarter, this.work.flip, this.pivot);
		});
		on('[data-ce-flip]', () => this.turn(this.work.angle, !this.work.flip, this.pivot));
		on('[data-ce-free]', button => {
			this.free = button.dataset.ceFree === '1';
			this.work = this.settled(this.work);
			this.render();
		});
		on('[data-ce-source]', () => {
			const g = this.masterOf(Object.assign({}, this.work, {cx: 0.5, cy: 0.5, scale: 1}));
			const touching = this.math.touching(g.originalWidth, g.originalHeight, this.work.angle, this.work.flip);
			this.work = Object.assign({}, this.work, {
				ratio: touching ? touching.crop.ratio : this.math.ratio(Math.round(g.referenceWidth), Math.round(g.referenceHeight)),
				cx: 0.5, cy: 0.5, scale: 1, auto: true, freeRatio: false
			});
			this.render();
		});
		on('[data-ce-fit]', () => {
			const m = this.data.master;
			this.free = true;
			this.work = Object.assign({}, this.work, {cx: 0.5, cy: 0.5, scale: this.math.fitScale(this.work.ratio, m.width, m.height)});
			this.render();
		});
		on('[data-ce-fill]', () => {
			this.work = Object.assign({}, this.work, {cx: 0.5, cy: 0.5, scale: 1});
			this.render();
		});

		// Brightness, contrast, saturation: one slider, beside the tool that opened it.
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
		range.addEventListener('dblclick', () => {
			range.value = 100;
			range.dispatchEvent(new Event('input'));
		});

		// The slider of ratios: a click picks a declared one; the master's knob also moves freely.
		el.querySelector('.ceRatios').addEventListener('click', event => {
			const button = event.target.closest('[data-ce-ratio]');
			if (!button || !this.work || this.busy) {
				return;
			}
			const item = this.ratios.find(r => r.name === button.dataset.ceRatio);
			if (this.mode === 'master') {
				this.setRatio(item.ratio, false);
			} else if (item.name !== this.ratioName && this.leave()) {
				this.ratioName = item.name;
				this.load();
			}
		});
		const track = el.querySelector('.ceTrack');
		const slide = event => {
			const box = track.getBoundingClientRect();
			// Down on a wide screen, across on a phone.
			const along = box.width > box.height ? (event.clientX - box.left) / box.width : (event.clientY - box.top) / box.height;
			this.setRatio(this.ratioAt(Math.min(1, Math.max(0, along))), true);
		};
		track.addEventListener('pointerdown', event => {
			if (this.mode !== 'master' || !this.work || this.busy || event.button !== 0) {
				return;
			}
			event.preventDefault();
			track.setPointerCapture(event.pointerId);
			track.sliding = true;
			slide(event);
		});
		track.addEventListener('pointermove', event => {
			if (track.sliding) {
				slide(event);
			}
		});
		const stop = () => { track.sliding = false; };
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
			if (!this.work || this.busy || (!onPivot && !onPoint && event.target.closest('button,.cePoiBar')) || (event.pointerType === 'mouse' && event.button !== 0)) {
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
			const last = this.pointers.get(event.pointerId);
			if (!last) {
				return;
			}
			const now = local(event);
			const gesture = this.pointers.size === 1 ? this.gesture : null;
			if (gesture && gesture.tool === 'pivot') {
				if (gesture.moved || Math.hypot(now.x - gesture.start.x, now.y - gesture.start.y) > 3) {
					gesture.moved = true;
					this.movePivot(now.x, now.y);
				}
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
			if (!this.work || this.busy) {
				return;
			}
			event.preventDefault();
			const at = local(event);
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
			} else if (event.key === 'Escape' && this.dirty()) {
				event.preventDefault();
				event.stopPropagation();
				this.cancel();
			}
		});

		if (typeof ResizeObserver === 'function') {
			this.resize = new ResizeObserver(() => this.render());
			this.resize.observe(stage);
		}
	};

	editor.Session = Session;
	sys.cropEditor = editor;
})();
