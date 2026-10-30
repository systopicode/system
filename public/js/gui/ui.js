
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
	icon.className = 'cmsIcon cmsIcon-' + img.dataset.broken + ' thumbBroken';
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
// Informational sheets close through their regular, history-free navigation.
document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape' || event.defaultPrevented) return;
    var boxes = Array.from(document.querySelectorAll('.cmslightbox[data-transient-info]')).filter(box => box.getClientRects().length);
    var close = boxes.at(-1)?.querySelector('header a[data-history="false"]');
    if (!close) return;
    event.preventDefault();
    close.click();
});

/** Rename administrative roles/groups using the same compact header as tags. */
function renameUserAdminTitle(button) {
    var heading = button.closest('header').querySelector('h1');
    if (heading.isContentEditable) { heading.blur(); return; }
    var previous = heading.textContent;
    var cancelled = false;
    heading.setAttribute('contenteditable', 'plaintext-only');
    var finish = function () {
        heading.removeEventListener('blur', finish);
        heading.removeEventListener('keydown', keydown);
        heading.removeAttribute('contenteditable');
        var value = heading.textContent.replace(/\s+/g, ' ').trim();
        if (cancelled || !value) { heading.textContent = previous; return; }
        heading.textContent = value;
        if (value !== previous) {
            var values = {};
            values[button.dataset.renameField || 'value'] = value;
            sys.core.server.sendCall({values: values}, button.dataset.renameUrl);
        }
    };
    var keydown = function (event) {
        if (event.key !== 'Enter' && event.key !== 'Escape') return;
        event.preventDefault();
        event.stopPropagation();
        cancelled = event.key === 'Escape';
        heading.blur();
    };
    heading.addEventListener('blur', finish);
    heading.addEventListener('keydown', keydown);
    heading.focus();
    selectElementContents(heading);
}

// Autosaved technical keys use native validity without sending invalid changes.
['input', 'change', 'blur', 'focusout'].forEach(function (type) {
    document.addEventListener(type, function (event) {
        var input = event.target;
        if (!input.matches || !input.matches('input[data-setting-key]')) return;
        var valid = input.checkValidity();
        input.setAttribute('aria-invalid', valid ? 'false' : 'true');
        if (!valid) {
            event.stopImmediatePropagation();
            if (type !== 'input') input.reportValidity();
        }
    }, true);
});
