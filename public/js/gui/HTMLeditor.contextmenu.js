/****************************** WYSIWYG BAR ***************************/
// One floating bar for everything in a field. A click shows what was hit:
// the caret its block or link, a selection the text formats, an image, video
// or embed its own commands. The keyboard stays down (inputmode none) until
// "Type" in the bar asks for it - it would cover half the page.
function initContenteditableContextMenus() {
	var selectionTimer, downInEditor = false;

	// the bar opens where the pointer pressed, not at the top of a large element
	var lastPoint = null;
	var pointOf = function (e) {
		return {left: e.clientX, right: e.clientX, top: e.clientY, bottom: e.clientY};
	};
	/** pointed: the bar of the link, image or video itself, whatever the selection holds. */
	var openFor = function (target, rect, pointed) {
		$(target).trigger($.Event('contextmenu', {target: target, anchorRect: rect, pointed: !!pointed}));
	};
	/** The bar for the media it was opened for, or where the caret or the selection is. */
	var show = function (media) {
		if (media && media.nodeType === 1 && media.isConnected && media.matches('img, video, iframe')) {
			return openFor(media, lastPoint || media.getBoundingClientRect());
		}
		var selection = getSelection();
		var editor = selection.rangeCount && wysiwygOf(selection.anchorNode);
		if (!editor) {
			return;
		}
		var range = selection.getRangeAt(0);
		var media = selectedMedia(range);
		if (media) {
			return openFor(media, lastPoint || media.getBoundingClientRect());
		}
		var rects = range.getClientRects();
		var at = selection.anchorNode.nodeType === 1 ? selection.anchorNode : selection.anchorNode.parentElement;
		var rect = range.collapsed ? rects[0] || at.getBoundingClientRect() : range.getBoundingClientRect();
		openFor(at, rect);
	};
	var openBar = function () {
		return document.querySelector('.HTMLeditorMenu');
	};

	// In the editor a film stays on its still. Playback belongs on the website.
	var editorVideo = function (node) {
		var video = node && node.closest && node.closest('video');
		return video && wysiwygOf(video) ? video : null;
	};
	var holdVideo = function (video) {
		if (!video) {
			return;
		}
		video.removeAttribute('controls');
		video.pause();
	};
	document.addEventListener('play', function (e) {
		holdVideo(editorVideo(e.target));
	}, true);
	document.addEventListener('playing', function (e) {
		holdVideo(editorVideo(e.target));
	}, true);
	document.addEventListener('pointerover', function (e) {
		holdVideo(editorVideo(e.target));
	}, true);
	document.addEventListener('pointerdown', function (e) {
		holdVideo(editorVideo(e.target));
		editorPointer = e.pointerType;
		downInEditor = !!wysiwygOf(e.target);
		lastPoint = downInEditor ? pointOf(e) : lastPoint;
		var editor = e.pointerType === 'touch' && wysiwygOf(e.target);
		if (editor && !editor.hasAttribute('data-typing')) {
			editor.setAttribute('inputmode', 'none');
		}
	}, true);
	// a pressed image would be selected, tinted and offered as text
	document.addEventListener('mousedown', function (e) {
		e.button === 0 && e.target.closest && e.target.closest('img, video, iframe') && wysiwygOf(e.target) && e.preventDefault();
	}, true);
	document.addEventListener('focusout', function (e) {
		var editor = wysiwygOf(e.target);
		if (editor && !editor.contains(e.relatedTarget)) {
			editor.removeAttribute('data-typing');
		}
	}, true);
	// iPad: hiding the keyboard keeps the focus - typing ends with it, the next tap brings the bar
	var view = window.visualViewport, keyboardUp = false;
	view && view.addEventListener('resize', function () {
		var covered = window.innerHeight - view.height * view.scale;
		if (covered > 150) {
			keyboardUp = true;
			return;
		}
		if (!keyboardUp) {
			return;
		}
		keyboardUp = false;
		document.querySelectorAll('.editor[data-typing]').forEach(function (editor) {
			editor.removeAttribute('data-typing');
			editor.setAttribute('inputmode', 'none');
		});
	});

	// touch: the tap waits for 'click' - the mousedown it emulates would close a bar opened earlier.
	// A tap is the hover of touch: on a link, image or video it opens their bar.
	document.addEventListener('click', function (e) {
		if (editorVideo(e.target)) {
			e.preventDefault();
		}
		// a link in a field is text to edit, never followed
		wysiwygOf(e.target) && e.target.closest('a') && e.preventDefault();
		var editor = editorPointer === 'touch' && wysiwygOf(e.target);
		if (!editor) {
			return;
		}
		var hit = document.elementFromPoint(e.clientX, e.clientY);
		hit = hit && editor.contains(hit) ? hit : e.target;
		var media = hit.closest('img, video, iframe, a');
		if (media && editor.contains(media)) {
			e.preventDefault();
			return openFor(media.matches('a') && media.querySelector('img') || media, pointOf(e), true);
		}
		if (editor.hasAttribute('data-typing') || !getSelection().isCollapsed) {
			return;
		}
		openFor(hit, pointOf(e));
	}, true);

	document.addEventListener('selectionchange', function () {
		clearTimeout(selectionTimer);
		var current = getSelection();
		if (current.rangeCount && wysiwygOf(current.anchorNode)) {
			editorRange = current.getRangeAt(0).cloneRange();
			if (editorPointer === 'touch' && !current.isCollapsed) {
				selectionTimer = setTimeout(show, 500);
			}
		}
	});
	// mouse: a click places the caret, a drag or double click selects - both show the bar
	document.addEventListener('mouseup', function (e) {
		if (e.button !== 0 || editorPointer === 'touch' || !downInEditor) {
			return;
		}
		var media = e.target.closest('img, video, iframe');
		if (media && wysiwygOf(media)) {
			var point = pointOf(e);
			return setTimeout(function () {
				openFor(media, point);
			});
		}
		openBar() || setTimeout(show);
	});
	document.addEventListener('keyup', function (e) {
		if (e.key === 'Shift' && wysiwygOf(e.target) && !getSelection().isCollapsed) {
			show();
		}
	});
	document.addEventListener('keydown', function (e) {
		if (!/^(Shift|Meta|Control|Alt)$/.test(e.key) && wysiwygOf(e.target) && openBar()) {
			closeAllMenus();
		}
	});
	// the bar stays where it was opened: scrolling the page ends it
	document.addEventListener('scroll', function (e) {
		var bar = openBar();
		bar && !bar.contains(e.target) && closeAllMenus();
	}, true);

	editorBubble = show;
	$("body").on("contextmenu", function (e) {
		var editor = $(e.target).closest('[contenteditable=true]')[0];
		if (!editor) {
			return;
		}
		e.preventDefault();
		var selection = getSelection();
		var el = e.target.closest('img, video, iframe') || (e.pointed && e.target.matches('a') && e.target) || (selection.rangeCount && selectedMedia(selection.getRangeAt(0)));
		var selected = !el && !selection.isCollapsed && selection.rangeCount && editor.contains(selection.anchorNode);
		el = el || (!selected && e.target.closest('a, td, th'));
		el = el && editor.contains(el) ? el : null;
		var type = selected ? 'selectedWord' : {IMG: 'image', VIDEO: 'video', IFRAME: 'iframe', A: 'anchor', TD: 'table', TH: 'table'}[el && el.nodeName] || 'selectedBlock';
		HTMLeditorRightClick(type, e, el ? $(el) : $(e.target));
	});
}

