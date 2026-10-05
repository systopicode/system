/* global sys,c,p,t,l,onInitEvents,updateOctalSelector */
sys.initEvents = function ($set, oneventcomplete) {
	var eventCapsule = function () {
		return e => {
			sys.processEvent(e);
		};
	};
	if (typeof $.fn.findInSet !== 'function') {
		$.fn.findInSet = function (selector) {
			return $(this.find(selector).add(this).filter(selector));
		};
	}
	/**** AJAX ALL NON FORM ELEMENTS ****/
	// Jedes <a> ohne target/download → XHR. Opt-out: target="_self" / _blank / download.
	$set.findInSet('a').not('[target], [download]').bind('click', sys.processEvent);
	$set.findInSet('[data-on_click]').not('a').bind('click', new eventCapsule);
	$set.findInSet('.ajax,.put').not('a,input,select,textarea,form,button,[contenteditable]').bind('click', sys.processEvent);

	$set.findInSet('[data-on_contextmenu]').bind('contextmenu', sys.processEvent);
	$set.findInSet('[data-on_change]').bind('change', sys.processEvent); // semms not to work with file inputs??

	$set.findInSet('[data-on_keydown]').bind('keydown', sys.processEvent);
	$set.findInSet('[data-on_keypress]').bind('keypress', sys.processEvent);
	$set.findInSet('[data-on_keyup]').bind('keyup', sys.processEvent);
	$set.findInSet('[data-on_dblclick]').bind('dblclick', sys.processEvent);
	$set.findInSet('[data-on_focus]').bind('focus', sys.processEvent);
	$set.findInSet('[data-on_blur]').bind('blur', sys.processEvent);
	$set.findInSet('[data-on_input]').bind('input', sys.processEventDelayed);
	$set.findInSet('.autosend').bind('input', sys.processEventDelayed);
	/**** drag ****/
	$set.findInSet('[data-on_draginit],[data-drag_type]').bind('mousedown', sys.processEvent);

	/**** AJAX FORM - SUBMIT WHOLE (XHR default; opt-out: target=_self) ****/
	var $xhrForms = $set.findInSet('form').not('[target]');
	$xhrForms.find('button[type="submit"],input[type="submit"]').not('.ajax').click(function () { // create helper element for action
		$(this).parents('form').append('<input class="cmsSubmitAjaxFormHelper" type="hidden" name="' + this.name + '" value="' + this.value + '"/>');
	});
	$xhrForms.find('.onchangesubmit').bind('change', function () {
		$(this).closest('form').submit();
	});
	$xhrForms.bind('submit', sys.processEvent);
	$set.findInSet("button[name=action]").bind('click', e => {
		e.currentTarget.form.setAttribute('action', e.currentTarget.value);
	});

	/**** AJAX FORM ELEMENTS - SUBMIT SINGLE ****/
	$set.findInSet('input[type="checkbox"].ajax').bind('change', sys.processEvent);
	$set.findInSet('input[type="radio"].ajax,input[type="submit"].ajax,input[type="button"].ajax,button.ajax').bind('click keypress', sys.processEvent);
	$set.findInSet('select.ajax').not('[multiple]').bind('change', sys.processEvent);
	$set.findInSet('select.ajax[multiple]').bind('click', sys.processEvent); // uncheck selected

	// Project-specific Events Hook - if func declared
	if (typeof onInitEvents === 'function') {
		onInitEvents($set);
	}

	// init CMS-internal Events if user is logged in
	$set.findInSet('.octalSelector input[type=checkbox]').bind('change', updateOctalSelector);
	if (typeof initCMSUsersAjaxEvents === 'function') {
		initCMSUsersAjaxEvents($set);
	}
	// other events - under construction
	var $onLoadAjax = $set.findInSet('[data-on_load]'); // experimental
	$onLoadAjax.bind('load', sys.processEvent);
	$onLoadAjax.trigger('load');

};

function initHTMLeditorInputTimer($items) {
	var contenteditableTimer;
	$items.bind('input drop paste click', function (e) {
		//$(this).removeClass('confirm error');
		if (e.type === 'click') {
			e.stopPropagation();
			return; // avoid fireing upper ajax elements when focussing
		}
		clearTimeout(contenteditableTimer);
		contenteditableTimer = setTimeout(function () {
			//insertHtmlAtCursor('<span class="cursorPosition">|</span>');
			e.stopPropagation();
			sys.processEvent(e);
		}, 500);
	});
}

