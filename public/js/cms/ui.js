
/****************************** KEEPRESS FUNCTIONSs ***************************/
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