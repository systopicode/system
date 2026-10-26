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
					if (e.keyCode !== 13) {
						return;
					}
					var value = event.element.value.trim();
					if (value === '') {
						return;
					}
					event.data.render = ['main'];
					event.data.value = value;
					event.element.value = '';
					sys.core.server.sendEventCall(event);
				};
				event.element.onblur = () => {
					event.element.onkeydown = null;
					event.element.onblur = null;
				};
			}
		};

		tags.delete = event => {
			var name = (event.element && event.element.dataset.label || '').trim();
			var question = name
				? 'Delete the tag "' + name + '"? It is removed everywhere it is used.'
				: 'Delete this tag? It is removed everywhere it is used.';
			if (confirm(question)) {
				sys.core.server.sendEventCall(event);
			}
		};

		tags.deleteGroup = event => {
			if (confirm('Delete this empty group?')) {
				sys.core.server.sendEventCall(event);
			}
		};

		tags.edit = event => {
			sys.core.server.sendGet('edit/', {id: event.data.dataset.id});
		};

		tags.updateColor = event => {
			var input = Object.values(event.data.values)[0];
			if (input.match(/#[a-f0-9]{6}/i)) {
				// The widget is the div that carries data-group_id. The text
				// colour is contrast-color(var(--tag-color)), so the variable
				// has to move with the swatch or the letters stay on the old
				// colour.
				var pills = event.element.closest('[data-group_id]').querySelectorAll('div.tag');
				for (var tag of pills) {
					tag.style.setProperty('--tag-color', input);
					tag.style.backgroundColor = input;
					tag.style.borderColor = input;
				}
				sys.core.server.sendEventCall(event);
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

		tags.finishRename = (h1, save) => {
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
				h1.textContent = tags.oldName;
				return;
			}
			if (name !== tags.oldName) {
				tags.updateName(h1);
			}
		};

		tags.rename = (event) => {
			var h1 = event.element.closest('header').querySelector('h1');
			if (tags.renameClosed) {
				tags.renameClosed = false;
				return;
			}
			if (h1.getAttribute('contenteditable') && h1.getAttribute('contenteditable') !== 'false') {
				tags.finishRename(h1, true);
				return;
			}
			tags.oldName = h1.textContent;
			h1.setAttribute('contenteditable', 'plaintext-only');
			var stop = (e) => {
				if (e.type === 'keydown') {
					if (e.key !== 'Enter' && e.key !== 'Escape') {
						return;
					}
					e.preventDefault();
					tags.finishRename(h1, e.key === 'Enter');
					return;
				}
				if (h1.contains(e.target)) {
					return;
				}
				var pencil = e.target.closest && e.target.closest('.tagGroupRename');
				if (pencil && pencil.closest('header') === h1.parentElement) {
					e.preventDefault();
					tags.renameClosed = true;
				}
				tags.finishRename(h1, true);
			};
			h1.renameStop = stop;
			document.addEventListener('pointerdown', stop, true);
			document.addEventListener('keydown', stop, true);
			h1.focus();
			selectElementContents(h1);
		};

	});
})();
