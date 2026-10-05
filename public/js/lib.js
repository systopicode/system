/* global obj,l,sys */
sys.lib = {
	add: (setup, constructor) => {
		// l('adding +++ ', setup.info, setup.route);
		if (!setup.route) {
			lt("empty route", setup);
		}
		var libnode = sys.lib.getOrCreateNode(setup.route);
		libnode.__class = constructor || setup.class;
		libnode.__setup = setup;
		// libnode.__class.prototype.__libnode__ = libnode; // NEW - not working need more test
	},

	updateClientData: data => {
		// l(data);
		if (Array.isArray(data)) {
			data.forEach(data => sys.lib.updateClientData(data));
			return;
		}
		if (!data) {
			lt("php class does not provide any client data", data);
			return;
		}
		;
		// p(data,3).open();
		var libnode = sys.lib.getOrCreateNode(data.route);
		if (!libnode.__data) {
			libnode.__data = {};
		}
		mergeDeep(libnode.__data, data.data);
		// check objectwise modification and trigger instance.update(data);
		libnode.__name = data.data.name;
		// l(data.route + ' >> ' + libnode.__name);
		if (data.onLibReady) {
			data.onLibReady.forEach(path => sys.onLibReady(path));
		}
	},

	completeModuleLibnodes: path => { // if path contains modules with no js files, no libnode is created

		var libnode = sys.panels;
		path.forEach(name => {
			if (libnode[name]) {
				libnode = libnode[name];
			} else {
				sys.lib.createDescendant(libnode[name]);
				sys.lib.activateDescendant(libnode[name]);
			}
		});

	},

	createDescendant: (libnode, name) => {
		libnode[name] = {
			__parent: libnode,
			__name: name,
			__children: []
		};
		libnode.__children.push(libnode[name]);

	},

	activateDescendant: (libnode) => {
		var parent = libnode.__parent; // ? libnode.__parent : sys.classes.panelNode;
		libnode.__class = parent.__class;
		libnode.__setup = obj.extend(parent.__setup);
		libnode.__extends = parent.__extends;
		libnode.__setup.info = libnode.__name + ' - new instance of ' + parent.__setup.route;
		libnode.__setup.route += '.' + libnode.__name;
		// parent.__children.push(libnode);
		libnode.__class.prototype.__libnode__ = libnode;
		libnode.__object = new libnode.__class(libnode);
		return libnode.__object;
	},

	getOrCreateNode: routeStr => {
		// var wrongNodeBefore = (sys.panels && sys.panels.settings);
		// l('getOrCreateNode >>>>>>>>>>> ' + routeStr);
		var route = routeStr ? routeStr.split('.') : []; // split returns array with length 1 on empty string
		var libnode = cms;
		var dbg = '>> ';
		route.forEach(name => { // find or create linode
			dbg += name + ' > ';
			if (!libnode[name]) {
				libnode[name] = {// create new if not yet exists
					__children: [],
					__state: {
						layersUpdated: new Map()
					}
				};
				if (libnode.__children) { // skip root (cms)
					libnode.__children.push(libnode[name]); // add child/parent relation
					libnode[name].__parent = libnode;
				}
			}
			libnode = libnode[name];
		});
		// var wrongNodeAfter = (sys.panels && sys.panels.settings);

		if (routeStr === 'singletons.http') {
			// l('getOrCreateNode >>>>>>>>>>>' + routeStr);
		}
		if (false && !wrongNodeBefore && wrongNodeAfter) { // verstehe ich nicht mehr
			t('*********************** Wrong Node Inserted @ ' + routeStr);
			l(sys.panels);
			l(sys.panels.settings);
		}
		return libnode;
	},

	getNode: path => {
		var libnode = sys.panels;
		path.forEach(name => {
			if (!libnode || !libnode[name]) {
				libnode = null;
				return;
			}
			libnode = libnode[name];
		});
		return libnode;
	},

	/* ******* JS CLASS INHERITANCE ********** */

	applyParentConstructor: (thisRef, setup, args) => {
		var parentLibnode = sys.lib.getOrCreateNode(setup.extends);
		if (setup.route === 'panels.patchworks.edit.grid') {
			var x = 1;
		}
		parentLibnode.__class.apply(thisRef, args);
	},

	extendFromParent: libnode => {
		var parentLibnode;
		if (libnode.__setup && libnode.__setup.extends) {
			parentLibnode = sys.lib.getOrCreateNode(libnode.__setup.extends);
			libnode.__class.prototype = Object.create(parentLibnode.__class.prototype);
			// create empty prototype chained with parent
			libnode.__class.prototype.constructor = libnode.__class;
			// set correct constructor
			libnode.__class.prototype.__extends = parentLibnode;
			// set parent to access parent prototype funcs
		}
		return parentLibnode;
	}
};

var sx = () => {
	if (sys.singletons) {
		l(Object.keys(sys.singletons));
	} else {
		l('!!!!!!!!!!!!!!!!! sys.singletons KILLED', cms);
	}
}