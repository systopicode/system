/* global sys, wysiwygOf, editorRange */
/**
 * Formatting of the WYSIWYG fields on the DOM itself - execCommand writes what
 * each browser likes (b or strong, spans with colours, empty links) and leaves
 * the menus guessing. What the menus offer is what a field may hold (PASTE):
 * pasted HTML is cut down to it, and the HTML that is saved loses what browsers
 * and earlier pastes left behind. Changing the DOM by hand ends the browser's
 * undo, so the fields keep their own history.
 */
var wysiwyg = (function () {
	var TEXT_BLOCKS = 'p,h1,h2,h3,h4,h5,h6';
	var BLOCKS = TEXT_BLOCKS + ',li,td,th,div';
	var FORMATS = {STRONG: 'strong,b', EM: 'em,i', U: 'u', S: 's,strike,del'};
	var INLINE_STYLES = 'strong,b,em,i,u,s,strike,sub,sup,mark,small,big,code,span,font';
	var MEDIA = 'img,video,iframe';
	var PASTE = {
		P: [], H1: [], H2: [], H3: [], H4: [], H5: [], H6: [], UL: [], OL: [], LI: [], BR: [],
		STRONG: [], EM: [], U: [], S: [], A: ['href', 'target'],
		TABLE: [], THEAD: [], TBODY: [], TR: [], TD: ['colspan', 'rowspan'], TH: ['colspan', 'rowspan']
	};
	var PASTE_DROP = 'script,style,meta,link,title,template,svg,math,object,embed,iframe,img,video,audio,picture,canvas,form,input,button,select,textarea,noscript';
	var PASTE_BLOCKISH = /^(DIV|SECTION|ARTICLE|HEADER|FOOTER|ASIDE|MAIN|NAV|BLOCKQUOTE|PRE|ADDRESS|FIGURE|FIGCAPTION|DT|DD)$/;

	var element = function (node) {
		return node && (node.nodeType === 1 ? node : node.parentElement);
	};
	/** A text field: the field is the block, only words inside it are formatted. */
	var inline = function (editor) {
		return !!editor && editor.classList.contains('plaintext');
	};
	var rename = function (el, tag) {
		var to = document.createElement(tag);
		Array.from(el.attributes).forEach(function (attr) {
			to.setAttribute(attr.name, attr.value);
		});
		while (el.firstChild) {
			to.appendChild(el.firstChild);
		}
		el.replaceWith(to);
		return to;
	};
	var unwrap = function (el) {
		el.replaceWith.apply(el, Array.from(el.childNodes));
	};
	var visible = function (el) {
		return /\S/.test(el.textContent) || !!el.querySelector(MEDIA);
	};
	/** Moves what lies beside node (inside ancestor) into copies of ancestor before and after it. */
	var isolate = function (ancestor, node) {
		var path = [];
		for (var n = node; n !== ancestor; n = n.parentNode) {
			path.unshift(n);
		}
		var left = ancestor.cloneNode(false), right = ancestor.cloneNode(false);
		var intoLeft = left, intoRight = right, container = ancestor;
		path.forEach(function (child, i) {
			while (container.firstChild !== child) {
				intoLeft.appendChild(container.firstChild);
			}
			var after = [];
			while (child.nextSibling) {
				after.push(child.nextSibling);
				child.nextSibling.remove();
			}
			after.forEach(function (sibling) {
				intoRight.appendChild(sibling);
			});
			if (i < path.length - 1) {
				var l = child.cloneNode(false), r = child.cloneNode(false);
				intoLeft.appendChild(l);
				intoRight.insertBefore(r, intoRight.firstChild);
				intoLeft = l;
				intoRight = r;
				container = child;
			}
		});
		if (left.querySelector('*') || /\S/.test(left.textContent)) {
			ancestor.before(left);
		}
		if (right.querySelector('*') || /\S/.test(right.textContent)) {
			ancestor.after(right);
		}
	};

	/* ---------------------------------------------------------------- cleaning */

	var wordLists = function (root) {
		var list = null;
		root.querySelectorAll('p[style*="mso-list"], p[class^="MsoListParagraph"]').forEach(function (p) {
			var marker = p.querySelector('[style*="mso-list:Ignore"], [style*="mso-list: Ignore"]');
			var markerText = marker ? marker.textContent : (p.textContent.match(/^\s*(\S{1,3})\s/) || ['', ''])[1];
			var tag = /^[\da-zA-Z]{1,3}[.)]/.test(markerText.trim()) ? 'OL' : 'UL';
			if (marker) {
				marker.remove();
			} else {
				var first = document.createTreeWalker(p, NodeFilter.SHOW_TEXT).nextNode();
				first && (first.data = first.data.replace(/^\s*([·•o§▪\-–]|[\da-zA-Z]{1,3}[.)])[\s\u00a0]+/, ''));
			}
			if (!list || p.previousElementSibling !== list || list.nodeName !== tag) {
				list = document.createElement(tag);
				p.before(list);
			}
			var li = document.createElement('li');
			while (p.firstChild) {
				li.appendChild(p.firstChild);
			}
			list.appendChild(li);
			p.remove();
		});
	};
	/** Bold, italic and underline set by a style (Google Docs, Word) as elements around the content. */
	var styleFormats = function (el) {
		var style = el.style;
		var tags = [];
		if (!style) {
			return tags;
		}
		/^(bold|bolder|[6-9]00)$/.test(style.fontWeight) && tags.push('STRONG');
		style.fontStyle === 'italic' && tags.push('EM');
		/underline/.test(style.textDecoration || style.textDecorationLine) && tags.push('U');
		/line-through/.test(style.textDecoration || style.textDecorationLine) && tags.push('S');
		return tags;
	};
	var wrapChildren = function (el, tags) {
		tags.forEach(function (tag) {
			var wrap = document.createElement(tag);
			while (el.firstChild) {
				wrap.appendChild(el.firstChild);
			}
			el.appendChild(wrap);
		});
	};
	var cleanNode = function (node, root, paste) {
		if (node.nodeType === 3) {
			if (paste) {
				node.data = node.data.replace(/\u00a0/g, ' ').replace(/\s*[\r\n]+\s*/g, ' ');
			}
			return;
		}
		if (node.nodeType !== 1) {
			node.remove();
			return;
		}
		// media: / page: (Cms\Refs). The cleaning stays; a reference is part of it.
		// Otherwise the save stores the shown text and the reference is gone.
		var refAttr = node.getAttributeNames().some(function (name) {
			return name === 'data-ref' || name.indexOf('data-ref-') === 0;
		});
		var refUrl = ['src', 'href', 'poster'].some(function (name) {
			return /^(media|page):/i.test(node.getAttribute(name) || '');
		});
		if (refAttr || refUrl) {
			return;
		}
		if (paste && node.matches(PASTE_DROP)) {
			node.remove();
			return;
		}
		if (node.matches(MEDIA)) {
			return;
		}
		var styled = paste ? styleFormats(node) : [];
		var plainBold = paste && /^(B|STRONG)$/.test(node.nodeName) && /^(normal|[1-5]00)$/.test(node.style.fontWeight);
		Array.from(node.childNodes).forEach(function (child) {
			cleanNode(child, root, paste);
		});
		var name = node.nodeName;
		if (plainBold) {
			wrapChildren(node, styled);
			unwrap(node);
			return;
		}
		if (name === 'B' || name === 'I' || name === 'STRIKE' || name === 'DEL') {
			node = rename(node, {B: 'STRONG', I: 'EM'}[name] || 'S');
			name = node.nodeName;
		}
		if (paste) {
			wrapChildren(node, styled);
			if (!PASTE[name]) {
				if (PASTE_BLOCKISH.test(name) && !node.querySelector(TEXT_BLOCKS + ',ul,ol,table')) {
					node = rename(node, 'P');
					name = 'P';
				} else {
					unwrap(node);
					return;
				}
			}
			Array.from(node.attributes).forEach(function (attr) {
				if (PASTE[name].indexOf(attr.name) < 0 && attr.name.indexOf('data-ref') !== 0) {
					node.removeAttribute(attr.name);
				}
			});
			if (name === 'A' && /^\s*javascript:/i.test(node.getAttribute('href') || '')) {
				node.removeAttribute('href');
			}
			if (name === 'LI' && !node.parentElement.closest('ul,ol')) {
				node = rename(node, 'P');
				name = 'P';
			}
			if (/^(UL|OL)$/.test(name)) {
				Array.from(node.childNodes).forEach(function (child) {
					if (child.nodeName !== 'LI') {
						if (/\S/.test(child.textContent)) {
							var li = document.createElement('li');
							child.before(li);
							li.appendChild(child);
						} else {
							child.remove();
						}
					}
				});
			}
			if (/^(P|H\d)$/.test(name)) {
				node.querySelectorAll(TEXT_BLOCKS).forEach(unwrap);
				if (!visible(node)) {
					node.remove();
					return;
				}
			}
			if (/^(STRONG|EM|U|S|A)$/.test(name) && node.querySelector(TEXT_BLOCKS + ',ul,ol,li,table')) {
				unwrap(node);
				return;
			}
		} else if (name === 'SPAN' || name === 'FONT') {
			unwrap(node);
			return;
		} else if (name === 'DIV' && node.parentNode === root && !node.attributes.length && !node.querySelector(TEXT_BLOCKS + ',ul,ol,table,div')) {
			node = rename(node, 'P');
			name = 'P';
		}
		if (name === 'A' && !node.hasAttribute('href') && !node.hasAttribute('data-ref-href') && !node.id && !node.hasAttribute('name')) {
			unwrap(node);
			return;
		}
		if (/^(STRONG|EM|U|S|A)$/.test(name)) {
			var same = name === 'A' ? 'a' : FORMATS[name] || name;
			if (!visible(node) || (node.parentElement.closest(same) && root.contains(node.parentElement.closest(same)))) {
				unwrap(node);
			}
		}
	};
	/** Neighbouring <strong>a</strong><strong>b</strong> become one. */
	var merge = function (root) {
		root.querySelectorAll('strong,em,u,s').forEach(function (el) {
			if (!root.contains(el) || el.attributes.length) {
				return;
			}
			var next = el.nextSibling;
			while (next && next.nodeType === 3 && next.length === 0) {
				next.remove();
				next = el.nextSibling;
			}
			while (next && next.nodeName === el.nodeName && !next.attributes.length) {
				while (next.firstChild) {
					el.appendChild(next.firstChild);
				}
				next.remove();
				next = el.nextSibling;
			}
		});
	};
	var looseToParagraphs = function (root) {
		var run = [];
		var flush = function () {
			if (run.some(function (n) {
				return /\S/.test(n.textContent) || (n.nodeType === 1 && n.matches(MEDIA));
			})) {
				var p = document.createElement('p');
				run[0].before(p);
				run.forEach(function (n) {
					p.appendChild(n);
				});
				while (p.lastChild && p.lastChild.nodeName === 'BR') {
					p.lastChild.remove();
				}
			} else {
				run.forEach(function (n) {
					n.remove();
				});
			}
			run = [];
		};
		Array.from(root.childNodes).forEach(function (node) {
			if (node.nodeType === 1 && node.matches(TEXT_BLOCKS + ',ul,ol,table')) {
				flush();
			} else {
				run.push(node);
			}
		});
		flush();
	};
	var clean = function (root, paste) {
		if (paste) {
			wordLists(root);
		}
		Array.from(root.childNodes).forEach(function (node) {
			cleanNode(node, root, paste);
		});
		if (paste && root.querySelector(':scope > :is(' + TEXT_BLOCKS + ',ul,ol,table)')) {
			looseToParagraphs(root);
		}
		merge(root);
		return root;
	};

	/* --------------------------------------------------------------- selection */

	var context = function () {
		var selection = getSelection();
		if (!selection.rangeCount) {
			return null;
		}
		var range = selection.getRangeAt(0);
		var editor = wysiwygOf(range.commonAncestorContainer);
		return editor ? {range: range.cloneRange(), editor: editor} : null;
	};
	/** A range as text offsets in the field: they survive the elements around the text. */
	var rangeOffsets = function (editor, range) {
		var measure = document.createRange();
		measure.selectNodeContents(editor);
		measure.setEnd(range.startContainer, range.startOffset);
		var start = measure.toString().length;
		measure.setEnd(range.endContainer, range.endOffset);
		return [start, measure.toString().length];
	};
	var offsets = function (editor) {
		var selection = getSelection();
		if (!selection.rangeCount || !editor.contains(selection.getRangeAt(0).startContainer)) {
			return null;
		}
		return rangeOffsets(editor, selection.getRangeAt(0));
	};
	var select = function (sc, so, ec, eo) {
		var range = document.createRange();
		range.setStart(sc, so);
		range.setEnd(ec, eo);
		var selection = getSelection();
		selection.removeAllRanges();
		selection.addRange(range);
		editorRange = range.cloneRange();
	};
	var restore = function (editor, mark) {
		var range = mark && rangeAt(editor, mark);
		range && select(range.startContainer, range.startOffset, range.endContainer, range.endOffset);
	};
	var rangeAt = function (editor, mark) {
		var walker = document.createTreeWalker(editor, NodeFilter.SHOW_TEXT);
		var pos = 0, sc, so, ec, eo, last, t;
		while ((t = walker.nextNode())) {
			if (!t.length) {
				continue;
			}
			if (!sc && mark[0] < pos + t.length) {
				sc = t;
				so = mark[0] - pos;
			}
			if (!ec && mark[1] <= pos + t.length) {
				ec = t;
				eo = mark[1] - pos;
			}
			pos += t.length;
			last = t;
		}
		var range = document.createRange();
		if (!last) {
			range.selectNodeContents(editor);
			range.collapse(false);
			return range;
		}
		ec = ec || last;
		eo = ec === last && eo === undefined ? last.length : eo;
		if (!sc || mark[0] === mark[1]) {
			sc = ec;
			so = eo;
		}
		range.setStart(sc, so);
		range.setEnd(ec, eo);
		return range;
	};
	var splitBoundaries = function (range) {
		var sc = range.startContainer, so = range.startOffset, ec = range.endContainer, eo = range.endOffset;
		if (ec.nodeType === 3 && eo > 0 && eo < ec.length) {
			ec.splitText(eo);
		}
		if (sc.nodeType === 3 && so > 0 && so < sc.length) {
			var rest = sc.splitText(so);
			if (ec === sc) {
				ec = rest;
				eo -= so;
			}
			sc = rest;
			so = 0;
		}
		range.setStart(sc, so);
		range.setEnd(ec, eo);
	};
	var textsIn = function (range) {
		var root = range.commonAncestorContainer;
		if (root.nodeType === 3) {
			return root.length ? [root] : [];
		}
		var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
		var texts = [], t;
		while ((t = walker.nextNode())) {
			if (!t.length || !range.intersectsNode(t)
					|| (t === range.startContainer && range.startOffset >= t.length)
					|| (t === range.endContainer && range.endOffset === 0)
					|| (!/\S/.test(t.data) && t.parentNode.matches('ul,ol,table,thead,tbody,tr,.editor'))) {
				continue;
			}
			texts.push(t);
		}
		return texts;
	};
	var wordAt = function (range) {
		var node = range.startContainer, i = range.startOffset;
		if (node.nodeType !== 3) {
			return;
		}
		var letter = /[\p{L}\p{N}_'’-]/u, s = i, e = i;
		while (s > 0 && letter.test(node.data[s - 1])) {
			s--;
		}
		while (e < node.length && letter.test(node.data[e])) {
			e++;
		}
		range.setStart(node, s);
		range.setEnd(node, e);
	};
	var formatOf = function (node, tag, editor) {
		var el = node.parentElement && node.parentElement.closest(FORMATS[tag]);
		return el && el !== editor && editor.contains(el) ? el : null;
	};
	var isInline = function (node) {
		return node.nodeType === 3 || (node.nodeType === 1 && !node.matches(TEXT_BLOCKS + ',ul,ol,table,div,blockquote,hr'));
	};
	/** The block around node; loose text in the field becomes a paragraph first. */
	var blockOf = function (node, editor) {
		if (inline(editor)) {
			return null;
		}
		var block = element(node).closest(BLOCKS);
		if (block && block !== editor && editor.contains(block)) {
			return block;
		}
		if (node === editor) {
			return null;
		}
		var top = node;
		while (top.parentNode !== editor) {
			top = top.parentNode;
		}
		if (!isInline(top)) {
			return null;
		}
		var first = top, last = top;
		while (first.previousSibling && isInline(first.previousSibling)) {
			first = first.previousSibling;
		}
		while (last.nextSibling && isInline(last.nextSibling)) {
			last = last.nextSibling;
		}
		var p = document.createElement('p');
		first.before(p);
		for (var n = first, next; n; n = next) {
			next = n === last ? null : n.nextSibling;
			p.appendChild(n);
		}
		return p;
	};
	var blocksIn = function (range, editor) {
		var nodes = range.collapsed ? [] : textsIn(range);
		if (!nodes.length) {
			var start = range.startContainer;
			nodes = [start.nodeType === 1 && start.childNodes[range.startOffset] || start];
		}
		var blocks = [];
		nodes.forEach(function (node) {
			var block = blockOf(node, editor);
			block && blocks.indexOf(block) < 0 && blocks.push(block);
		});
		return blocks;
	};

	/* ---------------------------------------------------------------- history */

	var histories = new WeakMap();
	var typingTimer = null;
	var record = function (editor) {
		var history = histories.get(editor);
		if (!history) {
			histories.set(editor, history = {stack: [], at: -1});
		}
		var html = editor.innerHTML;
		var current = history.stack[history.at];
		if (current && current.html === html) {
			current.mark = offsets(editor) || current.mark;
			return;
		}
		history.stack.length = history.at + 1;
		history.stack.push({html: html, mark: offsets(editor)});
		if (history.stack.length > 100) {
			history.stack.shift();
		}
		history.at = history.stack.length - 1;
	};
	var begin = function (editor) {
		clearTimeout(typingTimer);
		record(editor);
	};
	var changed = function (editor) {
		if (!editor) {
			return;
		}
		record(editor);
		var input = new Event('input', {bubbles: true});
		input.wysiwyg = true;
		editor.dispatchEvent(input);
	};
	var step = function (editor, by) {
		begin(editor);
		var history = histories.get(editor);
		var to = history.stack[history.at + by];
		if (!to) {
			return;
		}
		history.at += by;
		editor.innerHTML = to.html;
		restore(editor, to.mark);
		var input = new Event('input', {bubbles: true});
		input.wysiwyg = true;
		editor.dispatchEvent(input);
	};

	/* ---------------------------------------------------------------- commands */

	var toggle = function (tag) {
		var ctx = context();
		if (!ctx) {
			return;
		}
		begin(ctx.editor);
		var range = ctx.range;
		var mark = offsets(ctx.editor);
		if (range.collapsed) {
			wordAt(range);
			if (range.collapsed) {
				return;
			}
		}
		splitBoundaries(range);
		var texts = textsIn(range);
		if (!texts.length) {
			return;
		}
		var on = texts.every(function (t) {
			return formatOf(t, tag, ctx.editor);
		});
		texts.forEach(function (t) {
			var el;
			if (on) {
				while ((el = formatOf(t, tag, ctx.editor))) {
					isolate(el, t);
					unwrap(el);
				}
			} else if (!formatOf(t, tag, ctx.editor)) {
				el = document.createElement(tag);
				t.before(el);
				el.appendChild(t);
			}
		});
		clean(ctx.editor, false);
		restore(ctx.editor, mark);
		changed(ctx.editor);
	};
	var setBlock = function (tag) {
		var ctx = context();
		if (!ctx) {
			return;
		}
		begin(ctx.editor);
		var mark = offsets(ctx.editor);
		var blocks = blocksIn(ctx.range, ctx.editor);
		var to = tag !== 'P' && blocks.every(function (b) {
			return b.nodeName === tag;
		}) ? 'P' : tag;
		var lifted = [];
		blocks.forEach(function (block) {
			var li = block.closest('li');
			// A bullet or a number becomes the paragraph or the headline.
			// Leaving it inside the item kept the marker and the tag did nothing.
			if (li && li !== ctx.editor && ctx.editor.contains(li)) {
				if (lifted.indexOf(li) < 0) {
					lifted.push(li);
					var list = li.parentNode;
					if (list && /^(UL|OL)$/.test(list.nodeName)) {
						if (li.children.length === 1 && li.firstElementChild.matches(TEXT_BLOCKS)) {
							unwrap(li.firstElementChild);
						}
						isolate(list, li);
						list.replaceWith(rename(li, to));
					}
				}
				return;
			}
			if (block.matches('td,th')) {
				var heading = block.querySelector(':scope > :is(' + TEXT_BLOCKS + ')');
				if (to === 'P') {
					heading && unwrap(heading);
				} else if (heading) {
					rename(heading, to);
				} else {
					wrapChildren(block, [to]);
				}
			} else if (block.nodeName !== to) {
				rename(block, to).removeAttribute('style');
			}
		});
		ctx.editor.querySelectorAll('ul:empty,ol:empty').forEach(function (empty) {
			empty.remove();
		});
		restore(ctx.editor, mark);
		changed(ctx.editor);
	};
	var toggleList = function (tag) {
		var ctx = context();
		if (!ctx) {
			return;
		}
		begin(ctx.editor);
		var mark = offsets(ctx.editor);
		var blocks = [];
		blocksIn(ctx.range, ctx.editor).forEach(function (block) {
			block = block.closest('li') || block;
			!block.matches('td,th') && blocks.indexOf(block) < 0 && blocks.push(block);
		});
		if (!blocks.length) {
			return;
		}
		var items = blocks.filter(function (b) {
			return b.nodeName === 'LI';
		});
		if (items.length === blocks.length && items.every(function (li) {
			return li.parentNode.nodeName === tag;
		})) {
			items.forEach(function (li) {
				var list = li.parentNode;
				isolate(list, li);
				list.replaceWith(rename(li, 'P'));
			});
		} else if (items.length === blocks.length) {
			items.map(function (li) {
				return li.parentNode;
			}).filter(function (list, i, all) {
				return all.indexOf(list) === i;
			}).forEach(function (list) {
				rename(list, tag);
			});
		} else {
			var list = document.createElement(tag);
			blocks[0].before(list);
			blocks.forEach(function (block) {
				var li = block.nodeName === 'LI' ? block : document.createElement('li');
				if (li !== block) {
					while (block.firstChild) {
						li.appendChild(block.firstChild);
					}
					block.remove();
				}
				list.appendChild(li);
			});
			[list.previousElementSibling, list.nextElementSibling].forEach(function (sibling) {
				if (sibling && sibling.nodeName === tag) {
					while (sibling.firstChild) {
						sibling === list.previousElementSibling ? list.insertBefore(sibling.lastChild, list.firstChild) : list.appendChild(sibling.firstChild);
					}
					sibling.remove();
				}
			});
		}
		ctx.editor.querySelectorAll('ul:empty,ol:empty').forEach(function (empty) {
			empty.remove();
		});
		restore(ctx.editor, mark);
		changed(ctx.editor);
	};
	var clearFormat = function () {
		var ctx = context();
		if (!ctx) {
			return;
		}
		begin(ctx.editor);
		var mark = offsets(ctx.editor);
		var range = ctx.range;
		if (range.collapsed) {
			var block = blockOf(range.startContainer, ctx.editor);
			block ? range.selectNodeContents(block) : range.selectNodeContents(ctx.editor);
		}
		splitBoundaries(range);
		textsIn(range).forEach(function (t) {
			var el;
			while ((el = t.parentElement.closest(INLINE_STYLES)) && el !== ctx.editor && ctx.editor.contains(el)) {
				isolate(el, t);
				unwrap(el);
			}
			element(t).closest('[style]:not(' + MEDIA + ')') && ctx.editor.contains(element(t).closest('[style]')) && element(t).closest('[style]').removeAttribute('style');
		});
		clean(ctx.editor, false);
		restore(ctx.editor, mark);
		changed(ctx.editor);
	};
	/** Whether the whole selection is bold, italic, … - for the menu. */
	var active = function (tag) {
		var ctx = context();
		if (!ctx) {
			return false;
		}
		var texts = ctx.range.collapsed ? [ctx.range.startContainer] : textsIn(ctx.range);
		return texts.length > 0 && texts.every(function (t) {
			return element(t).closest(FORMATS[tag]) && ctx.editor.contains(element(t).closest(FORMATS[tag]));
		});
	};

	/* ------------------------------------------------------------------- links */

	// Opening the dialog renders the page anew: the field is found again by its
	// data and the place in it by text offsets.
	var linkState = null;
	/** What the link dialog works on: the link, the image or the text range under target. */
	var linkFor = function (target) {
		var el = element(target);
		var editor = wysiwygOf(el);
		if (!editor) {
			return null;
		}
		var ctx = context();
		var range = ctx && ctx.editor === editor ? ctx.range : null;
		var image = el.closest('img');
		var anchor = el.closest('a') || (range && element(range.startContainer).closest('a'));
		anchor = anchor && editor.contains(anchor) ? anchor : null;
		if (!range) {
			range = document.createRange();
			range.selectNodeContents(blockOf(el, editor) || editor);
			range.collapse(false);
		}
		var around = document.createRange();
		anchor && around.selectNodeContents(anchor);
		linkState = {
			editor: editor,
			data: Object.assign({}, editor.dataset),
			mark: rangeOffsets(editor, anchor ? around : range),
			image: image ? Array.prototype.indexOf.call(editor.querySelectorAll('img'), image) : -1,
			anchor: !!anchor
		};
		return {image: image, anchor: anchor || (image && image.closest('a')), text: anchor ? anchor.textContent : range.toString()};
	};
	var linkTarget = function (state) {
		var editor = state.editor;
		if (!editor.isConnected) {
			editor = document.querySelector('.editor[contenteditable=true][data-field="' + state.data.field + '"][data-node_id="' + state.data.node_id + '"][data-language_iso="' + state.data.language_iso + '"]');
		}
		if (!editor) {
			return null;
		}
		var range = rangeAt(editor, state.mark);
		var image = state.image > -1 ? editor.querySelectorAll('img')[state.image] : null;
		var anchor = image ? image.closest('a') : state.anchor ? element(range.startContainer).closest('a') : null;
		return {editor: editor, range: range, image: image, anchor: anchor && editor.contains(anchor) ? anchor : null};
	};
	var attr = function (el, name, value) {
		value === false || value === '' && name !== 'download' ? el.removeAttribute(name) : el.setAttribute(name, value);
	};
	var applyLink = function (values) {
		var state = linkState && linkTarget(linkState);
		linkState = null;
		if (!state) {
			return false;
		}
		begin(state.editor);
		var href = values.href.trim();
		var a = state.anchor;
		if (!href || href === 'https://') {
			if (!a) {
				return false;
			}
			unwrap(a);
			record(state.editor);
			return state.editor;
		}
		var text = (values.text || '').trim();
		if (!a) {
			a = document.createElement('a');
			if (state.image) {
				state.image.before(a);
				a.appendChild(state.image);
			} else {
				var range = state.range;
				var block = blockOf(range.startContainer, state.editor);
				if (block && !block.contains(range.endContainer)) {
					range.setEnd(block, block.childNodes.length);
				}
				if (range.collapsed || (text && text !== range.toString().trim())) {
					range.deleteContents();
					a.textContent = text || href;
				} else {
					a.appendChild(range.extractContents());
				}
				range.insertNode(a);
				a.querySelectorAll('a').forEach(unwrap);
			}
		} else if (!state.image && text && text !== a.textContent.trim()) {
			a.textContent = text;
		}
		attr(a, 'href', href);
		attr(a, 'target', values.newTab ? '_blank' : false);
		attr(a, 'rel', values.newTab ? 'noopener' : false);
		attr(a, 'download', values.download ? '' : false);
		attr(a, 'class', (values.className || '').trim());
		clean(state.editor, false);
		if (a.isConnected) {
			var index = Array.prototype.indexOf.call(a.parentNode.childNodes, a) + 1;
			select(a.parentNode, index, a.parentNode, index);
		}
		record(state.editor);
		return state.editor;
	};
	var unlink = function (target) {
		var el = element(target);
		var editor = wysiwygOf(el) || (context() || {}).editor;
		if (!editor) {
			return;
		}
		begin(editor);
		var mark = offsets(editor);
		var anchors = el && el.closest('a') && editor.contains(el.closest('a')) ? [el.closest('a')] : [];
		var ctx = context();
		if (!anchors.length && ctx && ctx.editor === editor) {
			anchors = Array.from(editor.querySelectorAll('a')).filter(function (a) {
				return ctx.range.intersectsNode(a);
			});
		}
		anchors.forEach(unwrap);
		restore(editor, mark);
		changed(editor);
	};

	/* ------------------------------------------------------------------- paste */

	var fragmentOf = function (data) {
		var html = data.getData('text/html');
		var box;
		if (html) {
			box = new DOMParser().parseFromString(html, 'text/html').body;
			return clean(box, true);
		}
		box = document.createElement('div');
		var paragraphs = data.getData('text/plain').replace(/\r\n?/g, '\n').split(/\n\s*\n/);
		paragraphs.forEach(function (text) {
			var into = paragraphs.length > 1 ? box.appendChild(document.createElement('p')) : box;
			text.split('\n').forEach(function (line, i) {
				i && into.appendChild(document.createElement('br'));
				into.appendChild(document.createTextNode(line));
			});
		});
		return box;
	};
	/** Pasted blocks in a text field: their lines, separated by line breaks. */
	var flatten = function (box) {
		box.querySelectorAll(TEXT_BLOCKS + ',li,tr').forEach(function (b) {
			b.nextSibling && b.after(document.createElement('br'));
			unwrap(b);
		});
		box.querySelectorAll('td,th').forEach(function (cell) {
			cell.nextSibling && cell.after(document.createTextNode(' '));
			unwrap(cell);
		});
		box.querySelectorAll('ul,ol,table,thead,tbody,tfoot').forEach(unwrap);
		return box;
	};
	var insert = function (editor, range, box) {
		inline(editor) && flatten(box);
		range.deleteContents();
		var nodes = Array.from(box.childNodes);
		if (!nodes.length) {
			return;
		}
		var block = blockOf(range.startContainer, editor);
		var blocks = nodes.some(function (n) {
			return n.nodeType === 1 && n.matches(TEXT_BLOCKS + ',ul,ol,table');
		});
		var last = nodes[nodes.length - 1];
		if (blocks && block && block.matches(TEXT_BLOCKS)) {
			var tail = document.createRange();
			tail.setStart(range.startContainer, range.startOffset);
			tail.setEnd(block, block.childNodes.length);
			var after = block.cloneNode(false);
			after.appendChild(tail.extractContents());
			block.after(after);
			nodes.forEach(function (n) {
				after.before(n);
			});
			if (nodes[0].nodeName === 'P') {
				while (nodes[0].firstChild) {
					block.appendChild(nodes[0].firstChild);
				}
				nodes[0].remove();
			}
			[block, after].forEach(function (b) {
				!visible(b) && b !== last && b.remove();
			});
			last = last.isConnected ? last : block;
			var end = document.createRange();
			end.selectNodeContents(last);
			end.collapse(false);
			select(end.startContainer, end.startOffset, end.startContainer, end.startOffset);
			return;
		}
		if (blocks && block) {
			// in a list item or a cell the paragraphs become lines
			box.querySelectorAll(TEXT_BLOCKS + ',li').forEach(function (b) {
				b.nextSibling && b.after(document.createElement('br'));
				unwrap(b);
			});
			box.querySelectorAll('ul,ol,table').forEach(unwrap);
			nodes = Array.from(box.childNodes);
			last = nodes[nodes.length - 1];
		}
		var fragment = document.createDocumentFragment();
		nodes.forEach(function (n) {
			fragment.appendChild(n);
		});
		range.insertNode(fragment);
		var index = Array.prototype.indexOf.call(last.parentNode.childNodes, last) + 1;
		select(last.parentNode, index, last.parentNode, index);
	};

	/* ------------------------------------------------------------------ tables */

	var newCell = function (tag) {
		var cell = document.createElement(tag);
		cell.appendChild(document.createElement('br'));
		return cell;
	};
	var caretInto = function (cell) {
		var range = document.createRange();
		range.selectNodeContents(cell);
		range.collapse(true);
		select(range.startContainer, range.startOffset, range.startContainer, range.startOffset);
	};
	/** A row or column next to cell, the header row, or the table itself away. */
	var table = function (action, cell) {
		var editor = wysiwygOf(cell);
		var el = cell && cell.closest('table');
		if (!editor || !el) {
			return;
		}
		begin(editor);
		var row = cell.parentElement;
		var index = Array.prototype.indexOf.call(row.cells, cell);
		var rows = Array.from(el.rows);
		var focus = cell;
		switch (action) {
			case 'rowBefore':
			case 'rowAfter':
				var copy = document.createElement('tr');
				Array.from(row.cells).forEach(function (c) {
					copy.appendChild(newCell(c.nodeName));
				});
				action === 'rowBefore' ? row.before(copy) : row.after(copy);
				focus = copy.cells[index] || copy.cells[0];
				break;
			case 'colBefore':
			case 'colAfter':
				rows.forEach(function (r) {
					var ref = r.cells[Math.min(index, r.cells.length - 1)];
					var c = newCell(ref.nodeName);
					action === 'colBefore' ? ref.before(c) : ref.after(c);
					r === row && (focus = c);
				});
				break;
			case 'rowRemove':
				focus = rows.length > 1 && (rows[rows.indexOf(row) + 1] || rows[rows.indexOf(row) - 1]).cells[0];
				rows.length > 1 ? row.remove() : el.remove();
				break;
			case 'colRemove':
				focus = row.cells.length > 1 && (row.cells[index + 1] || row.cells[index - 1]);
				row.cells.length > 1 ? rows.forEach(function (r) {
					r.cells[index] && r.cells[index].remove();
				}) : el.remove();
				break;
			case 'header':
				var first = rows[0];
				var head = el.tHead;
				if (head) {
					var body = el.tBodies[0] || el.appendChild(document.createElement('tbody'));
					Array.from(head.rows).reverse().forEach(function (r) {
						body.prepend(r);
					});
					head.remove();
				} else {
					el.createTHead().appendChild(first);
				}
				Array.from(first.cells).forEach(function (c) {
					var renamed = rename(c, head ? 'td' : 'th');
					c === cell && (focus = renamed);
				});
				break;
			case 'remove':
				focus = null;
				el.remove();
				break;
		}
		changed(editor);
		focus && focus.isConnected && caretInto(focus);
	};
	var insertTable = function () {
		var ctx = context();
		if (!ctx) {
			return;
		}
		begin(ctx.editor);
		var el = document.createElement('table');
		var body = el.appendChild(document.createElement('tbody'));
		for (var r = 0; r < 3; r++) {
			var row = body.appendChild(document.createElement('tr'));
			row.appendChild(newCell('td'));
			row.appendChild(newCell('td'));
		}
		var top = ctx.range.startContainer;
		while (top && top !== ctx.editor && top.parentNode !== ctx.editor) {
			top = top.parentNode;
		}
		if (!top || top === ctx.editor) {
			ctx.editor.appendChild(el);
		} else if (top.nodeType === 1 && !top.matches('table')) {
			// the table goes where the caret is: the paragraph or list splits there
			var tail = document.createRange();
			tail.setStart(ctx.range.endContainer, ctx.range.endOffset);
			tail.setEnd(top, top.childNodes.length);
			var rest = top.cloneNode(false);
			rest.appendChild(tail.extractContents());
			var first = rest.firstChild, last = top.lastChild;
			first && (first.nodeName === 'BR' || first.nodeName === 'LI' && !visible(first)) && first.remove();
			last && (last.nodeName === 'BR' || last.nodeName === 'LI' && !visible(last)) && last.remove();
			top.after(el);
			visible(rest) && el.after(rest);
			visible(top) || top.remove();
		} else {
			top.after(el);
		}
		changed(ctx.editor);
		caretInto(el.rows[0].cells[0]);
	};
	/** Tab walks through the cells; Tab in the last one adds a row. */
	var tabInTable = function (e, editor) {
		var cell = element(getSelection().anchorNode).closest('td,th');
		if (!cell || !editor.contains(cell)) {
			return;
		}
		e.preventDefault();
		var cells = Array.from(cell.closest('table').querySelectorAll('td,th'));
		var next = cells[cells.indexOf(cell) + (e.shiftKey ? -1 : 1)];
		if (next) {
			caretInto(next);
		} else if (!e.shiftKey) {
			table('rowAfter', cell);
		}
	};

	/* ------------------------------------------------------------ block classes */

	var CLASS_BLOCKS = TEXT_BLOCKS + ',ul,ol,table,' + MEDIA;
	var blockFor = function (target) {
		var editor = wysiwygOf(target);
		var block = editor && element(target).closest(CLASS_BLOCKS);
		return block && block !== editor && editor.contains(block) ? block : null;
	};
	var setBlockClass = function (block, value) {
		var editor = wysiwygOf(block);
		if (!editor) {
			return;
		}
		begin(editor);
		value = value.trim().split(/\s+/).filter(Boolean).join(' ');
		value ? block.setAttribute('class', value) : block.removeAttribute('class');
		changed(editor);
	};

	/* ------------------------------------------------------------------ events */

	// Enter in loose text: <p> like Safari, not Chrome's <div>
	document.execCommand('defaultParagraphSeparator', false, 'p');
	var KEYS = {b: 'STRONG', i: 'EM', u: 'U'};
	var INPUT_FORMATS = {formatBold: 'STRONG', formatItalic: 'EM', formatUnderline: 'U', formatStrikeThrough: 'S'};
	document.addEventListener('focusin', function (e) {
		var editor = wysiwygOf(e.target);
		editor && record(editor);
	});
	document.addEventListener('focusout', function (e) {
		var editor = wysiwygOf(e.target);
		if (editor && !editor.contains(e.relatedTarget)) {
			clearTimeout(typingTimer);
			clean(editor, false);
			record(editor);
		}
	});
	document.addEventListener('input', function (e) {
		var editor = !e.wysiwyg && wysiwygOf(e.target);
		if (editor) {
			clearTimeout(typingTimer);
			typingTimer = setTimeout(function () {
				record(editor);
			}, 400);
		}
	}, true);
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Tab' && !e.metaKey && !e.ctrlKey && !e.altKey && wysiwygOf(e.target)) {
			tabInTable(e, wysiwygOf(e.target));
			return;
		}
		if (e.key === 'Enter' && !e.isComposing && inline(wysiwygOf(e.target))) {
			e.preventDefault();
			document.execCommand('insertLineBreak');
			return;
		}
		var editor = (e.metaKey || e.ctrlKey) && !e.altKey && wysiwygOf(e.target);
		if (!editor) {
			return;
		}
		var key = (e.key || '').toLowerCase();
		if (key === 'z' || key === 'y') {
			e.preventDefault();
			step(editor, key === 'y' || e.shiftKey ? 1 : -1);
		} else if (KEYS[key] && !e.shiftKey) {
			e.preventDefault();
			toggle(KEYS[key]);
		} else if (key === 'x' && e.shiftKey) {
			e.preventDefault();
			toggle('S');
		}
	}, true);
	// the format buttons of the iOS keyboard and edit menu, and the shortcuts
	// of other browsers, come in as beforeinput
	document.addEventListener('beforeinput', function (e) {
		var editor = wysiwygOf(e.target);
		if (!editor) {
			if (/^format/.test(e.inputType) && e.target.closest && e.target.closest('[contenteditable]')) {
				e.preventDefault();
			}
			return;
		}
		if (e.inputType === 'insertParagraph' && inline(editor)) {
			e.preventDefault();
			document.execCommand('insertLineBreak');
		} else if (INPUT_FORMATS[e.inputType]) {
			e.preventDefault();
			toggle(INPUT_FORMATS[e.inputType]);
		} else if (/^format/.test(e.inputType)) {
			e.preventDefault();
		} else if (e.inputType === 'historyUndo' || e.inputType === 'historyRedo') {
			e.preventDefault();
			step(editor, e.inputType === 'historyRedo' ? 1 : -1);
		} else if (e.inputType === 'insertFromDrop' && e.dataTransfer && e.dataTransfer.types.indexOf('text/html') > -1) {
			var target = e.getTargetRanges()[0];
			if (target) {
				e.preventDefault();
				begin(editor);
				var range = document.createRange();
				range.setStart(target.startContainer, target.startOffset);
				range.setEnd(target.endContainer, target.endOffset);
				insert(editor, range, fragmentOf(e.dataTransfer));
				changed(editor);
			}
		}
	}, true);
	document.addEventListener('paste', function (e) {
		var editor = wysiwygOf(e.target);
		var selection = getSelection();
		if (!editor || !e.clipboardData || !selection.rangeCount) {
			return;
		}
		e.preventDefault();
		begin(editor);
		insert(editor, selection.getRangeAt(0), fragmentOf(e.clipboardData));
		var mark = offsets(editor);
		clean(editor, false);
		restore(editor, mark);
		changed(editor);
	}, true);

	return {
		toggle: toggle,
		inline: inline,
		setBlock: setBlock,
		toggleList: toggleList,
		clearFormat: clearFormat,
		active: active,
		linkFor: linkFor,
		applyLink: applyLink,
		cancelLink: function () {
			linkState = null;
		},
		unlink: unlink,
		table: table,
		insertTable: insertTable,
		blockFor: blockFor,
		setBlockClass: setBlockClass,
		step: step,
		/** Whether undo (-1) or redo (1) has a step; typing not yet recorded counts for undo. */
		canStep: function (editor, by) {
			var history = histories.get(editor);
			var current = history && history.stack[history.at];
			if (!current) {
				return false;
			}
			var typed = current.html !== editor.innerHTML;
			return by < 0 ? history.at > 0 || typed : !typed && history.at < history.stack.length - 1;
		},
		changed: changed,
		/** The field as it is saved: a cleaned copy, the field itself keeps the caret. */
		html: function (editor) {
			return clean(editor.cloneNode(true), false).innerHTML;
		},
		clean: clean
	};
})();