// A tap on the bar takes the selection out of the editor (iOS) before the
// command runs on it: the last range in an editor comes back first.
var editorRange = null;
var editorPointer = 'mouse';
/** The WYSIWYG editor around node - a text field (.plaintext) too, with inline formats only. */
var wysiwygOf = function (node) {
	var el = node && (node.nodeType === 1 ? node : node.parentElement);
	return el && el.closest('.editor[contenteditable=true]');
};
/** The image or video a range holds alone - a click on an image selects it. */
var selectedMedia = function (range) {
	var node = !range.collapsed && range.startContainer === range.endContainer && range.endOffset - range.startOffset === 1
		? range.startContainer.childNodes[range.startOffset] : null;
	return node && node.nodeType === 1 && node.matches('img, video, iframe') ? node : null;
};
var startTyping = function () {
	var editor = editorRange && wysiwygOf(editorRange.startContainer);
	if (!editor) {
		return;
	}
	var range = editorRange;
	closeAllMenus();
	editor.removeAttribute('inputmode');
	// a new focus brings the keyboard; the blur ends any earlier typing
	editor.blur();
	editor.setAttribute('data-typing', '');
	editor.focus({preventScroll: true});
	var selection = getSelection();
	selection.removeAllRanges();
	selection.addRange(range);
};
var restoreEditorRange = function () {
	var selection = getSelection();
	var node = selection.rangeCount && selection.anchorNode;
	var el = node && (node.nodeType === 1 ? node : node.parentElement);
	if (!editorRange || (el && el.closest('[contenteditable=true]'))) {
		return;
	}
	var start = editorRange.startContainer;
	var editor = (start.nodeType === 1 ? start : start.parentElement).closest('[contenteditable=true]');
	if (!editor || !editor.isConnected) {
		return;
	}
	editor.focus({preventScroll: true});
	selection.removeAllRanges();
	selection.addRange(editorRange);
};
var editorBubble = function () {};
var menuCommand = function (func, reopen) {
	return function () {
		if (!func) {
			return;
		}
		restoreEditorRange();
		var result = func.apply(this, arguments);
		if (result !== false) {
			closeAllMenus();
			reopen && editorBubble(arguments[0] && arguments[0].data && arguments[0].data.target);
		}
		return result;
	};
};

