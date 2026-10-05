/* global sys,c,p,t,l,Node,document */
(function () {
	var setup = {
		info: 'panelNode Base Class',
		route: 'classes.panelNode'
	};
	sys.lib.add(setup, function () {
		var node = this; // saveInstance
		node.__libnode = node.__libnode__; // will copy the property from the prototype to the instance
		node.name = node.__libnode.__name;
		if (node.__libnode.__parent) {
			node.parent = node.__libnode.__parent.__object;
			if (!node.parent) {
				// After the multi-root refactor it is possible to encounter
				// a libnode whose parent libnode never produced a panel
				// instance (e.g. an intermediate panel without panel.js
				// that wasn't activated as a descendant). Bail with a
				// descriptive log instead of throwing on parent.children
				// — a missing parent.__object only means *this* subtree is
				// not addressable via parent.children and root, the rest of
				// the build can still proceed.
				console.warn('panelNode: no parent __object for "' + node.name
					+ '" (parent libnode "' + node.__libnode.__parent.__name + '") — skipping parent wiring',
					node.__libnode);
				node.parent = null;
				node.path  = [node.__libnode.__name || node.name];
				node.root  = node;
			} else {
				node.parent.children[node.name] = node;
				node.root = node.parent.root;
				node.path = node.parent.path.concat([node.name]);
			}
		} else { // root node
			node.path = [];
			node.parent = null;
			node.root = node;
		}
		node.children = {};
		node.getChild = name => {
			if (node.__libnode[name]) { // look for libnode
				var childNode = node.__libnode[name];
				if (childNode.__object) { // object created?
					return {done: (func) => func(childNode.__object)};
				}
				if (childNode.__data.jsClass === false) {
					return {done: (func) => {
							var descendant = sys.lib.activateDescendant(childNode);
							descendant.update();
							func(descendant)
						}};
				}
				// ?? has own js ? -> server call, implement callback
			}

		};

		node.address = () => { // debug only
			return '/'.node.path.length ? node.path.join('/') : node.name;
		};
		node.views = {};

		node.onAfterBuild = libnode => {
			return libnode;
		};

		node.update = debug => {
			node.data = node.__libnode.__data || {};
			node.updateDocNodesFromLayers(debug);
			return;
		};

		node.updateDocNodesFromLayers = (debug) => {
			node.layers = node.__libnode.__layers;
			if (debug) {
				l(node.layers);
			}
			if (!node.doc) {
				node.doc = {};
			}

			for (var layerName in node.layers) {
				if (node.__libnode.__state.layersUpdated.has(layerName)) {
					var layer = node.layers[layerName];
					for (var docName in layer.docNodes) {
						node.doc[docName] = layer.docNodes[docName];
					}
					var funcName = 'onUpdate' + ucfirst(layerName);

					if (typeof (node[funcName]) === 'function') {
						node[funcName](layer.docNodes);
					}
					node.__libnode.__state.layersUpdated.delete(layerName)
				}
			}

		};

		// Shared client actions used by menu.php across all panels.
		// Formerly only on panels.cms — PWK panels never inherited them, so
		// data-on_click="toggleMenu" fell through to a no-op server PUT.
		node.toggleMenu = event => {
			sys.menus.toggle(event);
		};

		node.inputPrompt = event => {
			sys.menus.inputPrompt(event);
		};

		node.getContextmenu = event => {
			sys.menus.contextmenu(event);
		};

		node.userConfirm = event => {
			var data = event.data.dataset;
			if (data.action) {
				var query = data.confirm || "run '" + data.action + "' Are you sure?";
				if (confirm(query)) {
					event.executeAction(data.action);
				}
			} else {
				l('no action specified in data-action');
			}
		};

	});

})();
