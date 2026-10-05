$(document).ready(function () {
	/****************************** PUSH NOTIFICATIONS ***************************/
	if (settings.stage != 'DEV') {
		if (settings.notifications && !$("body").hasClass('login')) {
			if (Notification.permission === "granted") {
			} else if (Notification.permission !== 'denied') {
				Notification.requestPermission(function (permission) {
					if (permission === "granted") {
					}
				});
			}

			var eSource = new EventSource(settings.siteRoot + "?get=pushNotifications");

			eSource.onmessage = function (event) {
				var data = $.parseJSON(event.data);
				pushNotificationsPageHeader(data.number, data.notifications);
			}
		}

	}



	$('body').bind('click', function (e) {
		if (e.button == 0) {
			closeAllMenus();
		}

		if (!$(e.target).closest('body.cms div.mmlContent ul li').length) {
			$("body.cms div.mmlContent ul li").removeClass("selected");
		}
		if (!$(e.target).closest('body.cms div.dropdown').length) {
			closeAllMenus();
		}

		if (!$(e.target).closest('body.cms ul.notficiationList').length) {
			$("body.cms ul.notficiationList li").remove();
			$("div.notifications").removeClass('open');
		}
	});


	initContenteditableContextMenus();
});

function reloadToCMS() {
	window.location = settings.siteRoot + 'cms';
}


function addEvent(obj, evt, fn) {
	if (obj.addEventListener) {
		obj.addEventListener(evt, fn, false);
	} else if (obj.attachEvent) {
		obj.attachEvent("on" + evt, fn);
	}
}


function hideLoading() {
	$("#loading").fadeOut("fast").remove();
}


function ajaxsearch(html, append) {
	p(html);
}


