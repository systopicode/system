/* global sys,c,p,t,l,loglevel */
loglevel = 0;
// lev 0 off
// lev 1 invoke
// lev 2 process
// lev 3 data
var lastURL = location.href;
(function () {
	var origPushState = history.pushState;
	history.pushState = function () {
		origPushState.apply(this, arguments);
		lastURL = location.href;
	};
})();
onpopstate = (event) => {
	sys.processEvent(event);
};

sys.processEvent = function (e) {
	e.stopPropagation();
	if (e.type === 'popstate') {
		var event = new sys.core.event(e);
		event.popState();
		return;
	}
	var eventTarget = e.target || e.currentTarget;
	var defaultAnchor = (eventTarget && eventTarget.closest) ? (eventTarget.closest('a') || eventTarget) : null;
	if (defaultAnchor && defaultAnchor.hasAttribute && defaultAnchor.hasAttribute('target')) {
		l('||| ' + e.type + ' found default Anchor - skip and return to default');
		return;
	}
	var nativeForm = (eventTarget && eventTarget.closest) ? eventTarget.closest('form[target]') : null;
	if (nativeForm) {
		l('||| ' + e.type + ' found form[target] - skip and return to default');
		return;
	}
	if (e.type !== 'keypress') { // 2do ordentliche steuerung
		e.preventDefault();
	}

	var ondoneFunc = () => void 0;
	var hooks = {done: func => ondoneFunc = func};
	var runEvent = function (evt) {
		var event = new sys.core.event(evt);
		if (evt.type === 'load') {
			setTimeout(() => {
				event.collectElementData();
				event.dispatch().done(response => ondoneFunc(response));
			}, 500);
			return;
		}
		event.collectElementData();
		event.dispatch().done(response => ondoneFunc(response));
	};

	if (e.type === 'submit' && typeof sys.fsUploader === 'function' && sys.fsUploader.needsFlush && sys.fsUploader.needsFlush(e.currentTarget)) {
		var form = e.currentTarget;
		form.classList.add('loading');
		sys.fsUploader.flushForm(form).then(function () {
			// Native submit events are reused after await — target/currentTarget become null.
			runEvent({
				type: 'submit',
				target: form,
				currentTarget: form,
				preventDefault: function () {},
				stopPropagation: function () {}
			});
		}).catch(function (err) {
			form.classList.remove('loading');
			alert((err && err.message) ? err.message : 'Upload fehlgeschlagen');
		});
		return hooks;
	}

	runEvent(e);
	return hooks;
};

sys.processEventDelayed = function (e) {
	var events = sys.core.event;
	if (events.inputTimer) {
		clearTimeout(events.inputTimer);
	}
	events.inputTimer = setTimeout(function () {
		delete events.inputTimer;
		var event = new sys.core.event(e);
		event.collectElementData();
		event.dispatch();
	}, 333);
	var ondoneFunc = () => void 0;
	var hooks = {done: func => ondoneFunc = func};
	return hooks;
};

