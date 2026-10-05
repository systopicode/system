/* global sys,c,p,t,l,obj */


sys.core.event.pathItem = function (element, event) {
	var pathItem = this;
	var maxlevel = 50;
	var l = () => void 0;

	pathItem.element = element;

	pathItem.defaultData = {
		tag: element && element.tagName ? element.tagName : '',
		get: {},
		post: {},
		form: {},
		dataset: {},
		attrs: {},
		values: {},
		html: null,
		method: null,
		files: null,
		render: []
	};

	pathItem.construct = () => {
		if (!element || !element.tagName || element.tagName === 'HTML' || !maxlevel--) return;
		var parent = element.parentElement || element.parentNode;
		if (!parent || parent.nodeType !== 1) return;
		pathItem.parent = new sys.core.event.pathItem(parent, event);
	};

	pathItem.getData = () => {
		if (pathItem.parent) {
			// walk up recursive to html element
			return obj.extendIfNotEmpty(pathItem.parent.getData(), pathItem.getElementData(element));
		} else {
			return pathItem.getElementData(element);
		}
	};

	pathItem.getElementData = function (element) {
		if (!element || !element.tagName) {
			return obj.clone(pathItem.defaultData);
		}
		var elementData = obj.clone(pathItem.defaultData);
		elementData.tag = element.tagName;
		elementData.dataset = obj.clone((pathItem.element && pathItem.element.dataset) || element.dataset || {});
		// elementData.method = 'put'; // nöoverrides a href
		switch (element.tagName) {
			case 'FORM':
				var formData = pathItem.getFormData(element);
				var formMethod = (element.getAttribute('method') || '').toLowerCase();
				if (formMethod === 'get') {
					elementData.method = 'get';
					elementData.get = formData.get;
				} else if (formMethod === 'post') {
					elementData.method = 'post';
					elementData.post = formData.post;
					elementData.files = formData.files;
				} else {
					// missing or method="put" (alias) → PUT, fields in form
					elementData.method = 'put';
					elementData.form = formData.form || formData.post || {};
					elementData.files = formData.files;
				}
				if (element.getAttribute('action')) {
					// form.action can also be name of a form item
					elementData.dataset.on_submit = element.getAttribute('action');
				}
				break;

			case 'LABEL':
				// checkbox triggered by label content
				var formElements = element.getElementsByTagName('INPUT');
				if (formElements.length) {
					elementData.values = pathItem.getInputData(formElements[0]);
				}
				break;
			case 'INPUT':
			case 'BUTTON':
			case 'TEXTAREA':
			case 'SELECT':
				if (element.form) {
					var inputMethod = (element.form.getAttribute('method') || '').toLowerCase();
					if (inputMethod === 'get') {
						elementData.get = pathItem.getInputData(element);
					} else if (inputMethod === 'post') {
						elementData.post = pathItem.getInputData(element);
						if (element.files) {
							elementData.files = element.files;
							l('element.files.constructor.name:' + element.files.constructor.name);
						}
					} else {
						// put or missing method — same bucket FORM copies to event.data.form
						elementData.form = pathItem.getInputData(element);
						if (element.files) {
							elementData.files = element.files;
							l('element.files.constructor.name:' + element.files.constructor.name);
						}
					}
				} else {
					elementData.values = pathItem.getInputData(element);
				}
				break;

			case 'HTML':
				// elementData.dataset.on_drop = location.href;
				elementData.get = pathItem.getQuery(location.href);
				// getQuery macht probleme bei get requests - id bleibt in der url ...
				break;

			case 'A':
				l('>>> pathItem found anchor');
				var href = element.getAttribute('href');
				if (href) {
					elementData.attrs.href = href;
					if (!element.dataset.on_click) {
						elementData.dataset.on_click = href; // ??
						elementData.get = pathItem.getQuery(href);
						if (!element.className.match(/\bput\b/) && event.type === 'click') {
							elementData.method = 'get';
							// 2023-02 indented in href{} block due to contextmenu ubdateItemvalue should not change url
						} else {
							l('deprecated: classname put OR event type');
						}
					}
				}
				break;
			case undefined:
				// avoid erroro e.g @radio node list
				l('undefined tagName', element);
				return {};
		}
		if (elementData.dataset.href && !elementData.dataset.on_click) {
			// e.g. DIV behaving like Anchor ( exept onclick ist defined )
			elementData.dataset.on_click = elementData.dataset.href;
			elementData.get = pathItem.getQuery(elementData.dataset.href);
			elementData.method = 'get';
		}
		if (element.getAttribute('contenteditable')) {
			elementData.html = element.innerHTML;
		}
		l('>>> pathItem.getElementData from ' + element.tagName, elementData);
		return elementData;
	};

	pathItem.getQuery = href => {
		var hrefInfo = href.match(/^((https?:\/\/)([^\/]*)|)(.*\/|)(.*?)(\?(.*?)|)(#(.*?)|)$/);
		return sys.http.parseQuery(hrefInfo[7]);
	};

	pathItem.getInputData = function (element) {
		switch (element.type) {
			case 'checkbox':
				return {
					[element.name]: element.checked ? 1 : 0
				};
			default:
				return {
					[element.name]: element.value
				};
		}
	};

	pathItem.getFormData = function (form) {
		var formData = {};
		for (var index in form.elements) {
			if (typeof form.elements[index] !== 'object') continue;
			var el = form.elements[index];
			var data = pathItem.getElementData(el);
			if (data.files && data.files.length) {
				formData.files = formData.files || {};
				for (var i = 0; i < data.files.length; i++) {
					var key = data.files.length > 1 ? el.name + '[' + i + ']' : el.name;
					formData.files[key] = data.files[i];
				}
			}
			delete data.files; /* do not extend empty FileList – has non-Blob props (item, length) */
			obj.extend(formData, data);
		}
		// contenteditable can be used like regular form elements // data-name must contain name
		form.querySelectorAll('[contenteditable]').forEach(element => {
			if (element.dataset.name) {
				var contentMethod = (form.getAttribute('method') || '').toLowerCase();
				if (contentMethod === 'get') {
					formData.get = formData.get || {};
					formData.get[element.dataset.name] = element.innerHTML;
				} else if (contentMethod === 'post') {
					formData.post = formData.post || {};
					formData.post[element.dataset.name] = element.innerHTML;
				} else {
					formData.form = formData.form || {};
					formData.form[element.dataset.name] = element.innerHTML;
				}
			}
		});
		return formData;
	};
	// construct
	pathItem.construct();
};