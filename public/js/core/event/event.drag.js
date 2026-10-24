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

	function mousemove(e) {
		drag.e = e;

		drag.moveX = e.clientX - drag.downX;
		drag.moveY = e.clientY - drag.downY;
		drag.hooks.move();
		drag.keysheld = keysHoldable.filter(key => e[key + 'Key']);
		if (drag.moveX * drag.moveX + drag.moveY * drag.moveY > 25 && drag.status === 'down') { // drag threshold 5px
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
		if (drag.status !== 'down') {
			keysHoldable.forEach(key => drag.$helper[0].classList.remove(key));
			drag.keysheld.forEach(key => drag.$helper[0].classList.add(key));
			var nextDrop = nearestZone(e.target, e.target.closest('[data-drop_accept]'));
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
				drag.$helper.toggleClass('line', css.line);
				delete css.line;
				drag.$helper.css(css);
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
		if (e.shiftKey && e.ctrlKey) { // debug: freeze dragmode
			$(document).unbind(moveType, mousemove);
			$(document).unbind(upType, mouseup);
			return;
		}
		drag.e = e;
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
			return;
		}
		drag.hooks.release();
		if (drag.status === 'accept') {
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

	// Over an item that brings drop zones of its own (a thumb in a list) the
	// nearer of them is meant, not the list around the item - otherwise the
	// helper jumps between the list and a thin line at every gap.
	function nearestZone(target, drop) {
		for (var el = target; el && el !== drop; el = el.parentElement) {
			var zones = Array.prototype.filter.call(el.children, zone => zone.classList.contains('sort')
				&& (zone.dataset.drop_accept || '').split(' ').includes(drag.type) && zone.getClientRects().length);
			if (zones.length) {
				return el === drag.dragObj ? null : zones.reduce((a, b) => distance(a) <= distance(b) ? a : b);
			}
		}
		return drop;
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
		var zone = obj.classList.contains('sort');
		if (zone && obj.classList.contains('insertInto') && obj.parentElement) {
			obj = obj.parentElement;
			zone = false;
		}
		var style = getComputedStyle(obj);
		var px = name => zone ? parseFloat(style[name]) || 0 : 0;
		var rect = obj.getBoundingClientRect();
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

	var isAcceptable = () => {
		if (!drag.dropObj) {
			return;
		}
		if (drag.dragObj === drag.dropObj) {
			return false;
		}
		var acceptbleClasses = drag.dropObj.dataset.drop_accept.split(' ');
		if (!acceptbleClasses.includes(drag.type)) {
			return false;
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
	$(document).bind(moveType, mousemove);
	$(document).bind(upType, mouseup);
	e.preventDefault();

};