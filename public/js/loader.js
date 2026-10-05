/* global sys,c,p,t,l,settings */
sys.core.loader = {};
sys.core.loader.__class = function () { // class to control availability of scripts ans css files
	var loader = this;
	loader.scripts = new Map(); // all scripts (loading and loaded) by id including prefix cms:class/module/module.js
	loader.scriptsLoading = new Map();
	loader.scriptsLoaded = new Map();

	loader.addScripts = (scripts, call) => { // given info from server, create and append dom element
		scripts.forEach(info => {
			if (loader.scripts.has(info.id)) {
				lx('skipped: ', info.id);
				return;
			}
			info.domObj = document.createElement('script');
			new sys.script(loader, info, call);
			document.head.append(info.domObj);
			lx('script appended', info);
		});
		lx('totalScriptsAdded:', loader.scriptsLoading.size);
		if (loader.scriptsLoading.size === 0) {
			sys.loader.onScriptsReady(call);
		}
	};

	loader.onScriptsReady = (call) => {
		var documentReadyStateChange = () => {
			document.removeEventListener('readystatechange', documentReadyStateChange);
			loader.onScriptsReady(call);
		};
		if (document.readyState !== 'complete') {
			l('flush onJSReadyFuncs skipped >>> waiting for DOM ready >>> STATE::' + document.readyState);
			document.addEventListener('readystatechange', documentReadyStateChange);
			return;
		}
		l('flush onJSReadyFuncs:' + sys.onJSReadyFuncs.length + '(' + loader.scripts.size + ' sripts loaded) >>>>>>>> run build');
		sys.flushOnJSReady();
		sys.buildSingletons(sys.singletons);
		sys.buildObjects(sys.classes);
		if (sys.panelTree) { // no modules in page context?
			sys.lib.completeModuleLibnodes(sys.panelTree.recentPath());
			sys.panelTree.build(sys.panels);
			sys.panelTree.update();
		}
		// update routeRendererData
		// l('flush onLibReady');
		sys.flushOnLibReady();
		// sys.flushOnDocumentReady(); // removed 2026-01 calld from routRenderer after update Document
	};

};
var lx = (t1, t2) => {
	// l(t1, t2);
};

sys.core.loader.__object = new sys.core.loader.__class();

sys.loader = sys.core.loader.__object; // shorthand

sys.core.script = {};
sys.core.script.__class = function (loader, info, call) {
	var script = this; // save instance;
	loader.scripts.set(info.id, script);
	loader.scriptsLoading.set(info.id, script);
	info.domObj.addEventListener('load', e => {
		// l(e.target.src);
		lx('loading complete:', info.id);
		loader.scriptsLoaded.set(info.id, script);
		loader.scriptsLoading.delete(info.id, script);
		if (loader.scriptsLoading.size === 0) { // loading sequence complete
			loader.onScriptsReady(call); // prob. not triggered when noc scripts loaded -> sys.onload (untested)
		}
	});
	lx('start loading:', info.id);
	switch (info.root) {
		case 'sys':
			var root = settings.sysRoot;
			break;
		case 'app':
			// Project companion JS lives next to PHP under project root
			// (lib/class/…, panels/…), not under public/siteRoot.
			var root = settings.projectRoot || settings.siteRoot;
			break;
		default:
			// an add-on package (e.g. system-legacy), see Sys\Packages::urls()
			var root = (settings.packageRoots || {})[info.root];
	}
	if (typeof root === 'undefined') {
		f('root could not be defined ... dumping global settings:');
		l(settings);
	}
	// the file's own version (its mtime, from the server): the URL changes
	// exactly when the file does - see SYS/src/Http/Assets.php
	info.domObj.src = root + info.src + (info.t ? '?t=' + info.t : ''); //sys.http.cmsRoot not ready
};

sys.script = sys.core.script.__class; // shorthand

sys.dom = {}; //library of dom and module related layer objects

sys.onload(() => {
	if (document.getElementById('systemDomRendererPlaceholder')) {
		l('sys.onload => replaceBodyFromPhpClassesData');
		sys.dom.body = new sys.core.dom.layer.__class();
		sys.dom.body.createRootLayer(sys.singletons.dom.renderer.__data);
	} else {
		if (document.getElementById('siteDomRendererPlaceholder')) {
			// sys.onLibReady(() => sys.siteRenderer.updateDocument()); -> moved to php routeRenderer::clientData_export()
		} else {
			l('sys.onload => initEvents');
			sys.initEvents($(document.body));
		}
	}
});
