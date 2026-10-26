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

/** The menu commands, named as execCommand named them, done by HTMLeditor.format.js. */
function formatHTMLeditor(event) {
	switch (event.data.element) {
		case 'bold':
			return wysiwyg.toggle('STRONG');
		case 'italic':
			return wysiwyg.toggle('EM');
		case 'underline':
			return wysiwyg.toggle('U');
		case 'strikethrough':
			return wysiwyg.toggle('S');
		case 'formatBlock':
			return wysiwyg.setBlock(event.data.tag.toUpperCase());
		case 'insertUnorderedList':
			return wysiwyg.toggleList('UL');
		case 'insertorderedlist':
			return wysiwyg.toggleList('OL');
		case 'removeformat':
			return wysiwyg.clearFormat();
	}
}


function insertTable() {
	wysiwyg.insertTable();
}

function tableHTMLeditor(event) {
	wysiwyg.table(event.data.action, event.data.cell);
}

function undoHTMLeditor(event) {
	wysiwyg.step(wysiwygOf(event.data.target), -1);
}

function redoHTMLeditor(event) {
	wysiwyg.step(wysiwygOf(event.data.target), 1);
}

/** The class field takes the place of the buttons in the bar. */
function editBlockClass(event) {
	var block = wysiwyg.blockFor(event.data.target);
	var menu = document.querySelector('.HTMLeditorMenu');
	if (!block || !menu) {
		return;
	}
	var used = [];
	var same = /^(IMG|VIDEO|IFRAME|TABLE)$/.test(block.nodeName) ? block.nodeName : 'p,h1,h2,h3,h4,h5,h6,ul,ol';
	document.querySelectorAll('.editor[contenteditable=true] :is(' + same + ')[class]').forEach(function (el) {
		el.classList.forEach(function (name) {
			used.indexOf(name) < 0 && used.push(name);
		});
	});
	var form = document.createElement('form');
	form.className = 'HTMLeditorClass';
	var kind = {P: 'paragraph', UL: 'list', OL: 'list', TABLE: 'table', IMG: 'image', VIDEO: 'video', IFRAME: 'embed'}[block.nodeName] || 'headline';
	form.innerHTML = '<input name="className" list="HTMLeditorClasses" placeholder="CSS classes of this ' + kind + '" autocapitalize="off" autocomplete="off" spellcheck="false">'
		+ '<datalist id="HTMLeditorClasses"></datalist>'
		+ '<button type="submit" title="Apply"><i class="fa-regular fa-check"></i></button>';
	used.sort().forEach(function (name) {
		form.querySelector('datalist').appendChild(new Option(name));
	});
	var input = form.querySelector('input');
	input.value = block.getAttribute('class') || '';
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		wysiwyg.setBlockClass(block, input.value);
		closeAllMenus();
	});
	input.addEventListener('keydown', function (e) {
		e.key === 'Escape' && closeAllMenus();
	});
	menu.querySelector('ul').replaceWith(form);
	placeEditorMenu(menu);
	input.focus();
	return false;
}

/** "media:293?format=collection" from data-ref-src, set beside the url by Cms\Refs. */
function editorMediaRef(element) {
	var ref = (element && (element.getAttribute('data-ref-src') || element.getAttribute('data-ref-href'))) || '';
	var match = ref.match(/^media:(\d+)(?:\?format=([\w-]+))?/);
	return match ? {node_id: match[1], format: match[2] || 'original'} : null;
}

