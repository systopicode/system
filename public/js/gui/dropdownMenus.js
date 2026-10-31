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
	menuOpener = src && src.closest ? src.closest('button, a') : null;
	$menu.css({
		left: call.event.e?.detail === 0 || !call.event.e?.clientX ? (menuOpener?.getBoundingClientRect().left || 8) : call.event.e.clientX,
		top: (call.event.e?.detail === 0 || !call.event.e?.clientY ? (menuOpener?.getBoundingClientRect().bottom || 8) : call.event.e.clientY) + window.scrollY,
	});
	$(document.body).append($menu);
	var rect = $menu[0].getBoundingClientRect();
	if (rect.right > window.innerWidth) {
		$menu.css('left', Math.max(0, window.innerWidth - rect.width - 8));
	}
	if (rect.bottom > window.innerHeight) {
		$menu.css('top', Math.max(0, window.innerHeight - rect.height - 8) + window.scrollY);
	}
	$menu.bind('mouseup', () => {
		setTimeout(closeAllMenus, 200);
	});
	sys.initEvents($menu);
    prepareMenuKeyboard($menu[0], menuOpener, call.event.e?.detail === 0);
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
	document.querySelectorAll('ul.contextmenu,.HTMLeditorMenu').forEach(menu=>setMenuExpanded(menu.cmsMenuOpener,false));
	$('body > ul.contextmenu').off().remove();
    document.querySelectorAll('div.dropdown.contextMenu').forEach(menu=>{setMenuExpanded(menu.cmsMenuOpener,false);(menu.parentElement.matches('div.cms')?menu.parentElement:menu).remove();});
	for (var key in contextMenus) {
		contextMenus[key].domWrapper.remove();
		contextMenus[key].onClose?.();
		delete(contextMenus[key].contextMenu);
	}
	contextMenus = [];
}

// Most clicks in the CMS stop at the element (sys.processEvent) and never
// reach the body handler that used to close the menu. Capture runs first.
// A second press on the button that opened the menu only closes it.
var menuOpener = null;
var swallowClick = null;
document.addEventListener('pointerdown', function (event) {
	if (!document.querySelector('ul.contextmenu, div.dropdown.contextMenu, .HTMLeditorMenu')) {
		return;
	}
	if (event.target.closest && event.target.closest('ul.contextmenu, div.dropdown.contextMenu, .HTMLeditorMenu')) {
		return;
	}
	swallowClick = menuOpener && menuOpener.contains(event.target) ? menuOpener : null;
	closeAllMenus();
}, true);
document.addEventListener('click', function (event) {
	if (swallowClick && swallowClick.contains(event.target)) {
		event.preventDefault();
		event.stopPropagation();
	}
	swallowClick = null;
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
    prepareMenuKeyboard($menu[0],e.target.closest?.('button,a,[contenteditable]') || e.target,e.detail===0);
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
    prepareMenuKeyboard(this.domObj[0],targetOpener(event.target),event.detail===0);


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



// The arrow on a split button (image editor, language switch).
document.addEventListener('click', function (event) {
	var button = event.target.closest && event.target.closest('.splitApplyMenu');
	if (button && button.disabled) {
		return;
	}
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
			var width = list.classList.contains('unifiedAdjustSlider') ? Math.min(320,window.innerWidth-32) : Math.max(rect.width, list.scrollWidth);
			list.style.width = width + 'px';
			var toolbarBottom=list.classList.contains('imageEditorMenu') ? (split.closest('.cropBar')?.getBoundingClientRect().bottom || rect.bottom) : rect.bottom;
            list.style.top = (toolbarBottom + 4) + 'px';
            if(list.classList.contains('imageEditorMenu')){list.style.maxHeight=Math.max(48,window.innerHeight-toolbarBottom-12)+'px';list.style.overflowY='auto';}
			list.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)) + 'px';
			split.classList.add('is-open');
            if(button.hasAttribute('data-menu-selected')){button.classList.add('selected');button.setAttribute('aria-pressed','true');}
            prepareMenuKeyboard(list, button, event.detail === 0);
		}
		return;
	}
	if (!(event.target.closest && event.target.closest('.splitApplyList'))) {
		closeSplitMenus();
	}
});

document.addEventListener('cms:rendered', function () {
	closeSplitMenus();
});

// An entry closes its menu even when its own handler (ajax links) stops the click.
document.addEventListener('click', function (event) {
	if (event.target.closest && event.target.closest('.splitApplyList a, .splitApplyList button')) {
		setTimeout(closeSplitMenus);
	}
}, true);

