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
					history.length > 1 ? history.back() : box.querySelector('header a[href="../"]').click();
				} else if (e.key === 'Enter') {
					(current || hits[0])?.click();
				} else if (hits.length) {
					index = (index + (e.key === 'ArrowDown' ? 1 : -1) + hits.length) % hits.length;
					current?.classList.remove('is-selected');
					hits[index].classList.add('is-selected');
					hits[index].scrollIntoView({block: 'nearest'});
				}
			});
			// iOS opens the keyboard only for a focus within the tap, the search field comes
			// later by ajax: a stand-in field takes the focus and hands it on. Outside body,
			// where the renderer does not clear it away.
			document.addEventListener('click', function (e) {
				var navLink = e.target.closest && e.target.closest('main nav a[href]');
				if (navLink && e.detail !== 0 && !navLink.classList.contains('cmsSearchLink')) {
					navLink.blur();
				}
				if (!e.target.closest || !e.target.closest('a.cmsSearchLink')) {
					return;
				}
				var proxy = document.createElement('input');
				proxy.setAttribute('aria-hidden', 'true');
				proxy.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;font-size:16px;border:0;padding:0';
				document.documentElement.appendChild(proxy);
				proxy.focus({preventScroll: true});
				var tries = 0;
				var handOn = setInterval(function () {
					var field = document.querySelector('div.cmsFind input[name="query"]');
					if (field) {
						field.focus({preventScroll: true});
					}
					if ((field && document.activeElement === field) || ++tries > 40) {
						clearInterval(handOn);
						proxy.remove();
					}
				}, 50);
			}, true);

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
				menu.style.top = menu.style.bottom = menu.style.left = menu.style.right = menu.style.maxHeight = '';
				picker.isConnected && menu.parentElement !== picker && picker.appendChild(menu);
			};
			document.addEventListener('toggle', function (e) {
				var picker = e.target;
				if (!picker.matches || !picker.matches('details.projectTemplates')) {
					return;
				}
				if (!picker.open) {
					stashTemplateMenu(picker);
					return;
				}
				document.querySelectorAll('details.projectTemplates[open]').forEach(function (other) {
					other !== picker && other.removeAttribute('open');
				});
				placeTemplateMenu(picker);
			}, true);
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

// A gear on the debug frame, top right. It opens Environment, where debug is switched off.
(function () {
	var place = function () {
		if (!document.body || !document.body.classList.contains('cms') || !window.sysdebug || !sysdebug.getShadowContainer) {
			return;
		}
		var match = location.pathname.match(/^(.*\/cms\/)/);
		var root = sysdebug.getShadowContainer();
		if (!match || !root || root.querySelector('a.debugSettings')) {
			return;
		}
		var style = document.createElement('style');
		style.textContent = 'a.debugSettings{position:fixed;top:0;right:0;z-index:1000000002;display:flex;align-items:center;justify-content:center;width:22px;height:16px;box-sizing:content-box;border-top:4px solid #660066;border-radius:0 0 0 10px;background:rgba(0,0,0,.5);color:#fff;text-decoration:none}a.debugSettings:hover{background:#660066;color:#fff}';
		var link = document.createElement('a');
		link.className = 'debugSettings';
		link.href = match[1] + 'settings/environment/';
		link.title = 'Environment';
		link.setAttribute('aria-label', 'Environment');
		link.innerHTML = '<svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path fill="currentColor" d="M19.4 13a7.8 7.8 0 0 0 .1-1 7.8 7.8 0 0 0-.1-1l2.1-1.6a.5.5 0 0 0 .1-.6l-2-3.5a.5.5 0 0 0-.6-.2l-2.5 1a7.4 7.4 0 0 0-1.7-1l-.4-2.6a.5.5 0 0 0-.5-.4h-4a.5.5 0 0 0-.5.4l-.4 2.6a7.4 7.4 0 0 0-1.7 1l-2.5-1a.5.5 0 0 0-.6.2l-2 3.5a.5.5 0 0 0 .1.6L4.6 11a7.8 7.8 0 0 0 0 2l-2.1 1.6a.5.5 0 0 0-.1.6l2 3.5a.5.5 0 0 0 .6.2l2.5-1a7.4 7.4 0 0 0 1.7 1l.4 2.6a.5.5 0 0 0 .5.4h4a.5.5 0 0 0 .5-.4l.4-2.6a7.4 7.4 0 0 0 1.7-1l2.5 1a.5.5 0 0 0 .6-.2l2-3.5a.5.5 0 0 0-.1-.6L19.4 13ZM12 15.5A3.5 3.5 0 1 1 12 8.5a3.5 3.5 0 0 1 0 7Z"/></svg>';
		root.append(style, link);
	};
	document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', place, {once: true}) : place();
})();