/*NO JQUERY
 * 
 * 
 * sys.initEvents = function (set, oneventcomplete) {
 var eventCapsule = function () {
 return function (e) {
 sys.processEvent(e);
 };
 };
 
 if (typeof HTMLElement.prototype.findInSet !== 'function') {
 HTMLElement.prototype.findInSet = function (selector) {
 var foundElements = Array.from(this.querySelectorAll(selector));
 if (this.matches(selector)) {
 foundElements.push(this);
 }
 return foundElements;
 };
 }
 
 set.findInSet('a').forEach(function (element) {
 if (!element.hasAttribute('target')) {
 element.addEventListener('click', sys.processEvent);
 }
 });
 
 set.findInSet('.ajax,.put').forEach(function (element) {
 if (!['a', 'input', 'select', 'textarea', 'form', 'button', '[contenteditable]'].some(element.matches.bind(element))) {
 element.addEventListener('click', sys.processEvent);
 }
 });
 
 set.findInSet('[data-on_click]').forEach(function (element) {
 if (!element.matches('a')) {
 element.addEventListener('click', new eventCapsule());
 }
 });
 
 set.findInSet('[data-on_contextmenu]').forEach(function (element) {
 element.addEventListener('contextmenu', sys.processEvent);
 });
 
 set.findInSet('[data-on_change]').forEach(function (element) {
 element.addEventListener('change', sys.processEvent);
 });
 
 set.findInSet('[data-on_keyup]').forEach(function (element) {
 element.addEventListener('keyup', sys.processEvent);
 });
 
 set.findInSet('[data-on_dblclick]').forEach(function (element) {
 element.addEventListener('dblclick', sys.processEvent);
 });
 
 set.findInSet('[data-on_focus]').forEach(function (element) {
 element.addEventListener('focus', sys.processEvent);
 });
 
 set.findInSet('[data-on_blur]').forEach(function (element) {
 element.addEventListener('blur', sys.processEvent);
 });
 
 set.findInSet('[data-on_input]').forEach(function (element) {
 element.addEventListener('input', sys.processEventDelayed);
 });
 
 set.findInSet('.autosend').forEach(function (element) {
 element.addEventListener('input', sys.processEventDelayed);
 });
 
 set.findInSet('[data-on_draginit],[data-drag_type]').forEach(function (element) {
 element.addEventListener('mousedown', sys.processEvent);
 });
 
 set.findInSet('form.ajax button[type="submit"],form.ajax input[type="submit"]').forEach(function (element) {
 if (!element.classList.contains('ajax')) {
 element.addEventListener('click', function () {
 var form = element.closest('form');
 if (form) {
 form.insertAdjacentHTML('beforeend', '<input class="cmsSubmitAjaxFormHelper" type="hidden" name="' + element.name + '" value="' + element.value + '"/>');
 }
 });
 }
 });
 
 set.findInSet('form.ajax .onchangesubmit').forEach(function (element) {
 element.addEventListener('change', function () {
 var form = element.closest('form');
 if (form) {
 form.submit();
 }
 });
 });
 
 set.findInSet('form.ajax').forEach(function (element) {
 element.addEventListener('submit', sys.processEvent);
 });
 
 set.findInSet('form[method=put]').forEach(function (element) {
 element.addEventListener('submit', sys.processEvent);
 });
 
 set.findInSet('form[method=put] button[name=action]').forEach(function (element) {
 element.addEventListener('click', function (e) {
 var form = e.currentTarget.form;
 if (form) {
 form.setAttribute('action', e.currentTarget.value);
 }
 });
 });
 
 set.findInSet('input[type="checkbox"].ajax').forEach(function (element) {
 element.addEventListener('change', sys.processEvent);
 });
 
 set.findInSet('input[type="radio"].ajax,input[type="submit"].ajax,input[type="button"].ajax,button.ajax').forEach(function (element) {
 element.addEventListener('click keypress', sys.processEvent);
 });
 
 set.findInSet('select.ajax:not([multiple])').forEach(function (element) {
 element.addEventListener('change', sys.processEvent);
 });
 
 set.findInSet('select.ajax[multiple]').forEach(function (element) {
 element.addEventListener('click', sys.processEvent);
 });
 
 // Project-specific Events Hook - if func declared
 if (typeof onInitEvents === 'function') {
 onInitEvents(set);
 }
 
 // init CMS-internal Events if user is logged in
 set.findInSet('.octalSelector input[type=checkbox]').forEach(function (element) {
 element.addEventListener('change', updateOctalSelector);
 });
 
 if (typeof initCMSUsersAjaxEvents === 'function') {
 initCMSUsersAjaxEvents(set);
 }
 
 // other events - under construction
 var onLoadAjax = set.findInSet('[data-on_load]'); // experimental
 onLoadAjax.forEach(function (element) {
 element.addEventListener('load', sys.processEvent);
 });
 onLoadAjax.forEach(function (element) {
 element.dispatchEvent(new Event('load'));
 });
 };
 
 function initHTMLeditorInputTimer(items) {
 var contenteditableTimer;
 
 items.forEach(function (item) {
 item.addEventListener('input', function (e) {
 if (e.type === 'click') {
 e.stopPropagation();
 return; // avoid firing upper ajax elements when focusing
 }
 clearTimeout(contenteditableTimer);
 contenteditableTimer = setTimeout(function () {
 e.stopPropagation();
 sys.processEvent(e);
 }, 500);
 });
 
 item.addEventListener('drop', function (e) {
 e.stopPropagation();
 e.preventDefault();
 });
 
 item.addEventListener('paste', function (e) {
 e.stopPropagation();
 e.preventDefault();
 });
 
 item.addEventListener('click', function (e) {
 e.stopPropagation();
 });
 });
 }
 */