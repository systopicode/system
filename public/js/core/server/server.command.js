/* global $,sys,settings,cmsPrompt */
sys.core.server.command = function (call) {
	var cmd = this;
	var element = call.event ? call.event.element : null;
	var response = call.response;
	cmd.updateSettings = () => { // maybe switch -> methods

	};
	switch (response.command) {
        case 'requestResult':
            call.requestError = response.data?.ok === false;
            break;
		case 'updateSettings':
			//c(response.updateSettings,3).open();
			$.extend(settings, response.updateSettings);
			if (settings.bodyClasses) {
				$('body').attr('class', settings.bodyClasses);
				// The class string from the server has no mobile state. Putting
				// it back keeps the phone layout after a drop or a navigation.
				if (window.cmsMobile && typeof window.cmsMobile.sync === 'function') {
					window.cmsMobile.sync();
				}
			}
			break;
		case 'remove':
			$(response.selector).off();
			$(response.selector).remove();
			break;
		case 'append':
			var $appendObj = $($.parseHTML(response.html, '', true));
			$(response.selector).append($appendObj);
			sys.initEvents($appendObj);
			break;
		case 'prepend':
			var $prependObj = $($.parseHTML(response.html, '', true));
			$(response.selector).prepend($prependObj);
			sys.initEvents($prependObj);
			break;
		case 'skipRefresh': // called from php d() to keep message displayed
			cmd.constructor.prototype.skipRefresh = true;
			break;
		case cmd.skipRefresh ?? 'refresh':
			var refreshMethod = (response.data && response.data.method) || 'get';
			if (refreshMethod === 'native') {
				// Same address the ajax refresh would call, then a real
				// document so the next page brings its own styles.
				var nativeParsed = sys.http.parseURL((response.data && response.data.path) || '');
				var nativeRoot = nativeParsed.absolute ? sys.http.root : '';
				location.href = nativeRoot
					+ sys.http.path2pathStr(nativeParsed.path)
					+ (nativeParsed.action || '');
				break;
			}
			if (refreshMethod === 'put') {
				var sendPutRefresh = () => {
					var call = new sys.core.server.call({
						method: 'put',
						get: response.data.get || {},
					});
					call.send(response.data.path + response.data.action);
				};
				if (response.delay != null && response.delay > 0) {
					setTimeout(sendPutRefresh, 1000 * response.delay);
				} else {
					sendPutRefresh();
				}
			} else {
				// Same as redirect: a get stays in the ajax flow. Downloads
				// and a real form post still leave through the browser.
				var refreshData = response.data || {};
				var url = (refreshData.path || '')
					+ (typeof refreshData.action === 'string' ? refreshData.action : '');
				var refreshGet = (refreshData.get && typeof refreshData.get === 'object')
					? refreshData.get : {};
				var go = function () {
					if (!url) {
						return;
					}
					call.redirecting = true;
					sys.core.server.sendGet(url, refreshGet);
				};
				if (response.delay != null && response.delay > 0) {
					setTimeout(go, 1000 * response.delay);
				} else {
					go();
				}
			}
			break;
		case cmd.skipRefresh ?? 'redirect':
			var url = (response.data && response.data.path ? response.data.path : '')
				+ (response.data && response.data.action ? response.data.action : '');
			// A relative path is relative to the panel that answered, not to
			// the address bar: a lightbox call does not change the address.
			if (url && !/^(\/|https?:)/.test(url) && call.url && call.url.fullpath) {
				var parts = url.match(/^(.*\/|)(.*)$/);
				url = sys.http.root
					+ sys.http.path2pathStr(sys.http.simplifyPath(call.url.fullpath.concat(sys.http.pathStr2path(parts[1]))))
					+ parts[2];
			}
			if (url) {
				// The request URL must not be written back afterwards, or the
				// address bar stays on the page this redirect is leaving.
				// A get stays in the ajax flow: opening a module follows on
				// to its last child without a full page load.
				call.redirecting = true;
				var redirectMethod = (response.data && response.data.method) || 'get';
				if (redirectMethod === 'get') {
					sys.core.server.sendGet(url, (response.data && response.data.get) || {});
				} else if (response.delay > 0) {
					setTimeout(function () {
						location.href = url;
					}, 1000 * response.delay);
				} else {
					location.href = url;
				}
			}
			break;

		case 'callFunction':
			var responseFunction = window[response.functionName];

			if (typeof responseFunction === 'function') {
				let args;

				if (response.args) {
					if (Array.isArray(response.args)) {
						// Fall: Nur Werte → direkt übergeben
						args = response.args;
					} else if (typeof response.args === 'object' && response.args !== null) {
						// Fall: key => value Objekt → als EIN Argument übergeben
						args = [response.args];
					}
				}

				// Funktion aufrufen mit passenden Argumenten
				responseFunction.apply(null, args || []);
			} else {
				p('no function here:' + response.functionName);
			}
			break;
		case 'insertHtmlAtCursor':
			var result = insertHtmlAtCursor(response.html);
			if (!result || result === 'nodeditor') {
				var $appendObj = $($.parseHTML(response.html, '', true));
				$(response.selector).append($appendObj);
				sys.initEvents($appendObj);
			} else {
				return;
			}
			break;
		case 'openContextMenu':

			let args = {
				html: response.html,
				event: call.event
			};
			openContextMenu(args);

			break;

		case 'trigger':
			$(response.selector).trigger(response.trigger);
			break;

		case 'replace':
		case 'replaceInner':
			if (response.command === 'replaceInner') {
				// l(response);
				$(response.selector).each(function () {
					var $replaceObj = '';
					$(this).off(); // remove semms to keep events -> page slows down
					$(this).children().remove();
					$(this).html('');
					var $replaceObj = $($.parseHTML(response.html, '', true));
					$(this).append($replaceObj);
					sys.initEvents($replaceObj);
				});
			} else {
				$(response.selector).each(function () {
					var $replaceObj = '';
					if (response.html) {
						var $replaceObj = $($.parseHTML(response.html, '', true));
					}
					$(this).off(); // just remove() seems to keep events -> page slows down
					$replaceObj.insertAfter($(this));
					$(this).remove();
					sys.initEvents($replaceObj);
				});
			}
			break;

		case 'replaceAttr':
			$(response.selector).attr(response.replaceAttr, response.value);
			break;

		case 'replacePageTitle'://08112018 - LS
			var title = String(response.replacePageTitle || '');
			var marker = title.match(/[🔨🧪]/u);
			document.title = marker ? marker[0] + ' ' + title.replace(marker[0], '').trim() : title;
			break;

		case 'replaceMetaTags'://08112018 - LS
			$.each(response.replaceMetaTags, function (key, value) {
				var $key = $.escapeSelector(key);
				$('meta[' + value.type + '=' + $key + ']').attr('content', value.content);
			});
			break;

		case 'pushState':
			// p(response);
			history.pushState(null, null, response.url);
			// historyPush(response.url);
			break;

		case 'eval':
			eval(response.eval);
			break;

		case 'insertAfter':
			var $appendObj = $($.parseHTML(response.html, '', true)).insertAfter(response.insertAfter);
			sys.initEvents($appendObj);
			break;

		case 'insertAfterElement':
			var $appendObj = $($.parseHTML(response.html, '', true)).insertAfter(element);
			sys.initEvents($appendObj);
			break;

		case 'setValue':
			$(response.selector).val(response.value);
			break;

		case 'setCSS':
			$(response.selector).css(response.value);
			break;

		case 'prompt':
			$(response.selector).prepend(response.html);
			if (typeof cmsPrompt === 'object') {
				cmsPrompt.setPromptSize($('div.debug.ajaxResponse')[0]);
			}
			break;

		case 'scripttime':
			$('div.debug.scripttime').prepend(response.scripttime);
			break;

		case 'addClass':
			var target = element;
			if (response.selector) {
				target = $(response.selector);
			}
			$(target).addClass(response.className);
			if (response.timeout) {
				setTimeout(function () {
					$(target).removeClass(response.className);
				}, response.timeout);
			}
			break;

		case 'removeClass':
			var target = element;
			if (response.selector) {
				target = $(response.selector);
			}
			$(target).removeClass(response.className);
			if (response.timeout) {
				setTimeout(function () {
					$(target).removeClass(response.className);
				}, response.timeout);
			}
			break;

		case 'toggleClass':
			var target = element;
			if (response.classNameTarget) {
				target = response.classNameTarget;
			}
			$(target).toggleClass(response.toggleClass);
			break;
		case 'setAttribute':
			$(response.selector).attr(response.attr, response.value);
			break;
		case 'trigger':
			var target = element;
			if (response.classNameTarget) {
				target = response.classNameTarget;
			}
			$(target).trigger(response.trigger);
			break;
		case 'loadJS':
			sys.loader.addScripts(response.scriptFiles, call);
			break;
		case 'loadCSS':
			// The answer lists every module sheet for the page now open.
			// Sheets of the module just left are removed, including on back
			// and forward. A link without this mark is the system UI.
			// The link has to name layer="cms". A style tag that repeats
			// "@layer cms" does not join the layer on the system links.
			var sheetKey = (value) => {
				var path = String(value || '').split('?')[0];
				try {
					return new URL(path, document.baseURI).pathname;
				} catch (error) {
					return path;
				}
			};
			var wanted = new Set();
			var items = [];
			(response.cssFiles || []).forEach((item) => {
				var href = typeof item === 'string' ? item : (item && item.href);
				if (!href) {
					return;
				}
				var path = String(href).split('?')[0];
				var key = sheetKey(path);
				wanted.add(key);
				items.push({href: href, path: path, key: key});
			});
			document.querySelectorAll('link[data-cms-module]').forEach((link) => {
				if (!wanted.has(sheetKey(link.getAttribute('href')))) {
					link.remove();
				}
			});
			document.querySelectorAll('style[data-cms-module]').forEach((style) => {
				if (!wanted.has(sheetKey(style.getAttribute('data-cms-module')))) {
					style.remove();
				}
			});
			items.forEach((item) => {
				var existing = null;
				document.querySelectorAll('link[rel="stylesheet"]').forEach((link) => {
					if (!existing && sheetKey(link.getAttribute('href')) === item.key) {
						existing = link;
					}
				});
				// A sheet that came back without its layer paints over the
				// system UI. Replace it so the new link joins the cms layer.
				if (existing && existing.getAttribute('layer') !== 'cms') {
					existing.remove();
					existing = null;
				}
				if (existing) {
					existing.setAttribute('data-cms-module', item.path);
					// Keep the stylesheet node and its cascade position, but honour
					// the server's new version after a deployment or a Dev edit.
					if (existing.href !== new URL(item.href, document.baseURI).href) {
						existing.href = item.href;
					}
					return;
				}
				var link = document.createElement('link');
				link.rel = 'stylesheet';
				link.href = item.href;
				link.setAttribute('layer', 'cms');
				link.setAttribute('data-cms-module', item.path);
				document.head.appendChild(link);
			});
			break;
		case 'updateClientData':
			response.data.forEach(data => {
				sys.lib.updateClientData(data);
			});
			break;
		case 'updateDocument':
			if (settings.inApp) {
                // A background request may have waited while the user scrolled.
                // Preserve the position immediately before replacing the DOM.
                var backgroundScroll = call.background ? sys.core.server.scrollPositions() : null;
				sys.renderer.updateDocument();
                if (backgroundScroll) sys.core.server.restoreScroll(backgroundScroll);
				// Fire onUpdate* for layers marked layersUpdated (e.g. search
				// results). Legacy did NOT call panelTree.update here — only
				// after loadJS via loader.onScriptsReady. If this response
				// still queues new scripts (clientPreload / PreloadsClientScript,
				// e.g. fsUploader.js), defer update: otherwise onUpdateLightbox
				// runs before the class exists, clears layersUpdated, and the
				// later onScriptsReady update never re-fires the hook.
				if (sys.panelTree) {
					var hasPendingScripts = (call.responses || []).some((r) => {
						if (r.command !== 'loadJS') {
							return false;
						}
						return (r.scriptFiles || []).some((s) => s && s.id && !sys.loader.scripts.has(s.id));
					});
					if (!hasPendingScripts) {
						try {
							sys.panelTree.update();
						} catch (e) {
							console.error('panelTree.update after updateDocument', e);
						}
					}
				}
			} else {
				sys.siteRenderer.updateDocument();
			}
			break;
		case 'done':
			call.dataPassedToCallbackFunction = response.data;
			break;

		case 'localStorage':
			var target = element;
			if (response.selector) {
				localStorage.setItem(response.selector, response.data);
			}
			break;
	}

};
