function setFocus(selector) {
	var el = document.querySelectorAll(selector)[0];
	let len = el.value.length;
	el.focus();
	el.setSelectionRange(len, len);
}

function minmax(min, value, max) {
	return Math.min(Math.max(value, min), max);
}

function inArray(value, array) {
	var inArray = false;
	for (var key in array) {
		if (array[key] === value) {
			inArray = true;
			break;
		}
	}
	return inArray;
}

function printPercent(value) {
	return Math.floor(100000 * value) / 1000 + '%';
}

function str_pad(num, size, pad_string) {
	pad_string = pad_string || '0';
	var s = num + "";
	while (s.length < size)
		s = pad_string + s;
	return s;
}

function ucfirst(string) {
	return string.charAt(0).toUpperCase() + string.slice(1);
}

function getRandom(min, max) {
	return Math.floor(Math.random() * (max - min)) + min;
}

function empty(e) {
	switch (e) {
		case "":
		case 0:
		case "0":
		case null:
		case false:
		case typeof this == "undefined":
			return true;
		default :
			return false;
	}
}

function isset() {
	var a = arguments,
			l = a.length,
			i = 0,
			undef;
	if (l === 0) {
		p('Empty isset');
	}

	while (i !== l) {
		if (a[i] === undef || a[i] === null) {
			return false;
		}
		i++;
	}
	return true;
}

function exists(element) {
	if (typeof (element) !== 'undefined' && element != null) {
		return true;
	} else {
		return false;
	}
}

function setAttributes(selector, data) {
	var attributeValues = $.parseJSON(data);
	for (var attributeName in attributeValues) {
		$(selector).attr(attributeName, attributeValues[attributeName]);
	}
}

function showMessage(message, type, target, element) {
	type = type || '';
	target = target || 'page';
	if (!empty(message)) {
		switch (target) {
			case 'header':
				$("main > div.content > header dialog ul").prepend("<li>" + message + "</li>");
				$("#nprogress").addClass(type);
				break;
			default:

				if ($('dialog.page').length) {
				} else {
					$('body.cms').prepend('<dialog open class="page cms"></div>');
				}

				$('dialog.page').prepend("<h2 class='" + type + "'>" + message + "<i class='close filled'>Close</i></span></h2>");
				$('dialog.page').animate({
					height: '34px'
				});
				$('dialog.page h2').show({
					effect: 'blind'
				});
				$('#nprogress').addClass(type);

				$('dialog.page .close').click(function () {
					$("dialog.page").animate({
						height: "0"
					}).remove();
				});
				$('dialog.page').dblclick(function () {
					$("dialog.page").animate({
						height: "0"
					}).remove();
				});


				break;
		}
	}
}


///****OFFSCREEN****/////
/*(function ($) {
 $.extend($.expr[':'], {
 'off-top': function (el) {
 return $(el).offset().top < $(window).scrollTop();
 },
 'off-right': function (el) {
 return $(el).offset().left + $(el).outerWidth() - $(window).scrollLeft() > $(window).width();
 },
 'off-bottom': function (el) {
 return $(el).offset().top + $(el).outerHeight() - $(window).scrollTop() > $(window).height();
 },
 'off-left': function (el) {
 return $(el).offset().left < $(window).scrollLeft();
 }
 });
 })(jQuery); */

function reload(delay) {
	delay = delay || 0;
	window.setTimeout(function () {
		var nohasch = window.location.pathname;
		if (window.location == nohasch) {
			window.location.reload();
		} else {
			window.location = nohasch;
		}
	}, delay * 1000);
}


function redirect(url) {
	window.location = url;
}


function getSelText() {
	var text = "";
	if (window.getSelection) {
		text = window.getSelection().toString();
	} else if (document.selection && document.selection.type != "Control") {
		text = document.selection.createRange().text;
	}
	return text;
}

function showLoading(show, target, element) {
	element = element || '';
	if (!isset(target)) {
		target = 'body';
	}
	if (show) {
		if (target == "body") {
			$("body").append("<div class='loading'></div>");
		} else if (target == "fileUpload") {
			if (element.prop("tagName") == "LI") {
				$(".dz-started").append('<div class="spinner"></div>');
			} else {
				$(".dz-started").after('<div class="spinner"></div>');
			}
		} else {
			$(target).append("<div class='loading spinner'><div></div></div>");
		}
	} else {
		$(".loading").fadeOut("fast", function () {
			$(".loading").remove();
		});
	}

}

function rgb2hex(rgbCSSval) {
	var parts = rgbCSSval.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
	delete (parts[0]);
	for (var i = 1; i <= 3; ++i) {
		parts[i] = parseInt(parts[i]).toString(16);
		if (parts[i].length === 1) {
			parts[i] = '0' + parts[i];
		}
	}
	return '#' + parts.join('');
}

function getHash() {
	var loc = window.location.href;
	var pos = loc.indexOf('#');
	return pos === -1 ? false : loc.substr(pos);
}

function fillForm($form, data) {
	$.each(data, function (key, value) {
		var ctrl = $('[name=' + key + ']', $form);
		switch (ctrl.prop("type")) {
			case "radio":
			case "checkbox":
				ctrl.each(function () {
					if ($(this).attr('value') == value)
						$(this).attr("checked", value);
				});
				break;
			default:
				ctrl.val(value);
		}
	});
}

