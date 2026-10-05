/* global window,sys,settings,l,p,t */
function insertHtmlAtCursor(html) {
	var range, node;
	if (window.getSelection && window.getSelection().getRangeAt) {
		range = window.getSelection().rangeCount > 0 ? window.getSelection().getRangeAt(0) : false;
		if (!range) {
			return false;
		}
		if ($(range.startContainer.parentNode).closest('.editor').length > 0 || $(range.startContainer.parentNode).hasClass('editor')) {
			node = range.createContextualFragment(html);
			range.insertNode(node);
		} else {
			return 'nodeditor';
		}
	} else if (document.selection && document.selection.createRange) {
		document.selection.createRange().pasteHTML(html);
	} else {
		return false;
	}
	return true;
}

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


function insertTable(event) {
	var $parent = $(event.data.target).closest('[contenteditable="true"]');
	insertHtmlAtCursor("<table><tr><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td></tr></table>");
	$parent.trigger('input');
}

function removeTable(e) {
	var editor = $(e.data.elements).parents('div.editor');
	$(e.data.elements).parents('table').remove();
	editor.trigger('input');
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
			console.log(html);
			$(html).insertAfter($tr);
			break;
		case 'insertBefore':
			var columns = $tr.children('td').length;
			var i = 1;
			var html = document.createElement('tr');
			while (i++ <= columns) {
				$(html).append('<td>New Row</td>');
			}
			console.log(html);
			$(html).insertBefore($tr);
			break;
	}
	editor.trigger('input');
}

function editColumns(e) {
	var $td = $(e.data.elements);

	var editor = $td.parents('div.editor');
	var $table = $td.parents('table');
	var selectedIndex = $td.index();
	console.log($table.find('tr').html());
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
	editor.trigger('input');
}

function formatImage(event) {
	var $targetDomObj = $(event.data.target);
	if ($targetDomObj.data('format') == 'original') {
		alert("Original Format cannot be edited");
	} else {
		var href = settings.siteRoot + "cms/pages/editor/file/?target_id=" + $targetDomObj.data('node_id') + "&format=" + $targetDomObj.data('format') + "";
		sys.core.server.sendCall({
			method: 'get',
		}, href);
	}
}

function HTMLeditorHTMLMode(event) {
	var data = {};
	var $targetDomObj = $(event.data.target);
	var dataSourceSelector = 'div[data-field][contenteditable=true]';
	if (!$targetDomObj.is(dataSourceSelector)) { // find data source
		$targetDomObj = $targetDomObj.parents(dataSourceSelector);
		if ($targetDomObj.length === 0) {
			return;
		}
	}
	data = $targetDomObj.data();
	href = settings.siteRoot + "cms/pages/editor/htmlmode/?id=" + data.node_id + "&field=" + data.field;

	sys.core.server.sendCall({
		method: 'post',
		post: data,
	}, href);
}


function changeHTMLeditorImage(event) {
	var $parent = $(event.data.target).closest('[contenteditable="true"]');
	var newFormat = event.data.imageFormat;
	var src = event.data.target.getAttribute('src');
	var oldFormat = event.data.target.getAttribute('data-format');
	var image = $(event.data.target);

	if (oldFormat === '') {
		oldformat = src.match(/^.*\/var\/formats\/([^\/]*)/);
		if (oldformat === null) {
			oldformat = 'original';
		} else {
			oldformat = oldformat[1];
		}
	}

	if (newFormat === 'original') {
		var newSrc = src.replace('formats/' + oldFormat, newFormat);
	} else if (oldFormat === 'original' && newFormat !== 'original') {
		var newSrc = src.replace(oldFormat, 'formats/' + newFormat);
	} else {
		var newSrc = src.replace(oldFormat, newFormat);
	}

	var fileExt = newSrc.split('.').pop();

	if (newFormat === 'original') {
		newSrc = newSrc.replaceAll('.webp', '');
		var originalWebp = newSrc.split(".");
		originalWebp = originalWebp[originalWebp.length - 1].length === 3 ? false : true;
		if (originalWebp) {
			newSrc += '.webp';
		}
	} else if (!fileExt.includes('webp')) {
		newSrc += '.webp';
	}

	$(image).attr("src", newSrc);
	$(image).attr("data-format", newFormat);
	$parent.trigger('input');
}

