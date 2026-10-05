/* global $,sys,settings,cmsPrompt */
sys.core.server.command = function (call) {
	var cmd = this;
	var element = call.event ? call.event.element : null;
	var response = call.response;
	cmd.updateSettings = () => { // maybe switch -> methods

	};
	switch (response.command) {
		case 'updateSettings':
			//c(response.updateSettings,3).open();
			$.extend(settings, response.updateSettings);
			if (settings.bodyClasses) {
				$('body').attr('class', settings.bodyClasses);
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
				var url = response.data.path + response.data.action;
				if (response.delay != null && response.delay > 0) {
					setTimeout(() => {
						location.href = url;
					}, 1000 * response.delay);
				} else {
					location.href = url;
				}
			}
			break;
		case cmd.skipRefresh ?? 'redirect':
			var url = response.data.path + response.data.action;
			setTimeout(() => sys.core.server.sendGet(url), 100);
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
			document.title = response.replacePageTitle;
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
			(response.cssFiles || []).forEach((href) => {
				if (!href) {
					return;
				}
				var normalizedHref = String(href).split('?')[0];
				var exists = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).some((link) => {
					var existingHref = link.getAttribute('href') || '';
					return existingHref === href || existingHref.split('?')[0] === normalizedHref;
				});
				if (exists) {
					return;
				}
				var link = document.createElement('link');
				link.rel = 'stylesheet';
				link.type = 'text/css';
				link.href = href;
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
				sys.renderer.updateDocument();
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
