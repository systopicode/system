/* One status line in the footer bar. Upload and video formats share it. */
(function () {
	var slots = {};

	function esc(value) {
		return String(value).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function paint() {
		var el = document.querySelector('footer.cmsStatusbar .cmsJobs');
		if (!el) {
			return;
		}
		var html = '';
		['upload', 'video'].forEach(function (id) {
			var job = slots[id];
			if (!job) {
				return;
			}
			var total = Math.max(1, job.total || 0);
			var done = job.done || 0;
			var pct = Math.round((done / total) * 100);
			var spin = done < job.total ? '<i class="spin"></i>' : '';
			html += '<span class="job">' + spin
				+ '<b>' + esc(job.title) + '</b>'
				+ '<span>' + done + ' / ' + job.total + '</span>'
				+ '<span class="bar"><i style="width:' + pct + '%"></i></span>'
				+ '</span>';
		});
		if (!html) {
			el.classList.remove('on');
			el.hidden = true;
			el.innerHTML = '';
			return;
		}
		el.hidden = false;
		el.classList.add('on');
		el.innerHTML = html;
	}

	window.cmsJobs = {
		update: function (id, state) {
			if (!state) {
				delete slots[id];
			} else {
				slots[id] = state;
			}
			paint();
		},
		clear: function (id) {
			delete slots[id];
			paint();
		}
	};
})();
