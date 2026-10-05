/* global sys,c,p,t,l,history,settings */
(function () {
	var setup = {
		route: 'classes.fs.uploader',
		shorthand: 'fsUploader',
		spanSize: 1024 * 1024 * 2,
		maxPreviewFilesize: 1024 * 1024 * 50,
		conf: {
			actions: [{label: 'upload', href: 'sendData'}],
			labels: {
				uplodaComplete: 'Upload complete',
				save: 'Speichern',
				upload: 'upload'
			}
		}
	};

	var pending = new WeakMap();

	function fileMatchesAccept(file, accept) {
		if (!accept) return true;
		var type = String(file.type || '').toLowerCase();
		var name = String(file.name || '').toLowerCase();
		return String(accept).split(',').some(function (token) {
			token = token.trim().toLowerCase();
			if (!token) return false;
			if (token.slice(-2) === '/*') return type.indexOf(token.slice(0, -1)) === 0;
			if (token.charAt(0) === '.') return name.slice(-token.length) === token;
			return type === token;
		});
	}

	function extractDropUrls(dt) {
		var urls = [];
		if (!dt || typeof dt.getData !== 'function') return urls;
		var uriList = '';
		try { uriList = dt.getData('text/uri-list') || ''; } catch (e) { uriList = ''; }
		uriList.split('\n').forEach(function (line) {
			line = line.trim();
			if (line && line.charAt(0) !== '#') urls.push(line);
		});
		if (!urls.length) {
			var html = '';
			try { html = dt.getData('text/html') || ''; } catch (e) { html = ''; }
			var match = html.match(/<img[^>]+src=["']([^"']+)["']/i) || html.match(/<a[^>]+href=["']([^"']+)["']/i);
			if (match) urls.push(match[1]);
		}
		if (!urls.length) {
			var plain = '';
			try { plain = (dt.getData('text/plain') || '').trim(); } catch (e) { plain = ''; }
			if (/^https?:\/\//i.test(plain)) urls.push(plain);
		}
		return urls.filter(function (url) {
			return /^https?:\/\//i.test(url);
		});
	}

	function isFileOrUrlDrag(dt) {
		if (!dt || !dt.types) return false;
		var types = Array.prototype.slice.call(dt.types);
		return types.indexOf('Files') !== -1 || types.indexOf('text/uri-list') !== -1;
	}

	function uploadModeOf(el) {
		if (!el || !el.closest) return 'instant';
		var marked = el.closest('[data-upload]');
		return marked ? (marked.getAttribute('data-upload') || 'instant') : 'instant';
	}

	function findDropHost(el) {
		if (!el || !el.closest) return null;
		return el.closest('[data-drop-files]')
			|| el.closest('div.uploader')
			|| (el.matches && el.matches('input[type="file"]') ? el : null)
			|| (el.closest && el.closest('input[type="file"]'));
	}

	function fileInputIn(host) {
		if (!host) return null;
		if (host.matches && host.matches('input[type="file"]')) return host;
		return host.querySelector('input[type="file"]');
	}

	function isTextPasteTarget(el) {
		if (!el || !el.closest) return false;
		if (el.isContentEditable) return true;
		var field = el.closest('input, textarea, select, [contenteditable="true"], [contenteditable=""]');
		if (!field) return false;
		if (field.matches && field.matches('input[type="file"]')) return false;
		if (field.tagName === 'INPUT') {
			var type = String(field.type || 'text').toLowerCase();
			if (['button', 'submit', 'reset', 'checkbox', 'radio', 'hidden', 'range', 'color', 'file'].indexOf(type) !== -1) {
				return false;
			}
		}
		return true;
	}

	function clipboardHasFiles(dt) {
		if (!dt) return false;
		if (dt.files && dt.files.length) return true;
		if (dt.items && dt.items.length) {
			for (var i = 0; i < dt.items.length; i++) {
				if (dt.items[i].kind === 'file') return true;
			}
		}
		return false;
	}

	function namedClipboardFile(file) {
		if (!file) return file;
		if (file.name && file.name !== 'blob' && file.name !== 'image.png') return file;
		var ext = ((file.type || 'image/png').split('/')[1] || 'png').replace('jpeg', 'jpg');
		try {
			return new File([file], 'screenshot.' + ext, {type: file.type || 'image/png'});
		} catch (e) {
			return file;
		}
	}

	function dropZonesIn(root) {
		if (!root || !root.querySelectorAll) return [];
		return Array.prototype.slice.call(root.querySelectorAll('[data-drop-files], div.uploader'));
	}

	function prepareDropHost(host) {
		if (!host || host === document) return;
		if (!host.hasAttribute('tabindex')) host.setAttribute('tabindex', '0');
	}

	function findPasteHost(el) {
		var host = findDropHost(el);
		if (host && host.closest) {
			host = host.closest('[data-drop-files], div.uploader') || host;
			if (host.matches && (host.matches('[data-drop-files]') || host.matches('div.uploader'))) return host;
		}
		var box = (el && el.closest) ? el.closest('.cmslightbox, .cmslightboxContent') : null;
		var zones = dropZonesIn(box || document.querySelector('.cmslightbox') || document.body);
		if (!zones.length) return null;
		if (zones.length === 1) return zones[0];
		for (var i = 0; i < zones.length; i++) {
			if (zones[i] === document.activeElement || zones[i].contains(document.activeElement)) return zones[i];
		}
		for (var j = 0; j < zones.length; j++) {
			var inp = fileInputIn(zones[j]);
			var acc = (inp && inp.getAttribute('accept')) || zones[j].getAttribute('data-accept') || '';
			if (!acc || /image\//i.test(acc)) return zones[j];
		}
		return zones[0];
	}

	function applyResolvedToHost(host, files) {
		var first = files && files[0];
		var input = fileInputIn(host);
		if (input && first instanceof File) {
			assignFileToInput(input, first);
			return;
		}
		if (input && first && first.id) {
			pending.set(input, first);
			if (first.file instanceof File) {
				try {
					var dt = new DataTransfer();
					dt.items.add(first.file);
					input.files = dt.files;
				} catch (err) {}
				input.dispatchEvent(new Event('change', {bubbles: true}));
			} else {
				previewRemote(input, first.previewUrl || first.href);
			}
			return;
		}
		if (host.classList.contains('projectMmlDropZone')) {
			host.dispatchEvent(new CustomEvent('fsUploaderDrop', {bubbles: true, detail: {files: files.filter(function (f) { return f instanceof File; }), group: host.dataset.group || 'gallery'}}));
		}
	}

	function setHidden(form, name, value) {
		var input = form.querySelector('input[type="hidden"][name="' + name + '"]');
		if (!input) {
			input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			form.appendChild(input);
		}
		input.value = value;
	}

	function assignFileToInput(input, file) {
		if (!input || !file) return;
		try {
			var dt = new DataTransfer();
			dt.items.add(file);
			input.files = dt.files;
		} catch (e) { /* DataTransfer assignment not supported */ }
		pending.set(input, {file: file, id: null, name: file.name, type: file.type || ''});
		input.dispatchEvent(new Event('change', {bubbles: true}));
	}

	function previewRemote(input, url) {
		if (!input || !url) return;
		var host = input.closest('[data-drop-files]') || input.parentElement;
		if (!host) return;
		var img = host.querySelector('[data-role="contactPreviewImg"], [data-role="eventLogoPreviewImg"], [data-role="eventHeaderPreviewImg"], img');
		var wrap = host.querySelector('[data-role="contactPreviewWrap"], [data-role="eventLogoPreview"], [data-role="eventHeaderPreview"], .currentImage');
		if (!img && wrap) {
			img = document.createElement('img');
			img.alt = '';
			wrap.appendChild(img);
		}
		if (img) img.src = url;
		if (wrap) {
			wrap.classList.remove('placeholder');
			var icon = wrap.querySelector('i');
			if (icon) icon.remove();
		}
	}

	function FsUploader(doc, conf) {
		var that = this;
		for (var prop in setup.conf) {
			that[prop] = (conf ? conf[prop] : false) || setup.conf[prop];
		}
		that.uploadMode = (conf && conf.upload) || (doc && doc.box && doc.box.dataset && doc.box.dataset.upload) || 'instant';
		if (that.uploadMode === 'onsubmit' && that.actions && that.actions[0]) {
			that.actions[0].label = setup.conf.labels.save;
		}

		that.construct = () => {
			that.info = {};
			if (!doc || !doc.input) return;
			doc.input.onchange = () => {
				that.readFileFromInput(doc.input);
			};
			var dropRoot = doc.box || doc.uploader || doc.input.parentElement;
			if (dropRoot) {
				dropRoot.addEventListener('dragover', function (e) {
					if (!isFileOrUrlDrag(e.dataTransfer)) return;
					e.preventDefault();
					e.dataTransfer.dropEffect = 'copy';
					dropRoot.classList.add('dragover');
				});
				dropRoot.addEventListener('dragleave', function (e) {
					if (!e.relatedTarget || !dropRoot.contains(e.relatedTarget)) {
						dropRoot.classList.remove('dragover');
					}
				});
				dropRoot.addEventListener('drop', function (e) {
					dropRoot.classList.remove('dragover');
					if (!isFileOrUrlDrag(e.dataTransfer)) return;
					e.preventDefault();
					e.stopPropagation();
					var accept = doc.input.getAttribute('accept') || '';
					FsUploader.resolveDataTransfer(e.dataTransfer, accept).then(function (files) {
						if (files && files[0]) {
							that.readFromFile(files[0]);
						}
					}).catch(function (err) {
						alert((err && err.message) ? err.message : 'Drop fehlgeschlagen');
					});
				});
			}
		};

		that.readFromFile = function (file) {
			if (!file) return;
			that.fileInfo = file;
			if (doc.input) assignFileToInput(doc.input, file);
			else pending.set(doc.input || doc.box, {file: file, id: null, name: file.name, type: file.type || ''});
			that.type = that.type || String(file.type || '').split('/')[0];
			if (doc.box) doc.box.dataset.filetype = that.type;
			if (file.size > setup.maxPreviewFilesize) {
				if (doc.that) doc.that.textContent = 'File is larger than ... preview skipped';
				that.preview();
				return;
			}
			if (doc.barinfo1) that.updateBar(0, '');
			var reader = new FileReader();
			reader.onload = function () {
				that.src = reader.result;
				that.preview();
			};
			reader.onprogress = function (e) {
				if (e.total) that.updateBar(e.loaded / e.total, 'reading file');
			};
			if (doc.box) doc.box.className = 'reading';
			reader.readAsDataURL(file);
		};

		that.readFileFromInput = (input) => {
			if (!input || !input.files || !input.files[0]) return;
			that.readFromFile(input.files[0]);
		};
		that.readString = (callback) => {
			that.updateBar(0, '');
			var reader = new FileReader();
			reader.onload = function () {
				that.string = reader.result;
				if (callback) {
					callback(that.string);
				}
			};
			reader.onprogress = function (e) {
				that.updateBar(e.loaded / e.total, 'reading file');
			};
			doc.box.className = 'reading';
			reader.readAsText(that.fileInfo);
		};
		that.updateBar = (progress, info) => {
			if (!doc.barinfo1) return;
			doc.barinfo1.textContent = info;
			if (doc.barinfo2) doc.barinfo2.textContent = info;
			if (doc.bar) doc.bar.style.width = Math.round(progress * 100) + '%';
		};
		that.preview = () => {
			if (that.onPreview) {
				that.onPreview(that);
			}
			that.resetPreviewImage();
			that.updateBar(0, '');
			switch (that.fileInfo.type) {
				case 'image/jpeg':
				case 'image/png':
				case 'image/gif':
				case 'image/webp':
					if (doc.title) doc.title.textContent = ' - Image ';
					that.updatePreviewImage(() => {
						that.updateFileInfo();
					});
					break;
				case 'image/svg+xml':
					if (doc.title) doc.title.textContent = ' - Vector ';
					that.updatePreviewImage(() => {
						that.updateFileInfo();
					});
					that.type = 'vector';
					if (doc.box) doc.box.dataset.filetype = 'vector';
					break;
				case 'video/mp4':
				case 'video/quicktime':
					if (doc.title) doc.title.textContent = '- Video';
					that.updateFileInfo();
					that.updatePreviewVideo();
					break;
				case 'audio/mpeg':
					if (doc.title) doc.title.textContent = '- Audio';
					that.updateFileInfo();
					that.updatePreviewAudio();
					break;
				default:
					l('unknown:', that.fileInfo.type);
					that.type = 'generic';
					that.updateFileInfo();
			}
		};

		that.resetPreviewImage = () => {
			if (doc.box) doc.box.className = 'idle';
			if (doc.img) {
				doc.img.src = '';
				doc.img.style.width = 'auto';
			}
			that.info.width = null;
			that.info.height = null;
		};

		that.updatePreviewImage = (callback) => {
			if (!doc.img) {
				callback();
				return;
			}
			doc.img.onload = () => {
				that.info.width = doc.img.width;
				that.info.height = doc.img.height;
				doc.img.style.width = '100%';
				callback();
			};
			doc.img.src = that.src;
		};

		that.updatePreviewVideo = () => {
			if (!doc.video) return;
			doc.video.src = that.src;
			doc.video.onloadedmetadata = (event) => {
				that.thumbTime = doc.video.duration / 2;
				doc.video.currentTime = doc.video.duration / 2;
				if (doc.videoRange) doc.videoRange.value = (doc.video.duration / 2) / doc.video.duration * 100;
			};
			if (doc.videoRange) {
				doc.videoRange.onchange = () => {
					var barToTime = (doc.videoRange.value / 100) * doc.video.duration;
					doc.video.currentTime = barToTime;
					that.thumbTime = barToTime;
				};
			}
		};

		that.updatePreviewAudio = () => {
			if (doc.audio) doc.audio.src = that.src;
		};

		that.updateFileInfo = () => {
			if (!doc.info) {
				that.setAction();
				if (doc.box) doc.box.className = 'preview' + (that.replace ? ' replace' : '');
				return;
			}
			var info = '<b>File Name: </b> ' + that.fileInfo.name;
			info += '<br><b>File Type: </b> ' + that.fileInfo.type;
			info += '<br><b>Size: </b> ' + printFileSize(that.fileInfo.size);
			if (that.info.width) {
				info += '<br><b>Dimensions: </b>' + that.info.width + ' X ' + that.info.height + ' px';
			}
			doc.info.innerHTML = info;
			doc.box.className = 'preview' + (that.replace ? ' replace' : '');
			that.setAction();
		};

		that.setAction = () => {
			if (!doc.actions || !that.actions) return;
			if (that.uploadMode === 'onsubmit') {
				return;
			}
			that.actions.forEach(action => {
				if (!action.button) {
					action.button = document.createElement('button');
				}
				doc.actions.append(action.button);
				action.button.textContent = action.label;
				action.button.onclick = e => {
					that.prepareFile(action);
				};
			});
		};

		that.prepareFile = action => {
			var reader = new FileReader();
			reader.onload = function () {
				that.blob = reader.result;
				that.uploadFile(action);
			};
			reader.onprogress = function (e) {
				that.updateBar(e.loaded / e.total, 'preparing file');
			};
			if (doc.box) doc.box.className = 'preparing';
			reader.readAsArrayBuffer(that.fileInfo);
		};
		that.uploadFile = action => {
			if (doc.box) doc.box.className = 'uploading';
			that.size = that.blob.byteLength;
			that.spanIndex = 0;
			that.spanCount = Math.ceil(that.size / setup.spanSize);
			that.uploadFileSpan(action);
		};
		that.uploadFileSpan = action => {
			var spanSize, lastSpan;
			var spanStart = that.spanIndex * setup.spanSize;
			if ((that.spanIndex + 1) * setup.spanSize < that.size) {
				spanSize = setup.spanSize;
				lastSpan = false;
			} else {
				spanSize = that.size - that.spanIndex * setup.spanSize;
				lastSpan = true;
			}
			var spanEnd = spanStart + spanSize;
			var fileInfo = {};
			for (var prop in that.fileInfo) {
				fileInfo[prop] = that.fileInfo[prop];
			}
			var callData = {
				fileInfo: JSON.stringify(fileInfo),
				id: that.id ? that.id : null,
				size: that.size,
				spanIndex: that.spanIndex,
				spanSize: spanSize,
				lastSpan: lastSpan
			};
			l(callData);
			var call = new sys.core.server.call(callData);
			call.binary = that.blob.slice(spanStart, spanEnd);
			call.send(action.href)
					.onloadstart(e => {
						that.updateBar(0, 'upload started');
					})
					.onupdate(e => {
						var barinfo = 'uploading filepart ' + (that.spanIndex + 1) + ' / ' + that.spanCount;
						var spanProgress = e.loaded / e.total;
						var progress = (that.spanIndex + spanProgress) / that.spanCount;
						that.updateBar(progress, barinfo);
					})
					.done(response => {
						if (!response || !response.id) {
							l('no id returend from server', response);
							return;
						}
						that.id = response.id;
						if (lastSpan) {
							if (doc.box) doc.box.className = 'done';
							that.updateBar(100, that.labels.uploadComplete || that.labels.uplodaComplete);
						} else {
							that.spanIndex++;
							that.uploadFileSpan(action);
						}
					});
		};
		that.construct();
	}

	FsUploader.resolveDataTransfer = function (dt, accept) {
		return new Promise(function (resolve, reject) {
			if (!dt) {
				resolve([]);
				return;
			}
			var files = [];
			if (dt.files && dt.files.length) {
				Array.prototype.forEach.call(dt.files, function (file) {
					if (fileMatchesAccept(file, accept)) files.push(file);
				});
			}
			if (files.length) {
				resolve(files);
				return;
			}
			if (dt.items && dt.items.length) {
				for (var i = 0; i < dt.items.length; i++) {
					if (dt.items[i].kind === 'file') {
						var asFile = dt.items[i].getAsFile && dt.items[i].getAsFile();
						if (asFile && fileMatchesAccept(asFile, accept)) files.push(asFile);
					}
				}
			}
			if (files.length) {
				resolve(files);
				return;
			}
			var urls = extractDropUrls(dt);
			if (!urls.length) {
				resolve([]);
				return;
			}
			FsUploader.fetchRemoteUrl(urls[0], accept).then(function (result) {
				if (result && result.file) resolve([result.file]);
				else resolve(result ? [result] : []);
			}).catch(reject);
		});
	};

	FsUploader.fetchRemoteUrl = function (url, accept) {
		return new Promise(function (resolve, reject) {
			fetch(url, {mode: 'cors'}).then(function (res) {
				if (!res.ok) throw new Error('HTTP ' + res.status);
				return res.blob();
			}).then(function (blob) {
				var name = (url.split('?')[0].split('/').pop() || 'download');
				var file = new File([blob], name, {type: blob.type || 'application/octet-stream'});
				if (!fileMatchesAccept(file, accept)) {
					reject(new Error('Dateityp nicht erlaubt'));
					return;
				}
				resolve({file: file, id: null, name: file.name, type: file.type, previewUrl: url});
			}).catch(function () {
				var call = new sys.core.server.call({url: url, accept: accept || ''});
				call.send('fetchFromUrl').done(function (result) {
					if (!result || !result.ok) {
						reject(new Error((result && result.error) ? result.error : 'Download fehlgeschlagen'));
						return;
					}
					var staged = {
						file: null,
						id: result.id,
						name: result.name,
						type: result.type,
						previewUrl: url,
						href: result.href
					};
					if (result.href) {
						fetch(result.href, {credentials: 'same-origin'}).then(function (res) {
							return res.ok ? res.blob() : Promise.reject();
						}).then(function (blob) {
							staged.file = new File([blob], result.name, {type: result.type || blob.type});
							resolve(staged);
						}).catch(function () {
							resolve(staged);
						});
					} else {
						resolve(staged);
					}
				});
			});
		});
	};

	FsUploader.storeFile = function (file, onprogress) {
		return new Promise(function (resolve, reject) {
			var reader = new FileReader();
			reader.onerror = function () { reject(new Error('Datei konnte nicht gelesen werden')); };
			reader.onload = function () {
				var blob = reader.result;
				var id = null;
				var spanIndex = 0;
				var spanSize = setup.spanSize;
				var size = blob.byteLength;
				var spanCount = Math.ceil(size / spanSize) || 1;
				function sendSpan() {
					var spanStart = spanIndex * spanSize;
					var lastSpan = (spanIndex + 1) * spanSize >= size;
					var thisSize = lastSpan ? (size - spanStart) : spanSize;
					var fileInfo = {name: file.name, type: file.type, size: file.size};
					var call = new sys.core.server.call({
						fileInfo: JSON.stringify(fileInfo),
						id: id,
						size: size,
						spanIndex: spanIndex,
						spanSize: thisSize,
						lastSpan: lastSpan
					});
					call.binary = blob.slice(spanStart, spanStart + thisSize);
					call.send('storeSpan')
						.onupdate(function (e) {
							if (onprogress && e.total) {
								onprogress((spanIndex + e.loaded / e.total) / spanCount);
							}
						})
						.done(function (response) {
							if (!response || !response.id) {
								reject(new Error('Upload fehlgeschlagen'));
								return;
							}
							id = response.id;
							if (lastSpan) {
								resolve({id: id, name: file.name, type: file.type || ''});
							} else {
								spanIndex++;
								sendSpan();
							}
						});
				}
				sendSpan();
			};
			reader.readAsArrayBuffer(file);
		});
	};

	FsUploader.needsFlush = function (form) {
		if (!form || !form.querySelector) return false;
		var nodes = form.querySelectorAll('[data-upload="onsubmit"]');
		if (!nodes.length) return false;
		for (var i = 0; i < nodes.length; i++) {
			var input = fileInputIn(nodes[i]);
			if (!input) continue;
			var info = pending.get(input);
			if ((info && (info.file || info.id)) || (input.files && input.files[0])) return true;
		}
		return false;
	};

	FsUploader.flushForm = function (form) {
		var jobs = [];
		var nodes = form.querySelectorAll('[data-upload="onsubmit"]');
		Array.prototype.forEach.call(nodes, function (node) {
			var input = fileInputIn(node);
			if (!input || !input.name) return;
			var info = pending.get(input);
			if (!info && input.files && input.files[0]) {
				info = {file: input.files[0], id: null, name: input.files[0].name, type: input.files[0].type || ''};
			}
			if (!info || (!info.file && !info.id)) return;
			var field = input.name;
			var job = (info.id
				? Promise.resolve({id: info.id, name: info.name, type: info.type})
				: FsUploader.storeFile(info.file)
			).then(function (stored) {
				setHidden(form, field + '_uploader', stored.id);
				setHidden(form, field + '_uploader_name', stored.name || 'upload');
				try { input.value = ''; } catch (e) {}
				pending.delete(input);
			});
			jobs.push(job);
		});
		return jobs.length ? Promise.all(jobs) : Promise.resolve();
	};

	FsUploader.bindDocument = function () {
		if (FsUploader._bound) return;
		FsUploader._bound = true;
		document.addEventListener('dragover', function (e) {
			var host = findDropHost(e.target);
			if (!host || !isFileOrUrlDrag(e.dataTransfer)) return;
			e.preventDefault();
			e.dataTransfer.dropEffect = 'copy';
			host.classList.add('dragover');
		}, true);
		document.addEventListener('dragleave', function (e) {
			var host = findDropHost(e.target);
			if (!host) return;
			if (!e.relatedTarget || !host.contains(e.relatedTarget)) host.classList.remove('dragover');
		}, true);
		document.addEventListener('drop', function (e) {
			var host = findDropHost(e.target);
			document.querySelectorAll('.dragover').forEach(function (el) { el.classList.remove('dragover'); });
			if (!host) return;
			if (!isFileOrUrlDrag(e.dataTransfer) && !(e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length)) return;
			e.preventDefault();
			e.stopPropagation();
			var input = fileInputIn(host);
			var accept = (input && input.getAttribute('accept')) || host.getAttribute('data-accept') || '';
			FsUploader.resolveDataTransfer(e.dataTransfer, accept).then(function (files) {
				applyResolvedToHost(host, files);
			}).catch(function (err) {
				alert((err && err.message) ? err.message : 'Drop fehlgeschlagen');
			});
		}, true);
		document.addEventListener('mousedown', function (e) {
			var host = e.target && e.target.closest && e.target.closest('[data-drop-files], div.uploader');
			if (!host) return;
			prepareDropHost(host);
			if (isTextPasteTarget(e.target)) return;
			var input = fileInputIn(host);
			if (input && (e.target === input || (input.contains && input.contains(e.target)))) return;
			try { host.focus({preventScroll: true}); } catch (err) { host.focus(); }
		}, true);
		document.addEventListener('paste', function (e) {
			if (isTextPasteTarget(e.target)) return;
			if (!clipboardHasFiles(e.clipboardData)) return;
			var host = findPasteHost(e.target);
			if (!host) return;
			e.preventDefault();
			e.stopPropagation();
			prepareDropHost(host);
			try { host.focus({preventScroll: true}); } catch (err) { host.focus(); }
			var input = fileInputIn(host);
			var accept = (input && input.getAttribute('accept')) || host.getAttribute('data-accept') || '';
			FsUploader.resolveDataTransfer(e.clipboardData, accept).then(function (files) {
				if (!files || !files.length) return;
				files = files.map(function (f) {
					return f instanceof File ? namedClipboardFile(f) : f;
				});
				applyResolvedToHost(host, files);
			}).catch(function (err) {
				alert((err && err.message) ? err.message : 'Einfügen fehlgeschlagen');
			});
		}, true);
		document.addEventListener('change', function (e) {
			var input = e.target;
			if (!input || input.type !== 'file' || !input.files || !input.files[0]) return;
			if (uploadModeOf(input) !== 'onsubmit' && !input.closest('[data-drop-files]')) return;
			pending.set(input, {file: input.files[0], id: null, name: input.files[0].name, type: input.files[0].type || ''});
		}, true);
	};

	sys.lib.add(setup, FsUploader);
	FsUploader.bindDocument();
})();
