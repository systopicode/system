/* global sys,c,p,t,l,Node,document */
(function () {
	var setup = {
		info: 'domRenderer Base Class',
		route: 'singletons.dom.renderer',
		shorthand: 'renderer'
	};

	sys.lib.add(setup, function () {
		var renderer = this;
		
		renderer.updateDocument = () => {
			// lt('updateDocument');
			// A rebuilt layer keeps the focused element in the document - see
			// layer.rebuild() in public/js/core/domLayer.js.
			var rootLayerData = sys.singletons.dom.renderer.__data.layers;
			var bodyClasses = sys.singletons.dom.renderer.__data.bodyClasses || [];
			// prototype.bodyClasses may be undefined on first AJAX call from a non-slices page
			var prevBodyClasses = sys.core.dom.layer.__class.prototype.bodyClasses || [];
			prevBodyClasses.forEach(className => {
				document.body.classList.remove(className);
			});
			bodyClasses.forEach(className => {
				document.body.classList.add(className);
			});
			sys.core.dom.layer.__class.prototype.bodyClasses = bodyClasses;
			renderer.updateLayer(rootLayerData, sys.dom.body);
			sys.dom.body.printDebug();
			// for what lives outside the layers (menus parked on the body)
			document.dispatchEvent(new CustomEvent('cms:rendered'));
		};

		renderer.updateLayer = (layerData, layer) => {
			if (!layerData.data) {
				f('updateLayer failed - no layer data', layer.label());
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
			if (layerData.data.type === 'new' || layerData.data.type === 'update') { // create new branch
				// l('updateLayer',layer.label());
				layer.rebuild(layerData);
			} else { // keep: patch the children in place
				// l('skip updateLayer:',layer.label());
				// l(layerData);
				var newCount = layerData.childLayers.length;
				var oldCount = layer.children.length;

				// Child-count changed: use the layer's own HTML (always present in PHP export)
				// to do a full re-render rather than trying to patch by index.
				if (newCount !== oldCount && layerData.html != null) {
					layer.rebuild(layerData);
					return;
				}

				for (var index = 0; index < newCount; index++) {
					if (layer.children[index] === undefined) {
						// Guard: new child has no old counterpart (shouldn't happen after the check above)
						l('updateLayer: missing old child at index ' + index + ' in layer ' + layer.label());
						break;
					}
					renderer.updateLayer(layerData.childLayers[index], layer.children[index]);
				}
				// Remove orphaned old children that have no counterpart in the new structure
				for (var i = newCount; i < layer.children.length; i++) {
					layer.children[i].removeRootNodes();
				}
				if (newCount < layer.children.length) {
					layer.children.length = newCount;
				}
			}
		};

	});

})();