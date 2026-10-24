/* global sys */
/**
 * A table stays a table while its columns fit; when they do not, its rows
 * become cards (.tableScroll.stacked, 24_table.css): every cell on its own
 * line, labelled with the head of its column. Measured per table, no
 * breakpoint - a two-column table stays a table where nine inputs would
 * be squeezed.
 *
 * The width the table needs is taken while it is a table; stacked, it is
 * only compared - switching back and forth to measure would shorten the
 * page for a moment and lose the scroll position.
 *
 * A rebuild (a drop, a save) brings the table anew, out of the document:
 * it takes the last decision for a table of its kind at once, before the
 * layer puts the scroll position back.
 */
sys.fitTables = function ($set) {
	$set.findInSet('.tableScroll').each(function () {
		var box = this;
		var heads = box.querySelectorAll('thead th');
		box.querySelectorAll('tbody tr').forEach(function (row) {
			Array.prototype.forEach.call(row.children, function (cell, index) {
				var head = heads[index] && heads[index].textContent.trim();
				if (head) {
					cell.dataset.label = head;
				}
			});
		});
		box.fitKey = (box.querySelector('table') || box).className + '|' + Array.prototype.map.call(heads, head => head.textContent.trim()).join('|');
		var known = sys.fitTables.known.get(box.fitKey);
		if (known) {
			box.needWidth = known.needWidth;
			box.classList.toggle('stacked', known.stacked);
		}
		sys.fitTables.observer.observe(box);
	});
};

sys.fitTables.known = new Map();

sys.fitTables.fit = function (box) {
	var width = box.clientWidth;
	if (!width || width === box.fitWidth) {
		return;
	}
	box.fitWidth = width;
	if (!box.classList.contains('stacked')) {
		box.needWidth = box.scrollWidth;
	}
	box.classList.toggle('stacked', box.needWidth > width + 1);
	sys.fitTables.known.set(box.fitKey, {needWidth: box.needWidth, stacked: box.classList.contains('stacked')});
};

sys.fitTables.observer = new ResizeObserver(function (entries) {
	entries.forEach(function (entry) {
		sys.fitTables.fit(entry.target);
	});
});
