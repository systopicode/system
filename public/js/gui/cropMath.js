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
 * A ratio is {w, h} of whole numbers; a crop {ratio, cx, cy, scale}: the
 * centre a fraction of what it is cut from, the scale over "fill in" (1 =
 * the largest crop without a border, below 1 background shows).
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

	/** Width and height of a crop as fractions of a reference of the given size. */
	math.size = function (ratio, scale, width, height) {
		const r = math.value(ratio);
		return r >= width / height
			? [1 / scale, width / r / height / scale]
			: [height * r / width / scale, 1 / scale];
	};

	/** The crop as a rectangle, x and y its top left corner - fractions, not limited to 0-1. */
	math.rect = function (crop, width, height) {
		const [w, h] = math.size(crop.ratio, crop.scale, width, height);
		return {x: crop.cx - w / 2, y: crop.cy - h / 2, w: w, h: h};
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

	/** The nearest crop that lies inside the reference: scale at least 1, the centre moved in. */
	math.clamped = function (crop, width, height) {
		const scale = Math.max(1, crop.scale);
		const [w, h] = math.size(crop.ratio, scale, width, height);
		return Object.assign({}, crop, {
			cx: Math.min(Math.max(crop.cx, w / 2), 1 - w / 2),
			cy: Math.min(Math.max(crop.cy, h / 2), 1 - h / 2),
			scale: scale
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

	function between(ratio, below, above) {
		const t = math.position(ratio, below.ratio, above.ratio);
		return {
			ratio: ratio,
			cx: below.cx + (above.cx - below.cx) * t,
			cy: below.cy + (above.cy - below.cy) * t,
			scale: Math.pow(below.scale, 1 - t) * Math.pow(above.scale, t),
			source: 'interpolated'
		};
	}

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
	 * manual: [{ratio, cx, cy, scale}], pois: [{x, y, w, h, weight}] in fractions of the master.
	 */
	math.resolve = function (ratio, width, height, manual, pois) {
		const centred = {ratio: ratio, cx: 0.5, cy: 0.5, scale: 1, source: 'centered'};
		const byName = new Map();
		for (const crop of manual || []) {
			byName.set(crop.ratio.name, Object.assign({}, crop, {source: 'manual'}));
		}
		if (byName.has(ratio.name)) {
			return byName.get(ratio.name);
		}
		let [below, above] = neighbours(ratio, byName.values());
		if (below && above) {
			return math.clamped(between(ratio, below, above), width, height);
		}
		if (pois && pois.length) {
			const [w, h] = math.size(ratio, 1, width, height);
			return {ratio: ratio, cx: slide(w, pois, 'x', 'w'), cy: slide(h, pois, 'y', 'h'), scale: 1, source: 'poi'};
		}
		if (!byName.size) {
			return centred;
		}
		const master = math.ratio(width, height);
		if (master.name === ratio.name) {
			return centred;
		}
		const anchors = Array.from(byName.values());
		if (!byName.has(master.name)) {
			anchors.push({ratio: master, cx: 0.5, cy: 0.5, scale: 1});
		}
		[below, above] = neighbours(ratio, anchors);
		if (below && above) {
			return math.clamped(between(ratio, below, above), width, height);
		}
		const nearest = below || above;
		return math.clamped({ratio: ratio, cx: nearest.cx, cy: nearest.cy, scale: nearest.scale, source: 'anchor'}, width, height);
	};

	// ----------------------------------------------------------------- master

	/**
	 * Where the master lies in the original: mirrored, turned by any angle
	 * (clockwise, degrees), cut to its crop. Scale 1 is the largest frame of
	 * the ratio inside the turned picture; the centre is a fraction of the
	 * box around the turned picture.
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
			originalWidth: originalWidth, originalHeight: originalHeight, crop: crop, angle: angle || 0, flip: !!flip,
			referenceWidth: originalWidth * c + originalHeight * s,
			referenceHeight: originalWidth * s + originalHeight * c
		};
		// A frame w x h inside the turned picture: w·c + h·s <= width, w·s + h·c <= height.
		g.fill = Math.min(originalWidth / (r * c + s), originalHeight / (r * s + c));
		g.frameHeight = g.fill / crop.scale;
		g.frameWidth = g.frameHeight * r;
		g.width = Math.max(1, Math.round(g.frameWidth));
		g.height = Math.max(1, Math.round(g.frameHeight));
		const x = (crop.cx - 0.5) * g.referenceWidth;
		const y = (crop.cy - 0.5) * g.referenceHeight;
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
		g.fitScale = () => Math.min(g.fill * r / g.referenceWidth, g.fill / g.referenceHeight);
		return g;
	};

	/** The master whose four corners touch one edge of the turned picture each - null near the diagonal. */
	math.touching = function (width, height, angle, flip) {
		const probe = math.master(width, height, {ratio: math.ratio(width, height), cx: 0.5, cy: 0.5, scale: 1}, angle, flip);
		const rad = (angle || 0) * Math.PI / 180;
		const c = Math.abs(Math.round(Math.cos(rad) * 1e12) / 1e12);
		const s = Math.abs(Math.round(Math.sin(rad) * 1e12) / 1e12);
		const d = c * c - s * s;
		if (Math.abs(d) < 1e-9) {
			return null;
		}
		const w = Math.round((width * c - height * s) / d);
		const h = Math.round((height * c - width * s) / d);
		return w > 0 && h > 0 ? math.master(width, height, {ratio: math.ratio(w, h), cx: 0.5, cy: 0.5, scale: 1}, probe.angle, flip) : null;
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
