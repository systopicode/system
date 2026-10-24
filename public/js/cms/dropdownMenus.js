function markContextTarget(src) {
	document.querySelectorAll('.menuTarget').forEach(function (row) {
		row.classList.remove('menuTarget');
	});
	if (!src || !src.closest) return;
	var row = src.closest('.mmlContent ul ul > li') || src.closest('ul.tree li');
	if (row) row.classList.add('menuTarget');
}

function openContextMenu(call) {
	var $menu = $(call.html);
	// p(call.response.html);
	closeAllMenus();
	var src = call.event && (call.event.sourceElement || call.event.element);
	markContextTarget(src);
	$menu.css({
		left: call.event.e.clientX,
		top: call.event.e.clientY + window.scrollY,
	});
	$('div.main.cms').append($menu);
	$menu.bind('mouseup', () => {
		setTimeout(closeAllMenus, 200);
	});
	sys.initEvents($menu);
}
/****************************** CONTEXT/RIGHTCLICK MENU WYSIWYG ***************************/
function initContenteditableContextMenus() {

	//p('initContenteditableContextMenus');
	$("body").bind("contextmenu", function (e) {
		var $HTMLeditor = $(e.target).parents('[contenteditable=true]');
		if ($HTMLeditor.length === 0) {
			if ($(e.target).is('[contenteditable=true]')) {
				$HTMLeditor = $(e.target);
			}
		}
		if ($HTMLeditor.length === 0) {
			return;
		}
		e.preventDefault();
		$element = $(e.target);
		if (empty(getSelText())) {
			var walkup = true;
			while (walkup) {
				elementName = $element.prop('nodeName');
				if (!$element.is('[contenteditable=true]')) { // $HTMLeditor
					walkup = false;
                  	switch (elementName) {
						case "IMG":
							HTMLeditorRightClick("image", e, e.target);
							break;
						case "VIDEO":
							HTMLeditorRightClick("video", e, e.target);
							break;
						case "IFRAME":
							HTMLeditorRightClick("iframe", e, e.target);
							break;
						case "A":
							HTMLeditorRightClick("anchor", e, $element);
							break;
						case "TABLE":
							HTMLeditorRightClick("table", e, $(this));
							break;
						case "DIV":
						case "UL":
						case "OL":
						case "P":
						case "H1":
						case "H2":
						case "H3":
						case "H4":
						case "H5":
						case "H6":
							HTMLeditorRightClick("selectedBlock", e, $(this));
							break;
						default:
							$element = $element.parent();
							walkup = true;
							break;
					}
				} else {
					p(elementName);

					walkup = false;
					switch (elementName) {
						case "DIV":
							HTMLeditorRightClick("selectedWord", e, $(this));
							break;
					}

				}
			}
		} else {
			var inTable = $element.parents('table').length;
			if (inTable) {
				var tdsSelected = [];
				$element.parents('table').find('td').each(function () {
					if (getSelection().containsNode(this, false)) {
						tdsSelected.push(this);
					}
				});
				if (tdsSelected.length > 0) {
					HTMLeditorRightClick("selectedTable", e, $(tdsSelected));
				} else {
					HTMLeditorRightClick("selectedWord", e, $(this));
				}
			} else {
				if (isTextSelected()) {
					HTMLeditorRightClick("selectedWord", e, $(this));
				} else {
					HTMLeditorRightClick("selectedBlock", e, $(this));
				}
			}
		}
	});
}

/****************************** CONTEXT MENUS ***************************/
function initContextMenuEvents() {
	window.setTimeout(function () {
		if (typeof contextMenusConfig === 'object' && (settings.showeditableareas || settings.inCms)) {
			for (var key in contextMenusConfig) {
				var $elems = $(contextMenusConfig[key]['eventHandlerSelector']);
				$elems.each(function () {
					var $elem = $(this);
					$elem.unbind('contextmenu');
					$elem.bind('contextmenu', {contextMenuConfig: contextMenusConfig[key]}, function (event) {
						event.stopPropagation();
						//return false;
						new contextMenu(event.data.contextMenuConfig, event, event.data.contextMenuConfig.eventHandlerSelector);
					});
				});
			}
		}
	}, 500);
}

var contextMenus = [];
function closeAllMenus() {
	document.querySelectorAll('.menuTarget').forEach(function (row) {
		row.classList.remove('menuTarget');
	});
	$('div.main.cms ul.contextmenu').off();
	$('div.main.cms ul.contextmenu').remove();
	for (var key in contextMenus) {
		contextMenus[key].domWrapper.remove();
		delete(contextMenus[key].contextMenu);
	}
	contextMenus = [];
}

