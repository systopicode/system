/* global window, document, sys, fetch, IntersectionObserver, ResizeObserver, MutationObserver */

/**
 * The pictures of the media library on a page - the browser's half of
 * Tables\Medias\Picture (packages/system/src/Tables/Medias/Picture.php).
 *
 * A picture is made in several sizes; which one is shown is decided here, by
 * the box it sits in and the real pixels of the screen. Three kinds of <img>
 * are looked after:
 *
 *   sizes="auto, …"          a finished picture with srcset. The browser picks
 *                            the size itself; this only tells it the measured
 *                            width, for browsers that do not know "auto".
 *
 *   data-media-srcset        a picture whose files are not made yet. It shows
 *                            a preview (the small master, cut by CSS). Its
 *                            files are asked for here: the visible ones first,
 *                            a few at a time. The server makes only a few at
 *                            a time too and answers 503 when it is busy - then
 *                            the picture waits and asks again.
 *
 *   data-media-auto          the page did not name a ratio. The box decides:
 *                            the declared ratio nearest to it, in the size the
 *                            screen needs.
 *
 * After a backup was played back or the formats were cleared, a page of fifty
 * pictures therefore shows fifty previews at once and fills in what the
 * visitor looks at - instead of fifty renderings starting together.
 */
(function () {
	'use strict';

	const media = {};

	/** Pictures asked for at the same time. The server has its own limit. */
	media.parallel = 3;
	/** How often a picture asks again while the server is busy. */
	media.tries = 40;

	// ------------------------------------------------------------------ pure

	/** "url 943w, url 1333w" -> [{url, width}], in the order given (small to large). */
	media.parseSrcset = function (text) {
		const out = [];
		for (const part of String(text || '').split(',')) {
			const m = part.trim().match(/^(\S+)(?:\s+(\d+)w)?$/);
			if (m) {
				out.push({url: m[1], width: m[2] ? Number(m[2]) : 0});
			}
		}
		return out;
	};

	/** The smallest candidate that has the pixels, else the largest there is. */
	media.pick = function (candidates, need) {
		for (const candidate of candidates) {
			if (candidate.width === 0 || candidate.width >= need) {
				return candidate;
			}
		}
		return candidates.length ? candidates[candidates.length - 1] : null;
	};

	/** The key of the declared ratio nearest to a box - logarithmic, as on the editor's slider. */
	media.nearestRatio = function (ratios, width, height) {
		const value = Math.log(width / height);
		let nearest = null;
		let distance = Infinity;
		for (const key of Object.keys(ratios)) {
			const d = Math.abs(Math.log(ratios[key][0] / ratios[key][1]) - value);
			if (d < distance) {
				nearest = key;
				distance = d;
			}
		}
		return nearest;
	};

	/**
	 * What a box of the given size shows: ratio, variant and address.
	 *
	 * data: {base, type, v, sizes: [megapixels…], ratios: {key: [w, h, ownWidth]}}
	 * The width of a size is the renderer's own arithmetic: sqrt(megapixels · ratio).
	 */
	media.autoVariant = function (data, boxWidth, boxHeight, pixelRatio) {
		if (!data || !data.ratios || !(boxWidth > 0)) {
			return null;
		}
		const keys = Object.keys(data.ratios);
		if (!keys.length) {
			return null;
		}
		const key = boxHeight > 0 ? media.nearestRatio(data.ratios, boxWidth, boxHeight) : keys[0];
		const ratio = data.ratios[key][0] / data.ratios[key][1];
		const own = data.ratios[key][2] || 0;
		// The picture covers the box: it has to be as wide as the box, or as the box's height asks for.
		const need = Math.max(boxWidth, boxHeight > 0 ? boxHeight * ratio : 0) * (pixelRatio || 1);
		let variant = key;                // the crop's own pixels, when no size is enough
		let width = own;
		for (const size of data.sizes || []) {
			const w = Math.round(Math.sqrt(size * 1e6 * ratio));
			if (w >= need || (own > 0 && w >= own)) {
				variant = size + 'mp' + key;
				width = own > 0 ? Math.min(w, own) : w;
				break;
			}
		}
		return {
			key: key,
			variant: variant,
			width: width,
			url: data.base + variant + '.' + (data.type || 'webp') + (data.v ? '?v=' + data.v : '')
		};
	};

	/** The address the loader asks with: the server may answer "busy" to it instead of waiting. */
	media.queueUrl = function (url) {
		return url + (url.indexOf('?') === -1 ? '?' : '&') + 'queue=1';
	};

	// ----------------------------------------------------------------- queue

	const waiting = [];
	let running = 0;
	let seen = null;       // IntersectionObserver
	const visible = typeof WeakSet === 'function' ? new WeakSet() : null;

	const pixelRatio = () => (typeof window !== 'undefined' && window.devicePixelRatio) || 1;

	/**
	 * job: {element, url, done(ok)}. The file behind url is asked for (HEAD -
	 * the answer is not needed, only that it is made); done runs when it is
	 * there, or cannot be made.
	 */
	media.enqueue = function (job) {
		job.tries = 0;
		waiting.push(job);
		if (seen && job.element) {
			seen.observe(job.element);
		}
		pump();
	};

	function pump() {
		while (running < media.parallel && waiting.length) {
			// What the visitor looks at first; then the order of the page.
			let index = visible ? waiting.findIndex(job => job.element && visible.has(job.element)) : -1;
			if (index === -1) {
				index = 0;
			}
			run(waiting.splice(index, 1)[0]);
		}
	}

	function run(job) {
		running++;
		job.tries++;
		fetch(media.queueUrl(job.url), {method: 'HEAD', cache: 'no-store', credentials: 'same-origin'}).then(response => {
			if (response.status === 503 && job.tries < media.tries) {
				const wait = Number(response.headers.get('Retry-After')) || 2;
				setTimeout(() => {
					waiting.push(job);
					pump();
				}, wait * 1000);
				return;
			}
			finish(job, response.ok);
		}).catch(() => finish(job, false)).then(() => {
			running--;
			pump();
		});
	}

	function finish(job, ok) {
		if (seen && job.element) {
			seen.unobserve(job.element);
		}
		job.done(ok);
	}

	// ------------------------------------------------------------- the three

	/** A picture that shows its preview: ask for its file, then hand it its real addresses. */
	media.pending = function (img) {
		if (img.mediaQueued) {
			return;
		}
		img.mediaQueued = true;
		const srcset = img.dataset.mediaSrcset || '';
		const src = img.dataset.mediaSrc || '';
		const candidates = media.parseSrcset(srcset);
		const first = media.pick(candidates, (img.clientWidth || 0) * pixelRatio()) || {url: src};
		if (!first.url) {
			return;
		}
		media.enqueue({element: img, url: first.url, done: () => {
			// With or without success: a picture that cannot be made shows as any missing picture does.
			img.addEventListener('load', () => {
				img.style.background = '';
			}, {once: true});
			delete img.dataset.mediaSrcset;
			delete img.dataset.mediaSrc;
			if (srcset) {
				img.srcset = srcset;
			}
			img.src = src || first.url;
			media.fit(img);
		}});
	};

	/** Tells a picture with srcset how wide it is shown - what sizes="auto" does where it is known. */
	media.fit = function (img) {
		const width = Math.round(img.clientWidth || 0);
		if (width <= 0 || !img.srcset || (img.mediaFit && width <= img.mediaFit * 1.1)) {
			return;
		}
		// A finished picture is only asked again for more pixels, never for fewer.
		if (img.complete && img.naturalWidth && img.mediaFit === undefined && img.naturalWidth >= width * pixelRatio()) {
			img.mediaFit = width;
			return;
		}
		img.mediaFit = width;
		const sizes = width + 'px';
		if (img.parentNode && img.parentNode.nodeName === 'PICTURE') {
			for (const source of img.parentNode.querySelectorAll('source')) {
				source.sizes = sizes;
			}
		}
		img.sizes = sizes;
	};

	/** A picture without a ratio of its own: the box decides, again when it grows or changes shape. */
	media.auto = function (img) {
		let data;
		try {
			data = JSON.parse(img.dataset.mediaAuto);
		} catch (e) {
			return;
		}
		const choose = () => {
			const pick = media.autoVariant(data, img.clientWidth, img.clientHeight, pixelRatio());
			if (!pick || (pick.key === img.mediaAutoKey && pick.width <= (img.mediaAutoWidth || 0))) {
				return;
			}
			img.mediaAutoKey = pick.key;
			img.mediaAutoWidth = pick.width;
			img.mediaAutoUrl = pick.url;
			media.enqueue({element: img, url: pick.url, done: () => {
				if (img.mediaAutoUrl === pick.url) {
					img.src = pick.url;
				}
			}});
		};
		if (!img.mediaAutoWatched) {
			img.mediaAutoWatched = true;
			if (resized) {
				img.mediaChoose = choose;
				resized.observe(img);
			}
		}
		choose();
	};

	// ------------------------------------------------------------------ page

	let resized = null;    // ResizeObserver

	media.scan = function (root) {
		root = root || document;
		if (!root.querySelectorAll) {
			return;
		}
		const all = selector => {
			const found = Array.from(root.querySelectorAll(selector));
			if (root.matches && root.matches(selector)) {
				found.unshift(root);
			}
			return found;
		};
		all('img[data-media-srcset], img[data-media-src]').forEach(media.pending);
		all('img[data-media-auto]').forEach(media.auto);
		all('img[sizes^="auto"]').forEach(img => {
			if (!img.dataset.mediaSrcset) {
				media.fit(img);
				if (resized && !img.mediaFitWatched) {
					img.mediaFitWatched = true;
					resized.observe(img);
				}
			}
		});
	};

	media.start = function () {
		if (media.started || typeof document === 'undefined') {
			return;
		}
		media.started = true;
		if (typeof IntersectionObserver === 'function') {
			seen = new IntersectionObserver(entries => {
				for (const entry of entries) {
					if (entry.isIntersecting) {
						visible.add(entry.target);
					} else {
						visible.delete(entry.target);
					}
				}
			}, {rootMargin: '200px'});
		}
		if (typeof ResizeObserver === 'function') {
			resized = new ResizeObserver(entries => {
				for (const entry of entries) {
					const img = entry.target;
					if (img.mediaChoose) {
						img.mediaChoose();
					} else {
						media.fit(img);
					}
				}
			});
		}
		media.scan(document);
		// Pages are rebuilt in place (domLayer): what arrives later is looked after as well.
		if (typeof MutationObserver === 'function') {
			let scheduled = false;
			const added = [];
			new MutationObserver(records => {
				for (const record of records) {
					for (const node of record.addedNodes) {
						if (node.nodeType === 1) {
							added.push(node);
						}
					}
				}
				if (added.length && !scheduled) {
					scheduled = true;
					(window.requestAnimationFrame || setTimeout)(() => {
						scheduled = false;
						added.splice(0).forEach(node => media.scan(node));
					});
				}
			}).observe(document.documentElement, {childList: true, subtree: true});
		}
	};

	if (typeof sys !== 'undefined' && sys) {
		sys.media = media;
	} else if (typeof window !== 'undefined') {
		window.sysMedia = media;
	}
	if (typeof document !== 'undefined' && document.addEventListener) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', media.start);
		} else {
			media.start();
		}
	}
})();
