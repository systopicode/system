/* global sys,obj,c,p,t,l,Node,document,settings,cmsPrompt */
(function () {
	var setup = {
		info: 'domLayer Class',
		route: 'core.dom.layer'
	};
	// While rebuild() builds a layer away from the document, events and scripts
	// wait for the finished dom: {layers: [...]} whose scripts are still to run.
	var building = null;

	sys.lib.add(setup, function () {
		var layer = this;
		layer.parent = null;
		layer.libnode = null;
		layer.scripts = [];
		layer.placeholders = [];
		layer.ownPlaceholder; // empty dom element if no rootNodes
		layer.children = [];
		layer.rootNodes = []; // containing all dom root nodes and layer objects if their placeholders were on root level
		layer.outRootNodes = [];
		// What a browser would run: no type, a JavaScript type, or a module.
		layer.isExecutable = script => {
			var type = (script.getAttribute('type') || '').trim().toLowerCase();
			return type === '' || type === 'module' || /^(text|application)\/(x-)?(java|ecma)script$/.test(type);
		};
		layer.createRootLayer = (bodyLayerData) => {
			// lt('createRootLayer');
			layer.data = bodyLayerData.layers.data;
			bodyLayerData.html = bodyLayerData.layers.html.replace(/<(\/?)body([^>]*)>/g, '');
			var placeholderNode = document.createElement('template');
			// document.body.innerHTML = '';
			document.getElementById('systemDomRendererPlaceholder').remove();
			bodyLayerData.bodyClasses.forEach(className => {
				document.body.classList.add(className);
			});
			sys.core.dom.layer.__class.prototype.bodyClasses = bodyLayerData.bodyClasses;
			document.body.append(placeholderNode);
			layer.createDomObjects(bodyLayerData.layers);
			layer.addToLibnode();
			layer.appendDomObjects(placeholderNode);
			layer.createChildren(bodyLayerData.layers);
			layer.removePlaceholder();
			layer.printDebug();
		};

		layer.createDomObjects = layerData => {
			layer.data = layerData.data;
			// Failed opens export '' (legacy: null). Same as a reload: the
			// slot stays in the tree but has no markup, so previous DOM
			// (e.g. a lightbox overlay) is replaced with nothing.
			var html = layerData.html == null ? '' : layerData.html;
			layer.scripts = [];
			var parentNode = document.createElement('div');
			parentNode.innerHTML = '<div>' + html + '</div>'; //wrap to preserve textnodes;
			var placeholders = parentNode.getElementsByClassName('domLayerPlaceholder');
			layer.placeholders = Array.prototype.slice.call(placeholders);
			var scripts = parentNode.getElementsByTagName('script');
			// innerHTML does not execute scripts
			// !! execution order not mantained when re-inserted into document;
			// Data blocks (type="application/json" …) are not code: they stay in
			// the markup, where the page reads them, and are never executed.
			for (var script of scripts) {
				if (layer.isExecutable(script)) {
					layer.scripts.push(script);
				}
			}
			layer.scripts.forEach(script => script.remove());
			layer.rootNodes = Array.prototype.slice.call(parentNode.firstChild.childNodes);
			layer.docNodes = layer.getDocNodes(parentNode);

			if (!building) {
				layer.rootNodes.forEach(node => {
					sys.initEvents($(node));
				});
			}

			if (parentNode.childNodes.length > 1) {
				l('HTML error: Layer not well-formed', layerData);
			}

		};

		layer.getDocNodes = parentNode => {
			var docNodes = {};
			parentNode.querySelectorAll('[data-doc]').forEach(docNode => {
				if (!docNodes[docNode.dataset.doc]) { // single
					docNodes[docNode.dataset.doc] = docNode;
				} else { // multiple
					if (!Array.isArray(docNodes[docNode.dataset.doc])) { // 1st item
						docNodes[docNode.dataset.doc] = [docNodes[docNode.dataset.doc]];
					}
					docNodes[docNode.dataset.doc].push(docNode);
				}
			});
			return docNodes;
		};

		layer.addToLibnode = () => {
			// Cancelled placeholder (no panel opened): keep the previous
			// libnode so close/replace still notifies the panel that had
			// the view, instead of attaching to the root `panels` node.
			if (!layer.data.modulePath && !layer.data.moduleName) {
				return;
			}
			// A page layer has no panel object to notify, and its modulePath
			// is a node-id chain, not a folder path. Resolving it here built
			// sys.panels['1']['18']['112']… - a shadow tree of nodes with
			// neither a class nor data, which panelTree.build() then walked
			// and warned about, once per page in the cms preview.
			if (layer.data.source === 'page') {
				return;
			}
			var path = layer.data.modulePath ? 'panels.' + layer.data.modulePath : 'panels';
			layer.libnode = sys.lib.getOrCreateNode(path);
			if (!layer.libnode.__layers) {
				layer.libnode.__layers = {};
			}
			var viewName = String(layer.data.viewName); // unnamed views are called 'false' -> will fail on multiple
			layer.libnode.__layers[viewName] = layer;
		};

		layer.updateLibnode = () => {
			// link layer to module->view->layers[]
			// parse data-doc items
		};

		layer.appendDomObjects = positionNode => {
			for (var rootNode of layer.rootNodes) {
				positionNode.parentNode.insertBefore(rootNode, positionNode);
			}

			if (layer.rootNodes.length && layer.parent) {
				layer.parent.removePlaceholder(positionNode);
				positionNode.remove();
			}

			building ? building.layers.push(layer) : layer.runScripts();
		};

		/** The layer's scripts, then: its panel hears that the view is in place. */
		layer.runScripts = () => {
			// Preserve order: external scripts must finish before later inline
			// scripts run (e.g. patchwork config that needs $.fn.patchwork).
			(async () => {
				for (var deadScript of layer.scripts) {
					await new Promise(resolve => {
						var liveScript = document.createElement('script');
						if (deadScript.type) {
							liveScript.type = deadScript.type;   // keeps a module a module
						}
						if (deadScript.src) {
							liveScript.onload = resolve;
							liveScript.onerror = resolve;
							liveScript.src = deadScript.src;
							document.head.appendChild(liveScript);
						} else {
							liveScript.textContent = deadScript.textContent;
							document.head.appendChild(liveScript);
							resolve();
						}
						l(liveScript);
					});
				}
			})();
			var viewName = String(layer.data.viewName); // unnamed views are called 'false' -> will fail on multiple
			if (layer.libnode && layer.libnode.__state && layer.libnode.__state.layersUpdated) {
				layer.libnode.__state.layersUpdated.set(viewName);
			}
		};

		layer.shiftOutRootNodes = () => {
			layer.children.forEach(child => child.shiftOutRootNodes());
			var positionNode;
			if (layer.rootNodes.length) {
				layer.outRootNodes = layer.rootNodes.slice();
				positionNode = layer.outRootNodes[0];
			} else {
				positionNode = layer.ownPlaceholder;
			}
			layer.rootNodes = [];
			return positionNode;
			// maybe add transition class
		};

		layer.removePlaceholder = placeholderNode => {
			layer.rootNodes = layer.rootNodes.filter(node => {
				return !(node === placeholderNode);
			});
		};

		layer.removeOutRootNodes = () => {
			layer.children.forEach(child => child.removeOutRootNodes());
			if (layer.rootNodes.length === 0) {
				if (layer.outRootNodes.length) {
					// A slot that comes back empty leaves its placeholder behind,
					// so the next update knows where the slot was. Both halves of
					// that can be missing - a layer built by createRootLayer has
					// no ownPlaceholder, and a node another update already took
					// out of the document has no parent. Until now that threw
					// here, in the middle of the walk: everything after it kept
					// its old dom (a closed lightbox stayed on screen) and the
					// debug tree never got reprinted, so the client looked like
					// it had not been updated at all.
					var anchor = layer.outRootNodes[0];
					if (anchor.parentNode && layer.ownPlaceholder) {
						anchor.parentNode.insertBefore(layer.ownPlaceholder, anchor);
					} else {
						f('domLayer: no placeholder for ' + layer.label()
							+ ' - anchor in document: ' + !!anchor.parentNode
							+ ', placeholder: ' + !!layer.ownPlaceholder);
					}
				} else {
					// parent is null on the root layer - reading through it threw
					if (layer.parent && layer.parent.data.type === 'new') {
						// removing obsolete descendants - no more positioning required
					} else {
						// p('empty => empty - placeholder should alredy be there - ' + layer.label());
					}
				}
			}
			layer.outRootNodes.forEach(rootNode => {
				rootNode.remove();
			});
			layer.outRootNodes = [];
		};

		layer.removeRootNodes = () => {
			layer.rootNodes.forEach(node => node.remove());
		};

		/** Is the node in this layer's dom (its own or a descendant layer's)? */
		layer.holds = node => layer.rootNodes.some(root => root === node || (root.contains && root.contains(node)))
			|| layer.children.some(child => child.holds(node));

		/**
		 * Where a replaced node was scrolled to. The new tree has the same
		 * element nesting (a list inside .appEventsScroll, the box itself
		 * stays the second child of its section), so the path of child
		 * indices still finds it. A container on the way to the focused
		 * element is kept anyway and already holds its position.
		 */
		layer.scrollOffsets = () => {
			var offsets = [];
			var walk = (node, path) => {
				if (!node || node.nodeType !== 1) {
					return;
				}
				if (node.scrollTop || node.scrollLeft) {
					offsets.push({path: path.slice(), top: node.scrollTop, left: node.scrollLeft});
				}
				var children = node.children;
				for (var i = 0; i < children.length; i++) {
					path.push(i);
					walk(children[i], path);
					path.pop();
				}
			};
			layer.rootNodes.forEach((node, index) => walk(node, [index]));
			return offsets;
		};

		layer.applyScrollOffsets = offsets => {
			offsets.forEach(offset => {
				var node = layer.rootNodes[offset.path[0]];
				for (var i = 1; node && i < offset.path.length; i++) {
					node = node.children[offset.path[i]];
				}
				if (!node) {
					return;
				}
				node.scrollTop = offset.top;
				node.scrollLeft = offset.left;
			});
		};

		/**
		 * The layer anew from the server's html, its children included - for a
		 * layer that is 'new' or 'update', or whose children no longer match.
		 * With the focused element inside it, the new dom is built away from
		 * the document and morphed in around that element, which never leaves
		 * the document (core/focus.js).
		 */
		layer.rebuild = layerData => {
			var focused = focusPath.focused();
			var offsets = layer.scrollOffsets();
			if (!focused || !layer.holds(focused)) {
				var positionNode = layer.shiftOutRootNodes();
				layer.createDomObjects(layerData);
				layer.addToLibnode();
				layer.appendDomObjects(positionNode);
				layer.removeOutRootNodes();
				layer.children.forEach(child => child.removeRootNodes());
				layer.children = [];
				layer.createChildren(layerData);
				layer.applyScrollOffsets(offsets);
				return;
			}
			layer.rebuildAround(focused, layerData, offsets);
		};

		layer.rebuildAround = (focused, layerData, offsets) => {
			var positionNode = layer.shiftOutRootNodes();
			var oldNodes = [];
			var collect = old => {
				oldNodes.push(...old.outRootNodes);
				old.outRootNodes = [];
				old.children.forEach(collect);
			};
			collect(layer);

			// the new dom, complete with its children, in a detached host
			var host = document.createElement('div');
			var anchor = document.createComment('rebuild');
			host.appendChild(anchor);
			building = {layers: []};
			var built;
			try {
				layer.createDomObjects(layerData);
				layer.addToLibnode();
				layer.appendDomObjects(anchor);
				layer.children = [];
				layer.createChildren(layerData);
			} finally {
				built = building.layers;
				building = null;
			}
			anchor.remove();   // still there when the layer came back empty
			var fresh = Array.from(host.childNodes);

			var kept = focusPath.keep(focused, oldNodes, host);
			if (kept) {
				fresh = fresh.map(node => kept.swaps.get(node) || node);
				layer.swapNodes(kept.swaps);
			} else {
				fresh.forEach(node => positionNode.parentNode.insertBefore(node, positionNode));
				focusPath.release(focused);
			}

			// what appendDomObjects() and removeOutRootNodes() do with the
			// position: a layer with nodes needs no placeholder, an empty one does
			if (layer.rootNodes.length) {
				if (layer.parent) {
					layer.parent.removePlaceholder(positionNode);
					positionNode === layer.ownPlaceholder && positionNode.remove();
				}
			} else if (layer.ownPlaceholder && !layer.ownPlaceholder.isConnected && positionNode.parentNode) {
				positionNode.parentNode.insertBefore(layer.ownPlaceholder, positionNode);
			}
			oldNodes.forEach(node => {
				if (!kept || !kept.chain.includes(node)) {
					node.remove();
				}
			});

			sys.initEvents($(fresh), undefined, kept ? kept.chain : null);
			built.forEach(each => each.runScripts());
			layer.applyScrollOffsets(offsets || []);
		};

		/** References to the new nodes that were dropped for kept ones. */
		layer.swapNodes = swaps => {
			var swap = node => swaps.get(node) || node;
			layer.rootNodes = layer.rootNodes.map(swap);
			layer.placeholders = layer.placeholders.map(swap);
			layer.ownPlaceholder = layer.ownPlaceholder && swap(layer.ownPlaceholder);
			var docs = layer.docNodes || {};
			Object.keys(docs).forEach(name => {
				docs[name] = Array.isArray(docs[name]) ? docs[name].map(swap) : swap(docs[name]);
			});
			layer.children.forEach(child => child.swapNodes(swaps));
		};

		layer.createChildren = layerData => {
			if (layer.placeholders.length !== layerData.childLayers.length) {
				l('length does not match', layer.placeholders.length, layerData.childLayers);
				return false;
			}
			for (var i in layer.placeholders) {
				var childLayer = new sys.core.dom.layer.__class();
				layer.children.push(childLayer);
				childLayer.parent = layer;
				childLayer.ownPlaceholder = layer.placeholders[i];
				childLayer.createDomObjects(layerData.childLayers[i]);
				childLayer.addToLibnode();
				childLayer.appendDomObjects(layer.placeholders[i]);
				childLayer.createChildren(layerData.childLayers[i]);
			}
			return i; // debug only

		};

		/* *************** DEBUG ***************** */
		layer.printDebug = () => {
			if (window.sysdebug) {
				sysdebug.getPrompt('domLayersClient', 'boxTree').update({
					events: [{values: [{type: 'boxTree', content: layer.debugNode()}]}]
				});
			}
		};

		/**
		 * This layer and its children as box-tree data - the format the
		 * server tree is in too (DomRenderer::debug(), Debug\BoxTree), drawn
		 * by tools/debug/js/boxTreePrompt.js. The marks are the same classes
		 * the server sets, so css/promptDomLayers.css styles both alike.
		 */
		layer.debugNode = () => ({
			label: layer.label(),
			marks: [
				'layer',
				layer.data.type || 'new',
				layer.data.source === 'page' ? 'source-page' : null,
				// an insertion point that stayed empty - see DomRenderer::debugNode()
				layer.data.result === false ? 'placeholder' : null
			].filter(mark => mark),
			info: layer.infoData(),
			children: layer.children.map(child => child.debugNode()),
			// js only, not in the JSON copy: hovering a header outlines the
			// layer's dom nodes in the page, pinning keeps them outlined
			hooks: {
				show: layer.debugShowView,
				hide: layer.debugRestore,
				dump: layer.debugDump
			}
		});

		layer.elementRootNodes = () => {
			return layer.rootNodes.filter(node => {
				return node.nodeType === Node.ELEMENT_NODE;
			});
		};

		/**
		 * How this layer reads in the debug tree: 'kontakt:main', 'cms:body'.
		 * Php builds the same string in DomLayer::debugLabel() - the two
		 * trees come from different data and have to name a layer alike.
		 *
		 * Used to be called address(), which is what php called the chain of
		 * ALL ancestors. That chain is gone: the nesting already shows it.
		 */
		layer.label = () => {
			// debugName is the readable identity (':home', 'ted_atkinson:kuenstler');
			// moduleName is the raw layer identity and stays the fallback, for
			// layers that never opened and for payloads from an older server.
			// No view name means no include was called - inline html, nothing
			// that could be re-rendered on its own. Same marker as php's
			// DomLayer::VIEW_INLINE. Appended as a text node, so no escaping.
			// debugView is resolved by the server (DomLayer::debugView): the
			// capture root shows its method, an inline layer the marker. The
			// fallbacks are for payloads from a server that predates it.
			return (layer.data.debugName || layer.data.moduleName)
				+ ':' + (layer.data.debugView || layer.data.viewName || '</>');
		};

		/**
		 * The hover table of this layer - plain values only, it goes into
		 * the JSON copy: dom nodes are named by their tag instead of being
		 * handed over.
		 */
		layer.infoData = () => {
			var tag = node => node ? '<' + (node.nodeName || '?').toLowerCase() + '>' : null;
			return obj.extend({
				rootNodes: layer.rootNodes.length,
				elementRootNodes: layer.elementRootNodes().length,
				placeholders: layer.placeholders.length,
				children: layer.children.length,
				ownPlaceholder: tag(layer.ownPlaceholder),
				placeholderInDOM: layer.ownPlaceholder
					? (tag(layer.ownPlaceholder.parentNode) || 'detached')
					: 'no Placeholder',
				'-------------': '--------------'
			}, layer.data);
		};

		layer.debugShowView = () => {
			if (window.sysdebug && sysdebug.domElementOverlay) {
				sysdebug.domElementOverlay.show({
					root:        layer.rootNodes,
					placeholder: layer.ownPlaceholder
				});
			}
		};

		layer.debugRestore = () => {
			if (window.sysdebug && sysdebug.domElementOverlay) {
				sysdebug.domElementOverlay.hide();
			}
		};

		layer.debugDump = e => {
			e.stopPropagation();
			l(layer.rootNodes);
		};

	});
})();