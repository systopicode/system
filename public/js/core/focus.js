/* global document, window, Node, NodeFilter, sys, l */

/**
 * The field being edited survives a document update.
 *
 * Every update rebuilds the dom of the layers that changed. A field that is
 * rebuilt while somebody types in it loses focus, caret and - with the
 * latency of a server render - the characters typed since the request went
 * out. The browser also fires blur for it, and a form that saves on blur
 * saves again, its answer re-renders the form, the next field blurs ... an
 * endless round, as soon as anything re-renders the form while one edits it
 * (the hub's signal after a save, systopic/relay).
 *
 * So the field being edited is never replaced. capture() parks the very
 * element outside the layers, the update rebuilds whatever it rebuilds, and
 * restore() moves it into the place of its counterpart in the new dom - the
 * element the server rendered in its stead is dropped. Same node: focus,
 * caret, value, a composition in progress, the listeners - all stay. Moved
 * with Element.moveBefore(), which keeps the focus and fires nothing; all of
 * it in one task, so nothing is painted in between. The rest of the layer -
 * a result list next to a search field - is new as usual.
 *
 *   var focus = focusKeeper.capture();
 *   … swap the dom …
 *   focusKeeper.restore(focus);
 *
 * Without moveBefore (older browsers) the fallback: the field is replaced, its
 * counterpart takes over value and caret and gets the focus back; the blur of
 * the removed one is ignored (sys.processEvent).
 *
 * Only for a field one types in or picks from: text-like inputs, textarea,
 * select, contenteditable. A button or checkbox with focus is replaced as
 * rendered.
 */
var focusKeeper = (function () {

	var TEXT_INPUT = /^(text|search|email|url|tel|password|number|date|datetime-local|month|week|time)$/;

	/** The field being edited, or null. */
	function editing() {
		var element = document.activeElement;
		if (!element || !element.tagName || element === document.body) {
			return null;
		}
		if (element.isContentEditable === true || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT') {
			return element;
		}
		if (element.tagName === 'INPUT' && TEXT_INPUT.test(String(element.type || 'text').toLowerCase())) {
			return element;
		}
		return null;
	}

	var canMove = typeof Element !== 'undefined' && typeof Element.prototype.moveBefore === 'function';

	/** What is being edited - parked out of reach of the update when the browser can. */
	function capture() {
		var element = editing();
		if (!element) {
			return null;
		}
		var selector = selectorOf(element);
		if (!selector) {
			return null; // nothing to recognise it by, nothing to find again
		}
		var editable = element.isContentEditable === true;
		var focus = {
			element: element,
			selector: selector,
			index: queryAll(selector).indexOf(element),
			editable: editable,
			value: editable ? null : element.value,
			caret: editable ? editableOffset(element) : fieldCaret(element),
			parked: null
		};
		if (canMove && !editable) {
			park(focus);
		}
		return focus;
	}

	/** Back into the new dom: the same element where it was, or in its counterpart's place. */
	function restore(focus) {
		if (!focus) {
			return;
		}
		if (focus.parked) {
			unpark(focus);
			return;
		}
		if (focus.element.isConnected) {
			return; // the node survived, so did the focus
		}
		var active = document.activeElement;
		if (active && active !== document.body) {
			return; // something else holds it now - do not steal it back
		}
		var element = counterpart(focus);
		if (!element) {
			return;
		}
		if (focus.value !== null && element.value !== focus.value) {
			element.value = focus.value; // typed since the request went out
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

	// ------------------------------------------------------------ parking

	/**
	 * The element leaves its place for a hidden one; a marker keeps the place.
	 * Layers that list the element (a root node, a data-doc node) list the
	 * marker meanwhile, so a layer that is NOT rebuilt finds it there.
	 */
	function park(focus) {
		var element = focus.element;
		// Rendered, only out of sight: an element in a display:none box is not
		// focusable, and the next style update would take the focus after all.
		var lot = document.createElement('div');
		lot.setAttribute('data-focus-parking', '');
		lot.setAttribute('aria-hidden', 'true');
		lot.style.cssText = 'position:fixed;left:-10000px;top:0;width:1px;height:1px;overflow:hidden';
		document.body.appendChild(lot);
		var marker = document.createComment('focusKeeper');
		element.parentNode.insertBefore(marker, element);
		try {
			lot.moveBefore(element, null);
		} catch (error) {
			marker.remove();   // not movable here - the fallback takes over
			lot.remove();
			return;
		}
		swapReferences(element, marker);
		focus.parked = {lot: lot, marker: marker};
	}

	function unpark(focus) {
		var element = focus.element;
		var marker = focus.parked.marker;
		var lot = focus.parked.lot;
		focus.parked = null;
		var place = marker.isConnected ? marker : counterpart(focus);
		if (!place) {
			// the field is gone in the new dom: the user went somewhere else
			lot.remove();
			return;
		}
		try {
			// back where it was (nothing rebuilt it), or into its counterpart's
			// place - the value the server rendered there is older than what
			// was typed since
			place.parentNode.moveBefore(element, place);
		} catch (error) {
			lot.remove();      // the element goes; its counterpart takes over value and caret
			restore(focus);
			return;
		}
		swapReferences(place, element);
		// A script of the new dom may have focused the counterpart (an
		// autofocus for the search field it renders) - removing it would take
		// the focus to the body. Then, or when it is lost anyway, the kept
		// field gets it back; a focus a script put somewhere else stays there.
		var stolen = document.activeElement === place;
		place.remove();
		lot.remove();
		if (place !== marker) {
			l('focusKeeper: kept the field being edited - ' + focus.selector);
		}
		var active = document.activeElement;
		if (active !== element && (stolen || !active || active === document.body)) {
			refocus(element, focus);
		}
	}

	function refocus(element, focus) {
		try {
			element.focus({preventScroll: true});
		} catch (error) {
			element.focus();
		}
		if (focus.caret && !focus.editable) {
			setFieldCaret(element, focus.caret);
		}
	}

	/** The layers that pointed at one node point at the other. */
	function swapReferences(from, to) {
		var walk = layer => {
			if (!layer) {
				return;
			}
			var index = layer.rootNodes ? layer.rootNodes.indexOf(from) : -1;
			if (index !== -1) {
				layer.rootNodes[index] = to;
			}
			var docs = layer.docNodes || {};
			Object.keys(docs).forEach(name => {
				if (docs[name] === from) {
					docs[name] = to;
				} else if (Array.isArray(docs[name])) {
					var at = docs[name].indexOf(from);
					if (at !== -1) {
						docs[name][at] = to;
					}
				}
			});
			(layer.children || []).forEach(walk);
		};
		if (window.sys && sys.dom) {
			walk(sys.dom.head);
			walk(sys.dom.body);
		}
	}

	// ------------------------------------------------------------ finding

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

	/** The new element in the place of the old one, the old one itself left out. */
	function counterpart(focus) {
		var matches = queryAll(focus.selector).filter(element => element !== focus.element);
		return matches[focus.index] || matches[0] || null;
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

	// ------------------------------------------------------------ caret

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

	return {capture: capture, restore: restore, editing: editing};
})();
