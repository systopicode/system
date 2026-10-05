/* global sys,c,p,t,l */
(function () {
	var setup = {
		info: 'Tags panel class extends cmsPanel class', // debug,
		route: 'panels.cms.settings.tags',
		extends: 'panels.cms',
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		var tags = this; // internal instance name
		var safeNameSettings = sys.singletons.str.__data.safeNameSettings;
		var allowedStrings = new RegExp('^[' + safeNameSettings.allowedChars + ']*$');
		var forbiddenChars = new RegExp('[^' + safeNameSettings.allowedChars + ']');

		var tagsParentUpdate = tags.update;
		tags.update = () => {
			tagsParentUpdate();
			if (tags.doc.button && tags.doc.close) {
				tags.doc.button.onclick = tags.closeLightbox;
				tags.doc.close.onclick = tags.closeLightbox;
			}
		};

		tags.closeLightbox = () => {
			sys.core.server.sendCall({}, 'close');
		};

		tags.insertInto = (event) => {
			l(event);
		};

		tags.addGroup = (event) => {
			event.data.render = ['main'];
			sys.core.server.sendEventCall(event);
		};

		tags.editLabel = event => {
			if (event.element.contentEditable === 'inherit') {
				event.element.contentEditable = true;
				event.element.focus();
				tags.selectElementContents(event.element);
				event.element.onblur = () => {
					event.element.contentEditable = 'inherit';
				}
			}
		}

		tags.enterNewTag = event => {
			if (!event.element.onkeydown) {
				event.element.onkeydown = (e) => {
					if (e.keyCode === 13) {
						event.data.render = ['main'];
						event.data.value = event.element.value;
						sys.core.server.sendEventCall(event);
					}
				};
				event.element.onblur = () => {
					event.element.onkeydown = null;
					event.element.onblur = null;
				};
			}
		};

		tags.delete = event => {
			if (confirm('remove Tag')) {
				sys.core.server.sendEventCall(event);
			}
		};

		tags.edit = event => {
			sys.core.server.sendGet('edit/', {id: event.data.dataset.id});
		};

		tags.updateColor = event => {
			var input = Object.values(event.data.values)[0];
			if (input.match(/#[a-f0-9]{6}/i)) {
				// Das Widget wird ueber sein data-group_id gefunden, nicht ueber
				// den Tag-Namen: gerendert wird ein div, nicht eine section.
				// closest('section') landete deshalb bei section.cms und faerbte
				// die Tags *aller* Gruppen um.
				var tags = event.element.closest('[data-group_id]').querySelectorAll('div.tag');
				for (var tag of tags) {
					tag.style.backgroundColor = input;
					tag.style.borderColor = input;
					var count = tag.getElementsByTagName('number');
					if (count.length) {
						count[0].style.color = input;
					}

				}
				sys.core.server.sendEventCall(event)
			}

		};

		tags.triggerRemoteUpdate = event => {
			l('triggerRemoteUpdate');
			sys.core.server.sendEventCall(event).done(answer => {
				tags.doc.updateAnswer.innerHTML = answer.body;
				tags.doc.updateHeader.textContent = answer.header;

				l();
			});
		};

		tags.join = event => {
			sys.core.server.sendGet('join/', {
				id: event.dragEvent.data.dataset.id,
				into: event.data.dataset.id
			});
		};

		tags.updateName = (h1) => {
			var ondone, chain = {
				done: func => {
					ondone = func;
				}
			};
			// Siehe updateColor: das Widget ist ein div mit data-group_id, keine
			// section. Mit closest('section') landete man bei section.cms, deren
			// dataset kein group_id hat — die id war also immer undefined und
			// das Umbenennen hat nie funktioniert.
			var widget = h1.closest('[data-group_id]');
			sys.core.server.sendCall({
				group_id: widget.dataset.group_id,
				name: h1.textContent
			}, 'updateGroupLabel').done(response => {
				p(response);
				if (ondone) {
					ondone(response);
				}
			})
			return chain;
		};

		tags.rename = (event) => {
			tags.renameActive = true;
			var h1 = event.element.closest('header').getElementsByTagName('h1')[0];
			tags.oldName = h1.textContent;
			h1.setAttribute("contenteditable", "true");

			selectElementContents(h1);
			h1.onkeydown = (e) => {
				c(e.keyCode);
				switch (e.keyCode) {
					case 13:
						h1.removeAttribute('contenteditable');
						tags.updateName(h1).done(response => {
							p(response);
						});
						return false;
					case 27:
						h1.removeAttribute('contenteditable');
						h1.textContent = tags.oldName;
						return false;
				}
			};

			h1.oninput = (e) => {
				return;
				if (h1.textContent.length > 50) {
					var restore = saveCaretPosition(h1);
					h1.textContent = h1.textContent.substr(0, 99);
					restore();
				}
				while (!allowedStrings.test(h1.textContent)) {
					var restore = saveCaretPosition(h1);
					h1.textContent = h1.textContent.replace(forbiddenChars, '_');
					restore();
				}
			};
		};

	});
})();
