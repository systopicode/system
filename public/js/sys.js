/* global obj,l */
// console shorthands
window.l ? null : window.l = console.log;
window.p ? null : window.p = console.log;
window.f ? null : window.f = console.error;
window.t ? null : window.t = function () {
	console.groupCollapsed(...arguments);
	console.trace(); // hidden in collapsed group
	console.groupEnd();
};

var sysMainClass = function () {
	var sys = this;
	sys.core = {}; // js only classes

	sys.getClass = (className, callback) => {
		if (sys.classes[className]) { // already loaded -> return
			callback(sys.classes[className]);
		} else {
			sys.singletons.autoload.appendScriptByClassname(className, () => {
				callback(sys.classes[className]);
			});
		}
	};
	sys.getSingleton = (className) => {
		if (sys.singletons[className]) {
			return sys.singletons[className];
		} else {
			p('singleton not found:' + className).open();
		}
	};
	sys.DOMContentLoaded = false;
	sys.onloadFuncs = [];
	sys.onload = (func) => {
		if (sys.DOMContentLoaded) {
			func();
		} else {
			sys.onloadFuncs.push(func);
		}
	};


	// ************* onJSReady ***********************
	sys.onJSReadyFuncs = [];
	sys.onJSReady = (callback) => {
		sys.onJSReadyFuncs.push(callback);
	};
	sys.flushOnJSReady = () => {
		while (sys.onJSReadyFuncs.length) {
			var func = sys.onJSReadyFuncs.shift();
			sys.callfunc(func);
		}
	};


	// ************* onLibReady ***********************
	sys.onLibReadyFuncs = [];
	sys.onLibReady = (callback) => {
		sys.onLibReadyFuncs.push(callback);
	};
	sys.flushOnLibReady = () => {
		while (sys.onLibReadyFuncs.length) {
			var func = sys.onLibReadyFuncs.shift();
			sys.callfunc(func);
		}
	};
	sys.onAfterUpdate = sys.onLibReady; // alias
	// ************* onDocumentReady - called once ***********************
	sys.onDocumentReadyFuncs = [];
	sys.onDocumentReady = (callback) => {
		sys.onDocumentReadyFuncs.push(callback);
	};
	sys.flushOnDocumentReady = () => {
		while (sys.onDocumentReadyFuncs.length) {
			var func = sys.onDocumentReadyFuncs.shift();
			sys.callfunc(func);
		}
	};
	// ************* onDocumentUpdate - called on every update ***********************
	sys.onDocumentUpdateFuncs = [];
	sys.onDocumentUpdate = (callback) => {
		sys.onDocumentUpdateFuncs.push(callback);
	};
    
	sys.flushOnDocumentUpdate = () => {
        t('flushOnDocumentUpdate',sys.onDocumentUpdateFuncs);
        for(var index in sys.onDocumentUpdateFuncs){
            sys.callfunc(sys.onDocumentUpdateFuncs[index]);
        }
	};
    
	sys.callfunc = (func, args) => {
		if (typeof func === 'string') { // path expected
			func = obj.valueByPath(func);
		}
		if (typeof func === 'object') {
			return func.func.apply(sys, func.args);
		}
		if (typeof func === 'function') {
			return func.apply(sys, args);
		}
	//	lt('skip func', func);
	};

	sys.buildSingletons = (libnode) => {
		if (libnode.__setup && !libnode.__object) { // NEW 2021-08 build only once
			// 2do: extend from parent
			libnode.__object = new libnode.__class(); // create singleton instance
			sys[libnode.__setup.shorthand] = libnode.__object;
		}
		libnode.__children.forEach(childnode => {
			sys.buildSingletons(childnode);
		});
	};
	sys.buildObjects = (libnode) => {
		if (!libnode) { // nothing to build
			return;
		}
		if (libnode.__setup && libnode.__setup.shorthand) {
			var shorthandStr = libnode.__setup.shorthand || libnode.__setup.route;
			var shorthandArr = shorthandStr ? shorthandStr.split('.') : [];
			var shorthand = sys, parent, key;
			shorthandArr.forEach(item => {
				key = item;
				parent = shorthand;
				if (!shorthand[key]) {
					shorthand[key] = {};
				}
				shorthand = shorthand[key];
			});
			if (key) {
				parent[key] = libnode.__class;
			}
		}
		libnode.__children.forEach(childnode => {
			sys.buildObjects(childnode);
		});
	};

};

if (!sys) {
	l('create main class singleton');
	var sys = new sysMainClass();
	var cms = sys; // legacy alias
} else {
	lt('!!!!!!!!!!!!!!!! sys.js already loaded');
	// alert('fgag');
}

document.addEventListener("DOMContentLoaded", () => {
	sys.DOMContentLoaded = true;
	sys.onloadFuncs.forEach((func) => {
		func();
	});
});
