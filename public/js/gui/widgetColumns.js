/* global sys */
/**
 * .widgets stack their cards in columns (CSS `columns`). The browser balances
 * those anew whenever a card changes height - a tab, a fold - and cards jump
 * to other columns under the pointer. Here every card keeps the column it
 * got when the group was built; a card that grows only pushes down the ones
 * below it in its own column.
 *
 * The column count stays with the CSS: column-count and column-width of the
 * group are read, the layout is a grid of 1px rows (.is-masonry, 70_components.css).
 * A rebuild of the group with the same cards takes the same columns again.
 */
sys.fitWidgets = function ($set) {
	$set.findInSet('.widgets:not(.dashWidgets)').each(function () {
		var box = this;
		if (box.fitWidgets) {
			sys.fitWidgets.queue(box);
			return;
		}
		box.fitWidgets = new MutationObserver(() => sys.fitWidgets.queue(box));
		box.fitWidgets.observe(box, {childList: true});
		sys.fitWidgets.observer.observe(box);
		sys.fitWidgets.queue(box);
	});
};

sys.fitWidgets.known = new Map();
sys.fitWidgets.pending = new Set();

sys.fitWidgets.queue = function (box) {
	if (!sys.fitWidgets.pending.size) {
		requestAnimationFrame(function () {
			var boxes = [...sys.fitWidgets.pending];
			sys.fitWidgets.pending.clear();
			boxes.forEach(sys.fitWidgets.layout);
		});
	}
	sys.fitWidgets.pending.add(box);
};

sys.fitWidgets.columns = function (box) {
	var css = getComputedStyle(box);
	var gap = parseFloat(css.columnGap) || 0;
	var count = parseInt(css.columnCount, 10) || Infinity;
	var width = parseFloat(css.columnWidth);
	var fit = width ? Math.max(1, Math.floor((box.clientWidth + gap) / (width + gap))) : Infinity;
	var n = Math.min(count, fit);
	return n === Infinity ? 1 : n;
};

sys.fitWidgets.layout = function (box) {
	if (!box.isConnected || !box.clientWidth) {
		return;
	}
	var cards = [...box.children].filter(card => getComputedStyle(card).display !== 'none');
	var n = sys.fitWidgets.columns(box);
	if (n < 2) {
		box.classList.remove('is-masonry');
		cards.forEach(card => card.style.removeProperty('grid-area'));
		return;
	}
	cards.forEach(card => sys.fitWidgets.cards.observe(card));
	var keys = cards.map((card, index) => card.dataset.widget || card.id || 'card' + index);
	var signature = n + '|' + keys.join(',');
	var plan = sys.fitWidgets.known.get(signature);
	var height = card => Math.ceil(card.getBoundingClientRect().height + (parseFloat(getComputedStyle(card).marginBottom) || 0));
	if (!plan) {
		plan = {};
		var tops = new Array(n).fill(0);
		cards.forEach(function (card, index) {
			var h = height(card);
			if (card.classList.contains('is-wide')) {
				tops.fill(Math.max(...tops) + h);
				return;
			}
			var col = tops.indexOf(Math.min(...tops));
			plan[keys[index]] = col;
			tops[col] += h;
		});
		sys.fitWidgets.known.set(signature, plan);
	}
	box.style.setProperty('--cols', n);
	box.classList.add('is-masonry');
	var top = new Array(n).fill(0);
	cards.forEach(function (card, index) {
		var h = height(card);
		if (card.classList.contains('is-wide')) {
			var row = Math.max(...top);
			card.style.gridArea = (row + 1) + ' / 1 / span ' + h + ' / -1';
			top.fill(row + h);
			return;
		}
		var col = plan[keys[index]] ?? 0;
		card.style.gridArea = (top[col] + 1) + ' / ' + (col + 1) + ' / span ' + h;
		top[col] += h;
	});
};

sys.fitWidgets.observer = new ResizeObserver(function (entries) {
	entries.forEach(entry => sys.fitWidgets.queue(entry.target));
});

sys.fitWidgets.cards = new ResizeObserver(function (entries) {
	entries.forEach(entry => entry.target.parentElement && sys.fitWidgets.queue(entry.target.parentElement));
});
