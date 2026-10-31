/* One status line in the footer bar. Upload and video formats share it. */
(function () {
	var slots = {};
    var announcement;
    function announce(text) {
        if(!announcement?.isConnected){announcement=document.createElement('span');announcement.className='cmsA11yStatus';announcement.setAttribute('role','status');announcement.setAttribute('aria-live','polite');document.body.append(announcement);}
        announcement.textContent=text;
    }

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
            let details = line.querySelector('button');
            if (job.open && !details) { details=document.createElement('button');details.type='button';details.className='button transparent';details.textContent='Details';line.append(details); }
            if (details) { details.hidden=!job.open;details.onclick=job.open || null; }
			var total = Math.max(1, job.total || 0);
			var done = job.done || 0;
			// progress (0..1) for the bar when the count alone moves too seldom (one big file)
			var fraction = Math.max(0,Math.min(1,job.progress !== undefined ? job.progress : done / total));
            var pct = Math.round(fraction * 100) + '%';
            var bar=line.querySelector('.bar');
            bar.setAttribute('role','progressbar');bar.setAttribute('aria-label',job.title || 'Progress');
            bar.setAttribute('aria-valuemin','0');bar.setAttribute('aria-valuemax','100');
            if(job.progress !== undefined || job.total > 0)bar.setAttribute('aria-valuenow',String(Math.round(fraction*100)));else bar.removeAttribute('aria-valuenow');
            bar.setAttribute('aria-valuetext',job.total > 0 ? done+' of '+job.total : 'In progress');
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

    // The shared dialog code can move or replace the footer while a job runs.
    document.addEventListener('cms:rendered', paint);
    new MutationObserver(records => {
        if (records.some(record => [...record.addedNodes].some(node => node instanceof Element && (node.matches('footer.cmsStatusbar') || node.querySelector('footer.cmsStatusbar'))))) paint();
    }).observe(document.documentElement, {childList:true, subtree:true});

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
            const prior=slots[id];
            if(state&&!prior)announce((state.title||'Task')+' started');
            if(state&&state.total>0&&state.done>=state.total&&(!prior||prior.done<prior.total))announce((state.title||'Task')+' completed');
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
