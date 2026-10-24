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

		// toggleMenu / inputPrompt / getContextmenu / userConfirm live on
		// classes.panelNode so every panel (CMS and PWK) inherits them.

	});
})();
