/****************************** WYSIWYG BAR ***************************/
// One floating bar for everything in a field. A click shows what was hit:
// the caret its block or link, a selection the text formats, an image, video
// or embed its own commands. The keyboard stays down (inputmode none) until
// "Type" in the bar asks for it - it would cover half the page.
function initContenteditableContextMenus() {
	var selectionTimer, downInEditor = false, keyboardMenu = false;

	// the bar opens where the pointer pressed, not at the top of a large element
	var lastPoint = null;
	var pointOf = function (e) {
		return {left: e.clientX, right: e.clientX, top: e.clientY, bottom: e.clientY};
	};
	/** pointed: the bar of the link, image or video itself, whatever the selection holds. */
	var openFor = function (target, rect, pointed) {
		$(target).trigger($.Event('contextmenu', {target: target, anchorRect: rect, pointed: !!pointed, keyboard: keyboardMenu}));
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
	// The same for a film built into the page: a player would take the click.
	var editorVideo = function (node) {
		var video = node && node.closest && node.closest('video');
		if (!video) {
			return null;
		}
		return wysiwygOf(video) || video.closest('article.cmsContent') ? video : null;
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
            if (!editor.hasAttribute('data-selecting')) {
                // A tap opens tools. Only the explicit Type action focuses the editor.
                e.preventDefault();
                var currentSelection = getSelection();
                if (!currentSelection.isCollapsed && editor.contains(currentSelection.anchorNode)) return;
                var caret = document.caretRangeFromPoint?.(e.clientX, e.clientY);
                if (!caret && document.caretPositionFromPoint) {
                    var position = document.caretPositionFromPoint(e.clientX, e.clientY);
                    if (position) { caret = document.createRange(); caret.setStart(position.offsetNode, position.offset); caret.collapse(true); }
                }
                if (caret && editor.contains(caret.startContainer)) {
                    editorRange = caret.cloneRange();
                    var selection = getSelection(); selection.removeAllRanges(); selection.addRange(caret);
                }
            }
        }
	}, true);
	// a pressed image would be selected, tinted and offered as text.
	// one built into the page would be dragged instead of opening the bar.
	document.addEventListener('mousedown', function (e) {
		if (e.button !== 0 || editorPointer === 'touch' || !e.target.closest) {
			return;
		}
		var media = e.target.closest('img, video, iframe');
		var fixed = media && fixedMediaOf(media);
		if (media && (wysiwygOf(media) || (fixed && fixedMediaNodeId(fixed)))) {
			e.preventDefault();
		}
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
        var clickedLink = wysiwygOf(e.target) && e.target.closest('a');
        if (clickedLink) {
            e.preventDefault();
            e.stopPropagation();
            return openFor(clickedLink, pointOf(e), true);
        }
		var fixed = fixedMediaOf(e.target);
		if (fixed && fixedMediaNodeId(fixed)) {
			e.preventDefault();
			e.stopPropagation();
			if (editorPointer === 'touch') {
				openFixed(fixed, pointOf(e));
			}
			return;
		}
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
		if (editor.hasAttribute('data-selecting') && !getSelection().isCollapsed) {
            editor.removeAttribute('data-selecting');
            e.preventDefault();
            return show();
        }
        if (!getSelection().isCollapsed) { e.preventDefault(); return show(); }
        if (editor.hasAttribute('data-typing')) {
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
				selectionTimer = setTimeout(show, 350);
			}
		}
	});
	// mouse: a click places the caret, a drag or double click selects - both show the bar
	document.addEventListener('mouseup', function (e) {
		if (e.button !== 0 || editorPointer === 'touch') {
			return;
		}
		var fixed = fixedMediaOf(e.target);
		if (fixed) {
			var fixedPoint = pointOf(e);
			return setTimeout(function () {
				openFixed(fixed, fixedPoint);
			});
		}
		if (!downInEditor) {
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
        if (e.altKey && e.key === 'F10' && wysiwygOf(e.target)) {
            e.preventDefault();keyboardMenu=true;show();keyboardMenu=false;return;
        }
		if (!/^(Shift|Meta|Control|Alt)$/.test(e.key) && wysiwygOf(e.target) && openBar()) {
			closeAllMenus();
		}
	});
	// Caret focus and layout updates can emit scroll immediately after opening.
	// Follow the anchor instead of dismissing the tools (including touch drag).
	document.addEventListener('scroll', function (e) {
		var bar = openBar();
		if (!bar || bar.contains(e.target)) return;
		var anchor = bar.anchorTarget;
		if (!anchor?.isConnected) return closeAllMenus();
		var next = anchor.getBoundingClientRect(), previous = bar.anchorTargetRect;
		var rect = bar.anchorRect;
		if (previous && rect) {
			var dx = next.left - previous.left, dy = next.top - previous.top;
			rect = {left: rect.left + dx, right: rect.right + dx, top: rect.top + dy, bottom: rect.bottom + dy};
		}
		placeEditorMenu(bar, rect, anchor);
	}, true);

	var openFixed = function (el, rect) {
		if (!fixedMediaNodeId(el)) {
			return;
		}
		$(el).trigger($.Event('contextmenu', {target: el, anchorRect: rect, pointed: true}));
	};
	editorBubble = show;
	$("body").on("contextmenu", function (e) {
		var fixed = fixedMediaOf(e.target);
		if (fixed) {
			e.preventDefault();
			fixedMediaNodeId(fixed) && HTMLeditorRightClick(fixed.nodeName === 'VIDEO' ? 'fixedVideo' : 'fixedImage', e, $(fixed));
			return;
		}
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
/**
 * A picture or film drawn by the template, inside the page preview.
 * An external film is a poster with a cover over it: the click lands on the
 * cover, the picture underneath is the one to edit.
 */
var fixedMediaOf = function (node) {
	var el = node && node.closest && node.closest('img, video');
	if (el && !wysiwygOf(el) && el.closest('article.cmsContent')) {
		return el;
	}
	var box = node && node.closest && node.closest('article.cmsContent .extVideo');
	var poster = box && box.querySelector('img, video');
	return poster && !wysiwygOf(poster) ? poster : null;
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
	if (editorPointer !== 'touch') editor.focus({preventScroll: true});
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
        if (result && typeof result.then === 'function') {
            result.then(function () { closeAllMenus(); });
            return;
        }
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
 * touch, prefer below the selection so the native menu can remain above it.
 */
var placeEditorMenu = function (menu, rect, target) {
	rect = rect || menu.anchorRect;
	target = target || menu.anchorTarget;
	menu.anchorRect = rect;
	menu.anchorTarget = target;
	menu.anchorTargetRect = target?.getBoundingClientRect();
	if (menu.classList.contains('cmsPageDock')) return;
	var gap = 10, edge = 8;
	var width = menu.offsetWidth, height = menu.offsetHeight;
	var area = visibleArea(target);
	var docked = false; // Keep touch selections anchored to their text, like the caret.
	var fits = {above: rect.top - gap - height >= area.top + edge, below: rect.bottom + gap + height <= area.bottom - edge};
	var touchSelection = editorPointer === 'touch' && !getSelection().isCollapsed;
	var side = touchSelection && fits.below ? 'below' : docked ? (rect.top > (area.top + area.bottom) / 2 ? 'top' : 'bottom') : fits.above ? 'above' : fits.below && 'below';
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
	const dock = li.closest('.cmsPageDock');
	if (dock) {
		const button = li.getBoundingClientRect();
		list.style.left = Math.max(8, Math.min(button.left, innerWidth - list.offsetWidth - 8)) + 'px';
		list.style.bottom = (innerHeight - dock.getBoundingClientRect().top + 6) + 'px';
		return;
	}
	list.classList.remove('toLeft', 'toTop');
	var rect = list.getBoundingClientRect();
	rect.right > document.documentElement.clientWidth - 8 && list.classList.add('toLeft');
	rect.bottom > window.innerHeight - 8 && list.classList.add('toTop');
};

function enableEditorDockSwipe(scroller) {
	let swipe;
	scroller.addEventListener('pointerdown', event => {
		if (event.pointerType !== 'touch' || !event.isPrimary || event.target.closest('.is-drag, li > ul')) return;
		swipe = {id: event.pointerId, x: event.clientX, left: scroller.scrollLeft};
	});
	scroller.addEventListener('pointermove', event => {
		if (swipe?.id !== event.pointerId) return;
		const delta = swipe.x - event.clientX;
		if (Math.abs(delta) < 6 && !scroller.hasPointerCapture(event.pointerId)) return;
		if (!scroller.hasPointerCapture(event.pointerId)) scroller.setPointerCapture(event.pointerId);
		scroller.scrollLeft = swipe.left + delta;
	});
	const end = () => { swipe = null; };
	scroller.addEventListener('pointerup', end);
	scroller.addEventListener('pointercancel', end);
}

var contextMenuWW = function (params, event) {
	closeAllMenus();
	contextMenus = [this];
	var target = event.target;
	this.domWrapper = $("<div class='cms'></div>");
	this.domObj = $('<div class="HTMLeditorMenu" role="toolbar"></div>');
	var ul = $('<ul></ul>');
	var mediaBar = target && target.closest && (target.closest('img, video, iframe') || target.closest('a')?.querySelector('img, video, iframe'));
	if (editorPointer === 'touch' && wysiwygOf(target) && !mediaBar) {
		const editor = wysiwygOf(target);
		if (sys.pagesMedia && editorRange && editor.dataset.drop_accept?.split(/\s+/).includes('media') && editor.dataset.on_drop && document.body.classList.contains('cmsMobile')) {
			const range = editorRange.cloneRange();
			const mediaButton = (label, icon, action, showLabel = false) => {
				const button = $('<button type="button" data-cms-action></button>').attr({'aria-label': label, title: label});
				button.append($('<i>').addClass('cmsIcon cmsIcon-' + icon));
				if (showLabel) button.addClass('pageMediaPaste').append($('<span>').text(label));
				button.on('pointerdown', event => event.preventDefault());
				button.on('click', () => { closeAllMenus(); action(editor, range); });
				$('<li>').append(button).appendTo(ul);
			};
			mediaButton('Choose media', 'photo-film', sys.pagesMedia.pick);
			if (sys.pagesMedia.hasClipboard()) mediaButton('Paste media', 'copy', sys.pagesMedia.paste, true);
		}
		$('<li><button type="button" data-cms-action title="Type"><i class="cmsIcon cmsIcon-keyboard"></i></button></li>')
			.appendTo(ul).children('a,button[data-cms-action]').on('click', startTyping);
        $('<li><button type="button" data-cms-action title="Select text"><i class="cmsIcon cmsIcon-text-select"></i></button></li>')
            .appendTo(ul).children('button').on('click', function () {
                var editor = wysiwygOf(target);
                if (!editor || !editorRange) return;
                closeAllMenus();
                editor.setAttribute('data-selecting', '');
                editor.setAttribute('inputmode', 'none');
                var selection = getSelection();
                selection.removeAllRanges();
                selection.addRange(editorRange.cloneRange());
                if (selection.isCollapsed && selection.modify) selection.modify('extend', 'forward', 'word');
            });

	}
	$.each(params, function (key, item) {
		if (item.active === false) {
			return;
		}
		var li = $('<li></li>').toggleClass('selected', !!item.selected).toggleClass('disabled', !!item.disabled).toggleClass('is-drag', !!item.drag).toggleClass('sep', !!item.sep && ul.children().length > 0);
		var a = $('<button type="button" data-cms-action><i class="' + item.className + '"></i></button>').attr('title', item.title).attr('aria-label', item.title);
        a.prop('disabled', !!item.disabled);
        if(item.selected !== undefined)a.attr('aria-pressed',String(!!item.selected));
		li.append(a).appendTo(ul);
		if (item.drag) {
			a.on('pointerdown', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var media = target && target.closest && target.closest('img, video, iframe');
				// Keep touch events on the live editor before removing the pressed menu.
                var owner = media?.closest('.editor');
                if (owner && e.originalEvent?.pointerId !== undefined) owner.setPointerCapture(e.originalEvent.pointerId);
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
		var subul = $('<ul></ul>').attr('id','cms-editor-sub-'+(++cmsMenuSequence)).appendTo(li);
        a.attr('aria-expanded','false').attr('aria-controls',subul.attr('id'));
		$.each(item.subs, function (subKey, subItem) {
			$('<li></li>').toggleClass('selected', !!subItem.selected)
				.append($('<button type="button" data-cms-action></button>').text(subItem.title).on('click', subItem.param, menuCommand(window[subItem.func], subItem.reopen)))
				.appendTo(subul);
		});
	});
	this.domObj.toggleClass('is-touch', editorPointer === 'touch')
		.toggleClass('cmsPageDock', document.body.matches('.pages.cmsMobile') && !!wysiwygOf(target))
		.append(ul);
	$('body').append(this.domWrapper.append(this.domObj));
	if (this.domObj.hasClass('cmsPageDock')) {
		const editor = wysiwygOf(target);
		ul[0].scrollLeft = editor.cmsDockScrollLeft || 0;
		ul[0].addEventListener('scroll', () => { editor.cmsDockScrollLeft = ul[0].scrollLeft; }, {passive: true});
		enableEditorDockSwipe(ul[0]);
	}

	var rect = event.anchorRect;
	if (!rect) {
		var x = event.clientX, y = event.clientY;
		rect = {left: x, right: x, top: y, bottom: y};
	}
	placeEditorMenu(this.domObj[0], rect, target);
    this.domObj[0].dataset.keyboard = event.keyboard ? 'true' : 'false';
    prepareMenuKeyboard(this.domObj[0], target.closest?.('[contenteditable]') || target, !!event.keyboard);

	// the press keeps the focus in the editor; a tap opens what hover opens
	this.domObj.on('mousedown', function (e) {
		$(e.target).is('input, button:not([data-cms-action])') || e.preventDefault();
	});
	this.domObj.find('li.hasChildren').on('mouseenter', function () {
        this.querySelector(':scope > a,:scope > button[data-cms-action]')?.setAttribute('aria-expanded','true');
		fitEditorSubmenu(this);
	}).on('mouseleave',function(){this.querySelector(':scope > a,:scope > button[data-cms-action]')?.setAttribute('aria-expanded',String(this.classList.contains('open')));}).children('a,button[data-cms-action]').on('click', function () {
		var li = $(this).parent().toggleClass('open');
        this.setAttribute('aria-expanded',String(li.hasClass('open')));
		li.siblings().removeClass('open').children('a[aria-expanded],button[data-cms-action][aria-expanded]').attr('aria-expanded','false');
		fitEditorSubmenu(li[0]);
	});
};

// The format of a picture or film in the page preview. One chip, no button.
function initMediaFormatChip() {
	var chip = document.createElement('div');
	chip.className = 'cmsMediaFormat';
	chip.hidden = true;
	document.body.appendChild(chip);
	var current = null;

	var place = function (el) {
		var rect = el.getBoundingClientRect();
		if (rect.width < 2 || rect.height < 2) {
			chip.hidden = true;
			return;
		}
		var preview = el.closest('article.cmsContent');
		if (preview) {
			var bounds = preview.closest('.cmsPreview')?.getBoundingClientRect() || preview.getBoundingClientRect();
			var headerBottom = document.querySelector('.pageHead')?.getBoundingClientRect().bottom || 0;
			if (rect.top < Math.max(0, bounds.top, headerBottom) || rect.bottom <= bounds.top) { chip.hidden = true; return; }
		}
		chip.textContent = mediaFormatLabel(el);
		chip.hidden = false;
		chip.style.left = Math.max(4, rect.right - chip.offsetWidth - 4) + 'px';
		chip.style.top = Math.max(4, rect.top + 4) + 'px';
	};
	var show = function (el) {
		if (!el || !el.isConnected) return;
		if (current && current !== el) current.classList.remove('cmsMediaOn');
		current = el;
		el.classList.add('cmsMediaOn');
		place(el);
	};
	var hide = function () {
		if (current) current.classList.remove('cmsMediaOn');
		current = null;
		chip.hidden = true;
	};
	window.cmsMediaFormatShow = function (el) {
		show(el);
	};
	var mediaOf = function (node) {
		var el = node && node.closest && node.closest('article.cmsContent img, article.cmsContent video');
		return el || null;
	};
	document.addEventListener('pointerover', function (e) {
		if (e.pointerType && e.pointerType !== 'mouse') return;
		var el = mediaOf(e.target);
		if (!el) return;
		show(el);
	}, true);
	document.addEventListener('pointerout', function (e) {
		if (e.pointerType && e.pointerType !== 'mouse') return;
		var el = mediaOf(e.target);
		if (!el || el !== current) return;
		var to = e.relatedTarget;
		if (to && el.contains(to)) return;
		hide();
	}, true);
	// A finger has no hover. The frame stays until the next tap, so the format can be read.
	document.addEventListener('pointerdown', function (e) {
		if (!e.pointerType || e.pointerType === 'mouse') return;
		var el = mediaOf(e.target);
		el ? show(el) : hide();
	}, true);
	document.addEventListener('scroll', function () {
		if (current && current.isConnected) place(current);
		else chip.hidden = true;
	}, true);
}

$(function () {
	initContenteditableContextMenus();
	initMediaFormatChip();
	$('body').on('click', function (e) {
		if (!$(e.target).closest('body.cms div.mmlContent ul li').length) {
			$('body.cms div.mmlContent ul li').removeClass('selected');
		}
	});
});
