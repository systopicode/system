
/****************************** KEEPRESS FUNCTIONSs ***************************/
document.addEventListener('click', function (event) {
	var button = event.target.closest ? event.target.closest('[data-copy]') : null;
	if (!button || !navigator.clipboard) {
		return;
	}
	var text = button.getAttribute('data-copy') || '';
	navigator.clipboard.writeText(text).then(function () {
		button.classList.add('is-copied');
	});
});

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
	if (navigator.clipboard) {
		navigator.clipboard.writeText(text);
	}
}

$(document).keydown(function (event) {
	if (event.ctrlKey || event.metaKey) {
		switch (String.fromCharCode(event.which).toLowerCase()) {
			case 's':
				event.preventDefault();
				break;
			case 'p':
				event.preventDefault();
				if ($("body").hasClass('pages')) {
					var url = settings.siteRoot + "?id=" + userState.pages.id + "&preview=1";
					var popup = window.open(url, '_newtab');
					if (!popup) {
						alert("Your Browser is blocking PopUps for this Website.\nPlease check your Browser settings.");
						$("a.previewLink").click().addClass("keypressHighlight");
					} else {
						popup.focus();

					}
					;

				}
				break;
			case 'i':
				event.preventDefault();
				$("input[type='search']").focus();
				$("input.searchinput").focus();
				break;
		}
	}
});