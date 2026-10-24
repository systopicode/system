/* A portrait form accepts a dropped image and posts it as a normal upload. */
(function () {
	function zone(target) {
		return target && target.closest ? target.closest('form.portraitDrop') : null;
	}

	document.addEventListener('dragover', function (event) {
		var form = zone(event.target);
		if (!form) {
			return;
		}
		event.preventDefault();
		form.classList.add('is-over');
	});

	document.addEventListener('dragleave', function (event) {
		var form = zone(event.target);
		if (!form || form.contains(event.relatedTarget)) {
			return;
		}
		form.classList.remove('is-over');
	});

	document.addEventListener('drop', function (event) {
		var form = zone(event.target);
		if (!form) {
			return;
		}
		event.preventDefault();
		form.classList.remove('is-over');
		var file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
		var input = form.querySelector('input[type="file"]');
		if (!file || !input || String(file.type || '').indexOf('image/') !== 0 || typeof DataTransfer === 'undefined') {
			return;
		}
		var list = new DataTransfer();
		list.items.add(file);
		input.files = list.files;
		form.submit();
	});
})();
