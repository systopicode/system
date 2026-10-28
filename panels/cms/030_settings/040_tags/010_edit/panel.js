/* global sys,c,p,t,l,document */
(function () {
	var setup = {
		info: 'Edit Tags class extends basePanelClass', // debug,
		route: 'panels.cms.settings.tags.edit',
		extends: 'panels.cms',
	};

	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		var edit = this; // internal instance name
		var safeNameSettings = sys.singletons.str.__data.safeNameSettings;
		var allowedStrings = new RegExp('^[' + safeNameSettings.allowedChars + ']*$');
		var forbiddenChars = new RegExp('[^' + safeNameSettings.allowedChars + ']');

		edit.onUpdateLightbox = doc => {
			edit.tagdata = edit.__libnode.__data.tag;
			doc.box.onclick = (e) => {
				e.stopPropagation();
			};
			// edit.doc.ok.onclick = edit.ok;
		};

		edit.ok = () => {
			if (edit.renameActive) {
				edit.updateName().done(response => {
					sys.core.server.sendGet('.././');
				});
			} else {
				sys.core.server.sendGet('.././');
			}
		};

		edit.close = () => {
			sys.core.server.sendGet('.././');
		};

		edit.removeBrand = event => {
			event.element.parentNode.remove();
			sys.core.server.sendEventCall(event).done(() => {
				// n�
			});
		};

		edit.updateName = () => {
			var ondone, chain = {
				done: func => {
					ondone = func;
				}
			};
			sys.core.server.sendCall({
				name: edit.doc.tagname.textContent
			}, 'rename?id=' + edit.tagdata.id).done(response => {
				if (ondone) {
					ondone(response);
				}
			});
			return chain;
		};

		edit.finishRename = (save) => {
			var h1 = edit.doc.tagname;
			if (!h1 || h1.getAttribute('contenteditable') === 'false' || !h1.hasAttribute('contenteditable')) {
				return;
			}
			var name = h1.textContent;
			if (h1.renameStop) {
				document.removeEventListener('pointerdown', h1.renameStop, true);
				document.removeEventListener('keydown', h1.renameStop, true);
				h1.renameStop = null;
			}
			h1.removeAttribute('contenteditable');
			if (!save) {
				h1.textContent = edit.oldName;
				return;
			}
			if (name !== edit.oldName) {
				edit.updateName();
			}
		};

		edit.rename = () => {
			if (edit.renameClosed) {
				edit.renameClosed = false;
				return;
			}
			var h1 = edit.doc.tagname;
			if (h1.getAttribute('contenteditable') && h1.getAttribute('contenteditable') !== 'false') {
				edit.finishRename(true);
				return;
			}
			edit.oldName = h1.textContent;
			h1.setAttribute('contenteditable', 'plaintext-only');
			var stop = (e) => {
				if (e.type === 'keydown') {
					if (e.key !== 'Enter' && e.key !== 'Escape') {
						return;
					}
					e.preventDefault();
					edit.finishRename(e.key === 'Enter');
					return;
				}
				if (h1.contains(e.target)) {
					return;
				}
				edit.renameClosed = true;
				edit.finishRename(true);
			};
			h1.renameStop = stop;
			document.addEventListener('pointerdown', stop, true);
			document.addEventListener('keydown', stop, true);
			h1.focus();
			selectElementContents(h1);
		};

	});
})();
