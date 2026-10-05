/* global sys,obj,c,p,t,l,Node,document,settings,cmsPrompt */
(function () {
	var setup = {
		info: 'domLayer Class',
		route: 'core.dom.layer'
	};
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
		layer.createRootLayer = (bodyLayerData) => {
			// lt('createRootLayer');
			layer.data = bodyLayerData.layers.data;
			bodyLayerData.html = bodyLayerData.layers.html.replace(/<(\/?)body([^>]*)>/g, '');
			var placeholderNode = document.createElement('div');
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
			for (var script of scripts) { 
				layer.scripts.push(script);
			}
			layer.scripts.forEach(script => script.remove());
			layer.rootNodes = Array.prototype.slice.call(parentNode.firstChild.childNodes);
			layer.docNodes = layer.getDocNodes(parentNode);

			layer.rootNodes.forEach(node => {
				sys.initEvents($(node));
			});

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

			// Preserve order: external scripts must finish before later inline
			// scripts run (e.g. patchwork config that needs $.fn.patchwork).
			(async () => {
				for (var deadScript of layer.scripts) {
					await new Promise(resolve => {
						var liveScript = document.createElement('script');
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