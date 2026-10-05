/* global sys,c,p,t,l,history,location,getComputedStyle,parseInt,parseFloat */
(() => {
	var setup = {
		route: 'singletons.dom.editable',
		shorthand: 'editable',
		icons: {}
	};

	sys.lib.add(setup, function () {
		var editables = this;
		var items = new Map();
		var lastItemCreated;
		editables.create = conf => {
			var item;
			if (items.has(conf.element)) {
				item = items.get(conf.element);
				// update conf
				return;
			} else {
				item = new editable(conf);
				item.prev = lastItemCreated;
				if (lastItemCreated) {
					lastItemCreated.next = item;
				}
				lastItemCreated = item;
				items.set(conf.element, item);
			}
			return item;
		};

		editables.get = element => {
			return items.get(element);
		};

		var editable = function (conf) {
			var editable = this;
			var data = {}; // additional data sent to server ->use data({}) method
			var confirmcontrols; // check, cross, enter, escape active
			var confirmed; // flag if confirm was called
			var confirm_is_prevented, cancel_is_prevented; // flag set onmousedown because onblur fires before onclick
			var pen, check, cross, plus, minus; // dom elements with events
			// all values must be strings 
			var activateValue, inputValue, validValue;
			var root = conf.element;
			var el;
			var display; /* inline od block */
			if (!root) {
				return;
			}

			root.classList.add('editable');

			var defaultConf = {
				type: ['varchar', String],
				maxlength: [null, parseInt],
				min: [null, parseFloat],
				max: [null, parseFloat],
				decimals: [null, parseInt], // decimals to display
				precision: [null, parseInt], // decimals to round value to // not used 
				increment: [1, parseInt],
				allowedChars: [null, String]
			};

			for (var key in defaultConf) {
				if (!conf[key]) {
					if (root.dataset[key]) {
						// write to conf and set type
						conf[key] = defaultConf[key][1](root.dataset[key]);
					} else {
						conf[key] = defaultConf[key][0];
					}
				}
			}

			switch (conf.type) {
				case 'dropdown':
					root.dataset.value = root.textContent;
					root.textContent = conf.options[root.textContent];
					break;

			}

			/* *********** PUBLIC ***************** */
			var validate;

			editable.set = (value) => {
				validate(String(value)).done(() => {
					el.innerHTML = getDisplayValue(validValue);
				});
			};

			editable.value = () => {
				return validValue;
			};

			editable.element = () => {
				return root;
			};

			editable.data = (key, preset) => {
				if (key) {
					if (root.dataset[key]) {
						return root.dataset[key];
					} else {
						return (preset === undefined) ? null : preset;
					}
				}
				return root.dataset;
			};

			editable.addData = (input) => {
				if (typeof data !== 'object') {
					return;
				}
				for (var field in input) {
					root.dataset[field] = input[field];
				}
			};
			
			editable.activate = () => {
				activate();
			};

			/* *********** FUNCS ***************** */

			validate = value => {
				// restore invalid input and change detection
				var lastInputValue = value;
				var lastValidValue = validValue;
				// check type
				if (typeof value !== 'string') {
					l('validate expects string');
					return;
				}
				switch (conf.type) {
					case 'int':
						if (value.match(/^\-?\d*$/)) {
							inputValue = value;
							if (value.match(/^\-?\d+$/)) {
								value = parseInt(value);
								validValue = minmax(value);
							}
						} else { // ignore invalid inputs
							// inputValue = lastInputValue;
						}
						break;
					case 'float':
						if (value.match(/^\-?\d*\.?\d*$/)) {
							inputValue = value;
							if (value.match(/^\-?(\d+|\d*\.\d+)$/)) {
								value = parseFloat(value);
								validValue = minmax(value);
							}
						} else { // ignore invalid inputs
							// inputValue = lastInputValue;
						}
						break;
					case 'text':
					case 'varchar':
						if (conf.maxlength && value.length > conf.maxlength) {
							value = value.substr(0, conf.maxlength);
						}
						if (conf.allowedChars) {
							var allowedStrings = new RegExp('^[' + conf.allowedChars + ']*$');
							var forbiddenChars = new RegExp('[^' + conf.allowedChars + ']');
							var maxloop = 1000;
							while (maxloop-- && !allowedStrings.test(value)) {
								value = value.replace(forbiddenChars, '_');
							}
							if (maxloop === -1) {
								l('VALIDATION ERROR MAXLOOP');
							}
						} else {
							// all strings allowed
						}
						validValue = value;
						inputValue = value;
						break;
				}

				var validation = {
					validValue: validValue,
					inputValue: inputValue
				};
				validation.oninput = func => {
					if (inputValue !== lastInputValue) {
						func(validation);
					}
					return validation;
				};
				validation.onchange = func => {
					if (validValue !== lastValidValue) {
						func(validation);
					}
					return validation;
				};
				validation.done = func => {
					func(validation);
					return validation;
				};

				return validation;
			};
			editable.set = (value) => {
				validate(String(value)).done(() => {
					el.innerHTML = getDisplayValue(validValue);
				});
			};

			var getDisplayValue = value => {
				// 2do adding out of range Info ...
				if (conf.type === 'float') {
					var rounded = roundToDecimals(value);
					if (rounded !== value) {
						return '&#8764;' + String(rounded);
					}
				}
				return String(value);
			};

			var input = () => {
				validate(el.textContent)
						.oninput(validation => {
							var restore = saveCaretPosition(el);
							el.textContent = validation.inputValue;
							restore();
						})
						.onchange(validation => {
							callback('onchange');
						})
						;
			};

			var increment = e => {
				// e.stopPropagation();
				validate(String(~~validValue + conf.increment)).onchange(() => {
					el.textContent = getDisplayValue(validValue);
					callback('onchange');
				});
			};

			var decrement = e => {
				// e.stopPropagation();
				validate(String(~~validValue - conf.increment)).onchange(() => {
					el.textContent = getDisplayValue(validValue);
					callback('onchange');
				});
			};

			var preventconfirm = () => {
				confirm_is_prevented = true;
			};

			var confirm = () => {
				if (confirm_is_prevented) {
					confirm_is_prevented = false;
					return;
				}
				confirmed = true;
				el.removeAttribute('contenteditable'); // not working if in deactivate
				validate(el.textContent).done(() => {
					el.innerHTML = getDisplayValue(validValue);
					deactivate();
					prepare();
					callback('onconfirm');
				});
			};

			var preventcancel = () => {
				cancel_is_prevented = true;
			};

			var cancel = () => {
				if (cancel_is_prevented) {
					cancel_is_prevented = false;
					return;
				}
				el.removeAttribute('contenteditable'); // not working if in deactivate
				validate(String(activateValue)).done(() => {
					el.innerHTML = getDisplayValue(activateValue);
					deactivate();
					prepare();
					callback('oncancel');
				}).onchange(() => {
					callback('onchange');
				});
			};

			var create = () => {
				confirmcontrols = typeof conf.onconfirm !== 'undefined';
				if (confirmcontrols) {
					root.classList.add('confirmcontrols');
				}
				root.classList.add('domEditable');
				display = root.currentStyle || getComputedStyle(root, "").display;
				if (display === 'inline') {
					root.classList.add('inline');
				} else if (display === 'block') {
					root.classList.add('block');
				} else {
					l('unknown display type: ' + display);
				}
				root.classList.add(conf.type);
				el = document.createElement('span');
				el.textContent = root.textContent;
				root.textContent = '';
				root.appendChild(el);

				el.oninput = input;
				el.onkeydown = keydown;
				el.onblur = confirmcontrols ? cancel : confirm;
				createIcons();

				var activateValueString = root.dataset.value || el.textContent;
				switch (conf.type) {
					case 'int':
						activateValue = parseInt(activateValueString);
						break;
					case 'float':
						activateValue = parseFloat(activateValueString);
						break;
					default:
						activateValue = activateValueString;
						break;
				}
				validValue = activateValue;
				prepare();
			};

			var deactivate = () => {
				// removeSelection();
				cross.remove();
				check.remove();

			};

			var prepare = () => {
				el.onclick = activate;
				switch (conf.type) {
					case 'float':
					case 'text':
					case 'varchar':
						root.insertBefore(pen, el.nextSibling);
						break;
					case 'int':
						root.insertBefore(minus, el);
						root.insertBefore(plus, el.nextSibling);
						break;
					case 'dropdown':
						root.insertBefore(pen, el.nextSibling);
						let dropdown = root.querySelector('select');
						if (dropdown !== null) {
							el.textContent = dropdown.options[dropdown.selectedIndex].text;
							el.dataset.value = dropdown.options[dropdown.selectedIndex].value;
							dropdown.remove();
							send();
						}
						break;
				}

			};

			var activate = () => {
				confirmed = false;
				activateValue = validValue;
				inputValue = String(validValue);
				el.textContent = inputValue; // e.g. show additional decimals
				el.contentEditable = true;
				selectElementContents(el);
				el.onclick = null;

				switch (conf.type) {
					case 'text':
						pen.remove();
						if (confirmcontrols) {
							root.insertBefore(cross, el.nextSibling);
							root.insertBefore(check, el.nextSibling);
						}
						break;
					case 'float':
						pen.remove();
						root.insertBefore(cross, el.nextSibling);
						if (confirmcontrols) {
							root.insertBefore(check, el.nextSibling);
						}
						break;
					case 'int':
						if (confirmcontrols) {
							plus.remove();
							minus.remove();
							root.insertBefore(cross, el.nextSibling);
							root.insertBefore(check, el.nextSibling);
						}
						break;
					case 'dropdown':
						pen.remove();
						var dropdown = document.createElement("select");
						let option = document.createElement("option");
						dropdown.append(option);
						for (var item in conf.options) {
							let option = document.createElement("option");
							option.value = item;
							if (item == root.dataset.value) {
								option.setAttribute('selected', 'selected');
							}
							option.append(conf.options[item]);
							dropdown.append(option);
						}
						dropdown.setAttribute('style', 'width:' + root.offsetWidth + 'px;');
						dropdown.onchange = deactivate;
						root.prepend(dropdown);

						break;
				}
				callback('onfocus');
			};

			var createIcons = () => {
				pen = document.createElement('i');
				pen.className = 'far fa-pen editable';
				pen.onclick = activate;
				check = document.createElement('i');
				check.className = 'fas fa-check-circle editable';
				check.onclick = confirm;
				cross = document.createElement('i');
				cross.className = 'fas fa-times-circle editable';
				cross.onmousedown = preventconfirm;
				cross.onclick = cancel;
				pen = document.createElement('i');
				pen.className = 'far fa-pen editable';
				pen.onclick = activate;
				plus = document.createElement('i');
				plus.className = 'far fa-plus-circle editable';
				plus.onclick = increment;
				plus.onmousedown = e => e.preventDefault(); // skip dplckick selection
				minus = document.createElement('i');
				minus.className = 'far fa-minus-circle editable';
				minus.onclick = decrement;
				minus.onmousedown = e => e.preventDefault(); // skip dplckick selection
			};

			/* *********** INTERFACE ************ */

			var keydown = e => {
				if (e.ctrlKey || (e.shiftKey && display === 'block')) {
					return;
				}
				switch (e.keyCode) {
					case 9: // tab
						preventcancel();
						confirm();
						if (!e.shiftKey && editable.next) {
							editable.next.activate();
						}
						if (e.shiftKey && editable.prev) {
							editable.prev.activate();
						}
						return false;
					case 13:
						preventcancel();
						confirm();
						return false;
					case 27:
						cancel();
						return false;
				}
			};

			var callback = method => {
				if (typeof conf[method] === 'function') { // call js
					conf[method](editable);
				}
				if (typeof conf[method] === 'string') { // send to server
					send(method);
				}

			};

			var send = method => {
				var putData = (new sys.core.event.pathItem(el)).getData();
				putData.value = validValue;
				sys.core.server.sendCall(putData, conf[method]);
			};

			/* *********** HELPER ************ */
			var minmax = (val) => {
				if (conf.max !== null && val > conf.max) {
					val = conf.max;
				}
				if (conf.min !== null && val < conf.min) {
					val = conf.min;
				}
				return val;
			};

			var roundToDecimals = (val) => {
				if (conf.decimals !== null) {
					var val = Math.round(val * Math.pow(10, conf.decimals)) / Math.pow(10, conf.decimals);
				}
				return val;
			};

			create();
		};
	});
})();

