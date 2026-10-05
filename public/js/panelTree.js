/* global sys,c,p,t,l */
(function () {
	var setup = {// setup closure
		info: 'panelTree singleton - panelNode instance manager',
		route: 'singletons.panelTree',
		shorthand: 'panelTree'
	};
	sys.lib.add(setup, function () {
		var panelTree = this; // instance closure
		panelTree.recentPath = () => { // updated on every call by updateClientData // not related to url
			var path = [];
			var libnode = sys.panels;
			while (libnode && libnode.__data && libnode.__data.childSelected) {
				path.push(libnode.__data.childSelected);
				libnode = libnode[libnode.__data.childSelected];
			}
			return path;
		};
		panelTree.pathSelected = panelTree.recentPath; // alias
		panelTree.activeNode = null; // node in last CALL

		panelTree.build = libnode => { // called on every onScriptsReady event
			if (!libnode) { // page context
				return;
			}

			// The PanelTree refactor introduced a synthetic 'app' root
			// whose data is exported under route 'panels', so sys.panels
			// now has __data set. The original root branch below required
			// !__data and was no longer hit, leaving sys.panels without
			// a __class — the later activateDescendant() call would then
			// blow up because sys.panels has no __parent.
			if (!libnode.__class && !libnode.__parent) {
				libnode.__class = sys.classes.panelNode.__class;
				libnode.__setup = {
					info: 'empty root node !!!! once'
				};
				if (!libnode.__data) {
					libnode.__data = {
						inPath: true
					};
				}
			}
			if (libnode.__class && !libnode.__object) { // class exists but has no instance -> create
				l('>> build', libnode.__setup.info);
				libnode.__extends = sys.lib.extendFromParent(libnode); // modify prototype and constructor by __setup;
				libnode.__class.prototype.__libnode__ = libnode; // store libnode ref in prototype (parent access)
				libnode.__object = new libnode.__class();
			}
			if (!libnode.__class && !libnode.__data) {
				f('panelTree: empty libnode — no class and no data');
				l(panelTree);
				l(libnode);
			}
			if (!libnode.__class && libnode.__data && libnode.__data.inPath && !libnode.__object) {
				// no class -> no own JS but in path -> create from parent
				sys.lib.activateDescendant(libnode);
				l('>> built', libnode.__setup.info);
			}
			if (libnode.__object) {
				if (libnode.__data && libnode.__data.inPath) {
					panelTree.activeNode = libnode;
				}
				libnode.__object.onAfterBuild(libnode);
			}
			libnode.__children.forEach(childLibnode => {
				panelTree.build(childLibnode);
			});

		};

		debug = false;
		panelTree.update = libnode => {
			if (!libnode) {
				if (!sys.panels) { // page context
					return;
				}
				if (sys.panels.__setup) {
					libnode = sys.panels; // root panel in site
				} else {
					libnode = sys.panels.cms; // root panel in cms
				}
			}
			if (libnode.__object && libnode.__object.update) {
				try {
					libnode.__object.update(debug);
				} catch (err) {
					// One panel's onUpdate* must not abort the rest of the tree
					// (e.g. missing doc nodes / state) — siblings still need update.
					console.error('panelTree.update failed for', libnode.__name || libnode.__setup?.route, err);
				}
			}
			libnode.__children.forEach(childLibnode => {
				panelTree.update(childLibnode);
			});

		};
	});
})();
