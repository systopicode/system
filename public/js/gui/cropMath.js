/* global sys, window, module */

/**
 * The arithmetic of crops in the browser - the same rules as on the server:
 *
 *   Media\Ratio, Media\CropBox      a crop is a ratio, a centre and a scale
 *   Media\CropResolver              the crop of a ratio nobody set by hand
 *   Media\MasterGeometry            where the master lies in the original
 *
 * The image editor draws with it while the pointer moves; what it stores is
 * worked out again by the server. Both must agree: the resolver here passes
 * the same cases as the PHP class (tests/media-crop-resolver.cases.json).
 *
 * A ratio is {w, h} of whole numbers; a crop {ratio, ax, ay, scale, basis}
 * (Media\CropBox): the basis 'fill' (the edge of the picture stays outside,
 * scale 1 = "fill in", the alignment as with background-position in per
 * cent) or 'fit' (background may show, scale 1 = "fit in", 0 = the picture
 * touches the crop on the left / top, 1 on the right / bottom). No basis is
 * 'fill'. The alignment is 0-1 in both, 0.5 centred.
 */
(function () {
	'use strict';

	const math = {};

	// ------------------------------------------------------------------ ratio

	/** "16:9" -> {w, h, name}; a pair of numbers likewise. Null for anything else. */
	math.ratio = function (w, h) {
		if (typeof w === 'string' && h === undefined) {
			const m = w.match(/^\s*(\d+)\s*[:\/]\s*(\d+)\s*$/);
			if (!m) {
				return null;
			}
			w = Number(m[1]);
			h = Number(m[2]);
		}
		w = Math.round(w);
		h = Math.round(h);
		if (!(w > 0) || !(h > 0)) {
			return null;
		}
		let a = w;
		let b = h;
		while (b) {
			[a, b] = [b, a % b];
		}
		return {w: w / a, h: h / a, name: (w / a) + ':' + (h / a)};
	};

	math.value = ratio => ratio.w / ratio.h;

	/** -1, 0, 1 by width over height, exactly. */
	math.compare = (a, b) => Math.sign(a.w * b.h - b.w * a.h);

	/** Where a ratio lies between two others, 0 at from and 1 at to, logarithmically. */
	math.position = function (ratio, from, to) {
		const span = Math.log(math.value(to)) - Math.log(math.value(from));
		return span === 0 ? 0 : (Math.log(math.value(ratio)) - Math.log(math.value(from))) / span;
	};

	// ------------------------------------------------------------------- crop

	/** Width and height of a crop as fractions of a reference of the given size; the scale over 1 of the basis. */
	math.size = function (ratio, scale, width, height, basis) {
		const r = math.value(ratio);
		if (basis === 'fit') {
			scale *= math.fitScale(ratio, width, height);
		}
		return r >= width / height
			? [1 / scale, width / r / height / scale]
			: [height * r / width / scale, 1 / scale];
	};

	/** The middle of a crop of that length (fractions) under an alignment. */
	math.centreOf = (align, length, basis) => basis === 'fit'
		? 1 + length / 2 - align * (1 + length)
		: align + length * (0.5 - align);

	/** The alignment (0-1) that puts the middle of a crop of that length at a place - in 'fill' keep where the length is the whole axis. */
	math.alignOf = function (centre, length, keep, basis) {
		let align;
		if (basis === 'fit') {
			align = (1 + length / 2 - centre) / (1 + length);
		} else {
			const room = 1 - length;
			if (Math.abs(room) < 1e-9) {
				return keep === undefined ? 0.5 : keep;
			}
			align = (centre - length / 2) / room;
		}
		return Math.max(0, Math.min(1, align));
	};

	/** The middle of a crop, fractions of the reference: [cx, cy]. */
	math.centre = function (crop, width, height) {
		const [w, h] = math.size(crop.ratio, crop.scale, width, height, crop.basis);
		return [math.centreOf(crop.ax, w, crop.basis), math.centreOf(crop.ay, h, crop.basis)];
	};

	/** The crop as a rectangle, x and y its top left corner - fractions, not limited to 0-1. */
	math.rect = function (crop, width, height) {
		const [w, h] = math.size(crop.ratio, crop.scale, width, height, crop.basis);
		return {x: math.centreOf(crop.ax, w, crop.basis) - w / 2, y: math.centreOf(crop.ay, h, crop.basis) - h / 2, w: w, h: h};
	};

	/** The same frame in the basis 'fit' - where crops of two bases are interpolated; 'fit' holds every frame 'fill' can. */
	math.asFit = function (crop, width, height) {
		if (crop.basis === 'fit') {
			return crop;
		}
		const [cx, cy] = math.centre(crop, width, height);
		const [w, h] = math.size(crop.ratio, crop.scale, width, height);
		const scale = crop.scale / math.fitScale(crop.ratio, width, height);
		return Object.assign({}, crop, {ax: math.alignOf(cx, w, undefined, 'fit'), ay: math.alignOf(cy, h, undefined, 'fit'), scale: scale, basis: 'fit'});
	};

	/** The scale at which the whole reference lies inside the crop. */
	math.fitScale = function (ratio, width, height) {
		const r = math.value(ratio);
		const reference = width / height;
		return Math.min(r / reference, reference / r);
	};

	math.inside = function (crop, width, height, tolerance) {
		const t = tolerance === undefined ? 1e-9 : tolerance;
		const rect = math.rect(crop, width, height);
		return rect.x >= -t && rect.y >= -t && rect.x + rect.w <= 1 + t && rect.y + rect.h <= 1 + t;
	};

	/** The nearest crop its basis allows: the alignment within 0-1, in 'fill' the scale at least 1 (then it lies inside). */
	math.clamped = function (crop) {
		return Object.assign({}, crop, {
			ax: Math.min(Math.max(crop.ax, 0), 1),
			ay: Math.min(Math.max(crop.ay, 0), 1),
			scale: crop.basis === 'fit' ? crop.scale : Math.max(1, crop.scale)
		});
	};

	// --------------------------------------------------------------- resolver

	function neighbours(ratio, crops) {
		let below = null;
		let above = null;
		for (const crop of crops) {
			const side = math.compare(crop.ratio, ratio);
			if (side < 0 && (!below || math.compare(crop.ratio, below.ratio) > 0)) {
				below = crop;
			} else if (side > 0 && (!above || math.compare(crop.ratio, above.ratio) < 0)) {
				above = crop;
			}
		}
		return [below, above];
	}

	/**
	 * Between two crops: the alignment in a straight line, the scale in equal
	 * factors. Of one basis: that basis; of two, both read as 'fit' first -
	 * read as 'fill', a frame showing background would jump at the 'fit' end.
	 * Kept to what the basis allows (math.clamped). t: how far between, when
	 * it is known better than from the ratio (the editor's slider).
	 */
	math.between = function (ratio, below, above, width, height, t) {
		const basis = b => b.basis === 'fit' ? 'fit' : 'fill';
		if (basis(below) !== basis(above)) {
			below = math.asFit(below, width, height);
			above = math.asFit(above, width, height);
		}
		if (t === undefined) {
			t = math.position(ratio, below.ratio, above.ratio);
		}
		return math.clamped({
			ratio: ratio,
			ax: below.ax + (above.ax - below.ax) * t,
			ay: below.ay + (above.ay - below.ay) * t,
			scale: Math.pow(below.scale, 1 - t) * Math.pow(above.scale, t),
			basis: basis(below),
			source: 'interpolated'
		});
	};

	function slide(length, pois, position, size) {
		if (length >= 1 - 1e-12) {
			return 0.5;
		}
		const spans = [];
		for (const poi of pois) {
			const weight = poi.weight === undefined ? 1 : Number(poi.weight);
			if (!(weight > 0) || poi[position] === undefined) {
				continue;
			}
			const half = Math.max(0, Number(poi[size] || 0)) / 2;
			spans.push([Math.max(0, poi[position] - half), Math.min(1, poi[position] + half), weight]);
		}
		if (!spans.length) {
			return 0.5;
		}
		const room = 1 - length;
		const centred = room / 2;
		const first = Math.min(...spans.map(s => s[0]));
		const last = Math.max(...spans.map(s => s[1]));
		if (last - first <= length + 1e-12) {
			return Math.min(Math.max(centred, last - length), first) + length / 2;
		}
		const total = spans.reduce((sum, s) => sum + s[2], 0);
		const centre = spans.reduce((sum, s) => sum + (s[0] + s[1]) / 2 * s[2], 0) / total;
		const starts = [centred];
		for (const s of spans) {
			starts.push(s[0], s[1] - length);
		}
		let best = null;
		for (let start of starts) {
			start = Math.min(Math.max(start, 0), room);
			let held = 0;
			for (const s of spans) {
				if (s[0] >= start - 1e-12 && s[1] <= start + length + 1e-12) {
					held += s[2];
				}
			}
			const distance = Math.abs(start + length / 2 - centre);
			if (!best || held > best[1] + 1e-12 || (Math.abs(held - best[1]) <= 1e-12 && distance < best[2] - 1e-12)) {
				best = [start, held, distance];
			}
		}
		return best[0] + length / 2;
	}

	/**
	 * The crop of a ratio: set by hand, between two set by hand, over the
	 * points of interest, towards the whole master, or centred - in this order.
	 *
	 * manual: [{ratio, ax, ay, scale}], pois: [{x, y, w, h, weight}] in fractions of the master,
	 * declared: the declared ratios - the outermost of them is automatic (centred, scale 1)
	 * unless set by hand; beyond the last crop known the crops run towards it (without them: automatic at once).
	 * basis: that of what is automatic - the master's; with 'fit' the points of interest are not needed.
	 */
	math.resolve = function (ratio, width, height, manual, pois, declared, basis) {
		const automatic = basis === 'fit' ? 'fit' : 'fill';
		const centred = {ratio: ratio, ax: 0.5, ay: 0.5, scale: 1, basis: automatic, source: 'centered'};
		const byName = new Map();
		for (const crop of manual || []) {
			byName.set(crop.ratio.name, Object.assign({}, crop, {basis: crop.basis === 'fit' ? 'fit' : 'fill', source: 'manual'}));
		}
		if (byName.has(ratio.name)) {
			return byName.get(ratio.name);
		}
		let [below, above] = neighbours(ratio, byName.values());
		if (below && above) {
			return math.between(ratio, below, above, width, height);
		}
		if (pois && pois.length && automatic === 'fill') {
			const [w, h] = math.size(ratio, 1, width, height);
			return {ratio: ratio, ax: math.alignOf(slide(w, pois, 'x', 'w'), w), ay: math.alignOf(slide(h, pois, 'y', 'h'), h), scale: 1, basis: 'fill', source: 'poi'};
		}
		if (!byName.size) {
			return centred;
		}
		const master = math.ratio(width, height);
		if (master.name === ratio.name) {
			return centred;
		}
		// The whole master stands in for the missing side, in the basis of the nearest crop - centred at scale 1 the same frame in either.
		const anchors = Array.from(byName.values());
		if (!byName.has(master.name)) {
			anchors.push({ratio: master, ax: 0.5, ay: 0.5, scale: 1, basis: (below || above).basis});
		}
		[below, above] = neighbours(ratio, anchors);
		if (below && above) {
			return math.between(ratio, below, above, width, height);
		}
		// Outside everything known: the outermost declared ratio of that side is automatic - centred at scale 1, in the master's basis.
		// In between, the crops run from the nearest towards it.
		const nearest = below || above;
		const wider = !above;
		let end = null;
		for (const r of declared || []) {
			if (!end || math.compare(r, end) === (wider ? 1 : -1)) {
				end = r;
			}
		}
		if (end && math.compare(end, ratio) === (wider ? 1 : -1)) {
			const edge = {ratio: end, ax: 0.5, ay: 0.5, scale: 1, basis: automatic};
			return math.between(ratio, wider ? nearest : edge, wider ? edge : nearest, width, height);
		}
		return centred;
	};

	// ----------------------------------------------------------------- master

	/**
	 * Where the master lies in the original: mirrored, turned by any angle
	 * (clockwise, degrees), cut to its crop. In 'fill' scale 1 is the largest
	 * frame of the ratio inside the turned picture, in 'fit' the box around
	 * the turned picture inside the frame; the crop is aligned in that box.
	 * g.cx, g.cy: the frame's middle, fractions of that box.
	 */
	math.master = function (originalWidth, originalHeight, crop, angle, flip) {
		const quarters = Math.ceil((angle || 0) / 90 - 0.5);
		const tilt = ((angle || 0) - quarters * 90) * Math.PI / 180;
		const [qc, qs] = [[1, 0], [0, 1], [-1, 0], [0, -1]][((quarters % 4) + 4) % 4];
		const cos = qc * Math.cos(tilt) - qs * Math.sin(tilt);
		const sin = qs * Math.cos(tilt) + qc * Math.sin(tilt);
		const c = Math.abs(cos);
		const s = Math.abs(sin);
		const r = math.value(crop.ratio);
		const g = {
			originalWidth: originalWidth, originalHeight: originalHeight, crop: crop, angle: angle || 0, flip: !!flip, c: c, s: s,
			referenceWidth: originalWidth * c + originalHeight * s,
			referenceHeight: originalWidth * s + originalHeight * c
		};
		// A frame w x h inside the turned picture: w·c + h·s <= width, w·s + h·c <= height.
		g.fill = Math.min(originalWidth / (r * c + s), originalHeight / (r * s + c));
		/** The scale of "fit in" over "fill in". */
		g.fitScale = () => Math.min(g.fill * r / g.referenceWidth, g.fill / g.referenceHeight);
		g.basis = crop.basis === 'fit' ? 'fit' : 'fill';
		/** The scale, in this basis, of a frame of this ratio and angle that is h pixels high. */
		g.scaleFor = h => g.fill / h / (g.basis === 'fit' ? g.fitScale() : 1);
		g.frameHeight = g.fill / (crop.scale * (g.basis === 'fit' ? g.fitScale() : 1));
		g.frameWidth = g.frameHeight * r;
		g.width = Math.max(1, Math.round(g.frameWidth));
		g.height = Math.max(1, Math.round(g.frameHeight));
		g.cx = math.centreOf(crop.ax, g.frameWidth / g.referenceWidth, g.basis);
		g.cy = math.centreOf(crop.ay, g.frameHeight / g.referenceHeight, g.basis);
		const x = (g.cx - 0.5) * g.referenceWidth;
		const y = (g.cy - 0.5) * g.referenceHeight;
		const dx = x * cos + y * sin;
		const dy = -x * sin + y * cos;
		g.centreX = originalWidth / 2 + (g.flip ? -dx : dx);
		g.centreY = originalHeight / 2 + dy;

		g.toMaster = function (px, py) {
			let ax = px - g.centreX;
			const ay = py - g.centreY;
			if (g.flip) {
				ax = -ax;
			}
			return [ax * cos - ay * sin + g.frameWidth / 2, ax * sin + ay * cos + g.frameHeight / 2];
		};
		g.toOriginal = function (px, py) {
			px -= g.frameWidth / 2;
			py -= g.frameHeight / 2;
			const ax = px * cos + py * sin;
			const ay = -px * sin + py * cos;
			return [g.centreX + (g.flip ? -ax : ax), g.centreY + ay];
		};
		g.corners = () => [g.toOriginal(0, 0), g.toOriginal(g.frameWidth, 0), g.toOriginal(g.frameWidth, g.frameHeight), g.toOriginal(0, g.frameHeight)];
		g.insideOriginal = function (tolerance) {
			const t = tolerance === undefined ? 0.5 : tolerance;
			return g.corners().every(([px, py]) => px >= -t && py >= -t && px <= originalWidth + t && py <= originalHeight + t);
		};
		return g;
	};

	/**
	 * Width and height of the largest frame inside a picture turned by an
	 * angle of the given |cos| and |sin| (Media\MasterGeometry::largestSize()):
	 * near a right angle all four corners touch an edge, near the diagonal
	 * two corners stop at the long edges. The two meet without a jump.
	 */
	math.largestSize = function (width, height, c, s) {
		const long = Math.max(width, height);
		const short = Math.min(width, height);
		if (short <= 2 * s * c * long || Math.abs(s - c) < 1e-12) {
			const half = short / 2;
			return width >= height ? [half / s, half / c] : [half / c, half / s];
		}
		const d = c * c - s * s;
		return [(width * c - height * s) / d, (height * c - width * s) / d];
	};

	/** The master of the largest area inside the turned picture, no background - what a turned picture without a master crop shows. */
	math.largest = function (width, height, angle, flip) {
		const probe = math.master(width, height, {ratio: math.ratio(width, height), ax: 0.5, ay: 0.5, scale: 1}, angle, flip);
		const [w, h] = math.largestSize(width, height, probe.c, probe.s);
		return math.master(width, height, {ratio: math.ratio(Math.max(1, Math.round(w)), Math.max(1, Math.round(h))), ax: 0.5, ay: 0.5, scale: 1}, angle, flip);
	};

	/**
	 * The furthest a value may go from a valid state towards a wanted one:
	 * make(t) builds the state at t (0 = the valid one, 1 = the wanted one),
	 * ok(state) says whether it is allowed. Whatever the tool - moving,
	 * zooming, turning - the picture stops where its edge meets the frame.
	 */
	math.furthest = function (make, ok) {
		const wanted = make(1);
		if (ok(wanted)) {
			return wanted;
		}
		let low = 0;
		let high = 1;
		for (let i = 0; i < 24; i++) {
			const middle = (low + high) / 2;
			if (ok(make(middle))) {
				low = middle;
			} else {
				high = middle;
			}
		}
		return make(low);
	};

	if (typeof sys !== 'undefined' && sys) {
		sys.cropMath = math;
	} else if (typeof window !== 'undefined') {
		window.sysCropMath = math;
	}
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = math;
	}
})();
