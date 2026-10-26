(function () {
'use strict';
var root = document.body;
// This file can also be discovered as a standalone panel asset before the body exists.
if (!root || !root.querySelector(':scope > .cmsMobileHeader') || !root.querySelector(':scope > main')) return;
// Presentation only: existing panel nodes, actions and module state stay in place.
var previousMobile = window.cmsMobile;
previousMobile?.destroy();
var mobileEvents = new AbortController();
var mobileState = window.cmsMobile = {active: previousMobile?.active || 'base'};
/* Phone, and a tablet held upright. A tablet on its side stays the desktop. */
var mobile = matchMedia('(max-width: 1024px), (orientation: portrait) and (max-width: 1400px)');
var activeWindow = mobileState.active;
var returnFocus = previousMobile?.returnFocus || null;
var previousWindow = previousMobile?.previousWindow || 'base';
var overlayURL = previousMobile?.overlayURL || null;
var dismissedHref = null;
var introducedHref = null;
var showSheet = false;
var sheetGesture = false;
var sliding = false;
var dismissing = false;
var sheetMotion = null;
var slidePending = false;
var slidFor = null;
var arriveTimer = null;
var titleHomes = new WeakMap();
// The address this document was opened with. Only that one may slide in by
// itself (a reload, a typed or shared URL). A later address came from a tap
// or from the stored child, and stays on the tree until the row is tapped.
var loadedHref = location.href;
var autoOpen = false;
try {
	var navId = String(performance.timeOrigin);
	autoOpen = sessionStorage.getItem('cmsDoc') !== navId;
	sessionStorage.setItem('cmsDoc', navId);
} catch (e) {}
// A click on a leaf asked to open at once.
var openNow = !!previousMobile?.openNow;
var pendingHref = null;
try {
	var wantedSheet = sessionStorage.getItem('cmsOpenSheet');
	sessionStorage.removeItem('cmsOpenSheet');
	if (wantedSheet && wantedSheet === location.pathname + location.search) openNow = true;
} catch (e) {}
mobileState.openNow = false;
document.cookie = 'cmsMobile=' + (mobile.matches ? '1' : '0') + ';path=/;max-age=31536000;SameSite=Lax';
var closingHistory = previousMobile?.closingHistory || false;
var windowSelectors = {menu: 'main > .main', edit: 'main > .content', media: 'main > .pylon > footer'};
var mobileHeader = document.querySelector('.cmsMobileHeader');
var backdrop = document.querySelector('.cmsMobileBackdrop');
function windowNode(name) { return document.querySelector(windowSelectors[name] || 'main > .pylon'); }
function closeMore() {
	document.querySelectorAll('[data-cms-more]').forEach(function (more) {
		more.setAttribute('aria-expanded', 'false');
		var menu = more.menu;
		if (!menu) return;
		menu.classList.remove('is-open');
		menu.style.display = 'none';
		if (more.parentElement && menu.parentElement === document.body) more.parentElement.appendChild(menu);
	});
	if (typeof closeAllMenus === 'function') closeAllMenus();
}
function chromeColor() {
	// The bar, the page and the overscroll are one colour, written together.
	// A dialog on top does not get a colour of its own.
	if (!mobile.matches || !document.body) return;
	var petrol = document.body.dataset.cmsWindow === 'menu' || !!document.querySelector('.authCard');
	var color = petrol ? '#1f3a45' : '#f0f3f4';
	var node = document.getElementById('cmsChromeColor');
	if (!node) {
		node = document.createElement('style');
		node.id = 'cmsChromeColor';
		document.head.appendChild(node);
	}
	node.textContent = 'html, body { background: ' + color + ' !important; color-scheme: ' + (petrol ? 'dark' : 'light') + '; }';
	document.documentElement.style.background = color;
	document.body.style.background = color;
	var edge = document.getElementById('cmsChromeEdge');
	if (!edge) {
		edge = document.createElement('div');
		edge.id = 'cmsChromeEdge';
		edge.setAttribute('aria-hidden', 'true');
		edge.style.cssText = 'position:fixed;left:0;right:0;bottom:0;height:max(env(safe-area-inset-bottom), 34px);pointer-events:none;z-index:1';
		document.body.appendChild(edge);
	}
	edge.style.background = color;
	var meta = document.querySelector('meta[name="theme-color"]');
	if (meta && meta.getAttribute('content') !== color) meta.setAttribute('content', color);
}
function showWindow(name) {
	if (!mobile.matches) return;
	if (name !== 'base' && !windowNode(name)) return;
	if (activeWindow === 'base' && name !== 'base') {
		returnFocus = document.activeElement;
		overlayURL = location.href;
		history.pushState(Object.assign({}, history.state, {cmsMobileWindow: true}), '', location.href);
	}
	if (name === 'base' && overlayURL === location.href && history.state?.cmsMobileWindow) {
		closingHistory = true;
		history.back();
	}
	if (name === 'base') overlayURL = null;
	closeMore();
	previousWindow = activeWindow;
	activeWindow = name;
	root.dataset.cmsWindow = name;
	syncMobile();
	var target = windowNode(name);
	if (name !== 'base') {
		target?.setAttribute('tabindex', '-1');
		target?.focus({preventScroll: true});
	}
	else if (returnFocus?.isConnected) returnFocus.focus({preventScroll: true});
}
// Same-URL overlay entries do not reach the server's panel-navigation popstate handler.
window.addEventListener('popstate', function (event) {
	if (closingHistory) {
		closingHistory = false; mobileState.closingHistory = false;
		event.stopImmediatePropagation();
		return;
	}
	if (!overlayURL) return;
	var samePage = location.href === overlayURL;
	overlayURL = null;
	previousWindow = activeWindow;
	activeWindow = 'base';
	syncMobile();
	if (samePage) {
		event.stopImmediatePropagation();
		if (returnFocus?.isConnected) returnFocus.focus({preventScroll: true});
	}
}, {capture: true, signal: mobileEvents.signal});
function parentHref() {
	var url = new URL(location.href);
	if (url.searchParams.has('id')) {
		url.searchParams.delete('id');
		return url.pathname + url.search + url.hash;
	}
	var cms = url.pathname.indexOf('/cms/');
	if (cms < 0) return null;
	var rest = url.pathname.slice(cms + 5).replace(/\/+$/, '');
	if (rest.indexOf('/') < 0) return null;
	return url.pathname.slice(0, cms + 5) + rest.slice(0, rest.lastIndexOf('/')) + '/' + url.search + url.hash;
}
function closeWindow() {
	slidFor = null;
	sheetMotion = null;
	// Media opened from Edit returns to the same editor, with its inputs intact.
	var next = activeWindow === 'media' && previousWindow === 'edit' ? 'edit' : 'base';
	if (next === 'base') {
		var content = windowNode('edit');
		if (content) content.removeAttribute('data-cms-sheet');
		var parent = parentHref();
		overlayURL = null;
		if (parent) history.replaceState(history.state, '', parent);
		dismissedHref = location.href;
	}
	showWindow(next);
}
function slideSheet(node) {
	if (!node || !node.isConnected) return;
	sliding = true;
	var dur = 340;
	sheetMotion = {dir: 'in', node: node, t: performance.now(), dur: dur};
	node.style.transition = 'none';
	node.style.transform = 'translateY(100%)';
	requestAnimationFrame(function () {
		requestAnimationFrame(function () {
			if (!node.isConnected) return;
			node.style.transition = 'transform ' + dur + 'ms cubic-bezier(.22,.8,.3,1)';
			node.style.transform = 'translateY(0)';
			setTimeout(function () {
				if (!sheetMotion || sheetMotion.node !== node || sheetMotion.dir !== 'in') return;
				sliding = false;
				sheetMotion = null;
				slidFor = location.href;
				node.style.removeProperty('transition');
				node.style.removeProperty('transform');
			}, dur + 30);
		});
	});
}
// A render swaps the node while it is still moving. Wait until that settles,
// then run the rest of the same move on whatever node is left.
function queueSlide() {
	if (dismissing || sheetGesture) return;
	var live = windowNode('edit');
	if (!live || live.dataset.cmsSheet !== '1' || !showSheet) return;
	if (slidFor === location.href && (!sheetMotion || sheetMotion.node === live)) return;
	// Off the screen before the next paint, so the sheet does not flash
	// open and then jump down to start the entrance.
	live.style.transition = 'none';
	live.style.transform = 'translateY(100%)';
	if (slidePending) return;
	slidePending = true;
	requestAnimationFrame(function () {
		requestAnimationFrame(function () {
			slidePending = false;
			var node = windowNode('edit');
			if (!node || node.dataset.cmsSheet !== '1' || !showSheet || dismissing || sheetGesture) return;
			if (slidFor === location.href && (!sheetMotion || sheetMotion.node === node)) return;
			slideSheet(node);
		});
	});
}
function syncMobile() {
	var pylon = windowNode('base');
	var content = windowNode('edit');
	var media = windowNode('media');
	root.classList.toggle('cmsHasPylon', !!pylon);
	if (activeWindow !== 'base' && !windowNode(activeWindow)) activeWindow = 'base';
	if (!mobile.matches) activeWindow = 'base';
	// The document body is only a placeholder. The sheet flag arrives with the
	// layer, often after the first sync already wrote "base" and slid it shut.
	// A sheet address stays open until it is pulled down. A page without one
	// does not keep the previous sheet's window.
	// Dashboard, backup and the profile are the page. A leftover sheet flag
	// from the module we just left would slide them down and leave a gap.
	if (content && (content.querySelector('.dashboard, section.profile') || root.classList.contains('backup') || root.classList.contains('user'))) {
		content.removeAttribute('data-cms-sheet');
	}
	// Unfolding a branch must not bring up the empty page behind the submenu.
	if (content && new URL(location.href).searchParams.get('branch') === '1') {
		content.removeAttribute('data-cms-sheet');
	}
	// The tap is ahead of the answer. Don't open the page that is still on
	// screen, and don't spend the tap on it: the flag often stays on the
	// wrapper the answer does not replace.
	// The dialog sits on the sheet. A render that drops the flag would slide
	// the page away and leave the tree when the dialog closes.
	if (content && showSheet && document.querySelector('.cmslightbox') && content.dataset.cmsSheet !== '1' && new URL(location.href).searchParams.get('branch') !== '1' && !content.querySelector('.dashboard, section.profile') && !root.classList.contains('backup') && !root.classList.contains('user')) {
		content.dataset.cmsSheet = '1';
	}
	var waiting = openNow && pendingHref && (location.pathname + location.search) !== pendingHref;
	if (!waiting && openNow && mobile.matches && content && new URL(location.href).searchParams.get('branch') !== '1' && !content.querySelector('.dashboard, section.profile') && !root.classList.contains('backup') && !root.classList.contains('user')) {
		content.dataset.cmsSheet = '1';
		pendingHref = null;
	}
	var wantsSheet = !waiting && mobile.matches && content && content.dataset.cmsSheet === '1' && dismissedHref !== location.href;
	if (waiting && activeWindow === 'edit') activeWindow = 'base';
	if (wantsSheet) {
		if (introducedHref !== location.href && !sheetGesture) {
			// A dialog changes the address without leaving the page under it.
			// Closing it has to land on that same sheet, not on the tree.
			var keep = showSheet && activeWindow !== 'base';
			introducedHref = location.href;
			clearTimeout(arriveTimer);
			arriveTimer = null;
			var arriveNow = openNow;
			var arrive = arriveNow || (autoOpen && location.href === loadedHref);
			openNow = false;
			autoOpen = false;
			if (keep) {
				showSheet = true;
				if (activeWindow !== 'menu' && activeWindow !== 'media') activeWindow = 'edit';
			} else {
			showSheet = arrive;
			if (!arrive) {
				if (activeWindow === 'edit') activeWindow = 'base';
			} else if (arriveNow) {
				activeWindow = 'edit';
				queueSlide();
			} else {
				activeWindow = 'base';
				var href = location.href;
				var node = content;
				arriveTimer = setTimeout(function () {
					arriveTimer = null;
					if (location.href !== href || dismissedHref === href || !node.isConnected || node.dataset.cmsSheet !== '1') return;
					showSheet = true;
					activeWindow = 'edit';
					mobileState.active = 'edit';
					queueSlide();
					syncMobile();
				}, 480);
			}
			}
		} else if (showSheet && !arriveTimer && activeWindow !== 'menu' && activeWindow !== 'media') {
			activeWindow = 'edit';
		}
	} else if (activeWindow === 'edit' && !dismissing) {
		if (content && !sheetMotion) {
			content.style.transition = 'none';
			content.style.removeProperty('transform');
		}
		activeWindow = 'base';
		showSheet = false;
		slidFor = null;
		dismissedHref = null;
		introducedHref = null;
		clearTimeout(arriveTimer);
		arriveTimer = null;
	}
	// A swipe leaves translateY on the sheet. The next one then opened short.
	// A dialog changes the address. The sheet under it stays put: only a sheet
	// whose node was replaced mid-flight has to finish that same entrance.
	if (showSheet && activeWindow === 'edit' && content && !dismissing && !sheetGesture && !arriveTimer && sheetMotion && !sheetMotion.node.isConnected) {
		sheetMotion = null;
		queueSlide();
	}
	if (!sheetGesture && !sliding && !sheetMotion && !dismissing && !slidePending && !arriveTimer && !root.classList.contains('dragging')) {
		content?.style.removeProperty('height');
		content?.style.removeProperty('max-height');
		if (!openNow) {
			content?.style.removeProperty('transform');
			content?.style.removeProperty('transition');
		}
		document.querySelectorAll('.cmslightbox > section').forEach(function (section) {
			section.style.removeProperty('height');
			section.style.removeProperty('max-height');
			if (!sheetGesture) {
				section.style.removeProperty('transform');
				section.style.removeProperty('transition');
			}
		});
	}
	root.dataset.cmsWindow = activeWindow;
	chromeColor();
	Object.assign(mobileState, {active: activeWindow, returnFocus: returnFocus, previousWindow: previousWindow, overlayURL: overlayURL, closingHistory: closingHistory});
	mobileHeader = document.querySelector('.cmsMobileHeader');
	root.classList.toggle('cmsMobile', mobile.matches);
	root.classList.toggle('cmsDashboard', !!document.querySelector('main > .content .dashboard'));
	var selected = document.querySelector('main > .main nav li.active > a');
	var title = selected?.textContent.trim() || '';
	var moduleTitle = mobileHeader.querySelector('[data-cms-module]');
	if (moduleTitle.textContent !== title) moduleTitle.textContent = title;
	mobileHeader.querySelector('button').setAttribute('aria-expanded', String(activeWindow === 'menu'));
	backdrop.hidden = !mobile.matches || activeWindow === 'base';
	Object.keys(windowSelectors).forEach(function (name) {
		var node = windowNode(name);
		if (!node) return;
		var overlay = name !== 'edit' || !!pylon;
		if (name === 'edit' || name === 'menu') {
			var head = node.querySelector(':scope > header');
			var closable = name !== 'edit' || node.dataset.cmsSheet === '1';
			if (head && overlay && closable) {
				head.classList.add(name === 'edit' ? 'cmsMobileEditHeader' : 'cmsMobileDrawerHeader');
				if (name === 'edit' && !head.querySelector(':scope > .cmsMobileGrip')) {
					var grip = document.createElement('span');
					grip.className = 'cmsMobileGrip cmsMobileOnly'; grip.setAttribute('aria-hidden', 'true');
					head.prepend(grip);
				}
				head.querySelector('[data-cms-properties]')?.toggleAttribute('hidden', !node.querySelector('aside.cms'));
				head.querySelector('[data-cms-window="media"]')?.toggleAttribute('hidden', !media || !!media.querySelector('.mml-empty'));
			}
		} else if (overlay && !node.querySelector(':scope > .cmsMobileWindowBar')) {
			var bar = document.createElement('div');
			bar.className = 'cmsMobileWindowBar cms';
			bar.innerHTML = '<span class="cmsMobileGrip" aria-hidden="true"></span><strong></strong>';
			bar.querySelector('strong').textContent = 'Media Library';
			node.prepend(bar);
		}
		var dialog = overlay && (name !== 'edit' || node.dataset.cmsSheet === '1');
		node.inert = mobile.matches && dialog && activeWindow !== name;
		if (mobile.matches && dialog) {
			node.setAttribute('role', 'dialog');
			node.setAttribute('aria-modal', String(activeWindow === name));
			node.setAttribute('aria-label', {menu: 'Navigation', edit: 'Edit', media: 'Media Library'}[name]);
		} else {
			node.removeAttribute('role'); node.removeAttribute('aria-modal'); node.removeAttribute('aria-label');
		}
	});
	// Do not make the pylon inert: it contains the media window. Isolate its base instead.
	if (pylon) Array.from(pylon.children).forEach(function (node) {
		if (node !== media) node.inert = mobile.matches && activeWindow !== 'base';
	});
	if (content && !pylon) content.inert = mobile.matches && activeWindow === 'menu';
	if (content && content.dataset.cmsSheet !== '1') {
		content.querySelector(':scope > header')?.classList.remove('cmsMobileEditHeader');
		content.querySelector(':scope > header > .cmsMobileGrip')?.remove();
	}
	document.querySelectorAll('.cmslightbox > section').forEach(function (section) {
		var head = section.querySelector(':scope > header') || section;
		if (head.querySelector(':scope > .cmsMobileGrip')) return;
		section.querySelector(':scope > .cmsMobileGrip')?.remove();
		var grip = document.createElement('span');
		grip.className = 'cmsMobileGrip';
		grip.setAttribute('aria-hidden', 'true');
		head.classList.add('cmsMobileEditHeader');
		head.prepend(grip);
	});
	if (mobileObserver) mobileObserver.disconnect();
	placeTitle(content);
	if (mobileObserver) mobileObserver.observe(root, {childList: true, subtree: true});
	pinSearch();
	var find = document.querySelector('.cmslightbox.cmsFind input[name="query"]');
	if (find && document.activeElement !== find && !(document.activeElement && document.activeElement.closest && document.activeElement.closest('.cmslightbox.cmsFind'))) {
		find.focus({preventScroll: true});
	}
	fitHeader(content?.querySelector(':scope > header'));
}
// The page headline lives in the body so desktop keeps one source. On a phone
// it moves into the sheet header, next to the grip, and goes back afterwards.
function placeTitle(content) {
	if (!content) return;
	var head = content.querySelector(':scope > header');
	if (!head) return;
	// The page title stays in the layer. Moving that node made the next
	// update insert the page into the header, so the sheet showed the wrong
	// layout. The bar only copies the words.
	var parked = head.querySelector(':scope > h1:not(.cmsSheetTitle)');
	if (parked) restoreTitle(parked);
	var sheet = mobile.matches && content.dataset.cmsSheet === '1';
	var source = null;
	content.querySelectorAll('h1').forEach(function (node) {
		if (!source && !node.classList.contains('cmsSheetTitle') && !node.closest('header, .widget, .cmslightbox')) source = node;
	});
	var copy = head.querySelector(':scope > h1.cmsSheetTitle');
	if (!sheet || !source) {
		copy?.remove();
		if (source) source.hidden = false;
		return;
	}
	if (!copy) {
		copy = document.createElement('h1');
		copy.className = 'cmsSheetTitle';
		var grip = head.querySelector(':scope > .cmsMobileGrip');
		if (grip) grip.after(copy);
		else head.prepend(copy);
	}
	if (copy.textContent !== source.textContent) copy.textContent = source.textContent;
	source.hidden = true;
}
function restoreTitle(parked) {
	if (!parked) return;
	var home = titleHomes.get(parked);
	if (home && home.parent && home.parent.isConnected) {
		home.parent.insertBefore(parked, home.next && home.next.parentNode === home.parent ? home.next : null);
	} else if (parked.parentElement && parked.parentElement.matches('header')) {
		parked.remove();
	}
	titleHomes.delete(parked);
}
// New results must not inherit a scroll that iOS used to chase the field.
function pinSearch() {
	if (!mobile.matches) return;
	var box = document.querySelector('.cmslightbox.cmsFind > section > main');
	if (!box) return;
	var field = document.querySelector('.cmslightbox.cmsFind input[name="query"]');
	var mark = (field ? field.value : '') + ':' + box.textContent.length;
	if (box.dataset.cmsResults === mark) return;
	box.dataset.cmsResults = mark;
	box.scrollTop = 0;
}
function fitHeader(head) {
	if (!head || !mobile.matches || head.querySelector('.cmsPageTools')) return;
	if (head.querySelector('.cmsMobileMore')) return;
	var row = head.querySelector(':scope > .rightAlign') || head;
	var items = [...row.children].filter(function (node) {
		return node.matches('a.button, a.ajax.button, button.button, label.button, .button');
	});
	if (items.length < 3) return;
	var menu = document.createElement('div');
	menu.className = 'cmsPageToolsMenu';
	items.slice(0, -1).forEach(function (item) { menu.append(item); });
	var more = document.createElement('button');
	more.type = 'button';
	more.className = 'button cmsMobileMore';
	more.setAttribute('data-cms-more', '');
	more.setAttribute('aria-label', 'More');
	more.setAttribute('aria-expanded', 'false');
	more.innerHTML = '<i class="fa-regular fa-ellipsis"></i>';
	row.append(more, menu);
}
// Use capture so existing AJAX handlers still receive actual links and actions.
document.addEventListener('click', function (event) {
	if (!mobile.matches || !event.target.closest) return;
	var more = event.target.closest('[data-cms-more]');
	if (more) {
		event.preventDefault(); event.stopPropagation();
		var open = more.getAttribute('aria-expanded') !== 'true';
		more.setAttribute('aria-expanded', String(open));
		var menu = more.menu || more.parentElement?.querySelector(':scope > .cmsPageToolsMenu');
		if (menu && open) {
			more.menu = menu;
			document.body.appendChild(menu);
			var rect = more.getBoundingClientRect();
			menu.classList.add('is-open');
			menu.style.display = 'flex';
			menu.style.position = 'fixed';
			menu.style.top = (rect.bottom + 8) + 'px';
			menu.style.right = Math.max(8, window.innerWidth - rect.right) + 'px';
			menu.style.left = 'auto';
			menu.style.zIndex = '2600';
		} else if (menu) {
			menu.classList.remove('is-open');
			menu.style.display = 'none';
			if (more.parentElement) more.parentElement.appendChild(menu);
		}
		return;
	}
	var properties = event.target.closest('button[data-cms-properties]');
	if (properties) {
		var content = windowNode('edit');
		var open = content.dataset.cmsProperties !== 'true';
		content.dataset.cmsProperties = String(open);
		properties.setAttribute('aria-pressed', String(open));
		if (open) content.querySelector('aside.collapsed .sidebarFlag')?.click();
		content.querySelector('[data-cms-more]')?.setAttribute('aria-expanded', 'false');
		return;
	}
		if (event.target.closest('.dashHeadMenu')) closeMore();
		if (!event.target.closest('.cmsPageTools, .cmsPageToolsMenu, ul.contextmenu')) closeMore();
	var button = event.target.closest('button[data-cms-window], [data-cms-close]');
	if (button) {
		event.preventDefault();
		button.hasAttribute('data-cms-close') ? closeWindow() : (button.dataset.cmsWindow === 'menu' && activeWindow === 'menu' ? closeWindow() : showWindow(button.dataset.cmsWindow));
		return;
	}
	if (event.target.closest('.cmsMobileHeader a.cmsSearchLink')) {
		queueMicrotask(function () {
			var field = document.querySelector('.cmslightbox.cmsFind input[name="query"]');
			if (field) field.focus({preventScroll: true});
		});
	}
	if (event.target.closest('main > .main a[href]:not([target]):not([download])')) {
		// The link owns navigation. Closing the menu in this same click makes
		// it inert, and the browser then drops the tap. Close on the next turn.
		overlayURL = null;
		queueMicrotask(function () {
			activeWindow = 'base';
			syncMobile();
			var field = document.querySelector('.cmslightbox.cmsFind input[name="query"]');
			if (field) field.focus({preventScroll: true});
		});
	}
	// Only a selection opens Edit; folds, rename, context actions and drags keep the tree.
	var link = event.target.closest('main > .pylon nav a[href]:not([target]):not([download])');
	if (link && !link.classList.contains('put') && !event.defaultPrevented && !root.classList.contains('dragging')) {
		var url = new URL(link.href, location.href);
		if (link.hasAttribute('data-cms-branch')) {
			// A branch only unfolds its submenu. The leaf opens the sheet.
			activeWindow = 'base';
			mobileState.active = 'base';
			mobileState.openNow = false;
			if (!url.searchParams.has('branch')) {
				url.searchParams.set('branch', '1');
				link.href = url.pathname + url.search + url.hash;
			}
			return;
		}
		var leaf = url.searchParams.has('id') || !root.classList.contains('pages');
		if (!leaf) return;
		var here = url.pathname === location.pathname && url.search === location.search;
		dismissedHref = null;
		introducedHref = null;
		showSheet = false;
		pendingHref = url.pathname + url.search;
		openNow = true;
		mobileState.openNow = true;
		try { sessionStorage.setItem('cmsOpenSheet', pendingHref); } catch (e) {}
		activeWindow = 'edit';
		mobileState.active = 'edit';
		if (here) {
			// Closing left the row selected. The same address has to open again.
			event.preventDefault();
			event.stopPropagation();
			syncMobile();
		}
	}
}, {capture: true, signal: mobileEvents.signal});
document.addEventListener('keydown', function (event) {
	if (!mobile.matches || activeWindow === 'base' || document.querySelector('.cmslightbox')) return;
	if (event.key === 'Escape') {
		event.preventDefault();
		var more = document.querySelector('[data-cms-more][aria-expanded="true"]');
		if (more) { more.setAttribute('aria-expanded', 'false'); more.focus(); }
		else closeWindow();
	}
	if (event.key === 'Tab') {
		var items = Array.from(windowNode(activeWindow).querySelectorAll('button, a[href], input, select, textarea, [tabindex="0"], [contenteditable="plaintext-only"]')).filter(function (node) { return !node.disabled && !node.closest('[inert]') && node.getClientRects().length; });
		var first = items[0], last = items[items.length - 1];
		if (event.shiftKey && (document.activeElement === first || document.activeElement === windowNode(activeWindow))) { event.preventDefault(); last?.focus(); }
		else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
	}
}, {signal: mobileEvents.signal});
// A dedicated grip area cannot conflict with long press, editor selection or scrolling.
function shutSheet(sheet) {
	if (dismissing) return;
	var lightbox = sheet.closest && sheet.closest('.cmslightbox');
	function finish() {
		dismissing = false;
		sheetMotion = null;
		sliding = false;
		if (lightbox) {
			var live = document.querySelector('.cmslightbox') || lightbox;
			// The first link can be a download. Close is the plain ajax link.
			(live.querySelector('header a.ajax[href]') || live.querySelector('header a[href]:not([download])'))?.click();
			return;
		}
		if (sheet.matches && sheet.matches('ul.contextmenu') && typeof closeAllMenus === 'function') {
			closeAllMenus();
			return;
		}
		closeWindow();
	}
	if (!mobile.matches || !sheet.isConnected) {
		finish();
		return;
	}
	dismissing = true;
	var dur = 320;
	sheetMotion = {dir: 'out', node: sheet, t: performance.now(), dur: dur};
	var from = sheet.style.transform || 'translateY(0)';
	sheet.style.transition = 'none';
	sheet.style.transform = from;
	sheet.getBoundingClientRect();
	sheet.style.transition = 'transform ' + dur + 'ms cubic-bezier(.22,.8,.3,1)';
	sheet.style.transform = 'translateY(110%)';
	var done = false;
	function complete(event) {
		if (done) return;
		if (event && event.target !== sheet) return;
		if (event && event.propertyName !== 'transform') return;
		done = true;
		finish();
	}
	sheet.addEventListener('transitionend', complete);
	setTimeout(function () { complete(); }, dur + 80);
}
document.addEventListener('pointerdown', function (event) {
	var bar = event.target.closest?.('.cmsMobileWindowBar, .cmsMobileEditHeader');
	var sheet = null;
	if (bar) {
		if (event.target.closest('button, a, input, select, textarea, [contenteditable="plaintext-only"], .cmsPageTools') || activeWindow === 'menu' || root.classList.contains('dragging')) return;
		if (bar.classList.contains('cmsMobileEditHeader') && !event.target.closest('.cmsMobileGrip')) return;
		sheet = bar.parentElement;
	} else if (mobile.matches && event.target.closest?.('.cmslightbox > section > .cmsMobileGrip')) {
		sheet = event.target.closest('.cmslightbox > section');
		bar = event.target.closest('.cmsMobileGrip');
		event.preventDefault();
	} else if (mobile.matches && event.target.closest?.('body > ul.contextmenu')) {
		var menu = event.target.closest('ul.contextmenu');
		if (event.target.closest('a, button') || event.clientY - menu.getBoundingClientRect().top > 48) return;
		sheet = menu;
		bar = menu;
		event.preventDefault();
	}
	if (!mobile.matches || !sheet || !bar) return;
	var startY = event.clientY;
	var distance = 0;
	var lightbox = sheet.closest && sheet.closest('.cmslightbox');
	sheetGesture = true;
	bar.setPointerCapture(event.pointerId);
	function move(e) {
		if (e.pointerId !== event.pointerId) return;
		if (e.cancelable) e.preventDefault();
		var dy = e.clientY - startY;
		// Pulling a dialog up grows it with the finger. Down still dismisses,
		// including a dialog that is already full height.
		if (lightbox && dy < 0 && !sheet.classList.contains('is-expanded')) {
			if (!sheet.dataset.cmsBaseHeight) sheet.dataset.cmsBaseHeight = String(sheet.getBoundingClientRect().height);
			var full = window.innerHeight - 8;
			var next = Math.min(full, Number(sheet.dataset.cmsBaseHeight) - dy);
			sheet.style.transition = 'none';
			sheet.style.maxHeight = 'none';
			sheet.style.height = next + 'px';
			distance = dy;
			return;
		}
		distance = Math.max(0, dy);
		sheet.style.transition = 'none';
		sheet.style.transform = 'translateY(' + distance + 'px)';
	}
	function end(e) {
		if (e.pointerId !== event.pointerId) return;
		bar.removeEventListener('pointermove', move);
		bar.removeEventListener('pointerup', end);
		bar.removeEventListener('pointercancel', end);
		sheetGesture = false;
		var dy = e.clientY - startY;
		if (lightbox && e.type !== 'pointercancel') {
			if (dy < -40) {
				sheet.classList.add('is-expanded');
				sheet.getBoundingClientRect();
				sheet.style.transition = 'height 320ms cubic-bezier(.22,.8,.3,1), max-height 320ms cubic-bezier(.22,.8,.3,1)';
				sheet.style.removeProperty('height');
				sheet.style.removeProperty('max-height');
				sheet.style.removeProperty('transform');
				return;
			}
			if (dy > 80) {
				shutSheet(sheet);
				return;
			}
			sheet.style.removeProperty('transition');
			sheet.style.removeProperty('height');
			sheet.style.removeProperty('max-height');
			sheet.style.removeProperty('transform');
			return;
		}
		var shut = e.type !== 'pointercancel' && distance > 80;
		if (!shut) {
			sheet.style.removeProperty('transition');
			sheet.style.removeProperty('transform');
		}
		if (shut) shutSheet(sheet);
	}
	bar.addEventListener('pointermove', move);
	bar.addEventListener('pointerup', end);
	bar.addEventListener('pointercancel', end);
}, {signal: mobileEvents.signal});
mobile.addEventListener('change', syncMobile, {signal: mobileEvents.signal});
if (window.visualViewport) {
	var pinning = false;
	function fitKeyboard() {
		var vv = window.visualViewport;
		root.style.setProperty('--cmsKeyboard', Math.max(0, document.documentElement.clientHeight - vv.height - vv.offsetTop) + 'px');
		chromeColor();
		// iOS pans the layout to the focused field. A sheet then sits with its
		// header above the screen and the results in the middle.
		if (pinning || !mobile.matches || vv.offsetTop < 2 || !document.querySelector('.cmslightbox.cmsFind')) return;
		pinning = true;
		window.scrollTo(0, 0);
		pinning = false;
	}
	visualViewport.addEventListener('resize', fitKeyboard, {signal: mobileEvents.signal});
	visualViewport.addEventListener('scroll', fitKeyboard, {signal: mobileEvents.signal});
	fitKeyboard();
}
// Panel updates replace nodes; refresh only chrome and availability, never the editor.
var mobileObserver = new MutationObserver(syncMobile);
mobileObserver.observe(root, {childList: true, subtree: true});
mobileState.destroy = function () { mobileEvents.abort(); mobileObserver.disconnect(); };
mobileState.sync = syncMobile;
syncMobile();

}());
