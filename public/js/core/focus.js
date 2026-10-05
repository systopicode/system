/* global document, window, Node, NodeFilter */

/**
 * Focus and caret across a document update.
 *
 * Every panel update rebuilds the dom of the layers that changed, and an input
 * that gets replaced while somebody is typing in it loses focus and caret. It
 * hits exactly the fields that cause the update: `data-on_input` and
 * `.autosend` send on every keystroke, and the response re-renders the view
 * they sit in.
 *
 * So: remember what has focus, let the update run, and put the focus back on
 * the replacement node. Only then - if the user or the response moved focus
 * somewhere else meanwhile, it stays where it is.
 *
 *   var focus = focusKeeper.capture();
 *   … swap the dom …
 *   focusKeeper.restore(focus);
 *
 * The field's VALUE is never touched: what the server rendered stays. Only the
 * caret is put back, clamped to the new length.
 */
var focusKeeper = (function () {

	var FIELDS = ['INPUT', 'TEXTAREA', 'SELECT'];

	/** What has the focus, or null when there is nothing worth keeping. */
	function capture() {
		var element = document.activeElement;
		if (!element || !element.tagName || element === document.body) {
			return null;
		}
		var editable = element.isContentEditable === true;
		if (!editable && FIELDS.indexOf(element.tagName) === -1) {
			return null;
		}
		var selector = selectorOf(element);
		if (!selector) {
			return null; // nothing to recognise it by, nothing to find again
		}
		var matches = queryAll(selector);
		return {
			element: element,
			selector: selector,
			index: matches.indexOf(element),
			editable: editable,
			caret: editable ? editableOffset(element) : fieldCaret(element)
		};
	}

	/** Puts the focus back, if the update was what took it away. */
	function restore(focus) {
		if (!focus) {
			return;
		}
		if (focus.element.isConnected) {
			return; // the node survived, so did the focus
		}
		var active = document.activeElement;
		if (active && active !== document.body) {
			return; // something else holds it now - do not steal it back
		}
		var matches = queryAll(focus.selector);
		var element = matches[focus.index] || matches[0];
		if (!element) {
			return;
		}
		try {
			element.focus({preventScroll: true});
		} catch (error) {
			element.focus();
		}
		if (!focus.caret) {
			return;
		}
		if (focus.editable) {
			setEditableOffset(element, focus.caret.offset);
		} else {
			setFieldCaret(element, focus.caret);
		}
	}

	/**
	 * How to recognise the field again: id, else name, else data-field. The
	 * index behind it covers the case of the same name appearing more than once.
	 */
	function selectorOf(element) {
		if (element.id) {
			return '#' + cssEscape(element.id);
		}
		var tag = element.tagName.toLowerCase();
		var name = element.getAttribute('name');
		if (name) {
			return tag + '[name="' + attrEscape(name) + '"]';
		}
		var field = element.getAttribute('data-field');
		if (field) {
			return tag + '[data-field="' + attrEscape(field) + '"]';
		}
		return null;
	}

	function queryAll(selector) {
		try {
			return Array.prototype.slice.call(document.querySelectorAll(selector));
		} catch (error) {
			return [];
		}
	}

	function cssEscape(value) {
		return (window.CSS && CSS.escape) ? CSS.escape(value) : String(value).replace(/([^\w-])/g, '\\$1');
	}

	function attrEscape(value) {
		return String(value).replace(/(["\\])/g, '\\$1');
	}

	/**
	 * selectionStart exists only on fields that carry text - on
	 * checkbox/radio/number/email the access throws in some browsers.
	 */
	function fieldCaret(element) {
		try {
			if (typeof element.selectionStart !== 'number') {
				return null;
			}
			return {
				start: element.selectionStart,
				end: element.selectionEnd,
				direction: element.selectionDirection || 'none'
			};
		} catch (error) {
			return null;
		}
	}

	function setFieldCaret(element, caret) {
		try {
			var length = typeof element.value === 'string' ? element.value.length : 0;
			element.setSelectionRange(
				Math.min(caret.start, length),
				Math.min(caret.end, length),
				caret.direction
			);
		} catch (error) {
			// a field type without a caret - the focus alone is worth something
		}
	}

	/** The caret in a contenteditable, as a character offset from its start. */
	function editableOffset(root) {
		var selection = window.getSelection();
		if (!selection || !selection.rangeCount) {
			return null;
		}
		var current = selection.getRangeAt(0);
		if (!root.contains(current.endContainer)) {
			return null;
		}
		var range = current.cloneRange();
		range.selectNodeContents(root);
		range.setEnd(current.endContainer, current.endOffset);
		return {offset: range.toString().length};
	}

	function setEditableOffset(root, offset) {
		if (typeof offset !== 'number') {
			return;
		}
		var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
		var node, seen = 0, last = null;
		while ((node = walker.nextNode())) {
			if (offset <= seen + node.length) {
				return collapseTo(node, offset - seen);
			}
			seen += node.length;
			last = node;
		}
		// the text got shorter - go to the end of what is there
		last ? collapseTo(last, last.length) : collapseTo(root, 0);
	}

	function collapseTo(node, offset) {
		var range = document.createRange();
		range.setStart(node, Math.max(0, offset));
		range.collapse(true);
		var selection = window.getSelection();
		selection.removeAllRanges();
		selection.addRange(range);
	}

	return {capture: capture, restore: restore};
})();