/** The part of the window the node can be seen in: its scrolling boxes cut it down. */
var visibleArea = function (node) {
	var view = window.visualViewport;
	var area = view
		? {top: view.offsetTop, left: view.offsetLeft, bottom: view.offsetTop + view.height, right: view.offsetLeft + view.width}
		: {top: 0, left: 0, bottom: window.innerHeight, right: document.documentElement.clientWidth};
	for (var el = node && node.parentElement; el && el !== document.body; el = el.parentElement) {
		var style = getComputedStyle(el);
		if (/auto|scroll/.test(style.overflowY + style.overflowX)) {
			var box = el.getBoundingClientRect();
			area = {top: Math.max(area.top, box.top), left: Math.max(area.left, box.left), bottom: Math.min(area.bottom, box.bottom), right: Math.min(area.right, box.right)};
		}
	}
	return area;
};
/**
 * Puts the bar above its anchor (the point pressed, or the caret) in the
 * visible part of the page, below when there is no room. With selected text on
 * touch the system's own menu (copy, paste …) pops up at the text and can't be
 * turned off, so the bar docks at the edge of the screen away from the text.
 */
var placeEditorMenu = function (menu, rect, target) {
	rect = rect || menu.anchorRect;
	target = target || menu.anchorTarget;
	menu.anchorRect = rect;
	menu.anchorTarget = target;
	var gap = 10, edge = 8;
	var width = menu.offsetWidth, height = menu.offsetHeight;
	var area = visibleArea(target);
	var docked = editorPointer === 'touch' && !getSelection().isCollapsed && !(target && target.closest && target.closest('img, video, iframe, a'));
	var fits = {above: rect.top - gap - height >= area.top + edge, below: rect.bottom + gap + height <= area.bottom - edge};
	var side = docked ? (rect.top > (area.top + area.bottom) / 2 ? 'top' : 'bottom') : fits.above ? 'above' : fits.below && 'below';
	var top = {above: rect.top - gap - height, below: rect.bottom + gap, top: area.top, bottom: area.bottom}[side];
	top = side ? top : Math.max(rect.top, area.top) + gap;
	top = Math.max(area.top + edge, Math.min(top, area.bottom - height - edge));
	var center = docked ? (area.left + area.right) / 2 : (Math.max(rect.left, area.left) + Math.min(rect.right, area.right)) / 2;
	var left = Math.max(area.left + edge, Math.min(center - width / 2, area.right - width - edge));
	menu.style.left = left + 'px';
	menu.style.top = top + 'px';
	menu.style.setProperty('--arrow', Math.max(12, Math.min(center - left, width - 12)) + 'px');
	menu.classList.toggle('is-above', side === 'above');
	menu.classList.toggle('no-arrow', !side || docked);
};
/** A sub menu opens to the left or upwards when the window ends. */
var fitEditorSubmenu = function (li) {
	var list = li.querySelector('ul');
	if (!list) {
		return;
	}
	list.classList.remove('toLeft', 'toTop');
	var rect = list.getBoundingClientRect();
	rect.right > document.documentElement.clientWidth - 8 && list.classList.add('toLeft');
	rect.bottom > window.innerHeight - 8 && list.classList.add('toTop');
};