function href2params(href) {
	var params = {};
	if (href.indexOf('?') !== -1) {
		var paramString = href.split('?')[1];
		var paramsParts = paramString.split('&');

		var paramsKeyValue;
		for (i = 0; i < paramsParts.length; i++) {
			paramsKeyValue = paramsParts[i].split('=');
			params[paramsKeyValue[0]] = paramsKeyValue[1];
		}

	}
	return params;
}
function id2folders(id) {
	if (!id) {
		return '';
	}
	var zerofill = ('00000' + id).substr(id.length);
	var folder = zerofill.substr(0, 3) + '/';
	var subfolder = zerofill.substr(3, 5) + '/';
	return folder + subfolder;
}
;

function rgbToHex(rgb) {
	if (rgb.length === 6) {
		return '#' + rgb;
	}
	var c = rgb.match(/\d+(\.\d+)?%?/g);
	if (c) {
		c = c.slice(0, 3).map(function (next) {
			var itm = next;
			if (itm.indexOf('%') != -1) {
				itm = Math.round(parseFloat(itm) * 2.55);
			}
			if (itm < 0)
				itm = 0;
			if (itm > 255)
				itm = 255;
			itm = Math.round(itm).toString(16);
			if (itm.length == 1)
				itm = '0' + itm;
			return itm;
		});
		return '#' + c.join('').toLowerCase();
	}
	return '';

}

function getSingular(plural) {
	if (plural === 'logistics') {
		return plural;
	}
	if (plural.lastIndexOf('s') + 1 === plural.length) {
		return plural.substr(0, plural.length - 1);
	}
	return plural;
}

function printFileSize(fileSizeInBytes) {
	var i = -1;
	var byteUnits = [' kB', ' MB', ' GB', ' TB', 'PB', 'EB', 'ZB', 'YB'];
	do {
		fileSizeInBytes = fileSizeInBytes / 1024;
		i++;
	} while (fileSizeInBytes > 1024);

	return Math.max(fileSizeInBytes, 0.1).toFixed(1) + byteUnits[i];
}

function selectElementContents(el) {
	var range = document.createRange();
	range.selectNodeContents(el);
	var sel = window.getSelection();
	sel.removeAllRanges();
	sel.addRange(range);
}

function insertTextAtCaret(text) {
	var sel, range;
	if (window.getSelection) {
		sel = window.getSelection();
		if (sel.getRangeAt && sel.rangeCount && sel.rangeCount > 0) {
			range = sel.getRangeAt(0);
			range.deleteContents();
			range.insertNode(document.createTextNode(text));
		}
	} else if (document.selection && document.selection.createRange) {
		document.selection.createRange().text = text;
	}
}

function saveCaretPosition(context) {
	var selection = window.getSelection();
	if (selection.rangeCount > 0) {
		var range = selection.getRangeAt(0);
		range.setStart(context, 0);
		var len = range.toString().length;
		if (range) {
			return () => {
				var pos = getTextNodeAtPosition(context, len);
				selection.removeAllRanges();
				var range = new Range();
				range.setStart(pos.node, 0);
				selection.addRange(range);
			};
		}

	}
	return false;
}

function removeSelection() {
	var selection = window.getSelection();
	selection.removeAllRanges();
}

function getTextNodeAtPosition(root, index) {
	const NODE_TYPE = NodeFilter.SHOW_TEXT;
	var treeWalker = document.createTreeWalker(root, NODE_TYPE, function next(elem) {
		if (index > elem.textContent.length) {
			index -= elem.textContent.length;
			return NodeFilter.FILTER_REJECT
		}
		return NodeFilter.FILTER_ACCEPT;
	});
	var c = treeWalker.nextNode();
	return {
		node: c ? c : root,
		position: index
	};
}

function isObject(item) {
	return (item && typeof item === 'object' && !Array.isArray(item));
}

function mergeDeep(target, ...sources) {
	if (!sources.length)
		return target;
	const source = sources.shift();

	if (isObject(target) && isObject(source)) {
		for (const key in source) {
			if (isObject(source[key])) {
				// Not just empty: anything that is not a mergeable object has
				// to go. Merging into an array (isObject says no to those) is
				// a no-op, so the incoming object would vanish without a word
				// - which is what happened when a render arrived on a slot an
				// earlier response had set to [].
				if (!isObject(target[key]))
					Object.assign(target, {[key]: {}});
				mergeDeep(target[key], source[key]);
			} else {
				Object.assign(target, {[key]: source[key]});
			}
		}
	}
	return mergeDeep(target, ...sources);
}


function updateOctalSelector(event) {
	var $parent = $(this).parents('.octalSelector');
	var $checkboxes = $parent.find('input[type=checkbox]');
	var $input = $parent.siblings('input[type=text]');
	var $accesvalue = 0;
	$checkboxes.each(function () {
		var $checkbox = $(this);
		var oct = $checkbox.attr('name').split('_')[1];
		if ($checkbox.is(':checked')) {
			$accesvalue |= parseInt(oct, 8); // bit setzen
		} else {
			$accesvalue &= ~parseInt(oct, 8); // bit löschen
		}
	});
	$input.val(str_pad($accesvalue.toString(8), 4));
	$input.trigger('input');
}