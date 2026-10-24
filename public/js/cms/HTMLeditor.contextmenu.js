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

$(function () {
	initContenteditableContextMenus();
	$('body').on('click', function (e) {
		if (!$(e.target).closest('body.cms div.mmlContent ul li').length) {
			$('body.cms div.mmlContent ul li').removeClass('selected');
		}
	});
});