// Most clicks in the CMS stop at the element (sys.processEvent) and never
// reach the body handler that used to close the menu. Capture runs first.
document.addEventListener('mousedown', function (event) {
	if (!document.querySelector('ul.contextmenu, div.dropdown.contextMenu, .HTMLeditorMenu')) {
		return;
	}
	if (event.target.closest && event.target.closest('ul.contextmenu, div.dropdown.contextMenu, .HTMLeditorMenu')) {
		return;
	}
	closeAllMenus();
}, true);
function contextMenu2(config, e) {
	$('div.cms.dropdown.contextMenu').parent().remove();
	var $contextTarget = $(e.target);
	var $wrapper = $("<div class='cms'></div>");
	var $menu = $("<div class='cms dropdown contextMenu'></div>");
	var $content = $("<div class='dropdownContent'></div>");
	var $ul = $("<ul></ul>");
	$wrapper.append($menu.append($content.append($ul)));
	$.each(config.items, function (key) {
		var classTag = this.classNames ? ' class="' + this.classNames + '"' : '';
		$li = $('<li' + classTag + '>' + this.title + '</li>');
		$li.data('option_key', key);
		if (this.data) {
			$.extend($li.data, this.data); // not used?
		}
		$ul.append($li);
	});
	$menu.css({
		left: e.pageX - 45,
		top: e.pageY - 30,
		width: "200px"
	});
	$('body').append($wrapper);
	$ul.bind('click', function (e) {
		var key = $(e.target).data().option_key;
		var data = collectTrunkData($contextTarget);
		if (config.items[key].data) {
			$.extend(data, config.items[key].data);
		}
		callServerOnAjaxObjects(data);
		$wrapper.remove();
	});
}

var contextMenu = function (config, event, parent) {

	event.preventDefault();
	closeAllMenus();
	contextMenus = [this];

	var items = config['contextMenuItems'];
	var $targetDomObj = $(event.target);
	$targetDomObj.addClass('selected');
	var dataAttrs = '';
	var data = collectTrunkData($targetDomObj);
	data.selector = parent;
	for (var key in data) {
		dataAttrs += ' data-' + key + '="' + data[key] + '"';
	}
	this.domWrapper = $("<div class='cms'></div>");
	this.domObj = $('<div class="cms dropdown contextMenu"' + dataAttrs + '></div>');
	var dropdwonContent = $("<div class='dropdownContent'></div>");
	var dropwdownUl = $("<ul></ul>");
	var dropwdownLi = $("<li></li>");
	var ul = $('<ul></ul>');
	//p(items);
	$.each(items, function (key) {
		var item = this;
		var icon = '';
		var title = '';
		var DOMitem = false;
		var url = '';
		if (item.module_url) {
			if (item.module_url.substr(0, 1) === '/') { // full modulepath
				url = settings.siteRoot + item.module_url.substr(1) + '/';
			} else { // relative modulepath
				url = settings.siteRoot + settings.modulePath.join('/') + '/' + item.module_url + '/';
			}
		}
		var data = item.data ? '?' + $.param(item.data) : '';
		var target = item.target ? " target='" + target + "'" : '';
		var confirm = item.confirm ? " data-confirm='" + item.confirm + "'" : '';
		var html = "<li title='" + title + "'>";
		html += "<a class='ajax'" + target + confirm + " href='" + url + data + "' class='" + key + "'>";
		html += item.icon ? "<i class='" + item.className + "'></i>" : '';
		html += '<span>' + (item.title || 'no title') + '</span>';
		html += "</a></li>";
		DOMitem = $(html);
		ul.append(DOMitem);
		if (item["seperator"]) {
			ul.append("<li class='seperator'></li>");
		}
	});
	this.domObj.css({
		left: event.pageX - 45,
		top: event.pageY - 30,
		width: "200px"
	});
	dropdwonContent.append(ul);
	dropwdownLi.append(dropdwonContent);
	dropwdownUl.append(dropwdownLi);
	$('body').append(this.domWrapper.append(this.domObj.append(dropwdownUl)));
	ajax.initEvents(this.domWrapper);


};

function linkToClipboard(id) {
	var link = settings.PUBLIC_ROOT_URL + '?id=' + id;
	var input = document.createElement("INPUT");
	var body = document.getElementsByTagName("BODY")[0];
	$('body').append(input);
	input.value = link;
	input.select();
	document.execCommand("Copy");
	body.removeChild(input);
}



