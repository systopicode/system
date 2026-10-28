
// A thumb that does not load shows the crossed-out icon it names
// (data-broken="image-slash") instead of the browser's broken picture. Its
// parent gets .thumbMissing. Images that failed before this listener existed,
// or before a render inserted them, are caught by the sweep.
function markBrokenThumb(img) {
	var tile = img.parentElement;
	if (!tile || tile.classList.contains('thumbMissing')) {
		return;
	}
	var icon = document.createElement('i');
	icon.className = 'fa-regular fa-' + img.dataset.broken + ' thumbBroken';
	icon.title = 'The picture cannot be shown';
	tile.classList.add('thumbMissing');
	img.after(icon);
}
document.addEventListener('error', function (event) {
	if (event.target.tagName === 'IMG' && event.target.dataset.broken) {
		markBrokenThumb(event.target);
	}
}, true);
document.addEventListener('load', function (event) {
	var img = event.target;
	if (!img || img.tagName !== 'IMG' || !img.dataset.broken || !img.naturalWidth) {
		return;
	}
	var tile = img.parentElement;
	if (!tile) {
		return;
	}
	tile.classList.remove('thumbMissing');
	var icon = tile.querySelector(':scope > i.thumbBroken');
	if (icon) {
		icon.remove();
	}
}, true);
function sweepBrokenThumbs() {
	document.querySelectorAll('img[data-broken]').forEach(function (img) {
		if (img.complete && !img.naturalWidth) {
			markBrokenThumb(img);
		}
	});
}
document.addEventListener('DOMContentLoaded', sweepBrokenThumbs);
document.addEventListener('cms:rendered', sweepBrokenThumbs);

/****************************** KEEPRESS FUNCTIONSs ***************************/
document.addEventListener('click', function (event) {
	var open = event.target.closest ? event.target.closest('[data-open-box]') : null;
	if (open) {
		var box = document.querySelector(open.getAttribute('data-open-box'));
		if (box) {
			box.hidden = false;
			// Add device: a phone opens the camera from this click. A desktop
			// stays on the QR. The click is the gesture the prompt needs.
			var phone = window.matchMedia('(max-width: 720px), (hover: none) and (pointer: coarse)').matches;
			if (phone && box.classList.contains('remoteLoginBox') && window.QrScan) {
				var scanHost = box.querySelector('[data-qr-host="profile"]');
				if (scanHost) window.QrScan.start(scanHost);
			}
		}
		return;
	}
	var shut = event.target.closest ? event.target.closest('[data-box-close]') : null;
	if (shut) {
		var host = shut.closest('.cmslightbox');
		if (host) host.hidden = true;
	}
});

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