/**
 * The bar of the WYSIWYG fields: what the caret, the selection or the element
 * under the pointer offers. Items marked sep start a new group, reopen keeps
 * the bar after the command, so formatting can go on.
 */
function HTMLeditorRightClick(clicktype, event, elements) {
	var target = event.target;
	var editor = wysiwygOf(target);
	var anchorNode = getSelection().anchorNode || target;
	var at = anchorNode.nodeType === 1 ? anchorNode : anchorNode.parentElement;
	var inLink = !!at.closest('[contenteditable] a');
	var blockTag = (at.closest('p,h1,h2,h3,h4,h5,h6,li') || {}).nodeName;
	var listTag = (at.closest('ul,ol') || {}).nodeName;
	var param = {target: target};
	var cmd = function (title, icon, func, extra) {
		return Object.assign({title: title, className: 'fa-regular fa-' + icon, func: func, param: param}, extra);
	};
	var format = function (title, icon, data, extra) {
		return Object.assign({title: title, className: 'fa-regular fa-' + icon, func: 'formatHTMLeditor', param: data, reopen: true}, extra);
	};
	var sub = function (title, func, data, selected) {
		return {title: title, func: func, param: data, selected: selected, reopen: func !== 'createHTMLeditorLink'};
	};

	var history = editor ? {
		undo: cmd('Undo', 'arrow-rotate-left', 'undoHTMLeditor', {disabled: !wysiwyg.canStep(editor, -1), reopen: true}),
		redo: cmd('Redo', 'arrow-rotate-right', 'redoHTMLeditor', {disabled: !wysiwyg.canStep(editor, 1), reopen: true})
	} : {};
	var inline = {
		bold: format('Bold', 'bold', {element: 'bold'}, {selected: wysiwyg.active('STRONG'), sep: true}),
		italic: format('Italic', 'italic', {element: 'italic'}, {selected: wysiwyg.active('EM')}),
		underline: format('Underline', 'underline', {element: 'underline'}, {selected: wysiwyg.active('U')}),
		strikethrough: format('Strikethrough', 'strikethrough', {element: 'strikethrough'}, {selected: wysiwyg.active('S')})
	};
	var headings = {};
	[1, 2, 3, 4, 5, 6].forEach(function (n) {
		headings['h' + n] = sub('Headline ' + n, 'formatHTMLeditor', {element: 'formatBlock', tag: 'h' + n}, blockTag === 'H' + n);
	});
	var blocks = {
		headlines: {title: 'Headline', className: 'fa-regular fa-heading', selected: /^H\d$/.test(blockTag), subs: headings, sep: true},
		paragraph: format('Paragraph', 'paragraph', {element: 'formatBlock', tag: 'p'}, {selected: blockTag === 'P'}),
		list: {title: 'List', className: 'fa-regular fa-list-ul', selected: !!listTag, subs: {
			ul: sub('Bullet points', 'formatHTMLeditor', {element: 'insertUnorderedList'}, listTag === 'UL'),
			ol: sub('Numbered', 'formatHTMLeditor', {element: 'insertorderedlist'}, listTag === 'OL')
		}}
	};
	var link = {
		insertAnchor: cmd(inLink ? 'Edit link' : 'Insert link', inLink ? 'pen' : 'link', 'createHTMLeditorLink', {selected: inLink, sep: true}),
		removeAnchor: cmd('Remove link', 'link-slash', 'removeAnchor', {active: inLink})
	};
	var classItem = function (el, sep) {
		var name = el && el.getAttribute('class');
		return cmd(name ? 'CSS classes: ' + name : 'CSS classes', 'tag', 'editBlockClass', {selected: !!name, active: !!el, sep: sep, param: {target: el || target}});
	};
	var blockClass = {blockClass: classItem(wysiwyg.blockFor(at), true)};
	var html = {htmlMode: cmd('HTML mode', 'brackets-curly', 'HTMLeditorHTMLMode')};
	var clear = {clear: format('Clear format', 'text-slash', {element: 'removeformat'})};
	var removeMedia = {clear: cmd('Remove from page', 'trash', 'removeImageFromHTMLeditor', {sep: true})};
	var formatMenu = function (kind, usable) {
		var current = mediaFormatOf(target);
		var subs = {original: sub('Original', 'changeMediaFormat', {target: target, format: 'original'}, current === 'original')};
		$.each(editorFormats[kind], function (name, label) {
			subs[name] = sub(label, 'changeMediaFormat', {target: target, format: name}, current === name);
		});
		return {title: 'Format', className: 'fa-regular fa-' + (kind === 'video' ? 'film' : 'image'), active: usable && Object.keys(subs).length > 1, subs: subs};
	};
	var toggleAttr = function (attr, title, icon) {
		var on = target.hasAttribute(attr);
		return cmd(title, icon, on ? 'removeAttribute' : 'setAttribute', {selected: on, param: {attr: attr, val: attr, target: target}});
	};

	var items;
	// a text field is one fixed tag: its words can be formatted, the tag stays
	if (editor && wysiwyg.inline(editor) && (clicktype === 'selectedWord' || clicktype === 'selectedBlock')) {
		clicktype = 'inline';
	}
	switch (clicktype) {
		case 'inline':
			items = Object.assign({}, history, inline, link, clear);
			break;
		case 'selectedWord':
			items = Object.assign({}, history, inline, blocks, link, clear);
			break;
		case 'selectedBlock':
			items = Object.assign({}, history, blocks, link, blockClass, {insertTable: cmd('Insert table', 'table', 'insertTable')}, html, clear);
			break;
		case 'table':
			var cell = elements[0] || elements;
			var tableCmd = function (title, action) {
				return sub(title, 'tableHTMLeditor', {action: action, cell: cell});
			};
			items = Object.assign({}, history, {
				row: {title: 'Row', className: 'fa-regular fa-table-rows', sep: true, subs: {
					before: tableCmd('Insert row above', 'rowBefore'),
					after: tableCmd('Insert row below', 'rowAfter'),
					remove: tableCmd('Delete row', 'rowRemove')
				}},
				column: {title: 'Column', className: 'fa-regular fa-table-columns', subs: {
					before: tableCmd('Insert column left', 'colBefore'),
					after: tableCmd('Insert column right', 'colAfter'),
					remove: tableCmd('Delete column', 'colRemove')
				}},
				header: cmd('Header row', 'table-layout', 'tableHTMLeditor', {selected: !!cell.closest('thead'), reopen: true, param: {action: 'header', cell: cell}}),
				removeTable: cmd('Delete table', 'trash', 'tableHTMLeditor', {param: {action: 'remove', cell: cell}})
			}, link, {blockClass: classItem(cell.closest('table'), true)}, html);
			break;
		case 'anchor':
			items = {
				editanchor: cmd('Edit link', 'pen', 'createHTMLeditorLink'),
				openanchor: cmd('Open link', 'arrow-up-right-from-square', 'openAnchor'),
				removeanchor: cmd('Remove link', 'link-slash', 'removeAnchor')
			};
			break;
		case 'image':
			var linked = !!target.closest('a');
			items = Object.assign({
				formats: formatMenu('image', /^image\/(jpe?g|png|webp)$/.test(target.dataset.mimetype)),
				edit: cmd('Crop and adjust', 'crop-simple', 'formatImage'),
				anchor: cmd(linked ? 'Edit link' : 'Insert link', linked ? 'pen' : 'link', 'createHTMLeditorLink', {selected: linked, sep: true}),
				unlink: cmd('Remove link', 'link-slash', 'removeAnchor', {active: linked}),
				blockClass: classItem(target, true)
			}, removeMedia);
			break;
		case 'video':
			items = Object.assign({
				formats: formatMenu('video', !!mediaFormatParts(target)),
				edit: cmd('Edit', 'pen', 'formatImage'),
				controls: toggleAttr('controls', 'Controls', 'sliders'),
				autoplay: toggleAttr('autoplay', 'Autoplay', 'play'),
				muted: toggleAttr('muted', 'Muted', 'volume-slash'),
				loop: toggleAttr('loop', 'Loop', 'arrows-repeat'),
				playsinline: toggleAttr('playsinline', 'Play inline on phones', 'mobile'),
				blockClass: classItem(target, true)
			}, removeMedia);
			items.controls.sep = true;
			break;
		case 'iframe':
			items = Object.assign({edit: cmd('Edit', 'pen', 'formatImage'), blockClass: classItem(target, true)}, removeMedia);
			break;
	}
	items && new contextMenuWW(items, event, elements);
	return false;
}