function createHTMLeditorLink(event) {
	// $(event.data.target).append("<div class='insertHTMLeditorPlaceholder'></div>");
	var element = event.data.target.nodeName;
	var mode = "new";
	var href;
	var selectedText = "";
	var sel, range, clickedElement, content;
	if (empty(getSelText())) {
		selectedText = $(event.data.target).text();
	} else {
		selectedText = getSelText();
	}

	if ($(event.data.target).parent().prop("tagName") === "A") {
		mode = "edit";
		clickedElement = $(event.data.target).parent();
	}

	if ($(event.data.target).prop("tagName") === "A") {
		mode = "edit";
		clickedElement = $(event.data.target);
	}

	var data = {};

	var elementMode = element + "-" + mode;
	// l(elementMode,clickedElement[0]);
	switch (elementMode) {
		case "IMG-new":
			data.values = {
				image_node_id: $(event.data.target).attr('data-node_id'),
			};
			href = settings.siteRoot + "cms/pages/editor/editLink/";
			break;

		case "IMG-edit":
			data.values = {
				image_node_id: $(event.data.target).attr('data-node_id'),
				classNames: clickedElement.attr('class'),
				download: clickedElement.attr('download'),
				target: clickedElement.attr('target'),
				href: clickedElement.attr('href'),
				olink: clickedElement[0].outerHTML
			};
			href = settings.siteRoot + "cms/pages/editor/editLink/";
			break;

		case "A-edit":
			data.values = {
				text: selectedText,
				classNames: clickedElement.attr('class'),
				download: clickedElement.attr('download'),
				target: clickedElement.attr('target'),
				href: clickedElement.attr('href'),
				olink: clickedElement[0].outerHTML
			};
			href = settings.siteRoot + "cms/pages/editor/editLink/";
			break;

		default:
			if (empty(selectedText)) {
				href = settings.siteRoot + "cms/pages/editor/insertLink/";
			} else {
				href = settings.siteRoot + "cms/pages/editor/insertLink/";
				data.values = {
					text: selectedText,
					olink: selectedText
				};
			}
			break;
	}

	if (empty(selectedText)) {
		$(event.data.target).append("<span class='insertHTMLeditorPlaceholder'></span>");
	} else {
		sel = window.getSelection();
		range = sel.getRangeAt(0);
		content = range.toString();
		range.deleteContents();
		insertHtmlAtCursor("<span class='insertHTMLeditorPlaceholder'>" + content + "</span>");
	}
	sys.core.server.sendCall({
		method: 'post',
		post: data.values,
	}, href);
}

function removeAnchor(event) {
	var $anchor = $(event.data.target);
	var $parent = $(event.data.target).closest('[contenteditable="true"]');
	if ($anchor.prop('tagName') !== 'A') {
		$anchor = $(event.data.target).parent('a');
	}
	$anchor.replaceWith($anchor.html());
	$parent.trigger('input');
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
	l($("span.insertHTMLeditorPlaceholder").text());
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

function setAttribute(e) {
	var $parent = $(e.data.target).closest('[contenteditable="true"]');
	var attribute = e.data.attr;
	var val = e.data.val;
	$(e.data.target).attr(attribute, val);
	$parent.trigger('input');
}

function removeAttribute(e) {
	var $parent = $(e.data.target).closest('[contenteditable="true"]');
	var attribute = e.data.attr;
	$(e.data.target).removeAttr(attribute);
	$parent.trigger('input');
}

function removeImageFromHTMLeditor(event) {
	var $parent = $(event.data.target).closest('[contenteditable="true"]');
	$(event.data.target).remove();
	$parent.trigger('input');
}
