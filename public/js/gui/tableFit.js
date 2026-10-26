/* global sys */
/**
 * On a phone every table is one stack of cards (24_table.css). A row shows
 * its name until it is tapped. Desktop keeps the table.
 */
sys.fitTables = function ($set) {
	$set.findInSet('.tableScroll').each(function () {
		sys.fitTables.fit(this);
	});
};

sys.fitTables.known = new Map();

// Phone: every table is the same stack of cards, closed to one line until
// the row is tapped. Desktop stays a table, so a drag never toggles a
// scrollbar on the parent.
sys.fitTables.fit = function (box) {
	var phone = window.matchMedia('(max-width: 1024px)').matches;
	box.classList.toggle('stacked', phone);
	box.classList.toggle('is-foldable', phone);
	if (!phone) return;
	var heads = [];
	var table = box.querySelector(':scope > table');
	if (!table) return;
	table.querySelectorAll(':scope > thead th').forEach(function (th, index) {
		heads[index] = (th.textContent || '').trim();
	});
	table.querySelectorAll(':scope > tbody > tr').forEach(function (row) {
		Array.prototype.forEach.call(row.children, function (cell, index) {
			if (cell.tagName !== 'TD' || !heads[index]) return;
			if (cell.classList.contains('icons') || cell.querySelector('i[data-drag_type], .userStatus')) return;
			cell.dataset.label = heads[index];
		});
	});
};

if (!sys.fitTables.media) {
	sys.fitTables.media = window.matchMedia('(max-width: 1024px)');
	sys.fitTables.media.addEventListener('change', function () {
		document.querySelectorAll('.tableScroll').forEach(sys.fitTables.fit);
	});
}

sys.fitTables.observer = new ResizeObserver(function () {});

document.addEventListener('click', function (event) {
	var row = event.target.closest && event.target.closest('.tableScroll.stacked.is-foldable > table > tbody > tr');
	if (!row || !window.matchMedia('(max-width: 1024px)').matches) {
		return;
	}
	if (event.target.closest('a, button, input, select, textarea, label, [data-drag_type]')) {
		return;
	}
	row.classList.toggle('is-open');
});
