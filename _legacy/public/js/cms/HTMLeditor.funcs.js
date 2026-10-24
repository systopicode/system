/* Removed from public/js/cms/HTMLeditor.funcs.js with the execCommand formatting (GUI 0.4). */
function isTextSelected() {
	if (window.getSelection || document.selectio) {
		return true;
	}
	return false;
}

function SelectText(element) {
	var doc = document,
			text = doc.getElementById(element),
			range,
			selection;
	if (doc.body.createTextRange) {
		range = document.body.createTextRange();
		range.moveToElementText(text);
		range.select();
	} else if (window.getSelection) {
		selection = window.getSelection();
		range = document.createRange();
		range.selectNodeContents(text);
		selection.removeAllRanges();
		selection.addRange(range);
	}
}

function formatHTMLeditor(event) {
	var type = event.data.element;
	var tag = event.data.tag;
	if (!isset(tag)) {
		tag = false;
	}
	document.execCommand(type, false, tag);
}

function toggleClassname(e) {
	var editor = $(e.data.elements).parents('div.editor');
	var className = e.data.classname;
	(e.data.elements).each(function () {
		if (className === 'none') {
			$(this).removeAttr('class');
		} else {
			$(this).addClass(className);
		}
	});
	editor.trigger('input');
}

function insertHTMLeditorLink(event) {
	var title = $("input[name=linkTitle]").val();
	var url = $("input[name=linkUrl]").val();
	var linkclass = $("input[name=linkClass]").val();
	var link;
	var newTab = "";
	var download = "";

	if ($("input[name=newTab]").prop('checked')) {
		newTab = "target='_blank'";
	}
	if ($("input[name=download]").prop('checked')) {
		download = "download=''";
	}

	if (isset(linkclass)) {
		linkclass = "class='" + linkclass + "'";
	} else {
		linkclass = "";
	}

	link = " <a " + download + " " + linkclass + " " + newTab + " href='" + url + "'>" + title + "</a>";
	var $parent = $("span.insertHTMLeditorPlaceholder").closest('[contenteditable="true"]');
	if ($("span.insertHTMLeditorPlaceholder").text()) { // selection
		$("span.insertHTMLeditorPlaceholder").replaceWith(link);
	} else { // no selection
		$("span.insertHTMLeditorPlaceholder").parent().replaceWith(link);
	}

	$parent.trigger('input');
}

function insertHTMLeditorImageLink(event) {
	var url = $("input[name=linkUrl]").val();
	var linkclass = $("input[name=linkClass]").val();
	var link;
	var newTab = "";
	var download = "";
	var img = $("span.insertHTMLeditorPlaceholder").parent('img').prop("outerHTML");

	if ($("input[name=newTab]").prop('checked')) {
		newTab = "target='_blank'";
	}
	if ($("input[name=download]").prop('checked')) {
		download = "download=''";
	}

	if (isset(linkclass)) {
		linkclass = "class='" + linkclass + "'";
	} else {
		linkclass = "";
	}

	link = " <a " + download + " " + linkclass + " " + newTab + " href='" + url + "'>" + img + "</a>";

	var $parent = $("span.insertHTMLeditorPlaceholder").parent('img').closest('[contenteditable="true"]');
	$("span.insertHTMLeditorPlaceholder").parent('img').replaceWith(link);

	$parent.trigger('input');
}

function closeLinkLightbox() {
	if ($("span.insertHTMLeditorPlaceholder").text().length > 0) {
		$("span.insertHTMLeditorPlaceholder").replaceWith($("span.insertHTMLeditorPlaceholder").text());
	} else {
		$("span.insertHTMLeditorPlaceholder").remove();
	}
}

function closeHTMLeditorImageLink() {
	alert('deprecated');
}

function closeHTMLeditorLink() {
	alert('deprecated');
}

/* Removed with the table bar (GUI 0.4): the cell menu calls wysiwyg.table. */
function insertTable(event) {
	var $parent = $(event.data.target).closest('[contenteditable="true"]');
	insertHtmlAtCursor("<table><tr><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr></table>");
	wysiwyg.changed($parent[0]);
}

function removeTable(e) {
	var editor = $(e.data.elements).parents('div.editor');
	$(e.data.elements).parents('table').remove();
	wysiwyg.changed(editor[0]);
}

function editRows(e) {
	var $tr = $(e.data.elements).parent('tr');
	var editor = $tr.parents('div.editor');
	var $table = $tr.parents('table');
	switch (e.data.action) {
		case 'remove':
			$tr.remove();
			break;
		case 'duplicate':
			$tr.clone().insertAfter($tr);
			break;
		case 'insertAfter':
			var columns = $tr.children('td').length;
			var i = 1;
			var html = document.createElement('tr');
			while (i++ <= columns) {
				$(html).append('<td>New Row</td>');
			}
			$(html).insertAfter($tr);
			break;
		case 'insertBefore':
			var columns = $tr.children('td').length;
			var i = 1;
			var html = document.createElement('tr');
			while (i++ <= columns) {
				$(html).append('<td>New Row</td>');
			}
			$(html).insertBefore($tr);
			break;
	}
	wysiwyg.changed(editor[0]);
}

function editColumns(e) {
	var $td = $(e.data.elements);

	var editor = $td.parents('div.editor');
	var $table = $td.parents('table');
	var selectedIndex = $td.index();
	switch (e.data.action) {
		case 'remove':
			$table.find('tr').find('td:eq(' + selectedIndex + ')').remove();
			break;

		case 'insertAfter':
			$('<td>New Column</td>').insertAfter($table.find('tr').find('td:eq(' + selectedIndex + ')'));
			break;
		case 'insertBefore':
			$('<td>New Column</td>').insertBefore($table.find('tr').find('td:eq(' + selectedIndex + ')'));
	}
	wysiwyg.changed(editor[0]);
}

