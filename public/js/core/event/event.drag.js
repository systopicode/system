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
	drag.helperBorder = 2;
	drag.hasHelper = true;

	drag.dragObj = e.currentTarget;
	drag.dropObj = null;
	drag.downX = e.clientX;
	drag.downY = e.clientY;
	drag.status = 'down';
	drag.type = drag.dragObj.dataset.drag_type;
	var css, keysHoldable = ['shift', 'ctrl', 'alt'];
	drag.e = e;

	function mousemove(e) {
		if (e.target.tagName === 'S') { // ignore hover if draghelper is touched
			return;
			// e.target.className.parentNode === 'cms draghelper'
		}
		drag.e = e;

		drag.moveX = e.clientX - drag.downX;
		drag.moveY = e.clientY - drag.downY;
		drag.hooks.move();
		drag.keysheld = keysHoldable.filter(key => e[key + 'Key']);
		if (drag.moveX * drag.moveX + drag.moveY * drag.moveY > 25 && drag.status === 'down') { // drag threshold 5px
			drag.hooks.start();
			drag.status = 'dragging';
			drag.$helper = $('<div class="draghelper"><s></s><s></s><s></s><s></s></div>');
			drag.$helper.append(drag.$keyheldIcon);
			$('body').append(drag.$helper);
			$('body').addClass('dragging');
			$('body').addClass(drag.type + 'Dragging');
			drag.dragObj.classList.add('dragged');
			drag.dragObj.previousElementSibling && drag.dragObj.previousElementSibling.classList.add('beforeDragged');
			drag.dragObj.nextElementSibling && drag.dragObj.nextElementSibling.classList.add('afterDragged');
			// ?don't extend helper to parent objects
			// drag.dragObj.parents('[data-drop_accept="' + drag.type + '"]').attr('data-drop_accept', drag.type + '_parent');
			if (!drag.hasHelper) {
				drag.$helper.hide();
			}
		}
		if (drag.status !== 'down') {
			keysHoldable.forEach(key => drag.$helper[0].classList.remove(key));
			drag.keysheld.forEach(key => drag.$helper[0].classList.add(key));
			drag.dropObj = e.target.closest('[data-drop_accept]');
			if (isAcceptable()) {
				drag.status = 'accept';
				if (drag.hasHelper) {
					drag.$helper.addClass('accept');
					css = getBoundingBox(drag.dropObj);
				}
			} else {
				drag.status = 'dragging';
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
				drag.$helper.css(css);
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
			$(document).unbind('mousemove', mousemove);
			$(document).unbind('mouseup', mouseup);
			return;
		}
		drag.e = e;
		// Always undo what mousemove set up: hooks may switch hasHelper off
		// mid-drag (e.g. grid repositioning), which used to leave the helper in
		// the DOM and body.dragging / body.<type>Dragging stuck for good.
		if (drag.$helper) {
			drag.$helper.remove();
		}
		$('body').removeClass('dragging');
		$('body').removeClass(drag.type + 'Dragging');
		drag.dragObj.classList.remove('dragged');
		drag.dragObj.previousElementSibling && drag.dragObj.previousElementSibling.classList.remove('beforeDragged');
		drag.dragObj.nextElementSibling && drag.dragObj.nextElementSibling.classList.remove('afterDragged');
		// ?don't extend helper to parent objects
		// drag.dragObj.parents('[data-drop_accept="' + drag.type + '_parent"]').attr('data-drop_accept', drag.type);

		$(document).unbind('mousemove', mousemove);
		$(document).unbind('mouseup', mouseup);
		drag.hooks.release();
		if (drag.status === 'accept') {
			drag.hooks.accept();
		}
	}

	function getBoundingBox(obj) {
		var $obj = $(obj); // wip remove jQuery
		var padding = {
			top: ~~$obj.css('padding-top').replace('px', ''),
			right: ~~$obj.css('padding-right').replace('px', ''),
			bottom: ~~$obj.css('padding-bottom').replace('px', ''),
			left: ~~$obj.css('padding-left').replace('px', '')
		};
		var border = {
			top: ~~$obj.css('border-top').replace('px', ''),
			right: ~~$obj.css('border-right').replace('px', ''),
			bottom: ~~$obj.css('border-bottom').replace('px', ''),
			left: ~~$obj.css('border-left').replace('px', '')
		};
		return {
			width: $obj.outerWidth() - padding.left - padding.right - border.left - border.right + drag.helperBorder + 2 * drag.helperPadding,
			height: $obj.outerHeight() - padding.top - padding.bottom - border.top - border.bottom + drag.helperBorder + 2 * drag.helperPadding,
			left: $obj.offset().left - $obj.width() + padding.left + border.left - 2 * drag.helperBorder - 3 * drag.helperPadding,
			top: $obj.offset().top - $obj.height() + padding.top + border.top - 2 * drag.helperBorder - 3 * drag.helperPadding
		};
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
	$(document).bind('mousemove', mousemove);
	$(document).bind('mouseup', mouseup);
	e.preventDefault();

};