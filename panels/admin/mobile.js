(function () {
'use strict';
var root = document.body;
// This file can also be discovered as a standalone panel asset before the body exists.
if (!root || !root.querySelector(':scope > .cmsMobileHeader') || !root.querySelector(':scope > main')) return;
// Presentation only: existing panel nodes, actions and module state stay in place.
var previousMobile = window.cmsMobile;
previousMobile?.destroy();
var mobileEvents = new AbortController();
var mobileState = window.cmsMobile = {active: previousMobile?.active || 'base', module: previousMobile?.module || null, innerHref:previousMobile?.innerHref || null, innerBack:!!previousMobile?.innerBack};
/* Phone, and a tablet held upright. A tablet on its side stays the desktop. */
var mobile = matchMedia('(max-width: 1024px), (orientation: portrait) and (max-width: 1400px)');
var activeWindow = mobileState.active;
var returnFocus = previousMobile?.returnFocus || null;
var overlayURL = previousMobile?.overlayURL || null;
var dismissedHref = previousMobile?.dismissedHref || null;
var introducedHref = previousMobile?.introducedHref || null;
var showSheet = !!previousMobile?.showSheet;
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
var windowSelectors = {menu: 'main > .main', edit: 'main > .content'};
var mobileHeader = document.querySelector('.cmsMobileHeader');
var backdrop = document.querySelector('.cmsMobileBackdrop');
function windowNode(name) { return document.querySelector(windowSelectors[name] || 'main > .pylon'); }
function closeMore() {
	if (typeof closeAllMenus === 'function') closeAllMenus();
}
function hexColor(color) {
	var text = String(color).trim();
	var hex = text.match(/^#([0-9a-f]{6})$/i);
	if (hex) return '#' + hex[1].toLowerCase();
	var rgb = text.match(/rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)/i);
	if (rgb) {
		return '#' + rgb.slice(1, 4).map(function (n) {
			return ('0' + Number(n).toString(16)).slice(-2);
		}).join('');
	}
	// Safari reports a custom property as color(srgb …). That string is not a
	// valid theme-color, and the status bar falls back to white.
	var srgb = text.match(/color\(\s*srgb\s+([0-9.]+)\s+([0-9.]+)\s+([0-9.]+)/i);
	if (!srgb) return '';
	return '#' + srgb.slice(1, 4).map(function (n) {
		return ('0' + Math.max(0, Math.min(255, Math.round(Number(n) * 255))).toString(16)).slice(-2);
	}).join('');
}
function themeColor(name) {
	var probe = document.getElementById('cmsThemeProbe');
	if (!probe) {
		probe = document.createElement('span');
		probe.id = 'cmsThemeProbe';
		probe.style.cssText = 'position:absolute;left:-9999px;width:1px;height:1px;pointer-events:none';
		document.body.appendChild(probe);
	}
	probe.style.backgroundColor = 'var(' + name + ')';
	return getComputedStyle(probe).backgroundColor;
}
function chromeColor() {
	// The status bar follows the theme. A fixed hex would ignore a project
	// that changes --cmsMainColor. The nav is not the only surface that colour.
	var ink = hexColor(themeColor('--cmsMainColor'));
	if (ink) {
		document.querySelectorAll('meta[name="theme-color"]').forEach(function (bar) {
			if (bar.getAttribute('content') !== ink) bar.setAttribute('content', ink);
		});
	}
	if (!mobile.matches || !document.body) {
		// The mobile Safari edge otherwise covers the desktop status footer.
		var edge = document.getElementById('cmsChromeEdge');
		if (edge) edge.remove();
		var stale = document.getElementById('cmsChromeColor');
		if (stale) stale.remove();
		if (document.documentElement) if (document.documentElement.style.background) document.documentElement.style.background = '';
		if (document.body) if (document.body.style.background) document.body.style.background = '';
		return;
	}
	var login = !!document.querySelector('.authCard');
	var menu = document.body.dataset.cmsWindow === 'menu';
	var node = document.getElementById('cmsChromeColor');
	if (!node) {
		node = document.createElement('style');
		node.id = 'cmsChromeColor';
		document.head.appendChild(node);
	}
	// Safari 26 tints the status bar from background-color, not from theme-color
	// and not from the background shorthand. A hex, so a variable is not skipped.
	var chrome = document.getElementById('cmsSafariChrome');
	if (!login && ink) {
		var rule = 'html{background-color:' + ink + '}';
		if (!menu) rule += 'body.cms>.cmsMobileHeader{background-color:' + ink + '}';
		if (chrome && chrome.textContent !== rule) chrome.textContent = rule;
		if (node.textContent) node.textContent = '';
	} else {
		if (node.textContent) node.textContent = '';
	}
	if (document.documentElement.style.background) document.documentElement.style.background = '';
	if (document.body.style.background) document.body.style.background = '';
	var edge = document.getElementById('cmsChromeEdge');
	if (!edge) {
		edge = document.createElement('div');
		edge.id = 'cmsChromeEdge';
		edge.setAttribute('aria-hidden', 'true');
		edge.style.cssText = 'position:fixed;left:0;right:0;bottom:0;height:max(env(safe-area-inset-bottom), 34px);pointer-events:none;z-index:1';
		document.body.appendChild(edge);
	}
	var paper = hexColor(themeColor('--cmsBgColor'));
	if (matchMedia('(max-width: 767px)').matches) {
		var boxes = [...document.querySelectorAll('.cmslightbox:not([hidden])')].filter(function (box) { return box.getClientRects().length; });
		var section = boxes.at(-1)?.querySelector(':scope > section');
		if (!menu && section && !boxes.at(-1).classList.contains('cmsFind')) {
			paper = hexColor(getComputedStyle(section).backgroundColor) || paper;
			if (!boxes.at(-1).classList.contains('cmsFind')) {
				document.querySelectorAll('meta[name="theme-color"]').forEach(function (bar) { bar.content = paper; });
				if (chrome) chrome.textContent = 'html{background-color:' + paper + '}';
			}
		}
	}
	edge.style.backgroundColor = menu || login ? ink : paper;
}
function showWindow(name) {
	if (!mobile.matches) return;
	if (name !== 'base' && !windowNode(name)) return;
	if (activeWindow === 'base' && name !== 'base' && name !== 'menu') {
		returnFocus = document.activeElement;
		overlayURL = location.href;
		history.pushState(Object.assign({}, history.state, {cmsMobileWindow: name}), '', location.href);
	}
	if (name === 'base' && activeWindow !== 'menu' && overlayURL === location.href && history.state?.cmsMobileWindow) {
		closingHistory = true;
		history.back();
	}
	if (name === 'base') overlayURL = null;
	closeMore();
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
	// A module-root history entry represents its pylon, not the previous leaf.
	if (mobile.matches && /\/admin\/[^/]+\/$/.test(location.pathname) && !location.search && !event.state?.cmsMobileWindow) {
		activeWindow = 'base'; showSheet = false; openNow = false;
		pendingHref = null; introducedHref = null; dismissedHref = location.href;
		root.querySelector('main > .content')?.removeAttribute('data-cms-sheet');
	}
	if (mobile.matches && !event.state?.cmsMobileWindow && !/\/admin\/[^/]+\/$/.test(location.pathname)) {
		// A real history navigation must load its destination before opening its sheet.
		dismissedHref = null; introducedHref = null; pendingHref = location.pathname + location.search; openNow = true;
	}
	if (!overlayURL) {
		if (event.state?.cmsMobileWindow && mobile.matches) {
            overlayURL=location.href;
            activeWindow=typeof event.state.cmsMobileWindow === 'string' ? event.state.cmsMobileWindow : 'menu';
            syncMobile();
            event.stopImmediatePropagation();
        }
		return;
	}
	var samePage = location.href === overlayURL;
	overlayURL = null;
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
	var cms = url.pathname.indexOf('/admin/');
	if (cms < 0) return null;
	var rest = url.pathname.slice(cms + 5).replace(/\/+$/, '');
	if (rest.indexOf('/') < 0) return null;
	return url.pathname.slice(0, cms + 5) + rest.slice(0, rest.lastIndexOf('/')) + '/' + url.search + url.hash;
}
// Move the live view into the standard lightbox and return it on close.
var pageBoxes = new Map();
function closePageBox(kind) {
	var entry = pageBoxes.get(kind);
	if (!entry) return;
	pageBoxes.delete(kind);
	entry.observer.disconnect();
	if (entry.home.isConnected) entry.home.replaceWith(...entry.main.childNodes);
	entry.box.remove();
	if (kind === 'media') document.dispatchEvent(new Event('cms-media-closed'));
}
function openPageBox(kind) {
	if (!mobile.matches || !root.classList.contains('pages') || pageBoxes.has(kind)) return;
	var content = windowNode('edit');
	var view = kind === 'media'
		? document.querySelector('main > .pylon > footer .mmlContent')
		: content?.querySelector('aside.cms');
	if (!view) return;
	var title = kind === 'media' ? 'Media Library' : 'Properties';
	var home = document.createComment(title);
	var box = document.createElement('div');
	box.className = 'cmslightbox cms pageToolBox';
	box.dataset.pageTool = kind;
	box.innerHTML = '<section><header><h1></h1><button type="button" class="button transparent icononly" data-box-close><i class="cmsIcon cmsIcon-xmark"></i></button></header><main></main></section>';
	box.querySelector('h1').textContent = title;
	box.querySelector('button').setAttribute('aria-label', 'Close ' + title);
	var main = box.querySelector('main');
	view.before(home);
	main.append(view);
	var observer = new MutationObserver(function () {
		if (box.hidden) closePageBox(kind);
	});
	observer.observe(box, {attributes: true, attributeFilter: ['hidden']});
	pageBoxes.set(kind, {box, home, main, observer});
	root.append(box);
	if (kind === 'properties') view.querySelector('.collapsed .sidebarFlag')?.click();
}
document.addEventListener('cms-open-media-picker', function () { openPageBox('media'); }, {signal: mobileEvents.signal});
document.addEventListener('cms-close-media-picker', function () {
	pageBoxes.get('media')?.box.querySelector('[data-box-close]').click();
}, {signal: mobileEvents.signal});
function closeWindow() {
	slidFor = null;
	sheetMotion = null;
	var next = activeWindow === 'menu' && showSheet ? 'edit' : !windowNode('base') && activeWindow === 'edit' ? 'menu' : 'base';
	if (next === 'base') {
		var content = windowNode('edit');
		if (content) content.removeAttribute('data-cms-sheet');
		// Dismiss to the pylon without routing a redirect-only parent.
		var modulePath = location.pathname.match(/^(.*\/admin\/[^/]+\/)/)?.[1];
		overlayURL = null;
		if (modulePath) history.replaceState({cmsMobileWindow:'base'}, '', modulePath + '?branch=1');
		root.querySelectorAll('main > .pylon nav li.active').forEach(function (item) { item.classList.remove('active'); });
		pendingHref = null; introducedHref = null; openNow = false; showSheet = false;
		dismissedHref = location.href;
	}
	showWindow(next);
}
function slideSheet(node) {
	if (!node || !node.isConnected) return;
	sliding = true;
	var dur = 480;
	sheetMotion = {dir: 'in', node: node, t: performance.now(), dur: dur};
	node.style.transition = 'none';
	node.style.transform = 'translateY(100%)';
	requestAnimationFrame(function () {
		requestAnimationFrame(function () {
			if (!node.isConnected) return;
			node.style.transition = 'transform ' + dur + 'ms cubic-bezier(.25,.65,.3,1)';
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
	for (var [kind, entry] of pageBoxes) {
		if (!entry.home.isConnected || !mobile.matches) closePageBox(kind);
	}
	var media = document.querySelector('main > .pylon > footer');
	if (content && mobileState.innerHref === location.pathname + location.search && content.dataset.cmsSheet === '1') {
		mobileState.innerHref = null;
		var direction = mobileState.innerBack ? -16 : 16;
		mobileState.innerBack = false;
		var inner = content.querySelector(':scope > .flexRow');
		if (inner && !matchMedia('(prefers-reduced-motion:reduce)').matches) inner.animate([{opacity:.6,transform:'translateX(' + direction + 'px)'},{opacity:1,transform:'translateX(0)'}],{duration:260,easing:'ease-out'});
	}
	root.classList.toggle('cmsHasPylon', !!pylon);
	var module = root.querySelector('main > .main nav li.active > a[href]')?.pathname;
	if (module && module !== mobileState.module) {
		if (mobileState.module && matchMedia('(max-width:767px)').matches && !matchMedia('(prefers-reduced-motion:reduce)').matches) {
			// Move only the new navigation contents; the rounded shell stays put.
			var nav = pylon?.querySelector('nav');
			Array.from(nav?.children || []).forEach(function (node) {
				node.animate([{opacity:.45, transform:'translateY(5px)'}, {opacity:1, transform:'translateY(0)'}], {duration:200, easing:'ease-out'});
			});
		}
		mobileState.module = module;
	}

	if (activeWindow !== 'base' && !windowNode(activeWindow)) activeWindow = 'base';
	if (!mobile.matches) activeWindow = 'base';
	// The document body is only a placeholder. The sheet flag arrives with the
	// layer, often after the first sync already wrote "base" and slid it shut.
	// A sheet address stays open until it is pulled down. A page without one
	// does not keep the previous sheet's window.
	// Modules without a pylon are ordinary content, never a detail sheet.
	if (content && (root.classList.contains('backup') || root.classList.contains('user') || content.querySelector('.dashboard'))) {
		content.removeAttribute('data-cms-sheet'); showSheet = false; openNow = false;
		content.querySelectorAll('[data-cms-close-content], .cmsMobileGrip').forEach(node => node.remove());
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
	if (content && showSheet && (document.querySelector('.cmslightbox') || introducedHref === location.href) && content.dataset.cmsSheet !== '1' && new URL(location.href).searchParams.get('branch') !== '1' && !content.querySelector('.dashboard, section.profile') && !root.classList.contains('backup') && !root.classList.contains('user')) {
		content.dataset.cmsSheet = '1';
	}
	// A non-page leaf can arrive through several partial renders. Recover the
    // selected destination even if an intermediate script consumed the tap.
    if (mobile.matches && pylon && content && !root.classList.contains('pages') && !autoOpen && content.dataset.cmsSheet !== '1' && dismissedHref !== location.href && !new URL(location.href).searchParams.has('branch') && content.querySelector('h1') && !content.querySelector('.dashboard, section.profile') && !root.classList.contains('backup') && !root.classList.contains('user')) {
        var selectedLeaf = [...pylon.querySelectorAll('nav li.active > a[href]:not([data-cms-branch])')].find(function (link) {
            var target = new URL(link.href, location.href);
            return target.pathname === location.pathname && target.search === location.search;
        });
        if (selectedLeaf) { content.dataset.cmsSheet = '1'; openNow = true; }
    }
	// Pages keep their selected document across partial renders, just like other leaves.
	if (mobile.matches && content && root.classList.contains('pages') && Number(new URL(location.href).searchParams.get('id')) > 1 && dismissedHref !== location.href && content.dataset.cmsSheet !== '1' && !new URL(location.href).searchParams.has('branch')) {
		content.dataset.cmsSheet = '1'; openNow = true;
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
				if (activeWindow !== 'menu') activeWindow = 'edit';
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
		} else if (showSheet && !arriveTimer && activeWindow !== 'menu') {
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
	Object.assign(mobileState, {active: activeWindow, returnFocus: returnFocus, overlayURL: overlayURL, closingHistory: closingHistory, showSheet: showSheet, introducedHref: introducedHref, dismissedHref: dismissedHref, openNow: openNow});
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
		var overlay = name !== 'edit' || !!pylon || node.dataset.cmsSheet === '1';
		if (name === 'edit' || name === 'menu') {
			var head = node.querySelector(':scope > header');
			var closable = name !== 'edit' || node.dataset.cmsSheet === '1';
			if (head && overlay && closable) {
                if (name === 'edit' && !head.querySelector('[data-cms-close-content]')) {
                    var close = document.createElement('button');
                    close.type = 'button'; close.className = 'button transparent cmsMobileOnly';
                    close.dataset.cmsCloseContent = ''; close.setAttribute('aria-label', 'Close');
                    close.innerHTML = '<i class="cmsIcon cmsIcon-xmark" aria-hidden="true"></i>';
                    close.addEventListener('click', closeWindow); head.append(close);
                }
				head.classList.add(name === 'edit' ? 'cmsMobileEditHeader' : 'cmsMobileDrawerHeader');
				if (name === 'edit' && !head.querySelector(':scope > .cmsMobileGrip')) {
					var grip = document.createElement('span');
					grip.className = 'cmsMobileGrip cmsMobileOnly'; grip.setAttribute('aria-hidden', 'true');
					head.prepend(grip);
				}
				head.querySelector('.cmsPageToolsMenu .langMenu')?.removeAttribute('hidden');
				var properties = head.querySelector('[data-cms-properties]');
				properties?.toggleAttribute('hidden', !node.querySelector('aside.cms') && !pageBoxes.has('properties'));
				properties?.setAttribute('aria-pressed', String(pageBoxes.has('properties')));
				head.querySelector('[data-cms-window="media"]')?.toggleAttribute('hidden', !media || !!media.querySelector('.mml-empty'));
			}
		}
		var dialog = overlay && (name !== 'edit' || node.dataset.cmsSheet === '1');
		node.inert = mobile.matches && dialog && activeWindow !== name;
		if (mobile.matches && dialog) {
			node.setAttribute('role', 'dialog');
			node.setAttribute('aria-modal', String(activeWindow === name));
			node.setAttribute('aria-label', {menu: 'Navigation', edit: 'Edit'}[name]);
		} else {
			if (name === 'edit') node.setAttribute('role','main'); else node.removeAttribute('role'); node.removeAttribute('aria-modal'); node.removeAttribute('aria-label');
		}
	});
	if (pylon) Array.from(pylon.children).forEach(function (node) {
		node.inert = mobile.matches && activeWindow !== 'base';
	});
	if (content && !pylon) content.inert = mobile.matches && activeWindow === 'menu';
	if (content && content.dataset.cmsSheet !== '1') {
		content.querySelector(':scope > header')?.classList.remove('cmsMobileEditHeader');
		content.querySelector(':scope > header > .cmsMobileGrip')?.remove();
	}
	var dialogs = [...document.querySelectorAll('.cmslightbox')].filter(function (box) { return !box.hidden && box.getClientRects().length > 0; });
	dialogs.forEach(function (box, index) {
		box.classList.toggle('cmsNestedDialog', index > 0);
		box.style.setProperty('--cmsDialogLevel', index);
	});
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
	document.querySelectorAll('.cmslightbox .tabmenu .active, .cmslightbox .tabs .active, .cmslightbox [role=tab][aria-selected=true]').forEach(function (tab) {
		if (!mobile.matches) return;
		requestAnimationFrame(function () {
			if (!tab.isConnected) return;
			var rail = tab.closest('.tabmenu, .tabs, [role=tablist]') || tab.parentElement;
			var rect = tab.getBoundingClientRect(), bounds = rail.getBoundingClientRect();
			var delta = rect.left < bounds.left + 8 ? rect.left - bounds.left - 8 : rect.right > bounds.right - 8 ? rect.right - bounds.right + 8 : 0;
			if (Math.abs(delta) > 1) rail.scrollTo({left:rail.scrollLeft + delta, behavior:matchMedia('(prefers-reduced-motion:reduce)').matches ? 'instant' : 'smooth'});
		});
	});
	// Let scrolling content pass beneath the existing header (and its tab rail).
	document.querySelectorAll('.cmslightbox > section, main > .content[data-cms-sheet="1"]').forEach(function (surface) {
		var head = surface.querySelector(':scope > header');
		var scroll = surface.querySelector(':scope > main, :scope > .flexRow');
		if (!head || !scroll || !head.getClientRects().length) return;
		var tabs = head.nextElementSibling;
		var height = head.offsetHeight + (tabs?.matches('.userTabs, .tabmenu, .tabs') ? tabs.offsetHeight : 0);
		var value = height + 'px';
		if (surface.style.getPropertyValue('--cmsGlassHeader') !== value) surface.style.setProperty('--cmsGlassHeader', value);
		surface.classList.toggle('cmsHeaderScrolled', scroll.scrollTop > 1 || [...scroll.querySelectorAll('.flexColumn, section.cms')].some(function (node) { return node.scrollTop > 1; }));
	});
	if (typeof fitKeyboard === 'function') fitKeyboard();
	pinSearch();
	// Dialog focus belongs to the shared lightbox/openSearch lifecycle, not render sync.
	fitHeader(content?.querySelector(':scope > header'));
	content?.querySelectorAll('.widget > header').forEach(fitHeader);
	dialogs.forEach(function (box) { fitHeader(box.querySelector(':scope > section > header')); });
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
	var fallback = root.classList.contains('backup') ? 'Backup' : '';
	if (!sheet || (!source && !fallback)) {
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
	var text = source?.textContent || fallback;
	if (copy.textContent !== text) copy.textContent = text;
	if (source) source.hidden = true;
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
	if (!head || head.querySelector('.cmsPageTools')) return;
	var previous = head.querySelector('.cmsMobileMore');
	if (!mobile.matches || (head.closest('.cmslightbox') && !matchMedia('(max-width:767px)').matches)) {
		if (previous) {
			previous.cmsItems?.forEach(function (entry) { entry.marker.replaceWith(entry.item); });
			(previous.menu || head.querySelector('.cmsPageToolsMenu'))?.remove();
			previous.closest('.cmsMobileActions')?.remove();
			previous.remove();
		}
		return;
	}
	if (head.querySelector('.cmsPageTools')) return;
	if (previous) return;
	var row = head.querySelector(':scope > .rightAlign, :scope > .barmenu, :scope > .cmsPanelActions > .rightAlign') || head;
	var items = [...row.children].filter(function (node) {
		if (node.querySelector('.cmsIcon-pen') && !node.closest('.barmenu, .rightAlign')) return false;
		return node.matches('a.button, a.ajax.button, button.button, label.button, .button') || (node.matches('li') && node.querySelector('a,button'));
	});
	var dialog = head.closest('.cmslightbox');
	if (items.length < 2) return;
	var menu = document.createElement('div');
	menu.className = 'splitApplyList cmsMobileActionMenu';
	menu.hidden = true;
	var moved = [];
	var overflow = items.filter(function (item) {
		return dialog ? !item.querySelector('[data-box-close],[data-close-find],[data-lightbox-close], [class*="xmark"]') && !item.matches('[data-box-close],[data-close-find],[data-lightbox-close]') : !item.matches('.highlight, .submit, .confirm') && !item.querySelector('.highlight, .submit, .confirm');
	});
	if (overflow.length < 2) return;
	overflow.forEach(function (item) {
		var marker = document.createComment('mobile action');
		item.before(marker); moved.push({item:item, marker:marker}); menu.append(item);
	});
	if (!menu.children.length) return;
	var more = document.createElement('button');
	more.type = 'button';
	more.cmsItems = moved;
	more.className = 'button transparent cmsMobileMore splitApplyMenu';
	more.setAttribute('data-menu-selected', '');
	more.setAttribute('data-cms-more', '');
	more.setAttribute('aria-label', 'More');
	more.setAttribute('aria-expanded', 'false');
	more.innerHTML = '<i class="cmsIcon cmsIcon-ellipsis"></i>';
	var actions = document.createElement('div');
	actions.className = 'splitApply cmsMobileActions';
	actions.append(more, menu);
	var close = items.find(function (item) { return item.matches('[data-box-close],[data-close-find],[data-lightbox-close]') || item.querySelector('[class*="xmark"]'); });
	if (dialog && close) close.before(actions);
	else if (moved[0]) moved[0].marker.before(actions);
	else row.append(actions);
}
// Use capture so existing AJAX handlers still receive actual links and actions.
document.addEventListener('click', function (event) {
	if (!mobile.matches || !event.target.closest) return;
	var pageMore = document.querySelector('[data-cms-page-more]');
	if (event.target.closest('[data-cms-page-more]')) {
		pageMore.setAttribute('aria-expanded', String(pageMore.getAttribute('aria-expanded') !== 'true'));
		return;
	}
	if (!event.target.closest('.cmsPageToolsMenu, .langMenu') || event.target.closest('[data-cms-device], .langMenu a')) {
		pageMore?.setAttribute('aria-expanded', 'false');
	}
	var properties = event.target.closest('button[data-cms-properties]');
	if (properties) {
		openPageBox('properties');
		return;
	}
		if (!event.target.closest('.cmsPageTools, .cmsPageToolsMenu, .splitApply, .splitApplyList, ul.contextmenu, .HTMLeditorMenu')) closeMore();
	var button = event.target.closest('button[data-cms-window], [data-cms-close]');
	if (button) {
		event.preventDefault();
		if (button.dataset.cmsWindow === 'media') openPageBox('media');
		else if (button.hasAttribute('data-cms-close') || (button.dataset.cmsWindow === 'menu' && activeWindow === 'menu')) closeWindow();
		else showWindow(button.dataset.cmsWindow);
		return;
	}
	if (event.target.closest('main > .main a[href]:not([target]):not([download])')) {
		// The link owns navigation. Closing the menu in this same click makes
		// it inert, and the browser then drops the tap. Close on the next turn.
		overlayURL = null;
		queueMicrotask(function () {
			activeWindow = 'base';
			syncMobile();
		});
	}
	// Only a selection opens Edit; folds, rename, context actions and drags keep the tree.
	var link = event.target.closest('main > .pylon nav a[href]:not([target]):not([download]), main > .content a.ajax[href]:not([target]):not([download])');
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
		var leaf = link.hasAttribute('data-cms-open-sheet') || url.searchParams.has('id') || !root.classList.contains('pages');
		if (!leaf) return;
		var here = url.pathname === location.pathname && url.search === location.search;
		var withinContent = !!link.closest('main > .content[data-cms-sheet="1"]');
		if (withinContent) {
			introducedHref = url.href;
			slidFor = url.href;
			mobileState.innerBack = link.hasAttribute('data-cms-inner-back');
			mobileState.innerHref = link.hasAttribute('data-cms-refresh-view') ? null : url.pathname + url.search;
		}
		dismissedHref = null;
		if (!withinContent) introducedHref = null;
		showSheet = withinContent;
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
		var more = document.querySelector(':is([data-cms-more], [data-cms-page-more])[aria-expanded="true"]');
		if (more) { more.setAttribute('aria-expanded', 'false'); more.focus(); }
		else closeWindow();
	}
	if (event.key === 'Tab') {
		var items = [...windowNode(activeWindow).querySelectorAll('button, a[href], input, select, textarea, [tabindex="0"], [contenteditable="plaintext-only"]')].filter(function (node) { return !node.disabled && !node.closest('[inert]') && node.getClientRects().length; });
		var first = items[0], last = items[items.length - 1];
		if (event.shiftKey && (document.activeElement === first || document.activeElement === windowNode(activeWindow))) { event.preventDefault(); last?.focus(); }
		else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
	}
}, {signal: mobileEvents.signal});
// A leftward gesture on navigation closes the drawer; vertical scrolling stays native.
var menuTouch = null;
document.addEventListener('touchstart', function (event) {
 if (!matchMedia('(max-width: 767px)').matches || activeWindow !== 'menu' || event.touches.length !== 1) return;
 menuTouch = {x:event.touches[0].clientX, y:event.touches[0].clientY};
}, {passive:true, signal:mobileEvents.signal});
document.addEventListener('touchend', function (event) {
 if (!menuTouch) return;
 var point=event.changedTouches[0], dx=point.clientX-menuTouch.x, dy=point.clientY-menuTouch.y;
 menuTouch=null;
 if (activeWindow==='menu' && dx < -70 && Math.abs(dx)>Math.abs(dy)*1.5) closeWindow();
}, {passive:true, signal:mobileEvents.signal});
// A dedicated grip area cannot conflict with long press, editor selection or scrolling.
function shutSheet(sheet) {
	if (dismissing) return;
	var lightbox = sheet.closest && sheet.closest('.cmslightbox');
	function finish() {
		dismissing = false;
		sheetMotion = null;
		sliding = false;
		if (lightbox) {
			var live = lightbox.isConnected ? lightbox : (document.querySelector('.cmslightbox') || lightbox);
			// The first link can be a download. Close is the plain ajax link,
			// or a control marked for a lightbox that is not its own address.
			live.querySelector('[data-box-close], [data-close-find], [data-lightbox-close], header [aria-label="Close"]')?.click();
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
	sheet.style.transition = 'transform ' + dur + 'ms cubic-bezier(.25,.65,.3,1)';
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
	var bar = event.target.closest?.('.cmsMobileEditHeader');
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
	var baseHeight = lightbox ? sheet.getBoundingClientRect().height : 0;
	sheetGesture = true;
	bar.setPointerCapture(event.pointerId);
	function move(e) {
		if (e.pointerId !== event.pointerId) return;
		if (e.cancelable) e.preventDefault();
		var dy = e.clientY - startY;
		// Pulling a dialog up grows it with the finger. Down still dismisses,
		// including a dialog that is already full height.
		if (lightbox && dy < 0 && !sheet.classList.contains('is-expanded')) {
			sheet.classList.add('is-expanding');
			sheet.style.removeProperty('transform');
			var full = sheet.getBoundingClientRect().bottom - Math.max(6, lightbox.getBoundingClientRect().top, window.visualViewport?.offsetTop || 0);
			var next = Math.min(full, baseHeight - dy);
			sheet.style.transition = 'none';
			sheet.style.maxHeight = 'none';
			sheet.style.height = next + 'px';
			distance = dy;
			return;
		}
		distance = Math.max(0, dy);
		sheet.style.transition = 'none';
		sheet.style.transform = 'translateY(' + distance + 'px)';
		if (!lightbox) sheet.style.boxShadow = distance > 2 ? '0 -10px 24px -8px rgb(0 0 0 / .32), 0 120px 0 0 var(--cmsBgColor)' : '';
	}
	function end(e) {
		if (e.pointerId !== event.pointerId) return;
		bar.removeEventListener('pointermove', move);
		bar.removeEventListener('pointerup', end);
		bar.removeEventListener('pointercancel', end);
		sheetGesture = false;
		sheet.classList.remove('is-expanding');
		sheet.style.removeProperty('box-shadow');
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
document.addEventListener('scroll', function (event) {
	var scroll = event.target;
	if (scroll.matches?.('.controlmapTree > nav')) {
		scroll.parentElement.classList.toggle('cmsHeaderScrolled', scroll.scrollTop > 1);
	}
	var surface = scroll.closest?.('.cmslightbox > section, main > .content[data-cms-sheet="1"]');
	if (!surface || scroll.closest('header, .userTabs, .tabmenu')) return;
	surface.classList.toggle('cmsHeaderScrolled', scroll.scrollTop > 1);
}, {capture:true, passive:true, signal:mobileEvents.signal});
mobile.addEventListener('change', function () {
	closeMore();
	syncMobile();
}, {signal: mobileEvents.signal});
if (window.visualViewport) {
	var pinning = false;
	var keyboardOpen = false;
	var fitKeyboard = function () {
		var vv = window.visualViewport;
		var covered = Math.max(0, document.documentElement.clientHeight - vv.height - vv.offsetTop);
		root.style.setProperty('--cmsKeyboard', covered + 'px');
		// A touch device, and the on-screen keyboard actually covering the page.
		// A hardware keyboard does not shrink the visible area, so it stays put.
		var touch = navigator.maxTouchPoints > 0;
		var open = touch && covered > 120;
		if (open) {
			document.documentElement.classList.add('cms-keyboard');
			document.documentElement.style.setProperty('--cmsVvTop', Math.max(0, vv.offsetTop) + 'px');
			document.documentElement.style.setProperty('--cmsVvHeight', vv.height + 'px');
			keyboardOpen = true;
		} else if (keyboardOpen) {
			document.documentElement.style.setProperty('--cmsVvTop', '0px');
			document.documentElement.style.setProperty('--cmsVvHeight', window.innerHeight + 'px');
			keyboardOpen = false;
			window.setTimeout(function () {
				if (keyboardOpen) return;
				document.documentElement.classList.remove('cms-keyboard');
			}, 340);
		}
		// The large layout viewport and visual viewport differ on Safari. Measure
		// the covered portion of this sheet, not the document's keyboard estimate.
		var search = document.querySelector('.cmslightbox.cmsFind > section');
		if (search && matchMedia('(max-width:767px)').matches) {
			var occlusion = open ? Math.max(0, search.getBoundingClientRect().bottom - vv.offsetTop - vv.height) : 0;
			search.style.setProperty('--cmsFindOcclusion', occlusion + 'px');
		}
		chromeColor();
		// iOS pans the layout to the focused field. A sheet then sits with its
		// header above the screen and the results in the middle.
		if (pinning || !touch || vv.offsetTop < 2 || !document.querySelector('.cmslightbox.cmsFind')) return;
		pinning = true;
		window.scrollTo(0, 0);
		pinning = false;
	};
	visualViewport.addEventListener('resize', fitKeyboard, {signal: mobileEvents.signal});
	visualViewport.addEventListener('scroll', fitKeyboard, {signal: mobileEvents.signal});
	fitKeyboard();
}
// Panel updates replace nodes; refresh only chrome and availability, never the editor.
var mobileObserver = new MutationObserver(syncMobile);
mobileObserver.observe(root, {childList: true, subtree: true});
mobileState.destroy = function () { for (var kind of pageBoxes.keys()) closePageBox(kind); mobileEvents.abort(); mobileObserver.disconnect(); clearTimeout(arriveTimer); };
mobileState.sync = syncMobile;
syncMobile();

// Safari can chain a background touch into layout-viewport panning while its keyboard is open.
// Only the visible search results may consume a vertical gesture.
var searchTouchY=0;
document.addEventListener('touchstart',function(event){searchTouchY=event.touches[0]?.clientY||0;},{passive:true,signal:mobileEvents.signal});
document.addEventListener('touchmove',function(event){
 if(!document.documentElement.classList.contains('cms-keyboard')||!document.querySelector('.cmsFind:not([hidden])'))return;
 var results=event.target.closest?.('.cmsFindResults'),y=event.touches[0]?.clientY||searchTouchY,delta=y-searchTouchY;searchTouchY=y;
 var scroll=results || event.target.closest?.('.cmsFind > section > main');
 while(scroll&&scroll.closest('.cmsFind')){
  var style=getComputedStyle(scroll);
  if(/auto|scroll/.test(style.overflowY)&&scroll.scrollHeight>scroll.clientHeight+1&&((delta>0&&scroll.scrollTop>0)||(delta<0&&scroll.scrollTop+scroll.clientHeight<scroll.scrollHeight-1)))return;
  scroll=scroll.parentElement;
 }
 if(event.cancelable)event.preventDefault();
},{passive:false,signal:mobileEvents.signal});

}());
