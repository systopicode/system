/* global sys, cmsJobs */
(function () {
	var setup = {
		info: 'Backup panel',
		route: 'panels.cms.backup',
		extends: 'panels.cms',
		spanSize: 3 * 1024 * 1024
	};
	sys.lib.add(setup, function () {
		sys.lib.applyParentConstructor(this, setup, []);
		var backup = this;

		backup.upload = function (event) {
			var input = event.sourceElement && event.sourceElement.files ? event.sourceElement : null;
			if (!input || !input.files || !input.files.length) {
				return;
			}
			var files = Array.prototype.slice.call(input.files);
			input.value = '';
			var index = 0;
			backup.button = input.closest('label') || input;
			// fixed at the start: a relative address would follow the user to another panel
			backup.address = new URL('upload', location.href).pathname;
			backup.from = location.pathname;
			cmsJobs.update('upload', { title: files[0].name, done: 0, total: 1, el: backup.button });
			var next = function () {
				if (index >= files.length) {
					cmsJobs.clear('upload');
					return;
				}
				backup.sendSpans(files[index], function () {
					index++;
					next();
				});
			};
			next();
		};

		// One slice at a time, so a large zip never sits whole in memory.
		backup.sendSpans = function (file, done) {
			var spanCount = Math.max(1, Math.ceil(file.size / setup.spanSize));
			var spanIndex = 0;
			var send = function () {
				var start = spanIndex * setup.spanSize;
				var last = spanIndex + 1 >= spanCount;
				var end = last ? file.size : start + setup.spanSize;
				var reader = new FileReader();
				reader.onload = function () {
					var call = new sys.core.server.call({
						name: file.name,
						spanIndex: spanIndex,
						spanCount: spanCount,
						lastSpan: last,
						away: location.pathname !== backup.from
					});
					call.binary = reader.result;
					call.send(backup.address).done(function (data) {
						if (data && data.error) {
							cmsJobs.clear('upload');
							return;
						}
						spanIndex++;
						cmsJobs.update('upload', {
							title: file.name,
							done: spanIndex,
							total: spanCount,
							el: backup.button
						});
						last ? done() : send();
					}).fail(function () {
						cmsJobs.clear('upload');
					});
				};
				reader.readAsArrayBuffer(file.slice(start, end));
			};
			send();
		};
	});
})();
