function openContextMenu(call) {
	var $menu = $(call.html);
	// p(call.response.html);
	closeAllMenus();
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
	$('div.main.cms ul.contextmenu').off();
	$('div.main.cms ul.contextmenu').remove();
	for (var key in contextMenus) {
		contextMenus[key].domWrapper.remove();
		delete(contextMenus[key].contextMenu);
	}
	contextMenus = [];
}
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
;
