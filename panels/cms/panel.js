/* global sys,c,p,t,l */
(function () {
	var setup = {
		info: 'cmsPanel class extends basePanelClass', // debug,
		route: 'panels.cms',
		extends: 'classes.panelNode',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		var cmsModule = this; // internal instance name

		// The dialog is drawn onto the open page. A navigation would replace
		// that page, and closing would have to find the way back.
		cmsModule.openSearch = function () {
			if (document.body.dataset.cmsWindow === 'menu') {
				document.querySelector('.cmsMobileHeader button[data-cms-window="menu"]')?.click();
			}
			if (document.querySelector('.cmslightbox.cmsFind')) {
				document.querySelector('.cmslightbox.cmsFind input[name="query"]')?.focus({preventScroll: true});
				return;
			}
			var call = new sys.core.server.call();
			call.data.method = 'put';
			call.send(sys.http.root + 'cms/search/open').done(function () {
				document.querySelector('.cmslightbox.cmsFind input[name="query"]')?.focus({preventScroll: true});
			});
		};

		cmsModule.draginitPylonwidth = function (event) {
			var $pylon = $(event.element.closest('div.pylon'));
			var maxWidthInit = $pylon.width();
			var drag = event.drag;
			drag.hasHelper = false;
			drag.hooks.move = function () {
				$pylon[0].style.setProperty('--pylonWidth', (maxWidthInit + drag.moveX) + 'px');
			};
			drag.hooks.release = function () {
				// what the css limit left of the pull
				var width = Math.round($pylon.width());
				$pylon[0].style.setProperty('--pylonWidth', width + 'px');
				sys.core.server.sendCall({width: width}, 'updatePylonWidth');
			};
		};

		cmsModule.draginitSidebarwidth = function (event) {
			var $aside = $(event.element.closest('aside.cms'));
			var itemCount = $aside[0].dataset.items;
			var widthInit = $aside.width();
			var drag = event.drag;
			drag.hasHelper = false;
			drag.hooks.move = function () {
				$aside.css('flex', '0 0 ' + (widthInit - drag.moveX) + 'px');
				$aside.attr('data-cols', Math.min(3, itemCount, Math.floor((widthInit - drag.moveX) / 250)));
			};
			drag.hooks.release = function () {
				sys.core.server.sendCall({
					width: Math.round($aside[0].getBoundingClientRect().width)
				}, 'sidebar/updateWidth');
			};
		};

		cmsModule.draginitFooterHeight = function (event) {
			var $footer = $(event.element.closest('footer'));
			var maxHeightInit = $footer.height();
			// up to where the tree above stops giving way (its min-height)
			var nav = $footer[0].previousElementSibling;
			var limit = nav ? maxHeightInit + nav.offsetHeight - (parseFloat(getComputedStyle(nav).minHeight) || 0) : Infinity;
			var height = () => Math.min(limit, maxHeightInit - drag.moveY);
			var drag = event.drag;
			drag.hasHelper = false;
			drag.hooks.move = function () {
				$footer.css('max-height', height());
			};
			drag.hooks.release = function () {
				sys.core.server.sendCall({
					height: height()
				}, 'mml/updateFooterHeight');
			};
		};

		if (!window.cmsSearchKeys) {
			window.cmsSearchKeys = true;
			// shortcuts are shown with a mouse or trackpad, or once a key only a real keyboard has is pressed
			var keyboard = () => document.documentElement.classList.add('has-keyboard');
			if (matchMedia('(any-pointer: fine)').matches) {
				keyboard();
			}
			document.addEventListener('keydown', function (e) {
				if (e.metaKey || e.ctrlKey || ['Tab', 'Escape', 'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
					keyboard();
				}
				if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
					var open = document.querySelector('div.cmsFind input[name="query"]');
					var link = document.querySelector('a.cmsSearchLink');
					if (open || link) {
						e.preventDefault();
						open ? open.select() : link.click();
					}
					return;
				}
				var box = document.querySelector('div.cmsFind');
				if (!box || !['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].includes(e.key)) {
					return;
				}
				var hits = [...box.querySelectorAll('div.cmsFindResults a')];
				var current = box.querySelector('div.cmsFindResults a.is-selected');
				var index = hits.indexOf(current);
				e.preventDefault();
				if (e.key === 'Escape') {
					clearInterval(searchHand);
					searchProxy?.remove(); searchProxy = null;
					box.remove();
				} else if (e.key === 'Enter') {
					(current || hits[0])?.click();
				} else if (hits.length) {
					index = (index + (e.key === 'ArrowDown' ? 1 : -1) + hits.length) % hits.length;
					current?.classList.remove('is-selected');
					hits[index].classList.add('is-selected');
					hits[index].scrollIntoView({block: 'nearest'});
				}
			});
			// iOS keeps the keyboard only for a focus inside the tap. pointerdown is
			// that tap; the real field arrives with the ajax answer.
			var searchProxy = null;
			var searchHand = 0;
			var handSearch = function () {
				if (!searchProxy) {
					searchProxy = document.createElement('input');
					searchProxy.setAttribute('aria-hidden', 'true');
					searchProxy.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;font-size:16px;border:0;padding:0';
					document.documentElement.appendChild(searchProxy);
				}
				searchProxy.focus({preventScroll: true});
				clearInterval(searchHand);
				var tries = 0;
				searchHand = setInterval(function () {
					var field = document.querySelector('div.cmsFind input[name="query"]');
					if (field) field.focus({preventScroll: true});
					if ((field && document.activeElement === field) || ++tries > 60) {
						clearInterval(searchHand);
						searchProxy.remove();
						searchProxy = null;
					}
				}, 40);
			};
			document.addEventListener('pointerdown', function (e) {
				var link = e.target.closest && e.target.closest('a.cmsSearchLink');
				if (!link) return;
				handSearch();
			}, true);
			document.addEventListener('click', function (e) {
				var link = e.target.closest && e.target.closest('a.cmsSearchLink');
				if (link) {
					e.preventDefault();
					e.stopPropagation();
					cmsModule.openSearch();
					return;
				}
				var result = e.target.closest && e.target.closest('.cmsFindResults a[href]');
				if (result) {
					clearInterval(searchHand);
					searchProxy?.remove(); searchProxy = null;
					queueMicrotask(function () { document.querySelector('.cmslightbox.cmsFind')?.remove(); });
					return;
				}
				var closer = e.target.closest && e.target.closest('[data-close-find]');
				if (!closer) return;
				e.preventDefault();
				e.stopPropagation();
				clearInterval(searchHand);
				searchProxy?.remove(); searchProxy = null;
				closer.closest('.cmslightbox.cmsFind')?.remove();
			}, true);
			// The header link would take the focus the stand-in just opened.
			document.addEventListener('focusin', function (e) {
				if (!searchProxy || !e.target.closest || !e.target.closest('a.cmsSearchLink')) return;
				searchProxy.focus({preventScroll: true});
			}, true);
			// A click would focus the link and drop the keyboard the stand-in just opened.
			document.addEventListener('mousedown', function (e) {
				if (e.target.closest && e.target.closest('a.cmsSearchLink')) e.preventDefault();
			}, true);
			document.addEventListener('click', function (e) {
				var navLink = e.target.closest && e.target.closest('main > .main nav a[href]');
				if (!navLink || navLink.classList.contains('cmsSearchLink')) return;
				if (e.detail !== 0) navLink.blur();
				document.querySelectorAll('div.pylon nav a[href]').forEach(function (link) {
					if (document.activeElement === link) link.blur();
				});
			}, true);
			// A tap leaves the keyboard focus on the link. That reads as a second
			// selection after the main nav has moved on.
			var blurStaleNav = function () {
				var node = document.activeElement;
				var link = node && node.closest && node.closest('nav a[href]');
				if (!link || link.classList.contains('cmsSearchLink')) return;
				var item = link.parentElement;
				if (!item || !item.classList.contains('active')) link.blur();
			};
			document.addEventListener('cms:rendered', blurStaleNav);

			// The list leaves the row while it is open. The row's transform would
			// keep it under the next card, and the card clips anything inside it.
			var templateMenu = picker => picker.menu || picker.querySelector(':scope > .projectTemplatesMenu');
			var placeTemplateMenu = function (picker) {
				var menu = templateMenu(picker);
				var summary = picker.querySelector('summary');
				if (!menu || !summary) {
					return;
				}
				picker.menu = menu;
				menu.picker = picker;
				picker.toggleAttribute('data-some', !!menu.querySelector('input:checked'));
				menu.parentElement !== document.body && document.body.appendChild(menu);
				var phone = window.matchMedia('(max-width: 1024px), (orientation: portrait) and (max-width: 1400px)').matches;
				menu.classList.toggle('is-sheet', phone);
				if (phone) {
					menu.style.left = '0';
					menu.style.right = '0';
					menu.style.top = 'auto';
					menu.style.bottom = '0';
					menu.style.maxHeight = 'min(80lvh, calc(100lvh - env(safe-area-inset-top) - 12px))';
					return;
				}
				var rect = summary.getBoundingClientRect();
				var gap = 3;
				var below = window.innerHeight - rect.bottom - gap - 8;
				var above = rect.top - gap - 8;
				var down = below >= 160 || below >= above;
				menu.style.maxHeight = Math.max(120, down ? below : above) + 'px';
				menu.style.right = Math.max(8, window.innerWidth - rect.right) + 'px';
				menu.style.left = 'auto';
				menu.style.top = down ? (rect.bottom + gap) + 'px' : 'auto';
				menu.style.bottom = down ? 'auto' : (window.innerHeight - rect.top + gap) + 'px';
			};
			var stashTemplateMenu = function (picker) {
				var menu = picker.menu;
				if (!menu) {
					return;
				}
				menu.classList.remove('is-sheet');
				menu.style.top = menu.style.bottom = menu.style.left = menu.style.right = menu.style.maxHeight = '';
				picker.isConnected && menu.parentElement !== picker && picker.appendChild(menu);
			};
			var syncTemplateMenu = function (picker) {
				if (!picker || !picker.matches || !picker.matches('details.projectTemplates')) return;
				if (!picker.open) {
					stashTemplateMenu(picker);
					return;
				}
				document.querySelectorAll('details.projectTemplates[open]').forEach(function (other) {
					other !== picker && other.removeAttribute('open');
				});
				placeTemplateMenu(picker);
			};
			document.addEventListener('toggle', function (e) {
				syncTemplateMenu(e.target);
			}, true);
			// Some taps toggle the row and never deliver toggle. Place the menu
			// once the details has settled.
			document.addEventListener('click', function (e) {
				var summary = e.target.closest && e.target.closest('details.projectTemplates > summary');
				if (!summary) return;
				var picker = summary.parentElement;
				requestAnimationFrame(function () { syncTemplateMenu(picker); });
			});
			document.addEventListener('click', function (e) {
				document.querySelectorAll('details.projectTemplates[open]').forEach(function (picker) {
					var menu = templateMenu(picker);
					if (!picker.contains(e.target) && !(menu && menu.contains(e.target))) {
						picker.removeAttribute('open');
					}
				});
			}, true);
			var placeOpenTemplateMenus = function () {
				document.querySelectorAll('details.projectTemplates[open]').forEach(placeTemplateMenu);
			};
			document.addEventListener('scroll', placeOpenTemplateMenus, true);
			window.addEventListener('resize', placeOpenTemplateMenus);
			document.addEventListener('cms:rendered', function () {
				document.querySelectorAll('body > .projectTemplatesMenu').forEach(function (menu) {
					menu.picker && menu.picker.isConnected && menu.picker.open ? placeTemplateMenu(menu.picker) : menu.remove();
				});
			});

			// The field stays focused across the redraw, so a typed name would
			// remain. The value is already in the request by the time this runs.
			document.addEventListener('submit', function (event) {
				var form = event.target;
				if (!form.matches || !form.matches('form.tagAdd')) {
					return;
				}
				var field = form.querySelector('input[name="name"]');
				if (!field) {
					return;
				}
				setTimeout(function () { field.value = ''; }, 0);
			}, true);

			// a finger swipes a switch as on a phone: to the right on, to the left off
			document.addEventListener('pointerdown', function (down) {
				var track = down.pointerType !== 'mouse' && down.target.closest && down.target.closest('input.switch + div');
				var input = track && track.previousElementSibling;
				if (!input || input.disabled) {
					return;
				}
				var swiped = false;
				var move = function (ev) {
					var dx = ev.clientX - down.clientX;
					if (ev.pointerId !== down.pointerId || Math.abs(dx) < 10 || Math.abs(dx) < Math.abs(ev.clientY - down.clientY)) {
						return;
					}
					swiped = true;
					if (input.checked !== dx > 0) {
						input.checked = dx > 0;
						input.dispatchEvent(new Event('change', {bubbles: true}));
					}
				};
				var end = function () {
					document.removeEventListener('pointermove', move, true);
					document.removeEventListener('pointerup', end, true);
					document.removeEventListener('pointercancel', end, true);
					if (swiped) {
						// the click that may follow the swipe would toggle it back
						var block = ev => ev.preventDefault();
						document.addEventListener('click', block, true);
						setTimeout(() => document.removeEventListener('click', block, true), 400);
					}
				};
				document.addEventListener('pointermove', move, true);
				document.addEventListener('pointerup', end, true);
				document.addEventListener('pointercancel', end, true);
			}, true);
		}

		// toggleMenu / inputPrompt / getContextmenu / userConfirm live on
		// classes.panelNode so every panel (CMS and PWK) inherits them.

	});
})();