function initDropdownMenus($set) {
	$set.findInSet("div.dropdown > ul > li").bind('click', function (e) {
		$element = $(this);
		if ($(e.target).parent('li').hasClass('open')) {
			closeAll();
		} else {
			//	closeAll();
			closeAllMenus();
			var placeholderWidth = $element.outerWidth() + 1;
			if ($element.children(".dropdownContent").children(".before").length === 0) {
				$element.children(".dropdownContent").prepend("<div class='before' style='width:" + placeholderWidth + "px;'></div>");
			}



			var width = 0;
			$element.children(".dropdownContent").children("ul").each(function () {
				if ($(this).next().prop('tagName') !== 'BREAK') {
					width += $(this).outerWidth(true);

				}
			});

			$element.children().children('.arrow').addClass('animate');
			$element.addClass("open");
			$element.removeClass("closed");
			$element.children(".dropdownContent").css({
				width: width
			});
			var children = $element.children(".dropdownContent");
			if ($(children).is(':off-right')) {
				$(children).addClass('right');
				$(this).find(".before").css({
					width: placeholderWidth - 1
				})
				$(children).css({
					left: -width + $(this).width()
				});
			}

		}
	});
	$("body.cms div.dropdown li").bind('mouseover', function () {
		if ($(this).hasClass('open')) {
			return
		} else if ($(this).siblings().hasClass('open')) {
			closeAll();
			$(this).trigger('click');
		}
	});
	this.closeAll = function () {
		$('body.cms div.dropdown  ul li').each(function () {
			if ($(this).hasClass('open')) {
				$(this).removeClass('open');
				$(this).addClass('closed');
				$(this).children().children('.arrow').removeClass('animate');
			}
		});
	};
	closeAll();
}



var contextMenuWW = function (params, event, elements) {
	closeAllMenus();
	contextMenus = [this];
	this.domWrapper = $("<div class='cms'></div>");
	this.domObj = $('<div class="HTMLeditorMenu"></div>');
	var ul = $('<ul></ul>');
	var item, li, a_item, subul, subli, a_subs, confirm;
	for (var key in params) {
		item = params[key];
		if (item.active === false) {
			continue;
		}

		var selected = '';
		if (item.selected) {
			selected = 'selected'
		}
		if (item["confirm"]) {
			confirm = "data-confirm='" + item["confirm"] + "'";
		}
		a_item = $('<a href="javascript:void(0)" title="' + item.title + '"><i class="' + item.className + '"></i></a>');
		a_item.bind('click', item.param, window[item.func]);
		li = $("<li class='HTMLeditorMenuItem " + selected + "' ></li>");
		ul.append(li);
		li.append(a_item);

		if (item["subs"]) {
			subul = $('<ul></ul>');
			li.append(subul);
			li.addClass("hasChildren");
			for (var key in item["subs"]) {
				var subs = item["subs"][key];
				var selected = '';
				if (subs.selected) {
					selected = 'selected'
				}
				a_subs = $('<a>' + subs["title"] + '</<a>');
				a_subs.bind('mousedown', subs.param, window[subs.func]);
				subli = $("<li class='HTMLeditorMenuItem child " + selected + "'></li>");
				subul.append(subli);
				subli.append(a_subs);
			}
			ul.children("li").append("</ul>");
		}

	}

	this.domObj.css({
		left: event.pageX - 27,
		top: event.pageY
	});

	$('body').append(this.domWrapper.append(this.domObj.append(ul)));
	var HTMLeditorMenuWidth = $(".HTMLeditorMenu > ul").outerWidth(true);
	/*	if ($(".HTMLeditorMenu").is(':off-right')) {
	 HTMLeditorMenuWidth = HTMLeditorMenuWidth + 30;
	 } */
	$(".HTMLeditorMenu").css({
		width: HTMLeditorMenuWidth
	});

	/*if ($(".HTMLeditorMenu").is(':off-right')) {
	 $(".HTMLeditorMenu").addClass('outOfFocus');
	 $(".HTMLeditorMenu").css('left', event.pageX - HTMLeditorMenuWidth);
	 } */

	$(".HTMLeditorMenu").bind('click', function () {
		$(event.target).focus();
	});
}

// The arrow on a split button (image editor, language switch).
document.addEventListener('click', function (event) {
	var button = event.target.closest && event.target.closest('.splitApplyMenu');
	if (button) {
		event.preventDefault();
		event.stopPropagation();
		var split = button.closest('.splitApply');
		var list = split && (split.querySelector('.splitApplyList') || split.splitList);
		var open = list && list.classList.contains('is-open');
		closeSplitMenus();
		if (list && split && !open) {
			split.splitList = list;
			list.splitHome = split;
			document.body.appendChild(list);
			list.hidden = false;
			list.classList.add('is-open');
			list.style.width = 'max-content';
			var rect = split.getBoundingClientRect();
			var width = Math.max(rect.width, list.scrollWidth);
			list.style.width = width + 'px';
			list.style.top = (rect.bottom + 4) + 'px';
			list.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)) + 'px';
			split.classList.add('is-open');
		}
		return;
	}
	if (!(event.target.closest && event.target.closest('.splitApplyList'))) {
		closeSplitMenus();
	}
});

function closeSplitMenus() {
	document.querySelectorAll('.splitApplyList.is-open').forEach(function (list) {
		list.hidden = true;
		list.classList.remove('is-open');
		// The list is parked on the body while it is open. Put it back,
		// or the next click looks inside the button and finds nothing.
		if (list.splitHome && list.splitHome.isConnected) {
			list.splitHome.appendChild(list);
		} else {
			list.remove();
		}
	});
	document.querySelectorAll('.splitApply.is-open').forEach(function (split) {
		split.classList.remove('is-open');
	});
}