/** The editor of the image or video, in the format it shows now. */
function formatImage(event) {
	var el = event.data.target;
	var media = editorMediaRef(el);
	var id = media ? media.node_id : el.dataset.node_id;
	if (!id) {
		return;
	}
	sys.core.server.sendCall({method: 'get'}, settings.siteRoot + 'cms/pages/editor/file/?target_id=' + id + '&format=' + encodeURIComponent(media ? media.format : mediaFormatOf(el)));
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


/** var/original/… or var/formats/<format>/… of an image or video of the media library. */
function mediaFormatParts(el) {
	var m = (el.getAttribute('src') || '').split('?')[0].match(/^(.*\/var\/)(original|formats\/([^\/]+))\/(\d{3}\/\d{2}\/)([^\/]+)$/);
	return m && {base: m[1], format: m[3] || 'original', folders: m[4], file: m[5]};
}

function mediaFormatOf(el) {
	var media = editorMediaRef(el);
	if (media) {
		return media.format;
	}
	var parts = mediaFormatParts(el);
	return parts ? parts.format : el.dataset.format || 'original';
}

/** A missing image format is made by media.php, a missing video format by the project (the original plays meanwhile). */
function changeMediaFormat(event) {
	var el = event.data.target;
	var format = event.data.format;
	var parts = mediaFormatParts(el);
	if (!parts || parts.format === format) {
		return;
	}
	var video = el.nodeName === 'VIDEO';
	if (parts.format === 'original') {
		el.dataset.original = parts.file;
	}
	var original = el.dataset.original || (video ? parts.file : parts.file.replace(/(\.\w+)\.webp$/i, '$1'));
	var src = parts.base + 'original/' + parts.folders + original;
	if (format !== 'original') {
		src = parts.base + 'formats/' + format + '/' + parts.folders + (video ? original.replace(/\.\w+$/, '.mp4') : original.replace(/\.webp$/i, '') + '.webp');
	}
	el.setAttribute('src', src);
	el.dataset.format = format;
	var media = editorMediaRef(el);
	if (media) {
		// the url only shows it; what is saved is the reference
		el.setAttribute('data-ref-src', 'media:' + media.node_id + (format === 'original' ? '' : '?format=' + format));
	}
	wysiwyg.changed(el.closest('[contenteditable="true"]'));
}

/** Opens the link dialog for the link, image or text under the target. */
function createHTMLeditorLink(event) {
	var state = wysiwyg.linkFor(event.data.target);
	if (!state) {
		return;
	}
	var imageRef = state.image && editorMediaRef(state.image);
	var post = state.image ? {image_node_id: imageRef ? imageRef.node_id : state.image.dataset.node_id} : {text: state.text};
	var anchor = state.anchor;
	if (anchor) {
		post.href = anchor.getAttribute('data-ref-href') || anchor.getAttribute('href') || '';
		post.classNames = anchor.getAttribute('class') || '';
		post.download = anchor.hasAttribute('download') ? 1 : '';
		post.target = anchor.getAttribute('target') || '';
	}
	sys.core.server.sendCall({
		method: 'post',
		post: post
	}, settings.siteRoot + 'cms/pages/editor/editLink/');
}

/** "Save" in the link dialog: closing renders the page anew, so the field is saved first. */
function applyHTMLeditorLink(button) {
	var box = button.closest('.cmslightbox');
	var field = function (name) {
		return box.querySelector('[name=' + name + ']');
	};
	var close = function () {
		box.querySelector('header a').click();
	};
	var editor = wysiwyg.applyLink({
		text: field('linkTitle') ? field('linkTitle').value.replace(/\s+/g, ' ').trim() : '',
		href: linkHref(field('linkType').value, field('linkUrl').value),
		className: field('linkClass').value,
		newTab: field('target').value === '_blank',
		download: field('download').checked
	});
	if (!editor) {
		return close();
	}
	// touch: a caret left in the field keeps the keyboard up after the dialog, and the bar away
	if (editorPointer === 'touch') {
		getSelection().removeAllRanges();
		editor.blur();
	}
	button.classList.add('loading');
	var save = new sys.core.event({type: 'input', target: editor, currentTarget: editor});
	save.collectElementData();
	save.dispatch().done(close);
}

function linkHref(type, address) {
	address = address.replace(/[\r\n]+/g, '').trim();
	if (type === 'url') {
		address = address.replace(/ /g, '%20');
	}
	if (address === '' || /^[a-z][\w+.-]*:/i.test(address)) {
		return address;
	}
	if (type === 'url') {
		if (/^[^@\/]+@[^@\/]+\.\w+$/.test(address)) {
			return 'mailto:' + address;
		}
		return /^[\/#?.]/.test(address) ? address : 'https://' + address;
	}
	return type + ':' + (type === 'mailto' ? address : address.replace(/[^\d+*#]/g, ''));
}

function linkTypeChanged(select) {
	select.nextElementSibling.placeholder = select.selectedOptions[0].dataset.hint;
	select.nextElementSibling.focus();
}

/** A tab of the link dialog: an address, a page or a file. */
function linkTab(tab) {
	var target = tab.closest('.linkTarget');
	target.querySelectorAll('[role=tab]').forEach(function (other) {
		other.setAttribute('aria-selected', other === tab ? 'true' : 'false');
	});
	target.querySelectorAll('.linkPane').forEach(function (pane) {
		pane.hidden = pane.dataset.pane !== tab.dataset.tab;
	});
	var field = target.querySelector('.linkPane:not([hidden]) :is(textarea, input)');
	field && field.focus();
}

/** A page or a file picked in the link dialog becomes the address; an empty text takes its name. */
function linkPicked(button) {
	var main = button.closest('main');
	main.querySelectorAll('.linkPane button.selected').forEach(function (other) {
		other.classList.remove('selected');
	});
	button.classList.add('selected');
	main.querySelector('[name=linkType]').value = 'url';
	main.querySelector('[name=linkUrl]').value = button.dataset.href;
	var text = main.querySelector('[name=linkTitle]');
	if (text && text.value.trim() === '') {
		text.value = button.dataset.label;
	}
}

function linkFormatChosen(select) {
	var main = select.closest('main');
	main.querySelector('[name=linkType]').value = 'url';
	main.querySelector('[name=linkUrl]').value = select.value;
}

function removeAnchor(event) {
	wysiwyg.unlink(event.data.target);
}

function openAnchor(event) {
	var anchor = event.data.target.closest('a');
	anchor && window.open(anchor.href, '_blank', 'noopener');
}

function setAttribute(e) {
	var $parent = $(e.data.target).closest('[contenteditable="true"]');
	var attribute = e.data.attr;
	var val = e.data.val;
	$(e.data.target).attr(attribute, val);
	wysiwyg.changed($parent[0]);
}

function removeAttribute(e) {
	var $parent = $(e.data.target).closest('[contenteditable="true"]');
	var attribute = e.data.attr;
	$(e.data.target).removeAttr(attribute);
	wysiwyg.changed($parent[0]);
}

function removeImageFromHTMLeditor(event) {
	var node = event.data && event.data.target;
	var media = node && node.closest ? (node.closest('img, video, iframe') || (node.matches && node.matches('img, video, iframe') ? node : null)) : null;
	var editor = media && media.closest('[contenteditable="true"]');
	if (!media || !editor) {
		return false;
	}
	var anchor = media.parentElement && media.parentElement.matches('a') ? media.parentElement : null;
	var range = document.createRange();
	range.selectNode(media);
	range.deleteContents();
	if (anchor && editor.contains(anchor) && !anchor.querySelector('img, video, iframe') && !/\S/.test(anchor.textContent || '')) {
		anchor.remove();
	}
	getSelection().removeAllRanges();
	wysiwyg.changed(editor);
}
