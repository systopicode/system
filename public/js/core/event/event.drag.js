/* global sys,c,p,t,l */
sys.core.event.drag = function (event, hooks) { // 2. param raus
	var e = event.e;
	var drag = this;
	drag.hooks = obj.extend({
		move: function () {},
		start: function () {},
		release: function () {},
		accept: function () {}
	}, hooks);
	drag.extendHooks = function (hooks) {
		for (var key in hooks) {
			drag.hooks[key] = hooks[key];
		}
	};
	drag.helperPadding = 3;
	drag.hasHelper = true;

	drag.dragObj = e.currentTarget;
	drag.dropObj = null;
	drag.downX = e.clientX;
	drag.downY = e.clientY;
	drag.moveX = 0;
	drag.moveY = 0;
	drag.status = 'down';
	drag.type = drag.dragObj.dataset.drag_type;
	var pageId = drag.type === 'page' && drag.dragObj.closest('li[data-node_id]')?.dataset.node_id;
	var css, keysHoldable = ['shift', 'ctrl', 'alt'];
	drag.e = e;
	// Touch captures the pointer on the element it went down on; released,
	// e.target follows the finger like the mouse, so drop targets are found.
	var pointer = e.type === 'pointerdown';
	var moveType = pointer ? 'pointermove' : 'mousemove';
	var upType = pointer ? 'pointerup pointercancel' : 'mouseup';
	var down = e.originalEvent || e;
	if (pointer && down.target.hasPointerCapture && down.target.hasPointerCapture(down.pointerId)) {
		down.target.releasePointerCapture(down.pointerId);
	}
	// while this drag runs, the browser starts no drag of its own (a ghost of
	// the text or link under a finger that held still)
	var noNativeDrag = ev => ev.preventDefault();
	document.addEventListener('dragstart', noNativeDrag, true);

	// Holding is an alternative to moving the mouse, never a prerequisite.
	var holdTimer, mouseHeld = false;
	var mediaHold = pointer && e.cmsHeld && drag.type === 'media';
	function mediaHoldEvent(phase) {
		drag.dragObj.dispatchEvent(new CustomEvent('cms-media-hold', {bubbles: true, detail: phase}));
	}
	function beginDrag() {
		clearTimeout(holdTimer);
		if (drag.status !== 'down' || !drag.dragObj.isConnected) return;
		drag.hooks.start();
		drag.status = 'dragging';
		var before = drag.dragObj.getBoundingClientRect();
		drag.$helper = $('<div class="draghelper"></div>');
		drag.$helper.append(drag.$keyheldIcon);
		$('body').append(drag.$helper);
		$('body').addClass('dragging');
		$('body').addClass(drag.type + 'Dragging');
		drag.dragObj.classList.add('dragged');
		drag.dragObj.previousElementSibling && drag.dragObj.previousElementSibling.classList.add('beforeDragged');
		drag.dragObj.nextElementSibling && drag.dragObj.nextElementSibling.classList.add('afterDragged');
		keepUnderPointer(before);
		// ?don't extend helper to parent objects
		// drag.dragObj.parents('[data-drop_accept="' + drag.type + '"]').attr('data-drop_accept', drag.type + '_parent');
		if (!drag.hasHelper) {
			drag.$helper.hide();
		}
	}
	var lineKey = '';
	function mousemove(e) {
		drag.e = e;

		drag.moveX = e.clientX - drag.downX;
		drag.moveY = e.clientY - drag.downY;
		if (drag.moveX * drag.moveX + drag.moveY * drag.moveY > 25) {
			if (mediaHold && mouseHeld) mediaHoldEvent('cancel');
			mouseHeld = false;
		}
		drag.hooks.move();
		drag.keysheld = keysHoldable.filter(key => e[key + 'Key']);
		if (drag.moveX * drag.moveX + drag.moveY * drag.moveY > 25 && drag.status === 'down') { // drag threshold 5px
			beginDrag();
		}
		if (drag.status !== 'down') {
			// Folding redraws the tree. Keep the live source for hit testing and cues.
			if (pageId && !drag.dragObj.isConnected) {
				var replacement = document.querySelector('body.pages ul.tree li[data-node_id="' + CSS.escape(pageId) + '"] > label[data-drag_type="page"]');
				if (replacement) {
					drag.dragObj = replacement;
					replacement.classList.add('dragged');
					replacement.previousElementSibling?.classList.add('beforeDragged');
					replacement.nextElementSibling?.classList.add('afterDragged');
				}
			}
			keysHoldable.forEach(key => drag.$helper[0].classList.remove(key));
			drag.keysheld.forEach(key => drag.$helper[0].classList.add(key));
            // WebKit can keep a touch event targeted at the held row even
            // after implicit pointer capture is released. Hit-test the finger,
            // rather than treating that old event target as the drop row.
            var target = pointer && Number.isFinite(e.clientX) && Number.isFinite(e.clientY)
                ? document.elementFromPoint(e.clientX, e.clientY) : e.target;
            target = target || e.target;
            markMediaPolicy(target);
            if (e.cmsHandoff) drag.handoffPoint = {x:e.clientX,y:e.clientY};
            if (drag.handoffPoint && Math.hypot(e.clientX-drag.handoffPoint.x,e.clientY-drag.handoffPoint.y) > 5) drag.handoffPoint = null;
            var nextDrop = drag.handoffPoint ? null : nearestZone(target, target.closest('[data-drop_accept]'));
			// Resting on a row that can take the item switches from the sort
			// line to that row. Until then the line stays.
			nextDrop = drag.handoffPoint ? null : holdForOnto(target, nextDrop);
			// Pages change cue at the midpoint. Other lists keep a few pixels
			// so the line does not flicker between two neighbours.
			if (drag.type !== 'page' && isLine(drag.dropObj) && isLine(nextDrop) && drag.dropObj !== nextDrop && distance(nextDrop) + 10 > distance(drag.dropObj)) {
				nextDrop = drag.dropObj;
			}
			if (drag.dropObj && drag.dropObj !== nextDrop) {
				drag.dropObj.classList.remove('dropTarget');
			}
			drag.dropObj = nextDrop;
			if (isAcceptable()) {
				drag.status = 'accept';
				drag.dropObj.classList.add('dropTarget');
				if (drag.hasHelper) {
					drag.$helper.addClass('accept');
					css = getBoundingBox(drag.dropObj);
				}
			} else {
				drag.status = 'dragging';
				if (drag.dropObj) {
					drag.dropObj.classList.remove('dropTarget');
				}
				if (drag.hasHelper) {
					drag.$helper.show();
					drag.$helper.removeClass('accept');
					css = getBoundingBox(drag.dragObj);
					css.left += drag.moveX;
					css.top += drag.moveY;
				} else {
					drag.$helper.hide();
				}
			}
			if (drag.hasHelper) {
				drag.$helper.toggleClass('line', !!css.line);
				var showLine = !!css.line;
				delete css.line;
				if (showLine) {
					var key = css.top + '|' + css.left + '|' + css.width;
					if (key !== lineKey) {
						lineKey = key;
						drag.$helper.css(css);
					}
				} else {
					lineKey = '';
					drag.$helper.css(css);
				}
			}
			if (!edgeFrame) {
				edgeFrame = requestAnimationFrame(edgeScroll);
			}
		}

		if (e.shiftKey && e.ctrlKey) { // debug: freeze dragmode
			$('body').addClass('debug');
		} else {
			$('body').removeClass('debug');
		}
	}

	function mouseup(e) {
		clearTimeout(holdTimer);
		window.removeEventListener('blur', cancelHold);
		if (e.shiftKey && e.ctrlKey) { // debug: freeze dragmode
			$(document).unbind(moveType, mousemove);
			$(document).unbind(upType, mouseup);
			return;
		}
        // The final touch position may arrive only with pointerup.
        if (pointer && e.type !== 'pointercancel' && drag.status !== 'down'
            && Number.isFinite(e.clientX) && Number.isFinite(e.clientY)) mousemove(e);
        drag.e = e;
        clearDwell();
		cancelAnimationFrame(edgeFrame);
		edgeFrame = 0;
		// Always undo what mousemove set up: hooks may switch hasHelper off
		// mid-drag (e.g. grid repositioning), which used to leave the helper in
		// the DOM and body.dragging / body.<type>Dragging stuck for good.
		if (drag.$helper) {
			drag.$helper.remove();
		}
		if (drag.dropObj) {
			drag.dropObj.classList.remove('dropTarget');
		}
		setHover(null);
        markMediaPolicy(null);
		$('body').removeClass('dragging');
		$('body').removeClass(drag.type + 'Dragging');
		drag.dragObj.classList.remove('dragged');
		drag.dragObj.previousElementSibling && drag.dragObj.previousElementSibling.classList.remove('beforeDragged');
		drag.dragObj.nextElementSibling && drag.dragObj.nextElementSibling.classList.remove('afterDragged');
		// ?don't extend helper to parent objects
		// drag.dragObj.parents('[data-drop_accept="' + drag.type + '_parent"]').attr('data-drop_accept', drag.type);

		$(document).unbind(moveType, mousemove);
		$(document).unbind(upType, mouseup);
		document.removeEventListener('dragstart', noNativeDrag, true);
		if (e.type === 'pointercancel') { // the browser took the gesture (scroll)
			if (mediaHold && mouseHeld) mediaHoldEvent('cancel');
			return;
		}
		drag.hooks.release();
		if (mediaHold && mouseHeld) mediaHoldEvent('confirm');
		// A finished drag must not become the click on the element it
		// started on. The click is dispatched after mouseup.
		if (drag.status !== 'down' || mediaHold) {
			var swallowClick = function (ev) {
				ev.preventDefault();
				ev.stopPropagation();
				window.removeEventListener('click', swallowClick, true);
			};
			window.addEventListener('click', swallowClick, true);
			setTimeout(function () {
				window.removeEventListener('click', swallowClick, true);
			}, 400);
		}
		if (drag.status === 'accept' && !mouseHeld) {
			// A medium dropped into a text lands where the finger is, not at
			// the end: the selection is what the insert uses.
			if ((drag.type === 'media' || drag.type === 'page') && drag.e) {
				placeEditorCaret(drag.e.clientX, drag.e.clientY);
			}
			// A table row is moved here. The answer only stores the order,
			// so the list is not rebuilt and the place on the page stays.
			var row = drag.dragObj && drag.dragObj.closest && drag.dragObj.closest('tr');
			var target = drag.dropObj && drag.dropObj.closest && drag.dropObj.closest('tr');
			if (row && target && row !== target && drag.dropObj.classList.contains('sort')) {
				if (drag.dropObj.classList.contains('insertAfter')) {
					target.after(row);
				} else if (drag.dropObj.classList.contains('insertBefore')) {
					target.before(row);
				}
			}
			drag.hooks.accept();
		}
	}

	// Dragging may change the layout (a stacked table folds its rows): the
	// scroller moves the item back under the pointer, the rest is taken into
	// the offset of the helper.
	function keepUnderPointer(before) {
		var shift = drag.dragObj.getBoundingClientRect().top - before.top;
		if (Math.abs(shift) < 1) {
			return;
		}
		for (var el = drag.dragObj.parentElement; el; el = el.parentElement) {
			if (scrolls(el)) {
				el.scrollTop += shift;
				break;
			}
		}
		var after = drag.dragObj.getBoundingClientRect();
		drag.downX += after.left - before.left;
		drag.downY += after.top - before.top;
		drag.moveX = drag.e.clientX - drag.downX;
		drag.moveY = drag.e.clientY - drag.downY;
	}

	function scrolls(el) {
		var overflow = getComputedStyle(el).overflowY;
		return el === document.scrollingElement
			? el.scrollHeight > innerHeight
			: (overflow === 'auto' || overflow === 'scroll') && el.scrollHeight > el.clientHeight + 1;
	}

	// The pointer at the top or bottom edge of a scroll box scrolls it, faster
	// the nearer the edge - a long list sorts without dropping in between.
	// The pointer stands still, so the drop target is looked up again.
	var edgeFrame = 0;
	function edgeScroll() {
		edgeFrame = 0;
		var x = drag.e.clientX, y = drag.e.clientY;
		var under = document.elementFromPoint(x, y);
		for (var el = under; el; el = el === document.documentElement ? null : el.parentElement || document.scrollingElement) {
			if (!scrolls(el)) {
				continue;
			}
			var rect = el === document.scrollingElement ? {top: 0, bottom: innerHeight} : el.getBoundingClientRect();
			var top = Math.max(rect.top, 0), bottom = Math.min(rect.bottom, innerHeight);
			var edge = Math.min(64, (bottom - top) / 4);
			var step = 0;
			if (y < top + edge && el.scrollTop > 0) {
				step = -Math.ceil((top + edge - y) / edge * 14);
			} else if (y > bottom - edge && el.scrollTop + el.clientHeight < el.scrollHeight - 1) {
				step = Math.ceil((y - bottom + edge) / edge * 14);
			}
			if (step) {
				el.scrollTop += step;
				mousemove({
					type: drag.e.type, target: document.elementFromPoint(x, y) || under, originalEvent: drag.e.originalEvent,
					clientX: x, clientY: y, pageX: x + scrollX, pageY: y + scrollY,
					shiftKey: drag.e.shiftKey, ctrlKey: drag.e.ctrlKey, altKey: drag.e.altKey
				});
				return;
			}
		}
	}

	// Sort lines win at once. Dropping onto the row (a child page, a group,
	// a tag) waits until the pointer has stayed there, then the row draws
	// the frame. Mouse and touch share this, both come through mousemove.
	var dwell = { onto: null, ready: false, timer: 0 };

	function clearDwell() {
		if (dwell.timer) {
			clearTimeout(dwell.timer);
			dwell.timer = 0;
		}
		dwell.onto = null;
		dwell.ready = false;
	}

	function armDwell(onto) {
		if (dwell.onto === onto) {
			return;
		}
		if (dwell.timer) {
			clearTimeout(dwell.timer);
		}
		dwell.onto = onto;
		dwell.ready = false;
		dwell.timer = setTimeout(function () {
			dwell.timer = 0;
			if (dwell.onto !== onto || drag.status === 'down' || !drag.$helper) {
				return;
			}
			dwell.ready = true;
			if (drag.type === 'page' && onto.closest('ul.tree')) {
				var fold = onto.parentElement.querySelector(':scope > .fold[aria-expanded="false"]');
				if (fold) $(fold).triggerHandler('click');
			}
			if (drag.e) {
				mousemove(drag.e);
			}
		}, 280);
	}

	function acceptsType(zone) {
		return !!(zone && zone.dataset && !isSelf(zone) && (zone.dataset.drop_accept || '').split(' ').indexOf(drag.type) !== -1 && zone.getClientRects().length);
	}

	function isLine(zone) {
		return !!(zone && zone.classList.contains('sort') && !zone.classList.contains('insertInto'));
	}

	function isSelf(node) {
		if (!node || !drag.dragObj) {
			return false;
		}
		return node === drag.dragObj || node.contains(drag.dragObj) || drag.dragObj.contains(node);
	}

	function dwellHost(target) {
		if (!target || !target.closest) {
			return null;
		}
		var label = target.closest('ul.tree li > label');
		if (label && !isSelf(label)) {
			var zones = label.children;
			for (var i = 0; i < zones.length; i++) {
				if (zones[i].classList.contains('insertInto') && acceptsType(zones[i])) {
					return zones[i];
				}
			}
		}
		var tag = target.closest('.layout.tags .tag');
		if (tag && !isSelf(tag) && acceptsType(tag)) {
			return tag;
		}
		var title = target.closest('li.title');
		if (title && !isSelf(title) && acceptsType(title) && !target.closest('li.title li')) {
			return title;
		}
		var widget = target.closest('.layout.tags .widget');
		if (widget && !isSelf(widget) && acceptsType(widget) && !target.closest('.layout.tags .tag')) {
			return widget;
		}
		return null;
	}

	// The sort line owns a band on the edge, wider than the hairline gap,
	// so it answers before the pointer is exactly on the boundary. The
	// middle of the item stays free for a drop onto it.
	function edgeLine(target) {
		if (!target || !target.closest || !drag.e) {
			return null;
		}
		var item = target.closest('ul.tree li > label') || target.closest('.layout.tags .tag');
		if (!item || isSelf(item)) {
			return null;
		}
		var zones = Array.prototype.filter.call(item.children, function (zone) {
			return zone.classList && zone.classList.contains('sort')
				&& (zone.classList.contains('insertBefore') || zone.classList.contains('insertAfter'))
				&& (zone.dataset.drop_accept || '').split(' ').indexOf(drag.type) !== -1
				&& zone.getClientRects().length;
		});
		if (!zones.length) {
			return null;
		}
		var before = null;
		var after = null;
		zones.forEach(function (zone) {
			if (zone.classList.contains('insertBefore')) before = zone;
			else if (zone.classList.contains('insertAfter')) after = zone;
		});
		var box = item.getBoundingClientRect();
		var horizontal = zones[0].classList.contains('h');
		if (horizontal) {
			var reachX = Math.min(18, Math.max(12, box.width * 0.36));
			if (drag.e.clientX <= box.left + reachX) return before || after;
			if (drag.e.clientX >= box.right - reachX) return after || before;
			return null;
		}
		var reachY = 10;
		if (drag.e.clientY <= box.top + reachY) return before || after;
		if (drag.e.clientY >= box.bottom - reachY) return after || before;
		return null;
	}

	function hoverHost(onto) {
		if (!onto) {
			return null;
		}
		if (onto.classList.contains('insertInto')) {
			return onto.closest('label') || onto.parentElement;
		}
		return onto;
	}

	function setHover(node) {
		if (drag.hoverObj === node) {
			return;
		}
		if (drag.hoverObj) {
			drag.hoverObj.classList.remove('dropHover');
		}
		drag.hoverObj = node;
		if (node) {
			node.classList.add('dropHover');
		}
	}

	function holdForOnto(target, chosen) {
		var edge = edgeLine(target);
		if (edge) {
			clearDwell();
			setHover(null);
			return edge;
		}
		var onto = dwellHost(target);
		if (!onto) {
			clearDwell();
			setHover(null);
			return chosen;
		}
		var ready = dwell.ready && dwell.onto === onto;
		setHover(ready ? null : hoverHost(onto));
		armDwell(onto);
		if (ready) {
			return onto;
		}
		return isLine(chosen) ? chosen : null;
	}

	// Over an item that brings drop zones of its own (a thumb in a list) the
	// nearer of them is meant, not the list around the item - otherwise the
	// helper jumps between the list and a thin line at every gap.
	function nearestZone(target, drop) {
		// A table row's zones sit in the grip cell. The pointer is usually on
		// another cell of that row, so the zone is not the element under it.
		var row = target && target.closest && target.closest('tr');
		var ownRow = drag.dragObj && drag.dragObj.closest && drag.dragObj.closest('tr');
		if (row && row !== ownRow) {
			var inRow = Array.prototype.filter.call(row.querySelectorAll('div.sort'), function (zone) {
				return (zone.dataset.drop_accept || '').split(' ').includes(drag.type) && zone.getClientRects().length;
			});
			if (inRow.length) {
				return inRow.reduce(function (a, b) { return distance(a) <= distance(b) ? a : b; });
			}
		}
		for (var el = target; el && el !== drop; el = el.parentElement) {
			var zones = Array.prototype.filter.call(el.children, zone => zone.classList.contains('sort')
				&& (zone.dataset.drop_accept || '').split(' ').includes(drag.type) && zone.getClientRects().length);
			if (zones.length) {
				if (el === drag.dragObj) {
					return null;
				}
				// A page moves onto the nearer edge. Dropping into the row is
				// not this zone: it waits until the pointer rests (holdForOnto).
				if (drag.type === 'page' && el.closest && el.closest('ul.tree')) {
					var beforeZ = null;
					var afterZ = null;
					zones.forEach(function (zone) {
						if (zone.classList.contains('insertBefore')) beforeZ = zone;
						else if (zone.classList.contains('insertAfter')) afterZ = zone;
					});
					var box = el.getBoundingClientRect();
					var mid = box.top + box.height / 2;
					return (drag.e.clientY < mid ? beforeZ : afterZ) || beforeZ || afterZ;
				}
				return zones.reduce((a, b) => distance(a) <= distance(b) ? a : b);
			}
		}
		return drop;
	}

	function placeEditorCaret(x, y) {
		var node = document.elementFromPoint(x, y);
		var editor = node && node.closest && node.closest('.editor[contenteditable="true"]');
		if (!editor) {
			return;
		}
		var range = null;
		if (document.caretRangeFromPoint) {
			range = document.caretRangeFromPoint(x, y);
		} else if (document.caretPositionFromPoint) {
			var spot = document.caretPositionFromPoint(x, y);
			if (spot) {
				range = document.createRange();
				range.setStart(spot.offsetNode, spot.offset);
			}
		}
		if (!range || !editor.contains(range.startContainer)) {
			return;
		}
		// A medium is not a container. The caret must not land inside a
		// video, image or frame, or the dropped file is stored as its child.
		var host = range.startContainer.nodeType === 1 ? range.startContainer : range.startContainer.parentElement;
		var media = host && host.closest && host.closest('video, audio, img, iframe, figure');
		if (media && editor.contains(media) && media !== editor) {
			range.setStartAfter(media);
		}
		range.collapse(true);
		var selection = getSelection();
		selection.removeAllRanges();
		selection.addRange(range);
	}

	function distance(zone) {
		var rect = zone.getBoundingClientRect();
		return Math.hypot(drag.e.clientX - (rect.left + rect.width / 2), drag.e.clientY - (rect.top + rect.height / 2));
	}

	// The helper takes the shape of what it stands for: the dragged item, the
	// item a drop zone lies over (insertInto) - or, for a zone between two
	// items, the zone's content box (its padding is only there to be hit),
	// down to a line when that is thinner than a few pixels.
	function getBoundingBox(obj) {
        if (drag.type === 'setting' && obj === drag.dragObj && down.pointerType && down.pointerType !== 'mouse') obj = obj.closest('tr') || obj;
        if (drag.type === 'tag' && !obj.classList.contains('sort')) {
            var tagRect = obj.getBoundingClientRect();
            return {left: tagRect.left, top: tagRect.bottom, width: tagRect.width, height: 2, line: true};
        }
		var zone = obj.classList.contains('sort');
		if (zone && obj.classList.contains('insertInto') && obj.parentElement) {
			obj = obj.parentElement;
			zone = false;
		}
		var style = getComputedStyle(obj);
		var px = name => zone ? parseFloat(style[name]) || 0 : 0;
		var rect = obj.getBoundingClientRect();
		// A vertical gap is a line. Its padding is only the area the finger hits.
		// A column end on a phone is the same line, not a box.
		if (zone && obj.classList.contains('h') && !obj.classList.contains('insertInto')) {
			var afterH = obj.classList.contains('insertAfter');
			var pillEl = obj.parentElement;
			var pill = pillEl ? pillEl.getBoundingClientRect() : rect;
			var neighbour = pillEl && (afterH ? pillEl.nextElementSibling : pillEl.previousElementSibling);
			var gap = 8;
			if (neighbour && neighbour.getBoundingClientRect) {
				var other = neighbour.getBoundingClientRect();
				gap = Math.max(0, afterH ? other.left - pill.right : pill.left - other.right);
			}
			var mid = gap / 2;
            var extension = down.pointerType && down.pointerType !== 'mouse' ? 26 : 0;
			return {
				left: (afterH ? pill.right + mid : pill.left - mid) - 1,
				top: pill.top - extension,
				width: 2,
				height: pill.height + extension + (extension ? 10 : 0),
				line: true,
				borderRadius: 1
			};
		}
		var emptyDashboardColumn = obj.classList.contains('dashDrop') && obj.parentElement && obj.parentElement.children.length === 1;
		if ((zone && obj.classList.contains('v') && !obj.classList.contains('insertInto')) || (obj.classList.contains('dashDrop') && !emptyDashboardColumn)) {
			var after = obj.classList.contains('insertAfter');
			var rowBox = obj.closest && obj.closest('tr');
			var treeLabel = !rowBox && obj.parentElement && obj.closest && obj.closest('ul.tree') ? obj.parentElement : null;
			var span = rowBox ? rowBox.getBoundingClientRect() : (treeLabel ? treeLabel.getBoundingClientRect() : rect);
			var edge = after ? span.bottom : span.top;
			return {
				left: span.left,
				top: edge - 1,
				width: span.width,
				height: 2,
				line: true,
				borderRadius: 1
			};
		}
		var pad = drag.helperPadding;
		var box = {
			left: rect.left + px('borderLeftWidth') + px('paddingLeft'),
			top: rect.top + px('borderTopWidth') + px('paddingTop'),
		};
		box.width = rect.right - px('borderRightWidth') - px('paddingRight') - box.left;
		box.height = rect.bottom - px('borderBottomWidth') - px('paddingBottom') - box.top;
		box.line = box.width < 4 || box.height < 4;
		if (box.line) {
			if (box.height < 4) {
				box.top += box.height / 2 - 1;
				box.height = 2;
			} else {
				box.left += box.width / 2 - 1;
				box.width = 2;
			}
			box.borderRadius = 1;
		} else {
			var radius = parseFloat(style.borderTopLeftRadius) || 0;
			box.left -= pad;
			box.top -= pad;
			box.width += 2 * pad;
			box.height += 2 * pad;
			box.borderRadius = radius ? radius + pad : 0;
		}
		return box;
	}

    // Targets advertise the same extension list used by the server-side media policy.
    var rejectedMediaZone = null;
    function mediaPolicyRejected(zone) {
        if (drag.type !== 'media' || !zone) return false;
        if(zone.closest('.editor.plaintext'))return true;
        var policy = zone.closest('[data-media-accept]');
        if (!policy) return false;
        var ext = drag.dragObj.getAttribute('data-media-extension') || '';
        return (policy.getAttribute('data-media-accept') || '').split(',').indexOf('.' + ext) === -1;
    }
    function markMediaPolicy(target) {
        var policy = target && target.closest && target.closest('[data-media-accept],.editor.plaintext');
        var rejected = policy && mediaPolicyRejected(policy) ? policy : null;
        if (rejectedMediaZone && rejectedMediaZone !== rejected) rejectedMediaZone.classList.remove('dropRejected');
        if (rejected) rejected.classList.add('dropRejected');
        rejectedMediaZone = rejected;
    }

	var isAcceptable = () => {
		if (!drag.dropObj) {
			return;
		}
        if (mediaPolicyRejected(drag.dropObj)) return false;
		if (isSelf(drag.dropObj)) {
			return false;
		}
		// A medium dropped onto its own tile is the click that follows the
		// drag, not a new parent.
		if (drag.type === 'media') {
			var fromId = drag.dragObj.dataset.node_id || drag.dragObj.dataset.nodeId || '';
			var host = drag.dropObj.closest && drag.dropObj.closest('[data-node_id]');
			var toId = host ? (host.dataset.node_id || '') : '';
			if (fromId !== '' && fromId === toId) {
				return false;
			}
		}
		var acceptbleClasses = drag.dropObj.dataset.drop_accept.split(' ');
		if (!acceptbleClasses.includes(drag.type)) {
			return false;
		}
		// A project-list row carries a key and stays in its group. Template
		// settings use the id as the row, so a different id is a valid place.
		if (drag.type === 'setting') {
			var fromKey = drag.dragObj.dataset.key || '';
			var toKey = drag.dropObj.dataset.key || '';
			var fromId = drag.dragObj.dataset.id || '';
			var toId = drag.dropObj.dataset.id || '';
			if (fromKey !== '' && toKey !== '' && fromId !== '' && toId !== '' && fromId !== toId) {
				return false;
			}
		}
		// Drop-Zone der gleichen Gruppe nicht als Ziel (nur andere Gruppen)
		var dragGroup = drag.dragObj.dataset.media_group || drag.dragObj.dataset.mediaGroup;
		var dropGroup = drag.dropObj.dataset.media_group || drag.dropObj.dataset.mediaGroup;
		if ((drag.dropObj.dataset.drop_exclude_same_group || drag.dropObj.dataset.dropExcludeSameGroup)
			&& dragGroup && dropGroup && dragGroup === dropGroup) {
			return false;
		}
		return true;
	};
	function cancelHold() { mouseup($.Event('pointercancel')); }
	if (!pointer && drag.type && !drag.dragObj.dataset.on_draginit
        && (!e.target.closest('a, button, input, select, textarea, label, [contenteditable]')
            || (drag.type === 'page' && e.target.closest('a.label, button.label')?.parentElement === drag.dragObj)
            || (drag.dragObj.matches('button[data-drag_type], label[data-drag_type]') && e.target.closest('a, button, input, select, textarea, label, [contenteditable]') === drag.dragObj))
        && (e.button === undefined || e.button === 0)) {
		holdTimer = setTimeout(function () {
			mouseHeld = true;
			beginDrag();
			if (drag.status !== 'down') mousemove(drag.e);
		}, 400);
		window.addEventListener('blur', cancelHold);
	}
	$(document).bind(moveType, mousemove);
    // A dashboard hold already passed the shared recogniser before its edit render.
    if (e.cmsHeld) {
		mouseHeld = true;
		if (mediaHold) {
			mediaHoldEvent('start');
		} else {
			beginDrag();
			mousemove(e);
		}
	}
	$(document).bind(upType, mouseup);
	e.preventDefault();

};