sys.core.event = function (e) {
	var l = () => void 0;
	l('||| sys.core.event construct', e);
	// event class instanced on every event

	var event = this;
	event.e = e;
	event.type = e.type;
	event.url = {};

	event.collectElementData = () => {
		l('||| sys.core.event collectElementData called');
		event.element = e.currentTarget;
		// element where event fired
		if (event.dragEvent) {
			// drop: use the drop target from the last mousemove (drag.dropObj), not e.target.
			// On release the cursor is usually over the drag-helper, so e.target would be wrong.
			var drag = event.dragEvent.drag;
			if (drag && drag.dropObj) {
				event.element = drag.dropObj;
				event.sourceElement = drag.dropObj;
			} else {
				event.element = e.target;
				while (true) {
					if (event.element.dataset && event.element.dataset.drop_accept === event.dragEvent.data.dataset.drag_type) {
						break;
					}
					if (event.element.parentNode) {
						event.element = event.element.parentNode;
					} else {
						break;
					}
				}
				event.sourceElement = e.target;
			}
		} else {
			event.sourceElement = e.target || e.currentTarget;
		}
		if (!event.sourceElement) {
			event.data = {};
			return;
		}
		l('||| sourceElement', event.sourceElement);
		// e.g button clicked in ajaxform
		var pathItem = new sys.core.event.pathItem(event.sourceElement, event);
		event.data = pathItem.getData();
		l('||| event.data:', event.data);

	};

	event.setAction = action => {
		l('||| sys.core.event setAction');
		event.url.action = action;
	};

	var debug = 0;
	event.dispatch = () => {
		var ondoneFunc = () => void 0;
		var hooks = {done: func => ondoneFunc = func};
		// l('||| sys.core.event dispatch');
		// e.preventDefault();
		event.getTriggers();
		if (event.triggers[event.type]) {
			l('||| sys.core.event found trigger "on_' + event.type + '" in event.triggers');
			// e.g on_contextmenu -> contextmenu
			event.data.href = event.triggers[event.type];
			l('||| sys.core.event set event.data.href to "' + event.data.href + '"');
		} else {
			// e.g. on_drag -> mousedown or stndard anchors with href
			l('||| sys.core.event process non standard event "' + event.type + '"');
			switch (event.type) {
				case 'mousedown':
					switch (false) {
						// !notEmpyString evals to false
						case !event.triggers.drag:
						case !event.data.dataset.drag_type:
							event.createDrag();
							return hooks;
							// stop dispatch event handling -> event.drag
						default:
							p('EVENT canceled: ' + event.type).open();
							p(event.data);
							return hooks;
					}
					/* *********** */
					break;
				case 'mouseup':
					if (event.dragEvent) {
						if (event.data.dataset.on_drop) {
							event.resumeDrag();
						} else {
							return hooks;
							// return if resume does not trigger an action
						}


					}
					break;
				default:
					p('EVENT canceled: ' + event.type);
					p(event.data);
					return hooks;
			}
		}
		event.url = sys.http.parseURL(event.data.href);
		if (event.data.method === 'get') {
			if (event.url.pathChanged || event.url.queryChanged || event.url.actionChanged) {
				l("render");
				l('||| sys.core.event method "' + event.data.method + '" => change location');
				sys.core.server.sendEventCall(event).done(response => ondoneFunc(response));
			} else if (event.url.hashChanged) {
				sys.http.historyPush(event.data.href);
				var target = document.getElementById(event.url.hash.replace('#', ''));
				if (target) {
					l("scrollto " + event.url.hash);
					target.scrollIntoView(true);
				}
			}

		} else if (event.data.method === 'post') {
			l('||| sys.core.event method "' + event.data.method + '" => change location');
			event.changeLocation().done(response => ondoneFunc(response));
		} else {
			event.executeAction().done(response => ondoneFunc(response));
		}
		return hooks;
	};

	event.executeAction = (hrefOverride) => {
		if (hrefOverride) {
			l('||| event.executeAction hrefOverride:"' + hrefOverride);
			event.url = sys.http.parseURL(hrefOverride);
		}
		if (debug) {
			l('getTargetModuleLibnode', event);
		}
		// make sure targeted Module exists and is loaded
		event.getTargetModuleLibnode().done(libnode => {
			// find function in target module or send call to server
			if (debug) {
				l('target libnode:', libnode);
			}
			if (debug) {
				l('dispatchAction', event);
			}
			event.dispatchAction(event.url.action, libnode)
					.clientModule(module => {
						// l('targetModule:', module);
						// l('method:', event.url.action);
						// l('argument:', event);
						module[event.url.action](event);
						// module[event.url.action].call(module, iks);
					})
					.clientGlobal(func => {
						func(event);
					})
					.serverCall(() => {
						l('serverCall');
						sys.core.server.sendEventCall(event).done(response => l(response));
					});
		});
		var ondoneFunc = () => void 0;
		var hooks = {done: func => ondoneFunc = func};
		return hooks;
	};

	event.changeLocation = () => {
		sys.core.server.sendEventCall(event).done(response => ondoneFunc(response));
		var ondoneFunc = () => void 0;
		var hooks = {done: func => ondoneFunc = func};
		return hooks;
	};

	event.getTargetModuleLibnode = () => {
		var libnode, ondone, chain = {
			done: func => {
				if (libnode) {
					func(libnode);
				} else {
					ondone = func;
				}
				return chain;
			}
		};
		// event.moduleRendered = sys.lib.getNode(sys.http.renderPath);
		var target = sys.lib.getNode(event.url.fullpath);
		if (target) {
			if (target.__object) {
				libnode = target;
			} else {
				if (target.__data.hasOwnJS) {// load js and create instance
					l('js');

				} else {// no js
					l('nojs');
					sys.lib.activateDescendant(target); // create instance from parent
					libnode = target;
				}
			}
		} else {
			// 2do: page context ordentlich implemenrieren
			l('page context? -> sendEventCall ' + event.url.action);
			// event.dispatchAction(event.url.action);
			if (typeof (window[event.url.action]) === 'function') {
				window[event.url.action]();
			} else {
				sys.core.server.sendEventCall(event);
			}
			//ondone(event);
			//l('failed:getTargetModuleLibnode - send get, retry', event.url);
			/* sys.core.server.sendGet(event.url).done(() => {
			 l('module loaded');
			 }) */
		}
		return chain;
	};

	event.dispatchAction = (action, libnode) => {
		var module, globalfunc, chain = {
			clientModule: func => {
				if (module) {
					func(module);
				}
				return chain;
			},
			clientGlobal: func => {
				if (globalfunc) {
					func(globalfunc);
				}
				return chain;
			},
			serverCall: func => {
				if (!globalfunc && !module) {
					func();
				}
				return chain;
			}
		};
		if (libnode && typeof libnode.__object[action] === 'function') {
			// action exists in selected module?
			l('found function ' + action + ' in ' + libnode.__name);
			// l(event);
			module = libnode.__object;
		} else {
			if (typeof (window[action]) === 'function') {
				// global function exists
				globalfunc = window[action];
			}
		}

		return chain;
	};

	event.getTriggers = () => {
		event.triggers = {};
		for (var name in event.data.dataset) {
			var nameInfo = name.match(/^on_(.*)$/);
			if (nameInfo) {
				event.triggers[nameInfo[1]] = event.data.dataset[name];
			}
		}
	};

	event.createDrag = () => {
		event.drag = new sys.core.event.drag(event, {
			// save instance for drop
			move: function () {},
			drag: function () {},
			release: function () {},
			accept: function () {
				f(event.drag.e);
				var dropEvent = new sys.core.event(event.drag.e);
				dropEvent.dragEvent = event;
				dropEvent.collectElementData();
				dropEvent.dispatch();
			}
		});
		if (event.triggers.draginit) {
			event.url = sys.http.parseURL(event.triggers.draginit);
			event.executeAction();
		}
	};

	event.resumeDrag = () => {
		event.data.dragdata = event.dragEvent.data;
		event.data.dragdata.keysheld = event.dragEvent.drag.keysheld;
		p(event.data.dragdata, event.dragEvent);
		event.data.href = event.data.dataset.on_drop;
	};

	event.handleEventsOnTriggerObjects = function () {
		// experimental
		if (e.target.dataset.on_load) {
			if (typeof window[e.target.dataset.on_load] === 'function') {
				window[e.target.dataset.on_load](e);
			}
		}
	};

	event.popState = function () {
		var url = sys.http.parseURL(location.href, lastURL);
		lastURL = location.href;
		event.url = url;
		if (url.pathChanged || url.queryChanged || url.actionChanged) {
			sys.core.server.sendGet(url);
		} else if (url.hashChanged) {
			l("scrollto " + url.hash);
			var target = document.getElementById(url.hash.replace('#', ''));
			if (target) {
				target.scrollIntoView(true);
			}
		}
	};
	/* ****************** REMOVE ******************* */

};