var contextMenuWW = function (params, event) {
	closeAllMenus();
	contextMenus = [this];
	var target = event.target;
	this.domWrapper = $("<div class='cms'></div>");
	this.domObj = $('<div class="HTMLeditorMenu" role="toolbar"></div>');
	var ul = $('<ul></ul>');
	var mediaBar = target && target.closest && target.closest('img, video, iframe');
	if (editorPointer === 'touch' && wysiwygOf(target) && !mediaBar) {
		$('<li><a href="javascript:void(0)" title="Type"><i class="fa-regular fa-keyboard"></i></a></li>')
			.appendTo(ul).children('a').on('click', startTyping);
	}
	$.each(params, function (key, item) {
		if (item.active === false) {
			return;
		}
		var li = $('<li></li>').toggleClass('selected', !!item.selected).toggleClass('disabled', !!item.disabled).toggleClass('is-drag', !!item.drag).toggleClass('sep', !!item.sep && ul.children().length > 0);
		var a = $('<a href="javascript:void(0)"><i class="' + item.className + '"></i></a>').attr('title', item.title).attr('aria-label', item.title);
		li.append(a).appendTo(ul);
		if (item.drag) {
			a.on('pointerdown', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var media = target && target.closest && target.closest('img, video, iframe');
				closeAllMenus();
				if (media && window.editorBeginMediaMove) {
					editorBeginMediaMove(media, e.originalEvent || e);
				}
			});
			return;
		}
		if (!item.subs) {
			a.on('click', item.param, menuCommand(window[item.func], item.reopen));
			return;
		}
		li.addClass('hasChildren');
		var subul = $('<ul></ul>').appendTo(li);
		$.each(item.subs, function (subKey, subItem) {
			$('<li></li>').toggleClass('selected', !!subItem.selected)
				.append($('<a href="javascript:void(0)"></a>').text(subItem.title).on('click', subItem.param, menuCommand(window[subItem.func], subItem.reopen)))
				.appendTo(subul);
		});
	});
	this.domObj.toggleClass('is-touch', editorPointer === 'touch').append(ul);
	$('body').append(this.domWrapper.append(this.domObj));

	var rect = event.anchorRect;
	if (!rect) {
		var x = event.clientX, y = event.clientY;
		rect = {left: x, right: x, top: y, bottom: y};
	}
	placeEditorMenu(this.domObj[0], rect, target);

	// the press keeps the focus in the editor; a tap opens what hover opens
	this.domObj.on('mousedown', function (e) {
		$(e.target).is('input, button') || e.preventDefault();
	});
	this.domObj.find('li.hasChildren').on('mouseenter', function () {
		fitEditorSubmenu(this);
	}).children('a').on('click', function () {
		var li = $(this).parent().toggleClass('open');
		li.siblings().removeClass('open');
		fitEditorSubmenu(li[0]);
	});
};

$(function () {
	initContenteditableContextMenus();
	$('body').on('click', function (e) {
		if (!$(e.target).closest('body.cms div.mmlContent ul li').length) {
			$('body.cms div.mmlContent ul li').removeClass('selected');
		}
	});
});
