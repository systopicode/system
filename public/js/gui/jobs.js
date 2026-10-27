/* One status line in the footer bar. Upload and video formats share it. */
(function () {
	var slots = {};

	function esc(value) {
		return String(value).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	// A line is built once and then only its numbers change: rebuilt on every
	// slice of an upload, the spinner restarts and the bar flickers.
	function paint() {
		var el = document.querySelector('footer.cmsStatusbar .cmsJobs');
		if (!el) {
			return;
		}
		var any = false;
		['upload', 'video'].forEach(function (id) {
			var job = slots[id];
			var line = el.querySelector('span.job[data-job="' + id + '"]');
			if (!job) {
				line && line.remove();
				return;
			}
			any = true;
			if (!line) {
				line = document.createElement('span');
				line.className = 'job';
				line.dataset.job = id;
				line.innerHTML = '<i class="spin"></i><b></b><span></span><span class="bar"><i></i></span>';
				el.appendChild(line);
			}
			var total = Math.max(1, job.total || 0);
			var done = job.done || 0;
			// progress (0..1) for the bar when the count alone moves too seldom (one big file)
			var pct = Math.round((job.progress !== undefined ? job.progress : done / total) * 100) + '%';
			if (job.el) {
				job.el.classList.add('is-busy');
				job.el.style.setProperty('--jobProgress', pct);
			}
			line.querySelector('i.spin').hidden = done >= job.total;
			line.querySelector('b').innerHTML = esc(job.title);
			line.querySelector('b + span').textContent = done + ' / ' + job.total;
			line.querySelector('.bar i').style.width = pct;
		});
		el.hidden = !any;
		el.classList.toggle('on', any);
	}

	// Closing or reloading the tab would cut a running upload, backup or
	// restore off. Video formats are rendered on the server and carry on.
	window.addEventListener('beforeunload', function (event) {
		var server = window.sys && sys.core && sys.core.server;
		if (slots.upload || (server && (server.uploading > 0 || server.backupBusy))) {
			event.preventDefault();
			event.returnValue = '';
		}
	});

	// state.el: the button or tile the job started from - it shows the progress too
	function release(id, next) {
		var el = slots[id] && slots[id].el;
		if (el && (!next || next.el !== el)) {
			el.classList.remove('is-busy');
			el.style.removeProperty('--jobProgress');
		}
	}

	window.cmsJobs = {
		update: function (id, state) {
			release(id, state);
			if (!state) {
				delete slots[id];
			} else {
				slots[id] = state;
			}
			paint();
		},
		clear: function (id) {
			release(id);
			delete slots[id];
			paint();
		}
	};
})();
