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
				// nö
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

		edit.rename = () => {
			edit.renameActive = true;
			edit.oldName = edit.doc.tagname.textContent;
			edit.doc.tagname.setAttribute('contenteditable', true);
			selectElementContents(edit.doc.tagname);
			edit.doc.tagname.onkeydown = (e) => {
				switch (e.keyCode) {
					case 13:
						edit.doc.tagname.removeAttribute('contenteditable');
						edit.updateName().done(response => {
						});
						return false;
					case 27:
						edit.doc.tagname.removeAttribute('contenteditable');
						edit.doc.tagname.textContent = edit.oldName;
						return false;
				}
			};

		};

	});
})();