// The gear lives in the debug tool (placeSettingsLink). This used to add a
// second one, fixed to the viewport corner, which covered a debug frame.

sys.fitDashboards = function ($set) {
    $set.findInSet('.dashWidgets').each(function () { sys.fitDashboards.init({element: this}); });
};

// Dashboard only: reuse the shared drag/long-press engine and keep one DOM copy.
sys.fitDashboards.init = function (event) {
    var box = event.element || event.sourceElement;
    if (!box || !box.matches('.dashWidgets')) return;
    if (box.dashboardObserver) box.dashboardObserver.disconnect();
    if (!box.dashboardButtonsBound) {
        var keepButton = down => {
            if (down.target.closest('.dashHide')) down.stopPropagation();
            if (down.target.closest('header[data-on_longpress]')) {
                // Preserve the visible column plan before the editor collapses bodies.
                var count = parseInt(box.dataset.columns, 10) || 1;
                var baseOrder;
                try { baseOrder = JSON.parse(box.dataset.layouts)[3]; } catch (_) {}
                baseOrder = [...new Set((baseOrder || [...box.querySelectorAll('.dashWidget')].map(card => card.dataset.widget)).filter(key => key !== '|'))];
                var plan = [...box.querySelectorAll(':scope > .dashColumn')].flatMap((column,i) =>
                    [...(i ? ['|'] : []), ...[...column.querySelectorAll('.dashWidget')].map(card => card.dataset.widget)]);
                if (plan.length && sys.fitDashboards.plans) sys.fitDashboards.plans.set(count + ':' + JSON.stringify(baseOrder), plan);
                window.getSelection()?.removeAllRanges();
            }
        };
        box.addEventListener('pointerdown', keepButton, true);
        box.addEventListener('mousedown', keepButton, true);
        box.dashboardButtonsBound = true;
    }
    var layouts;
    try { layouts = JSON.parse(box.dataset.layouts); } catch (_) { layouts = {}; }
    var cards = new Map([...box.querySelectorAll('.dashWidget')].map(card => [card.dataset.widget, card]));
    cards.forEach(card => {
        var header = card.querySelector(':scope > header');
        if (!header) return;
        if (box.classList.contains('is-editing')) delete header.dataset.on_longpress;
        else header.dataset.on_longpress = 'dashEdit';
        header.cmsLongPress = box.classList.contains('is-editing') ? null : down => {
            // Carry this same hold through the existing server-rendered edit transition.
            let held = true, point = {x:down.clientX,y:down.clientY};
            let renderWatch;
            const titleRect=header.getBoundingClientRect();
            const grip={x:down.clientX-titleRect.left,y:down.clientY-titleRect.top};
            const move = e => { if(e.pointerId===down.pointerId)point={x:e.clientX,y:e.clientY}; };
            const end = () => { held=false;cleanup(); };
            const cleanup = () => {
                renderWatch?.disconnect();
                document.removeEventListener('pointermove',move,true);
                document.removeEventListener('pointerup',end,true);
                document.removeEventListener('pointercancel',end,true);
                window.removeEventListener('blur',end);
            };
            document.addEventListener('pointermove',move,true);
            document.addEventListener('pointerup',end,true);
            document.addEventListener('pointercancel',end,true);
            window.addEventListener('blur',end);
            const carryHold = () => {
                if(!held)return;
                let replacement=[...document.querySelectorAll('.dashWidgets.is-editing .dashWidget')].find(el=>el.dataset.widget===card.dataset.widget);
                if(!replacement)return;
                // A connected AJAX fragment must finish responsive placement before
                // dragging freezes layout; otherwise server columns leave an extra row.
                const nextBox=replacement.closest('.dashWidgets');
                const columns=parseInt(getComputedStyle(nextBox).getPropertyValue('--dashboard-columns'),10)||1;
                if(Number(nextBox.dataset.columns)!==columns) {
                    sys.fitDashboards.init({element:nextBox});
                    replacement=[...nextBox.querySelectorAll('.dashWidget')].find(el=>el.dataset.widget===card.dataset.widget);
                }
                held=false; // Exactly one handoff, even if completion follows insertion.
                cleanup();
                replacement.cmsTouchMouseUntil=0;
                // The edit render collapses all bodies. Anchor the new drag to
                // the same place in the title, then move it back under the hold.
                const title=replacement.querySelector(':scope > header').getBoundingClientRect();
                const start={x:title.left+Math.min(Math.max(0,grip.x),title.width),y:title.top+Math.min(Math.max(0,grip.y),title.height)};
                sys.processEvent($.Event('pointerdown',{currentTarget:replacement,target:replacement,
                    clientX:start.x,clientY:start.y,pointerId:down.pointerId,pointerType:down.pointerType,cmsHeld:true,
                    originalEvent:{target:replacement,pointerId:down.pointerId,pointerType:down.pointerType,preventDefault(){},stopPropagation(){}}}));
                $(document).trigger($.Event('pointermove',{target:replacement,clientX:point.x,clientY:point.y,pointerId:down.pointerId,cmsHandoff:true}));
            };
            // Event completion can precede the view insertion. Carry the hold
            // when the replacement is connected, not when the request resolves.
            renderWatch = new MutationObserver(carryHold);
            renderWatch.observe(document.body,{childList:true,subtree:true});
            sys.processEvent($.Event('longpress',{currentTarget:header,target:header})).done(carryHold);
        };
    });
    var base = [...new Set((layouts[3] || [...cards.keys()]).filter(key => key !== '|'))];
    var end = box.querySelector('.dashDrop');
    var previous = 0;
    sys.fitDashboards.plans = sys.fitDashboards.plans || new Map();
    function place() {
        if (!box.isConnected) { if (previous) box.dashboardObserver?.disconnect(); return; }
        var count = parseInt(getComputedStyle(box).getPropertyValue('--dashboard-columns'), 10) || 1;
        if (previous === count || document.body.classList.contains('widgetDragging')) return;
        previous = count;
        var order = layouts[count];
        if (!order) {
            var signature = count + ':' + JSON.stringify(base);
            order = sys.fitDashboards.plans.get(signature);
            if (!order) {
                var groups = Array.from({length: count}, () => []), heights = new Array(count).fill(0);
                base.forEach(key => {
                    var column = heights.indexOf(Math.min(...heights));
                    groups[column].push(key);
                    var card = cards.get(key);
                    heights[column] += card ? card.getBoundingClientRect().height + 20 : 0;
                });
                order = groups.flatMap((group, i) => i ? ['|', ...group] : group);
                sys.fitDashboards.plans.set(signature, order);
            }
        }
        var columns = Array.from({length: count}, () => {
            var column = document.createElement('div'); column.className = 'dashColumn'; return column;
        });
        var index = 0;
        order.forEach(key => {
            if (key === '|') { index = Math.min(count - 1, index + 1); return; }
            if (cards.has(key)) columns[index].appendChild(cards.get(key));
        });
        columns.forEach((column, i) => {
            if (end) { var drop = end.cloneNode(true); drop.dataset.column = i; column.appendChild(drop); }
        });
        box.replaceChildren(...columns);
        box.dataset.columns = count;
        box.querySelectorAll('[data-on_drop]').forEach(zone => {
            zone.dataset.columns = count;
            zone.dataset.order = JSON.stringify(order);
        });
        // Newly created end targets need the same event binding as any other drop zone.
        if (end) sys.initEvents($(box).find('.dashDrop'));
    }
    box.dashboardObserver = new ResizeObserver(place);
    box.dashboardObserver.observe(box);
    place();
};

// Also initialise the first full document; later AJAX fragments use initEvents.
sys.onload(function () { sys.fitDashboards($(document.body)); });