/**************************** PASSWORT CHECK *********************************************/
function checkStrength(password) {

	var strength = 0

	//if length is 8 characters or more, increase strength value
	if (password.length > 5)
		strength += 1
	//if password contains both lower and uppercase characters, increase strength value
	if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/))
		strength += 1

	//if it has numbers and characters, increase strength value
	if (password.match(/([a-zA-Z])/) && password.match(/([0-9])/))
		strength += 1

	//if it has one special character, increase strength value
	if (password.match(/([!,%,&,@,#,$,^,*,?,_,~])/))
		strength += 1

	//if it has two special characters, increase strength value
	if (password.match(/(.*[!,%,&,@,#,$,^,*,?,_,~].*[!,%,&,@,#,$,^,*,?,_,~])/))
		strength += 1

	//now we have calculated strength value, we can return messages


	if (!empty($("#inputPassword2").val())) {
		if ($("#inputPassword2").val() !== $("#inputPassword1").val()) {
			$('#complexity').removeClass()

			$('#complexity').addClass('weak')
			return '<span>Passwords dont match</span>'
		}
	}

	if (password.length < 6) {
		$('#complexity').removeClass()
		$('#complexity').addClass('default')
		return '<span>Too short</span>'
	}

	if (strength < 2) {
		$('#complexity').removeClass()
		$('#complexity').addClass('weak')
		return '<span>Weak</span>'
	} else if (strength == 2) {
		$('#complexity').removeClass()
		$('#complexity').addClass('strong')
		return '<span>Good</span>'
	} else {
		$('#complexity').removeClass()
		$('#complexity').addClass('strongest')
		return '<span>Strong</span>'
	}



}


/**************************** COUNTER *********************************************/
;
(function ($) {
	$.fn.counter = function (options) {
// Set default values
		var defaults = {
			start: 0,
			end: 10,
			time: 10,
			step: 1000,
			callback: function () {
			}
		}
		var options = $.extend(defaults, options);
		// The actual function that does the counting
		var counterFunc = function (el, increment, end, step) {
			var value = parseInt(el.html(), 10) + increment;
			if (value >= end) {
				el.html(Math.round(end));
				options.callback();
			} else {
				el.html(Math.round(value));
				setTimeout(counterFunc, step, el, increment, end, step);
			}
		}
// Set initial value
		$(this).html(Math.round(options.start));
		// Calculate the increment on each step
		var increment = (options.end - options.start) / ((1000 / options.step) * options.time);
		// Call the counter function in a closure to avoid conflicts
		(function (e, i, o, s) {
			setTimeout(counterFunc, s, e, i, o, s);
		})($(this), increment, options.end, options.step);
	}
})(jQuery);
/*!
 * pGenerator jQuery Plugin v1.0.0
 * http://accountspassword.com/password-generator-jquery-plugin
 *
 * Created by AccountsPassword.com
 * Released under the GPL General Public License (Feel free to copy, modify or redistribute this plugin.)
 *
 */
(function ($) {
	var numbers_array = new Array(),
			upper_letters_array = new Array(),
			lower_letters_array = new Array(),
			special_chars_array = new Array(),
			$pGeneratorElement = null;
	var methods = {
		init: function (options, callbacks) {
			var settings = $.extend({
				'bind': 'click',
				'passwordElement': null,
				'displayElement': null,
				'passwordLength': 16,
				'uppercase': true,
				'lowercase': true,
				'numbers': true,
				'specialChars': true,
				'onPasswordGenerated': function (generatedPassword) {
				}
			}, options);
			for (var i = 48; i < 58; i++)
				numbers_array.push(i);
			for (i = 65; i < 91; i++)
				upper_letters_array.push(i);
			for (i = 97; i < 123; i++)
				lower_letters_array.push(i);
			special_chars_array = [33, 35, 64, 36, 38, 42, 91, 93, 123, 125, 92, 47, 63, 58, 59, 95, 45, 53];
			return this.each(function () {
				$pGeneratorElement = $(this);
				methods.generatePassword(settings);
			});
		},
		generatePassword: function (settings) {
			var password = new Array(),
					selOptions = settings.uppercase + settings.lowercase + settings.numbers + settings.specialChars,
					selected = 0,
					no_lower_letters = new Array();
			var optionLength = Math.floor(settings.passwordLength / selOptions);
			if (settings.uppercase) {
// uppercase letters
				for (var i = 0; i < optionLength; i++) {
					password.push(String.fromCharCode(upper_letters_array[randomFromInterval(0, upper_letters_array.length - 1)]));
				}
				no_lower_letters = no_lower_letters.concat(upper_letters_array);
				selected++;
			}
			if (settings.numbers) {
// numbers letters
				for (var i = 0; i < optionLength; i++) {
					password.push(String.fromCharCode(numbers_array[randomFromInterval(0, numbers_array.length - 1)]));
				}
				no_lower_letters = no_lower_letters.concat(numbers_array);
				selected++;
			}
			if (settings.specialChars) {
// numbers letters
				for (var i = 0; i < optionLength; i++) {
					password.push(String.fromCharCode(special_chars_array[randomFromInterval(0, special_chars_array.length - 1)]));
				}
				no_lower_letters = no_lower_letters.concat(special_chars_array);
				selected++;
			}
			var remained = settings.passwordLength - (selected * optionLength);
			if (settings.lowercase) {
				for (var i = 0; i < remained; i++) {
					password.push(String.fromCharCode(lower_letters_array[randomFromInterval(0, lower_letters_array.length - 1)]));
				}
			} else {
				for (var i = 0; i < remained; i++) {
					password.push(String.fromCharCode(no_lower_letters[randomFromInterval(0, no_lower_letters.length - 1)]));
				}
			}
			password = shuffle(password);
			passwordString = password.join('');
			if (settings.passwordElement !== null) {
				$(settings.passwordElement).val(passwordString);
			}
			if (settings.displayElement !== null) {
				if ($(settings.displayElement).is("input")) {
					$(settings.displayElement).val(passwordString);
				} else {
					$(settings.displayElement).text(passwordString);
				}
			}
			settings.onPasswordGenerated(passwordString);
		}
	};
	function shuffle(o) { //v1.0
		for (var j, x, i = o.length; i; j = parseInt(Math.random() * i), x = o[--i], o[i] = o[j], o[j] = x)
			;
		return o;
	}
	;
	function randomFromInterval(from, to)
	{
		return Math.floor(Math.random() * (to - from + 1) + from);
	}
	;
	$.fn.pGenerator = function (method) {
		if (methods[method]) {
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		} else if (typeof method === 'object' || !method) {
			return methods.init.apply(this, arguments);
		} else {
			$.error('Method ' + method + ' does not exist on jQuery.pGenerator');
		}
	};
})(jQuery);
function animateHide(obj) {
	$(obj + ' .animateHide').hide('blind', function () {
		$(obj + ' .animateHide').addClass('animateHidden');
		$(obj + ' .animateHide').removeClass('animateHide');
	});
	$(obj + ' .animateHidden').show('blind', function () {
		$(obj + ' .animateHidden').addClass('animateHide');
		$(obj + ' .animateHidden').removeClass('animateHidden');
	});
}


function footerResize() {
	var $parent = $("body");
	/****************************** FOOTER RESIZE ***************************/
	if ($parent.find('footer.cms .footerContent').length === 0) {
		return;
	}
	var windowHeight = $(window).height();
	var maxFooterlHeight = windowHeight - 100;
	$parent.find("footer.cms").resize({
		handle: 'n',
		maxHeight: maxFooterlHeight,
		minHeight: $parent.find("footer.cms label").outerHeight(),
		onStop: function (e, ui) {
			$.ajax('?height=' + ui.maxHeight + "&action=setMetaHeight&ajax=1");
		}

	});
	$parent.find("footer.cms .resizable-n").dblclick(function () {
		$parent.find("footer.cms").animate({
			maxheight: maxFooterlHeight,
		});
		$.ajax('?height=' + maxFooterlHeight + "&action=setMetaHeight&ajax=1");
	});
}


function toggleDisabled(element) {
	var $this = $(element);
	var $disabled = $this.next();
	if ($disabled.is(":disabled")) {
		$disabled.addClass('disabledRemoved');
		$this.next().removeAttr('disabled');
	} else {
		$disabled.removeClass('disabledRemoved');
		$disabled.attr('disabled', true);
	}
}

function appendHeadFiles(html) {
	$("head").append(html);
}

function pushNotifications(notificationContent) {
	var url = settings.siteRoot + "cms/";
	if (notificationContent.notification_href !== null) {
		url = notificationContent.notification_href;
	}


	var data = [];
	data.action = 'notificationPushed';
	data.ajax = '1';
	data.node_id = notificationContent.notification_id;
	data.target_id = notificationContent.notification_pushed_user_ids;


	callServerOnAjaxObjects(data, settings.siteRoot + '/cms');

	if (window.Notification && Notification.permission !== "denied") {
		var icon = settings.siteRoot + 'img/app_icon.png';
		if (notificationContent.notification_icon !== null) {
			icon = settings.siteRoot + notificationContent.notification_icon;
		}
		Notification.requestPermission(function (status) {  // status is "granted", if accepted by user
			var n = new Notification(notificationContent.notification_headline, {
				body: notificationContent.notification_text,
				requireInteraction: true,
				tag: notificationContent.notification_id,
				icon: icon // optional
			});

			n.onclick = function (event) {
				if (url.search(settings.siteRoot) >= 0) {
					window.loacation = url;
				} else {
					event.preventDefault(); // prevent the browser from focusing the Notification's tab
					window.open(url, '_blank');
				}
			}
		});

	}
}

function pushNotificationsPageHeader(number, notifications) {
	var title = document.title;
	var regex = /\((\d+)\)/;
	var str = title.replace(regex, '');
	if (number > 0) {
		document.title = '(' + number + ') ' + str;
		var list = '';
		var ids = '';
		notifications.forEach(function (notification, index) {
			if (notification.notification_pushed === '0') {
				pushNotifications(notification);
			}
		});
		$("div.bottomMenu div.notifications").addClass('selected');
		$("div.bottomMenu div.notifications").attr('data-number', number);
	} else if (number === '0') {
		document.title = str;
		$("div.bottomMenu div.notifications").removeClass('selected');
		$("div.bottomMenu div.notifications").attr('data-number', '');
	} else {
		document.title = str;
		$("div.bottomMenu div.notifications").removeClass('selected');
		$("div.bottomMenu div.notifications").attr('data-number', '');
	}

}


function copyToClipboard(e) {

	$(e.target).addClass('confirm');
	if ($(e.target).prop('tagName') === 'A') {
		e.preventDefault();
		var text = $(e.target).attr('href');
	} else if ($(e.delegateTarget).prop('tagName') === 'A') {
		var text = $(e.delegateTarget).attr('href');
	} else {
		var text = $(e.target).html();
	}
	if (typeof $(e.target).attr('data-copy') !== 'undefined') {
		var text = $(e.target).attr('data-copy');
	}

	if ((typeof text) === 'undefined') {
		var text = e.element.innerText;
		if (typeof $(e.element).attr('data-copy') !== 'undefined') {
			var text = $(e.element).attr('data-copy');
		}
	}

	if (window.clipboardData && window.clipboardData.setData) {
		return clipboardData.setData("Text", text);
	} else if (document.queryCommandSupported && document.queryCommandSupported("copy")) {
		var textarea = document.createElement("textarea");
		textarea.textContent = text;
		textarea.style.position = "fixed";
		document.body.appendChild(textarea);
		textarea.select();
		try {
			return document.execCommand("copy");
		} catch (ex) {
			alert("Copy to clipboard failed.");
			return false;
		} finally {
			document.body.removeChild(textarea);
		}
	}
}