function closeSplitMenus() {
	document.querySelectorAll('.splitApplyList.is-open').forEach(function (list) {
		const opener=list.splitHome?.querySelector('.splitApplyMenu');
        opener?.setAttribute('aria-expanded','false');
        if(opener?.hasAttribute('data-menu-selected')){opener.classList.remove('selected');opener.setAttribute('aria-pressed','false');}
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

// Keyboard selection and history navigation do not necessarily emit pointerdown.
document.addEventListener('click', function (event) {
    if (event.target.closest?.('ul.tree .label,nav a[href]') && !event.target.closest('ul.contextmenu')) closeAllMenus();
}, true);
window.addEventListener('popstate', closeAllMenus);

// Local popovers use native tab order; do not declare an ARIA menu without its behavior.
var cmsMenuSequence = 0;
function prepareMenuKeyboard(menu, opener, focusFirst) {
    menu.cmsMenuOpener = opener;
    if (!menu.id) menu.id = 'cms-actions-' + (++cmsMenuSequence);
    if (!menu.hasAttribute('aria-label')) menu.setAttribute('aria-label', menu.classList.contains('HTMLeditorMenu') ? 'Text formatting' : 'Actions');
    if (opener?.matches('button,a')) {
        opener.setAttribute('aria-expanded', 'true');
        opener.setAttribute('aria-controls', menu.id);
    }
    menu.querySelectorAll('a:not([href]),a[href=""],a[href="#"],a[href="javascript:void(0)"]').forEach(a => {
        a.setAttribute('role', 'button'); a.tabIndex = 0;
    });
    menu.querySelectorAll('li').forEach(li=>{if($(li).data('option_key')!==undefined){li.setAttribute('role','button');li.tabIndex=0;}});
    menu.querySelectorAll('li.disabled > a,li.disabled > button').forEach(el => { el.setAttribute('aria-disabled','true');el.tabIndex=-1; });
    if (focusFirst) menuKeyboardItems(menu)[0]?.focus({preventScroll:true});
}
function setMenuExpanded(opener,expanded) { if(opener?.matches('button,a')){opener.setAttribute('aria-expanded',String(expanded));if(!expanded)opener.removeAttribute('aria-controls');} }
function targetOpener(target) { return target?.closest?.('button,a,[contenteditable]') || target; }
function menuKeyboardItems(menu) {
    return [...menu.querySelectorAll('a[href],button,a[role="button"],[tabindex="0"]')].filter(el => !el.disabled && el.getAttribute('aria-disabled') !== 'true' && !el.closest('.disabled') && !!el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden');
}
function restoreMenuOpener(menu) {
    const opener = menu.cmsMenuOpener;
    if (opener?.isConnected) { setMenuExpanded(opener,false);opener.focus({preventScroll:true}); }
}
document.addEventListener('keydown', function (event) {
    if (event.key === 'ContextMenu' || (event.shiftKey && event.key === 'F10')) {
        const row=event.target.closest?.('.cms ul.tree li');
        const trigger=row?.querySelector(':scope > label > .treeMenu');
        if(trigger){event.preventDefault();event.stopImmediatePropagation();trigger.click();return;}
    }
    const menu = event.target.closest?.('ul.contextmenu,.HTMLeditorMenu,.splitApplyList.is-open,div.dropdown.contextMenu');
    if (!menu || event.target.matches('input,textarea,select,[contenteditable="true"]')) return;
    const current = event.target.closest('a,button,[role="button"]'), branch = current?.parentElement;
    const sub = branch?.classList.contains('hasChildren') && branch.querySelector(':scope > ul');
    if ((event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowRight') && sub) {
        event.preventDefault();event.stopImmediatePropagation();branch.classList.add('open');
        current.setAttribute('aria-expanded','true');
        requestAnimationFrame(()=>{if(branch.classList.contains('open')&&document.activeElement===current)menuKeyboardItems(sub)[0]?.focus();});return;
    }
    if (event.key === 'ArrowLeft') {
        const parent = current?.closest('ul')?.parentElement;
        if (parent?.classList.contains('hasChildren')) {
            event.preventDefault();event.stopImmediatePropagation();parent.classList.remove('open');
            const trigger=parent.querySelector(':scope > a,:scope > button');trigger?.setAttribute('aria-expanded','false');trigger?.focus();return;
        }
    }
    if (event.key === 'Escape') {
        event.preventDefault();event.stopImmediatePropagation();restoreMenuOpener(menu);
        if(menu.matches('.splitApplyList')) closeSplitMenus();else closeAllMenus();return;
    }
    if (['ArrowDown','ArrowUp','ArrowRight','ArrowLeft','Home','End'].includes(event.key)) {
        const list=menuKeyboardItems(menu);if(!list.length)return;
        event.preventDefault();event.stopImmediatePropagation();const i=list.indexOf(current);
        const next=event.key==='Home'?0:event.key==='End'?list.length-1:(i+(['ArrowDown','ArrowRight'].includes(event.key)?1:list.length-1))%list.length;
        list[next].focus();return;
    }
    if ((event.key==='Enter'||event.key===' ') && current?.matches('[role="button"]:not(button)')) {
        event.preventDefault();event.stopImmediatePropagation();current.click();
    }
}, true);
// Retain normal pointer selection; close keyboard popovers after tabbing away.
document.addEventListener('focusout', function(event) {
    const menu=event.target.closest?.('ul.contextmenu,.HTMLeditorMenu,.splitApplyList.is-open');
    if (!menu) return;
    const opener=menu.cmsMenuOpener;
    setTimeout(()=>{
        if (menu.cmsMenuOpener!==opener || (menu.matches('.splitApplyList') && !menu.classList.contains('is-open'))) return;
        if (!menu.isConnected || menu.contains(document.activeElement) || document.activeElement===menu.cmsMenuOpener) return;
        setMenuExpanded(menu.cmsMenuOpener,false);
        if(menu.matches('.splitApplyList'))closeSplitMenus();else closeAllMenus();
    },0);
});

document.addEventListener('click', function(event) {
    const disabled=event.target.closest?.('[aria-disabled="true"]');
    if(disabled?.closest('ul.contextmenu,.HTMLeditorMenu,.splitApplyList,div.dropdown.contextMenu')){
        event.preventDefault();event.stopImmediatePropagation();
    }
},true);
