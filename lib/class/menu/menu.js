/* global sys,c,p,t,l,history */
(function () {
	var setup = {
		route: 'singletons.menus',
		shorthand: 'menus'
	};
	sys.lib.add(setup, function () {

		var menus = this;
		var menusMap = new Map();
		var activeContextmenu;
		var container;

		document.addEventListener('click', e => {
			menus.closeAll();
		});

		menus.toggle = event => {
			var element = event.element;
			if (!menusMap.has(element)) {
				menusMap.set(element, new menu(element));
			}
			menusMap.get(event.element).toggle();
		};

		menus.closeAll = () => {
			menusMap.forEach(menu => menu.close());
			if (activeContextmenu) {
				activeContextmenu.remove();
				activeContextmenu = null;
			}
		};

		menus.contextmenu = event => {
			event.url.action = event.data.dataset.contextmenu;
			sys.core.server.sendEventCall(event).done(html => {
				if (!container) {
					container = document.createElement('div');
					container.className = 'contextmenu';
					document.body.append(container);
				}
				// var container = sys.panels.__object.doc.contextmenu;
				container.innerHTML = html;
				container.style.position = 'absolute';
				container.style.top = (event.e.clientY + window.scrollY) + 'px';
				container.style.left = event.e.clientX + 'px';
				activeContextmenu = container.firstChild;
				sys.initEvents($(activeContextmenu), () => {

				});
			});
		};

		menus.inputPrompt = event => {
			var dataset = event.data.dataset;
			var data = {
				dataset: dataset
			};
			var input = prompt(event.element.innerText + ': ' + dataset.action, dataset.value);
			data.value = input;
			sys.core.server.sendCall(data, dataset.action);
		};

		var menu = function (element) {

			var menu = this;
			var open = false;
			menu.toggle = () => {
				if (open) {
					menu.close();
				} else {
					menu.open();
				}
			};
			menu.open = () => {
				menus.closeAll();
				element.parentNode.classList.add('open');
				open = true;
			};
			menu.close = () => {
				element.parentNode.classList.remove('open');
				open = false;
			};


		};
	});
})();
