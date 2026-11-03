/* global sys */
/**
 * On a phone every table is a list of accordion rows (24_table.css). A row shows
 * its name until it is tapped. Desktop keeps the table.
 */
sys.fitTables = function ($set) {
	$set.findInSet('.tableScroll, .schemaScroll').each(function () {
		sys.fitTables.fit(this);
	});
};

sys.fitTables.known = new Map();

// Phone: every table is the same list of accordion rows, closed to one line until
// the row is tapped. Desktop stays a table, so a drag never toggles a
// scrollbar on the parent.
sys.fitTables.fit = function (box) {
	var phone = !box.hasAttribute('data-table-scroll') && window.matchMedia('(max-width: 767px)').matches;
	box.classList.toggle('stacked', phone);
	box.classList.toggle('is-foldable', phone);
	box.querySelectorAll('[data-table-fold]').forEach(function (button) {
        if (!phone) button.remove();
    });
	sys.fitTables.edge(box);
	if (!sys.fitTables.known.has(box)) {
		sys.fitTables.known.set(box, true);
		sys.fitTables.observer.observe(box);
	}
    if (!phone) return;
    if (box.matches('.schemaScroll')) {
        box.querySelectorAll('tbody.schemaCard > tr:not(.schemaCardHead,.schemaCardCols,.schemaCardNote)').forEach(function(row) {
            var cell=row.querySelector('td');
            if (!cell || cell.querySelector('[data-table-fold]')) return;
            var button=document.createElement('button'); button.type='button'; button.className='button transparent icononly tableFoldToggle'; button.dataset.tableFold='';
            button.setAttribute('aria-label','Details: '+cell.textContent.trim()); button.setAttribute('aria-expanded',String(row.classList.contains('is-open')));
            button.innerHTML='<i class="cmsIcon cmsIcon-chevron-down" aria-hidden="true"></i>'; cell.append(button);
            [...row.cells].forEach(function(item,index) { if(index===1)item.dataset.label='Database'; if(index===2)item.dataset.label='Model'; });
        });
        return;
    }
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
        const scalarControl = table.matches('.scalars') && row.cells.length === 2 ? row.cells[1] : null;
        if (scalarControl?.querySelector(':scope > .projectSwitch:only-child')) scalarControl.dataset.tableInline = '';
        // Keep native table semantics and provide a separate, named toggle.
        var hiddenCells = [...row.children].filter(function (cell) {
            return cell.tagName === 'TD' && !cell.matches(':first-child, [data-table-inline], [data-table-desktop-only], :has(> .userStatus, > i[data-drag_type]), :has(> .userStatus, > i[data-drag_type]) + td, .icons, .projectRemove, .schemaApply');
        });
        var heading = [...row.children].find(function (cell) {
            return cell.tagName === 'TD' && !cell.querySelector('.userStatus, i[data-drag_type]');
        });
        // Selection checkboxes stay available; the subject is the card heading.
        if (heading?.matches('.auditCheck')) heading = heading.nextElementSibling;
        if (!heading) return;
        row.querySelectorAll('[data-table-heading]').forEach(function (cell) { delete cell.dataset.tableHeading; });
        heading.dataset.tableHeading = '';
        if (heading.previousElementSibling?.matches('.auditCheck')) heading.previousElementSibling.dataset.tableSelection = '';
        if (!hiddenCells.some(function (cell) { return cell.textContent.trim() || cell.querySelector('input, select, textarea, button, a, img, svg'); })) {
            row.querySelector('[data-table-fold]')?.remove();
            return;
        }
        var toggle = row.querySelector('[data-table-fold]');
        if (!toggle) {
            toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.dataset.tableFold = '';
            toggle.className = 'button transparent icononly tableFoldToggle';
            toggle.setAttribute('aria-label', 'Details: ' + (heading.querySelector('span')?.firstChild?.textContent || heading.textContent).trim());
            toggle.innerHTML = '<i class="cmsIcon cmsIcon-chevron-down" aria-hidden="true"></i>';
            (row.querySelector(':scope > :is(.icons, .projectRemove, .schemaApply)') || heading).append(toggle);
        }
        toggle.setAttribute('aria-expanded', String(row.classList.contains('is-open')));
	});
};

if (!sys.fitTables.media) {
	sys.fitTables.media = window.matchMedia('(max-width: 767px)');
	sys.fitTables.media.addEventListener('change', function () {
		document.querySelectorAll('.tableScroll, .schemaScroll').forEach(sys.fitTables.fit);
	});
}

sys.fitTables.edge = function (box) {
	if (!box) return;
	var table = box.querySelector(':scope > table');
	var extra = table ? Math.max(table.offsetWidth, table.scrollWidth) - box.clientWidth : 0;
	var can = !box.classList.contains('stacked') && extra > 1;
	if (box.classList.contains('canScrollX') !== can) {
		box.classList.toggle('canScrollX', can);
	}
};

sys.fitTables.observer = new ResizeObserver(function (entries) {
	entries.forEach(function (entry) {
		sys.fitTables.edge(entry.target);
	});
});

document.addEventListener('click', function (event) {
	var row = event.target.closest && event.target.closest('.tableScroll.stacked.is-foldable > table > tbody > tr, .schemaScroll.stacked.is-foldable > table > tbody > tr');
	if (!row) {
		return;
	}
	// The grip is the place that opens the row. Fields and the template
	// list keep their own tap. A drag in progress must not toggle it.
	if (document.body.classList.contains('dragging') || document.body.classList.contains('settingDragging')) {
		return;
	}
	if (!event.target.closest('[data-table-fold]') && event.target.closest('a, button, input, select, textarea, label, summary, details')) {
		return;
	}
    var toggle = row.querySelector('[data-table-fold]');
    if (!toggle) return;
	row.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(row.classList.contains('is-open')));
});
