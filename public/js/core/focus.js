/* global document, sys, l, f */

/**
 * The focused element stays in the document while its layer is rebuilt.
 *
 * Every update rebuilds the dom of the layers that changed. An element that
 * is taken out of the document loses the focus: the caret and what was typed
 * since the request went out are gone, the browser fires blur, and a field
 * that saves on blur saves again - its answer re-renders the form, the next
 * field blurs ... (systopic/relay re-renders a form whenever someone saves).
 *
 * So nothing on the way to the focused element is taken out. domLayer.rebuild()
 * builds the new dom of the layer away from the document; keep() finds the
 * focused element's counterpart in it and morphs along the path: the focused
 * element and its ancestors up to the layer's root stay, everything beside
 * that path is the new dom, and the path takes over the new attributes. The
 * element never leaves the document - no blur, the caret, a composition in
 * progress, the scroll position of a container, an open mobile keyboard all
 * stay, in every browser. A field with input keeps it: setting the value
 * attribute does not touch a dirty field, an untouched one shows the new value.
 *
 * Finding the counterpart, the first rule that hits exactly one element:
 *   1. data-focus-id - set it in the template where the others are ambiguous
 *   2. the element's attributes, except the ones a render changes (value,
 *      class, style, state); ids are not used
 *   3. tag, type, name, data-field and the data-on_* actions
 *   4. 3 again, by position, when old and new have the same number of them
 * The paths of old and new must have the same depth and the same tags.
 *
 * When there is no counterpart, the layer is rebuilt as always and the focus
 * is gone. release() notes that, so the blur of the removed element is known
 * to come from a rebuild (sys.processEvent).
 */
