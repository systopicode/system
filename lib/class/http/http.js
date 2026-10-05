/* global sys,c,p,t,l,history,location,settings,onBeforeHisotryPush */
(() => {
	var setup = {
		info: 'http class Singleton',
		route: 'singletons.http',
		shorthand: 'http'
	};
	sys.lib.add(setup, function () {
		var http = this;
		http.siteRoot = settings.siteRoot;
		http.root = settings.siteRoot;
		http.sysRoot = settings.sysRoot;
		http.onAfterConstruct = () => {
			// http.renderPath = http.parseURL(location.href).path;
		};
		http.parseQuery = query => {
			if (!query) {
				return false;
			}
			var queryParamStrings = query.split('&');
			var data = {};
			for (var i in queryParamStrings) {
				var queryParamArry = queryParamStrings[i].split('=');
				data[queryParamArry[0]] = queryParamArry[1];
			}
			return data;
		};
		http.buildQuery = (obj) => {
			var query = '';
			for (var key in obj) {
				if (obj[key]) {
					query += '&' + key + '=' + obj[key];
				}
			}
			return query === '' ? '' : '?' + query.substring(1);
		};
		http.locationPath = () => {
			return http.parseURL(location.href).path;
		};

		http.queriesEqual = (q1, q2) => {
			return JSON.stringify(q1 || {}) === JSON.stringify(q2 || {});
		};

		http.parseURL = (href, fromHref) => {
			if (typeof href === 'object') {
				return href;
			}
			var newUrl, url, newPathStr;
			var pattern = /^((https?:\/\/)([^\/]*)|)(.*\/|)(.*?)(\?(.*?)|)(#(.*?)|)$/;
			var from = fromHref || location.href;
			var urlMatch = from.match(pattern);
			var newUrlMatch = href.match(pattern);

			var rawPath = newUrlMatch[4];
			var absolute = rawPath[0] === '/';
			if (absolute) { // absolute Path
				newPathStr = rawPath.replace(http.root, '');
			} else {
				newPathStr = rawPath; // relative Path
			}
			var newPath = http.simplifyPath(http.pathStr2path(newPathStr));
			var newFullpath = http.simplifyPath(absolute ? newPath : http.locationPath().concat(newPath));

			if (!newUrlMatch) {
				f("href '" + href + "' does not match required pattern.");
				return;
			} else {
				newUrl = {
					path: newPath,
					fullpath: newFullpath,
					hash: newUrlMatch[8],
					action: newUrlMatch[5],
					query: http.parseQuery(newUrlMatch[7]),
					absolute: absolute
				};
			}
			if (!urlMatch) {
				f("url '" + from + "' does not match required pattern.");
				return;
			} else {
				url = {
					fullpath: http.pathStr2path(urlMatch[4].replace(http.root, '')),
					hash: urlMatch[8],
					action: urlMatch[5],
					query: http.parseQuery(urlMatch[7]),
					absolute: true,
				};
			}

			if (from !== href) {
				newUrl.pathChanged = !http.pathsEqual(url.fullpath, newUrl.fullpath);
				newUrl.hashChanged = url.hash !== newUrl.hash;
				newUrl.queryChanged = !http.queriesEqual(url.query, newUrl.query);
				newUrl.actionChanged = (url.action || '') !== (newUrl.action || '');
			} else {
				newUrl.pathChanged = false;
				newUrl.hashChanged = false;
				newUrl.queryChanged = false;
				newUrl.actionChanged = false;
			}

			return newUrl;
		};

		http.url2string = url => {
			return http.root + http.path2pathStr(url.fullpath) + url.action + http.query2qeryStr(url.query) + url.hash;
		};

		/**
		 * The url of what this window currently has on screen.
		 *
		 * location would nearly do — historyPush() keeps the address bar in
		 * step, in the response handler, at the moment the dom changes. But
		 * it spells the url its own way (url2string rebuilds the query from
		 * an object, in object order), and this string has to match what the
		 * server filed the render under, character for character, or the
		 * lookup misses and every navigation falls back to a full render.
		 *
		 * So the exact href that was sent is remembered instead. It also
		 * keeps the diff independent of WHEN the address bar is updated,
		 * which is a separate concern and has moved around before (see the
		 * commented-out historyPush calls in server.js and server.command.js).
		 *
		 * NULL until the first ajax navigation — until then the window shows
		 * the document the browser loaded, and location does name it.
		 */
		http.rendered = null;

		http.renderedUrl = () => {
			return http.rendered || (window.location.pathname + window.location.search);
		};

		/** Records what a navigation is about to put on screen. */
		http.markRendered = href => {
			try {
				var url = new URL(href, window.location.href);
				if (url.origin === window.location.origin) {
					http.rendered = url.pathname + url.search;
				}
			} catch (e) {
				// leave the last known state alone
			}
		};

		/**
		 * Turns a target href into a diff url: '<reference>~<target>'.
		 *
		 * The reference is what this window is showing. The server files
		 * every render under the url it rendered and looks it up by that
		 * name, instead of keeping one reference per session — a session is
		 * not a window, and two tabs used to overwrite each other's tree on
		 * every request.
		 *
		 * The target is shortened against the project root — repeating it
		 * doubles the url for no information, and the server puts it back:
		 *
		 *   showing /projects/x/public/kontakt/, going to /galerie/
		 *     -> /projects/x/public/kontakt/~/galerie/
		 *   showing /projects/x/public/cms/pages/?id=19, going to ?id=20
		 *     -> /projects/x/public/cms/pages/?id=19~/cms/pages/?id=20
		 *
		 * The reference keeps its full spelling: it is the url this window
		 * actually has, and reads as such in a log.
		 *
		 * The tilde sits between the two urls and belongs to neither, so
		 * each keeps its own slashes. The server splits on '~/' and hands
		 * the target half to the ordinary request parsing.
		 *
		 * Hands the href back untouched when the url would turn ambiguous.
		 * The server then finds no reference and renders in full — the safe
		 * outcome, never a patch against the wrong dom.
		 */
		http.diffUrl = href => {
			var target;
			try {
				target = new URL(href, window.location.href);
			} catch (e) {
				return href;
			}
			if (target.origin !== window.location.origin) {
				return href;
			}
			var reference = http.renderedUrl();
			var to = target.pathname + target.search;
			if (reference.indexOf('~') !== -1 || to.indexOf('~') !== -1) {
				return href;
			}
			// '/projects/x/public/cms/pages/' -> '/cms/pages/'; a root of '/'
			// takes the slash off and puts it straight back, as it should
			if (http.root && to.indexOf(http.root) === 0) {
				to = '/' + to.slice(http.root.length);
			}
			return reference + '~' + to;
		};

		http.query2qeryStr = query => {
			var values = [];
			for (var key in query) {
				values.push(key + '=' + query[key]);
			}
			return values.length ? '?' + values.join('&') : '';
		};

		http.pathsEqual = (p1, p2) => {
			return http.path2pathStr(p1) === http.path2pathStr(p2);
		};

		http.pathStr2path = pathStr => {
			if (!pathStr) {
				return [];
			}
			var path = pathStr.split('/');
			path.pop(); // path strings always end with '/' -> drop empty tail
			// A leading or doubled '/' must not produce empty path items:
			// they break sys.lib.getNode() (every client action would fall
			// through to the server) and produce '…/public//panel/' hrefs.
			return path.filter(name => name !== '');
		};
		http.path2pathStr = path => {
			return path.length ? path.join('/') + '/' : '';
		};
		http.realpath = path => {
			t('obsolete http.realpath - use http.simplifyPath');
			return http.simplifyPath(path);
		};

		http.simplifyPath = path => {
			if (typeof path === 'string') {
				path = http.path2array(path);
			}

			var realpath = [];
			var lastitem;
			path.forEach(name => {
				if (name === '..' && realpath.length && lastitem !== '..') {
					realpath.pop();
					return;
				}
				if (name === '.') {
					return;
				}
				realpath.push(name);
				lastitem = name;
			});
			return realpath;
		};

		http.historyPush = (pageOrURL) => {
			if (typeof onBeforeHisotryPush === "function") {
				onBeforeHisotryPush(pageOrURL);
			}
			history.pushState('something', '', pageOrURL);
		};

		http.onAfterConstruct();
	});
})();
