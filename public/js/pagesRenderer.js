/* global sys, $, l, f, document */
/**
 * pagesRenderer — client side of the public website (DB pages).
 *
 * Counterpart of core/renderer's domRenderer, which drives the panel app.
 * Both patch the SAME layer objects (sys.core.dom.layer); the difference is
 * that an app render only ever fills document.body, while a page render has
 * to fill document.head as well — <title>, og:*, hreflang alternates all
 * change when the visitor navigates.
 *
 * The server therefore ships two layer trees (PagesRenderer::exportDocument):
 *
 *   { headLayer, bodyLayer, bodyClasses }
 *
 * and each is mounted against its own placeholder in the served skeleton:
 * <template id=systemPagesHeadPlaceholder> in the head, and
 * <div id=systemPagesBodyPlaceholder> in the body.
 *
 * The shorthand is `siteRenderer` because that is the name
 * core/server/server.command.js already dispatches to when settings.inApp is
 * false. Replaces routeRenderer.js + routeLayer.js.
 */
(function () {
	var setup = {
		info: 'pagesRenderer — public site (DB pages)',
		route: 'singletons.pages.renderer',
		shorthand: 'siteRenderer'
	};

	sys.lib.add(setup, function () {
		var renderer = this;

		// Root layer objects, kept across requests. Their presence is what
		// distinguishes the first paint from a diff update.
		var headLayer = null;
		var bodyLayer = null;

		/**
		 * Entry point. Called once after the first page load (via the
		 * onLibReady hook that PagesRenderer::clientData_export declares) and
		 * again for every AJAX response carrying an updateDocument command.
		 */
		renderer.updateDocument = () => {
			var data = sys.singletons.pages.renderer.__data;
			if (!data) {
				f('pagesRenderer: no layer data — is PagesRenderer registered in clientInterfaces?');
				return;
			}

			// same as in domRenderer - see public/js/core/focus.js
			var focus = window.focusKeeper ? focusKeeper.capture() : null;

			renderer.applyBodyClasses(data.bodyClasses || []);

			if (data.headLayer) {
				headLayer = renderer.mount(headLayer, data.headLayer, 'systemPagesHeadPlaceholder', document.head);
			}
			if (data.bodyLayer) {
				bodyLayer = renderer.mount(bodyLayer, data.bodyLayer, 'systemPagesBodyPlaceholder', document.body);
			}

			// Expose under the same names the panel client uses, so shared
			// debug tooling and anything reaching for sys.dom.body keeps working.
			sys.dom.head = headLayer;
			sys.dom.body = bodyLayer;

			focus && focusKeeper.restore(focus);

			if (bodyLayer) {
				bodyLayer.printDebug();
			}
			sys.flushOnDocumentReady();
		};

		/**
		 * First call builds the layer from scratch against its placeholder;
		 * every later call diffs into the existing layer objects.
		 */
		renderer.mount = (layer, layerData, placeholderId, host) => {
			if (layer) {
				renderer.updateLayer(layerData, layer);
				return layer;
			}

			var placeholder = document.getElementById(placeholderId);
			if (!placeholder) {
				// No skeleton placeholder (e.g. a page served before this
				// script existed). Create an anchor so the layer still has a
				// stable position to render at and to collapse back into.
				placeholder = document.createElement('div');
				placeholder.id = placeholderId;
				host.appendChild(placeholder);
			}

			layer = new sys.core.dom.layer.__class();
			layer.ownPlaceholder = placeholder;
			layer.createDomObjects(layerData);
			layer.addToLibnode();
			layer.appendDomObjects(placeholder);
			layer.createChildren(layerData);
			// The placeholder deliberately stays in the DOM: it is this
			// layer's anchor (ownPlaceholder) and is needed to position the
			// content again if the layer ever renders empty.
			return layer;
		};

		/**
		 * Applies the server's body classes, removing the ones the previous
		 * render added. Same contract as domRenderer.updateDocument.
		 */
		renderer.applyBodyClasses = bodyClasses => {
			var proto = sys.core.dom.layer.__class.prototype;
			var previous = proto.bodyClasses || [];
			previous.forEach(className => {
				if (className) { document.body.classList.remove(className); }
			});
			bodyClasses.forEach(className => {
				if (className) { document.body.classList.add(className); }
			});
			proto.bodyClasses = bodyClasses;
		};

		/**
		 * Walks a layer tree and applies the server's diff decision.
		 *
		 *   new / update → rebuild this subtree from the shipped HTML
		 *   keep         → keep the DOM, descend into the children
		 *
		 * Two behaviours, three names: 'update' is a 'keep' whose owner asked
		 * to be re-rendered anyway (PageNode::updateView), and telling the two
		 * apart is worth it in the debug tree even though the client does the
		 * same thing with both.
		 */
		renderer.updateLayer = (layerData, layer) => {
			if (!layerData || !layerData.data) {
				f('pagesRenderer.updateLayer: no layer data', layer && layer.label ? layer.label() : '?');
				return;
			}

			// Take the fresh data even when nothing is rebuilt below. Only
			// createDomObjects() used to assign it, and that runs on the
			// rebuild path alone - so a layer that keeps its dom kept the
			// data of the render that last BUILT it. Every 'keep' layer
			// therefore still reported the 'new' it was on the initial load,
			// which is what the debug tree showed. Nothing reads it on this
			// path, but the debug tree does, and it should not lie.
			layer.data = layerData.data;

			var type = layerData.data.type;
			if (type === 'new' || type === 'update') {
				renderer.rebuild(layerData, layer);
				return;
			}

			var newCount = layerData.childLayers.length;
			var oldCount = layer.children.length;

			// Child count changed: the positional walk below would misalign,
			// so fall back to rebuilding from this layer's own HTML. The
			// server always exports it, so this is always possible.
			if (newCount !== oldCount) {
				l('pagesRenderer: child count changed at ' + layer.label() + ' (' + oldCount + ' -> ' + newCount + ') — rebuilding');
				renderer.rebuild(layerData, layer);
				return;
			}

			for (var index = 0; index < newCount; index++) {
				if (layer.children[index] === undefined) {
					l('pagesRenderer.updateLayer: missing old child at index ' + index + ' in ' + layer.label());
					break;
				}
				renderer.updateLayer(layerData.childLayers[index], layer.children[index]);
			}
		};

		/** Replaces a layer's DOM subtree with freshly built nodes. */
		renderer.rebuild = (layerData, layer) => {
			var positionNode = layer.shiftOutRootNodes();
			layer.createDomObjects(layerData);
			layer.addToLibnode();
			layer.appendDomObjects(positionNode);
			layer.removeOutRootNodes();
			layer.children.forEach(child => child.removeRootNodes());
			layer.children = [];
			layer.createChildren(layerData);
		};
	});
})();