var focusPath = (function () {

	/** Attributes a render may change without the element being another one. */
	var VOLATILE = /^(value|class|style|checked|selected|disabled|readonly|hidden|tabindex|id|data-focus-id|aria-.*)$/;

	var released = new WeakSet();

	/** The focused element, or null. */
	function focused() {
		var element = document.activeElement;
		return element && element !== document.body && element !== document.documentElement ? element : null;
	}

	/**
	 * Morph the new dom (the children of host) into the place of the old one,
	 * around the focused element. oldNodes: the root nodes of the old layer and
	 * of all its descendants. Returns {chain, swaps} - the kept elements, top
	 * down, and new => kept for the layer references - or null when it cannot:
	 * then nothing has been touched.
	 */
	function keep(element, oldNodes, host) {
		var top = outermost(oldNodes, element);
		if (!top) {
			return null;
		}
		var counterpart = find(element, scope(oldNodes), scope(Array.from(host.childNodes)));
		if (!counterpart) {
			return null;
		}
		var kept = path(element, top);
		var fresh = path(counterpart, null, host);
		if (!fresh || kept.length !== fresh.length || kept.some((node, i) => node.tagName !== fresh[i].tagName)) {
			f('focusPath: the new dom has the focused element in another place - replaced, focus lost', describe(element));
			return null;
		}
		var swaps = new Map();
		// root level: the new root nodes around the kept one
		var parent = top.parentNode;
		while (host.firstChild !== fresh[0]) {
			parent.insertBefore(host.firstChild, top);
		}
		host.removeChild(fresh[0]);
		var after = top.nextSibling;
		while (host.firstChild) {
			parent.insertBefore(host.firstChild, after);
		}
		for (var i = 0; i < kept.length; i++) {
			var old = kept[i];
			var neu = fresh[i];
			swaps.set(neu, old);
			warnEvents(old, neu);
			attributes(old, neu);
			if (i < kept.length - 1) {
				around(old, neu, kept[i + 1], fresh[i + 1]);
			} else {
				content(old, neu);
			}
		}
		return {chain: kept, swaps: swaps};
	}

	/** The element was removed by a rebuild on purpose - see sys.processEvent. */
	function release(element) {
		element && released.add(element);
	}

	function wasReleased(element) {
		return released.has(element);
	}

	// ------------------------------------------------------------ finding

	function find(element, oldScope, newScope) {
		var focusId = element.getAttribute('data-focus-id');
		if (focusId !== null) {
			return one(newScope.filter(node => node.getAttribute('data-focus-id') === focusId), element, 'data-focus-id');
		}
		var tag = element.tagName;
		var candidates = newScope.filter(node => node.tagName === tag);
		var strict = signature(element, true);
		var hits = candidates.filter(node => signature(node, true) === strict);
		if (hits.length === 1) {
			return hits[0];
		}
		var loose = signature(element, false);
		hits = candidates.filter(node => signature(node, false) === loose);
		if (hits.length === 1) {
			return hits[0];
		}
		if (!hits.length) {
			l('focusPath: the focused element is gone in the new dom', describe(element));
			return null;
		}
		var olds = oldScope.filter(node => node.tagName === tag && signature(node, false) === loose);
		if (olds.length === hits.length) {
			return hits[olds.indexOf(element)] || null;
		}
		f('focusPath: ' + hits.length + ' candidates for the focused element, '
			+ olds.length + ' before - set data-focus-id', describe(element));
		return null;
	}

	function one(hits, element, by) {
		if (hits.length === 1) {
			return hits[0];
		}
		if (hits.length) {
			f('focusPath: ' + by + ' is not unique in the new dom', describe(element));
		}
		return null;
	}

	/** strict: all attributes but the volatile ones; loose: what says which field it is. */
	function signature(element, strict) {
		var parts = [element.tagName];
		Array.from(element.attributes)
			.filter(attribute => strict
				? !VOLATILE.test(attribute.name)
				: /^(type|name|data-field|data-on_.*)$/.test(attribute.name))
			.sort((a, b) => a.name < b.name ? -1 : 1)
			.forEach(attribute => parts.push(attribute.name + '=' + attribute.value));
		return parts.join('\u0001');
	}

	/** Every element of these nodes, each once, in document order. */
	function scope(nodes) {
		var seen = new Set();
		var out = [];
		nodes.forEach(node => {
			if (node.nodeType !== 1 || seen.has(node)) {
				return;
			}
			[node].concat(Array.from(node.querySelectorAll('*'))).forEach(element => {
				if (!seen.has(element)) {
					seen.add(element);
					out.push(element);
				}
			});
		});
		return out;
	}

	/** The one of nodes that contains the element and is contained by no other. */
	function outermost(nodes, element) {
		var holders = nodes.filter(node => node.contains && node.contains(element));
		return holders.find(node => !holders.some(other => other !== node && other.contains(node))) || null;
	}

	/** top ... element; with host: up to the child of host. */
	function path(element, top, host) {
		var nodes = [];
		for (var node = element; node; node = node.parentNode) {
			nodes.unshift(node);
			if (node === top || (host && node.parentNode === host)) {
				return nodes;
			}
		}
		return null;
	}

	function describe(element) {
		return '<' + element.tagName.toLowerCase()
			+ Array.from(element.attributes)
				.filter(attribute => /^(type|name|data-field|data-focus-id|data-on_.*)$/.test(attribute.name))
				.map(attribute => ' ' + attribute.name + '="' + attribute.value + '"').join('')
			+ '>';
	}

	// ------------------------------------------------------------ morphing

	/** old takes the attributes of neu. */
	function attributes(old, neu) {
		Array.from(old.attributes).forEach(attribute => {
			if (!neu.hasAttribute(attribute.name)) {
				old.removeAttribute(attribute.name);
			}
		});
		Array.from(neu.attributes).forEach(attribute => {
			if (old.getAttribute(attribute.name) !== attribute.value) {
				old.setAttribute(attribute.name, attribute.value);
			}
		});
	}

	/** Everything in old but the kept child is replaced by what is in neu around its child. */
	function around(old, neu, keptChild, newChild) {
		Array.from(old.childNodes).forEach(node => node !== keptChild && old.removeChild(node));
		while (neu.firstChild !== newChild) {
			old.insertBefore(neu.firstChild, keptChild);
		}
		neu.removeChild(newChild);
		while (neu.firstChild) {
			old.appendChild(neu.firstChild);
		}
	}

	/**
	 * The focused element itself: what one types in keeps its content
	 * (textarea, contenteditable), anything else shows the new one - the label
	 * of a button, the options of a select (with the choice, if still there).
	 */
	function content(old, neu) {
		if (old.tagName === 'TEXTAREA' || old.isContentEditable) {
			return;
		}
		var choice = old.tagName === 'SELECT' ? old.value : null;
		while (old.firstChild) {
			old.removeChild(old.firstChild);
		}
		while (neu.firstChild) {
			old.appendChild(neu.firstChild);
		}
		if (choice !== null && Array.from(old.options).some(option => option.value === choice)) {
			old.value = choice;
		}
	}

	/** Events are bound when an element is built - a kept one keeps the old ones. */
	function warnEvents(old, neu) {
		var actions = element => Array.from(element.attributes)
			.filter(attribute => /^data-on_/.test(attribute.name))
			.map(attribute => attribute.name + '=' + attribute.value).sort().join(' ');
		if (actions(old) !== actions(neu)) {
			f('focusPath: a kept element has other actions now, its events are the old ones', describe(old), describe(neu));
		}
	}

	return {focused: focused, keep: keep, release: release, wasReleased: wasReleased};
})();
