/* global sys,c,p,t,l,f,obj,location */


sys.core.server = new function () {
    var server = this;

    // Dev prefixes ajax with a debug block. The login page does not load
    // that client, and a click then dies in JSON.parse. The marker is cut
    // off here; when the client is present it still receives the block.
    server.stripDebug = function (responseText) {
        if (window.sysdebug && sysdebug.trimXhrResponse) {
            try {
                return sysdebug.trimXhrResponse(responseText);
            } catch (error) {}
        }
        if (!responseText) {
            return responseText;
        }
        var marker = '/*sysdebug.json:';
        var start = responseText.indexOf(marker);
        if (start === -1) {
            return responseText;
        }
        responseText = responseText.substring(start);
        var match = responseText.match(/^\/\*sysdebug\.json:(\d+)\*\//);
        if (!match) {
            return responseText;
        }
        return responseText.substring(match[0].length + (parseInt(match[1], 10) || 0));
    };
    // var lt = () => void 0;

    server.sendEventCall = function (event) {
        // t(event);
        // sending calls with data from event rep. invoked dom objects
        var hooks, doneFunc, ondonefirstFunc, onloadstartFunc, onupdateFunc;
        hooks = {
            done: (func) => {
                doneFunc = func;
                return hooks;
            },
            ondonefirst: (func) => {
                ondonefirstFunc = func;
                return hooks;
            },
            onloadstart: (func) => {
                onloadstartFunc = func;
                return hooks;
            },
            onupdate: (func) => {
                onupdateFunc = func;
                return hooks;
            }
        };
        var call = new sys.core.server.call();
        call.event = event;
        call.send(event.url)
                .done(doneResponse => {
                    if (doneFunc) {
                        doneFunc(doneResponse);
                    }
                })
                .ondonefirst(doneResponse => {
                    if (ondonefirstFunc) {
                        ondonefirstFunc(doneResponse);
                    }
                })
                .onloadstart(e => {
                    if (onloadstartFunc) {
                        onloadstartFunc(e);
                    }
                })
                .onupdate(e => {
                    if (onupdateFunc) {
                        onupdateFunc(e);
                    }
                })
                ;
        return hooks;
    };

    server.sendCall = (data, xhrRootHref) => {
        // sending calls with no dom object or event involved
        // 2d0 - does not forward url parameters
        var ondone, hooks = {
            done: func => {
                ondone = func;
            }
        };
        var call = new sys.core.server.call(data);
        call.send(xhrRootHref).done(doneResponse => {
            if (ondone) {
                ondone(doneResponse);
            }
        });
        return hooks;
    };
    server.sendGet = (xhrRootHrefInput, data) => {
        // little tested -> merge all data into string -> make method
        // sending calls that change location and render page
        if (typeof data === 'object' && data !== null) {
            Object.keys(data).map(function (key) { // untested
                data[key] = encodeURIComponent(data[key]);
            });
        }
        var url = sys.http.parseURL(xhrRootHrefInput);
        var root = url.absolute ? sys.http.root : '';

        var xhrRootHref = root + sys.http.path2pathStr(url.path);
        xhrRootHref += url.action || '';

        var queryData = obj.extend({}, url.query);
        obj.extend(queryData, data);
        var queryString = obj.getQueryString(queryData, '?');
        xhrRootHref += queryString;
        var call = new sys.core.server.call();
        call.data.method = 'get'; // data get will not be sent
        return call.send(xhrRootHref);
    };

    server.getData = route => { //unfinished
        var target = cms;
        route.split('.').forEach(module => {
            if (target[module]) {
                target = target[module];
            } else {
                target = null;
            }
        });
        if (target && target.__data) {
            return target.__data;
        }
    };

    /**
     * What a failed call was asking for, flat and stringified.
     *
     * A response that is not json is a php fatal in almost every case, and
     * the body alone says where php died - not which element was clicked or
     * what went out with it. Flat on purpose: p() parses two levels deep, so
     * nested objects would come back as '!depth limit'.
     */
    server.callContext = (call, method, url, xhr) => {
        var target = call.event && call.event.target;
        var element = target && target.closest ? (target.closest('a,button,form,[data-on_click],[data-on_change]') || target) : null;
        return {
            method: method.toUpperCase(),
            url: url,
            status: xhr.status,
            event: call.event ? call.event.type : 'none',
            element: element ? element.outerHTML.split('>')[0] + '>' : 'none',
            href: (element && element.getAttribute) ? (element.getAttribute('href') || '') : '',
            data: server.stringify(call.data)
        };
    };

    /** never let the report itself throw - call.data may carry a FileList or a node */
    server.stringify = value => {
        try {
            return JSON.stringify(value);
        } catch (error) {
            return '[not serializable: ' + error.message + ']';
        }
    };

    /**
     * Headers for every request. A package adds a function that returns an
     * object of headers (systopic/relay: which tab sends, what changed under
     * it); it is asked once per request, right before it goes out, and is
     * told the method. The system itself names no package here.
     */
    server.headerHooks = [];
    server.addHeaders = fn => {
        server.headerHooks.push(fn);
    };
    server.headersFor = method => {
        var headers = {};
        server.headerHooks.forEach(fn => {
            try {
                Object.assign(headers, fn(method) || {});
            } catch (error) {
                f('server header hook failed', error);
            }
        });
        return headers;
    };

    /**
     * Requests on their way. onIdle() runs a function once none is - right
     * away if none is now. For whoever wants to wait for the answers before
     * acting on the page (a held layer, a refresh that a request in flight
     * may make unnecessary).
     */
    server.inFlight = 0;
    server.uploading = 0;   // file slices on their way - see the unload guard in gui/jobs.js
    // A backup create or restore is a chain of requests. Leaving would cut it off.
    server.backupBusy = false;
    server.backupStep = function (url) {
        var path = String(url || '').split('?')[0];
        var name = path.replace(/\/+$/, '').split('/').pop();
        if (name === 'cancel') {
            return path.indexOf('backup') === -1 ? '' : name;
        }
        switch (name) {
            case 'createTaskList':
            case 'addMysqldump':
            case 'addSettings':
            case 'addEnvironment':
            case 'addMediaDir':
            case 'finishBackup':
            case 'extractMysqldump':
            case 'extractSettings':
            case 'extractEnvironment':
            case 'extractMediaDir':
            case 'finishRestore':
            case 'clearTempfiles':
                return name;
        }
        return '';
    };
    server.idleHooks = [];
    server.onIdle = fn => {
        if (server.inFlight === 0) {
            fn();
            return;
        }
        server.idleHooks.push(fn);
    };
    server.showBusy = () => {
        clearTimeout(server.busyTimer);
        var bar = document.getElementById('nprogress');
        if (!bar) {
            bar = document.createElement('div');
            bar.id = 'nprogress';
            bar.innerHTML = '<div class="bar"></div>';
            document.body.appendChild(bar);
        }
        bar.hidden = false;
        document.documentElement.classList.add('nprogress-busy');
    };
    server.hideBusy = () => {
        var bar = document.getElementById('nprogress');
        if (bar) {
            bar.hidden = true;
        }
        document.documentElement.classList.remove('nprogress-busy');
    };
    server.loginUrl = () => (sys.http && sys.http.root ? sys.http.root : '/') + 'cms/auth/';
    server.settled = () => {
        server.inFlight = Math.max(0, server.inFlight - 1);
        if (server.inFlight === 0) {
            // an upload sends its slices one after the other: the bar stays instead of blinking
            clearTimeout(server.busyTimer);
            server.busyTimer = setTimeout(() => {
                if (server.inFlight === 0) {
                    server.hideBusy();
                }
            }, 250);
        }
        while (server.inFlight === 0 && server.idleHooks.length) {
            var fn = server.idleHooks.shift();
            try {
                fn();
            } catch (error) {
                f('server idle hook failed', error);
            }
        }
    };

    server.scrollHold = null;
    server.pendingScroll = null;
    server.pendingY = 0;

    /** The scrolled boxes of the page, each named by the tags and classes on its way down from body. */
    server.scrollPositions = () => {
        var selectorOf = el => {
            var parts = [];
            for (; el && el !== document.body; el = el.parentElement) {
                parts.unshift(el.localName + [...el.classList].map(name => '.' + CSS.escape(name)).join(''));
            }
            return 'body > ' + parts.join(' > ');
        };
        var found = [...document.body.querySelectorAll('*')].filter(el => el.scrollTop > 0)
            .map(el => ({selector: selectorOf(el), top: el.scrollTop}));
        // The phone sheet scrolls here. The long selector misses it after a redraw.
        document.querySelectorAll('main > div.content, main > div.content > .flexRow').forEach(function (el) {
            if (el.scrollTop > 0) {
                found.push({
                    selector: el.classList.contains('flexRow') ? 'main > div.content > .flexRow' : 'main > div.content',
                    top: el.scrollTop
                });
            }
        });
        return found;
    };
    /** A box built anew gets its place back, also once its images have their height. */
    server.restoreScroll = positions => {
        var apply = () => {
            (positions || []).forEach(position => {
                var el = document.querySelector(position.selector);
                if (!el || !position.top) {
                    return;
                }
                var min = el.style.minHeight;
                el.style.minHeight = (position.top + el.clientHeight) + 'px';
                el.scrollTop = position.top;
                el.style.minHeight = min;
            });
            if (sys.core.server.pendingY) {
                window.scrollTo(0, sys.core.server.pendingY);
            }
        };
        apply();
        requestAnimationFrame(apply);
        setTimeout(apply, 50);
        setTimeout(apply, 300);
        setTimeout(apply, 800);
    };

    server.startCommands = ['skipRefresh', 'done', 'updateClientData', 'updateDocument'];
    server.endCommands = []; // maybe onafter build hook
    server.sortCommands = (c1, c2) => {
        var o1 = server.getCommandOrdering(c1.command);
        var o2 = server.getCommandOrdering(c2.command);
        if (o1 === o2) {
            return 0;
        }
        return o1 > o2 ? 1 : -1;

    };

    server.getCommandOrdering = command => {
        if (server.startCommands.includes(command)) {
            return server.startCommands.indexOf(command);
        }
        if (server.endCommands.includes(command)) {
            return server.startCommands.length + server.endCommands.indexOf(command);
        }
        return server.startCommands.length;

    };
};

sys.core.server.call = function (data) {
    var call = this;
    call.binary = null;
    // expecting binary data as ArrayBuffer
    call.data = obj.extend({
        get: {}
    }, data);

    call.send = function (inputUrl) { // url object or string
        var hooks, doneFunc, failFunc, ondonefirstFunc, onloadstartFunc, onupdateFunc, changeLocation;
        // Back and forward already moved the address. Pushing it again
        // stacks a copy, so the next back stays on the same page.
        call.fromHistory = sys.core.server.historyMode === 'pop';
        sys.core.server.historyMode = '';
        hooks = {
            done: (func) => {
                doneFunc = func;
                return hooks;
            },
            // the answer was no 200 or no json - done() will not come
            fail: (func) => {
                failFunc = func;
                return hooks;
            },
            ondonefirst: (func) => {
                ondonefirstFunc = func;
                return hooks;
            },
            onloadstart: (func) => {
                onloadstartFunc = func;
                return hooks;
            },
            onupdate: (func) => {
                onupdateFunc = func;
                return hooks;
            }
        };

        call.fromLightbox = !!document.querySelector('.cmslightbox');
        var liveScroll = sys.core.server.scrollPositions();
        var heldScroll = sys.core.server.scrollHold;
        sys.core.server.scrollHold = null;
        if (heldScroll && heldScroll.boxes) {
            heldScroll.boxes.forEach(function (item) {
                var found = null;
                for (var i = 0; i < liveScroll.length; i++) {
                    if (liveScroll[i].selector === item.selector) {
                        found = liveScroll[i];
                        break;
                    }
                }
                if (!found) {
                    liveScroll.push(item);
                } else if (item.top > found.top) {
                    found.top = item.top;
                }
            });
            call.scrollY = heldScroll.y || 0;
            // Only a prompt remembers a place. A real navigation still starts at the top.
            sys.core.server.pendingScroll = liveScroll;
            sys.core.server.pendingY = call.scrollY;
        } else {
            sys.core.server.pendingScroll = null;
            sys.core.server.pendingY = 0;
        }
        call.scrolled = liveScroll;
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = () => {

            if (xhr.readyState === XMLHttpRequest.DONE) {
                if (xhr.status === 200) {
                    var responseStr = sys.core.server.stripDebug(xhr.responseText);
                    try {
                        // A php file saved with a BOM prints three bytes before
                        // anything else. The xhr decoder drops one at the very
                        // front, but not one that sits behind the debug block -
                        // and JSON.parse chokes on it. charCodeAt instead of a
                        // regex: the escape itself would be a BOM in this file.
                        while (responseStr.charCodeAt(0) === 0xFEFF) {
                            responseStr = responseStr.slice(1);
                        }
                        call.responses = JSON.parse(responseStr);
                    } catch (e) {
                        p('NO JSON!', sys.core.server.callContext(call, method, xhrRootHref, xhr), responseStr);
                        l(responseStr);
                        if (typeof responseStr === 'string' && /class="authCard"|Sign in to fluxo cms/.test(responseStr)) {
                            location.href = sys.core.server.loginUrl();
                            return;
                        }
                        if (failFunc) {
                            failFunc(xhr);
                        }
                        if (backupStep) {
                            sys.core.server.backupBusy = false;
                        }
                    }
                    if (call.responses) {
                        call.responses.sort(sys.core.server.sortCommands);
                        if (ondonefirstFunc) {
                            if (call.responses[0] && call.responses[0].command === 'done') {
                                var doneResponse = call.responses[0].data;
                            }
                            //l('***************** ondonefirst call', doneResponse);
                            ondonefirstFunc(doneResponse);
                        }
                        var backupContinues = false;
                        for (var index in call.responses) {
                            var queued = call.responses[index];
                            if (queued && queued.command === 'refresh' && queued.data && queued.data.method === 'put' && sys.core.server.backupStep((queued.data.path || '') + (queued.data.action || ''))) {
                                backupContinues = true;
                            }
                            call.response = queued;
                            call.response.index = index;
                            l(call.response.command);
                            new sys.core.server.command(call);
                            if (call.redirecting) {
                                break;
                            }
                        }
                        if (backupStep && !backupContinues) {
                            sys.core.server.backupBusy = false;
                        }
                        if (doneFunc) {
                            //l('***************** ondone call', call.dataPassedToCallbackFunction);
                            doneFunc(call.dataPassedToCallbackFunction);
                        }

                        if (method !== 'put' && !call.redirecting) {
                            // a post that rendered (a lightbox opened) is what the next diff starts from
                            if (method === 'post' && call.responses.some(response => response.command === 'updateDocument')) {
                                sys.http.markRendered(xhrRootHref);
                            }
                            // an action is a command, not an address - a reload would run it again
                            if (!call.fromHistory) {
                                sys.http.historyPush(sys.http.url2string(Object.assign({}, call.url, {action: ''})));
                            }
                            if (call.url.hashChanged && call.url.hash !== '' && !call.url.pathChanged && !call.url.queryChanged) {
                                var target = document.getElementById(call.url.hash.replace('#', ''));
                                if (target) {
                                    target.scrollIntoView(true);
                                }
                            } else {
                                if (typeof onAfterHisotryPush === "function") {
                                    onAfterHisotryPush(call.url);
                                }
                                // A new page starts at the top. Add, delete and save stay
                                // where the list was; only a different address scrolls up.
                                var navigation = call.url && call.url.pathChanged && !call.url.actionChanged;
                                if (navigation && !call.fromLightbox && !document.querySelector('.cmslightbox')) {
                                    document.body.scrollTop = document.documentElement.scrollTop = 0;
                                    document.querySelectorAll('main > div.content, main > div.content *').forEach(function (node) {
                                        if (node.scrollTop) node.scrollTop = 0;
                                        if (node.scrollLeft) node.scrollLeft = 0;
                                    });
                                } else {
                                    sys.core.server.restoreScroll(call.scrolled);
                                }
                            }
                        }
                    }
                } else if (xhr.status === 500 && method === 'get' && !call.retried) {
                    // A lightbox close is one GET. The first answer is sometimes
                    // empty (Apache 500, no headers). The same click a moment
                    // later works, so this one is tried once more.
                    call.retried = true;
                    call.send(inputUrl);
                } else {
                    p('XHERRor! Status ' + xhr.status);
                    if (backupStep) {
                        sys.core.server.backupBusy = false;
                    }
                    if (xhr.status === 401 || xhr.status === 403) {
                        location.href = sys.core.server.loginUrl();
                    } else if (failFunc) {
                        failFunc(xhr);
                    }
                }
            }
        };
        // After the handler above - once per request, also when it failed, was
        // aborted, or a command threw: whoever waits for quiet sees the page
        // as this answer left it, and never waits for good.
        xhr.addEventListener('loadend', () => sys.core.server.settled());

        if (call.event) {
            obj.extend(call.data, call.event.data);
            call.url = call.event.url;
        }

        if (typeof inputUrl === 'string') {
            call.url = sys.http.parseURL(inputUrl);
        }
        if (typeof inputUrl === 'object') {
            call.url = inputUrl;
        }

        if (call.data.dataset && call.data.dataset.method === 'get') {
            call.data.method = 'get';
        }

        // clone data from event target trunk into call data if given
        var root = call.url.absolute ? sys.http.root : '';

        xhr.upload.onloadstart = (e) => {
            if (onloadstartFunc) {
                onloadstartFunc(e);
            }
        };

        xhr.upload.onprogress = (e) => {
            if (onupdateFunc) {
                onupdateFunc(e);
            }
        };

        /* *************** define method ***************** */

        var method = 'put';
        if (call.event) {
            if (call.event.type === 'click' && call.data.method) { // anchor dfault
                method = call.data.method;
            }
            if (call.event.type === 'submit' && call.data.method) { // form default
                method = call.data.method;
            }
        }

        if (call.data.method) { // define method manually in call or event.data
            method = call.data.method;
        }

        /* *************** build xhrRootHref ***************** */

        var root = call.url.absolute ? sys.http.root : './';
        //root + sys.http.array2path(call.url.path);

        var xhrRootHref = root + sys.http.path2pathStr(call.url.path);
        xhrRootHref += call.url.action || '';
        // how to decide if url,query or dat.get is sent? -> so long send both
        var query;
        if (method === 'get') {
            // A link already names its query in the url. A form does not:
            // its fields travel in data.get, and a native submit never ran.
            query = obj.extend({}, call.url.query);
            if (call.event && call.event.type === 'submit' && call.data.get) {
                obj.extend(query, call.data.get);
            }
        } else {
            query = obj.extend(obj.extend({}, call.data.get), call.url.query);
        }
        xhrRootHref += obj.getQueryString(query, '?');
        var backupStep = sys.core.server.backupStep(xhrRootHref);
        if (backupStep) {
            sys.core.server.backupBusy = true;
        }

        // A GET is a navigation here (anchor click, form with method get),
        // and a navigation is what the server diffs. Name the state this
        // window is showing so it compares against the right tree — see
        // http.diffUrl(). PUT carries commands, not a render, and stays as is.
        //
        // diffUrl() first, markRendered() second: the reference is where we
        // are coming FROM. Marked on send rather than on response — a
        // request that never arrives leaves the server without a matching
        // reference, and no reference means a full render, which is the
        // outcome we want when the two sides are unsure of each other.
        if (method === 'get') {
            var diffHref = sys.http.diffUrl(xhrRootHref);
            sys.http.markRendered(xhrRootHref);
            xhrRootHref = diffHref;
        } else if (method === 'put') {
            // A put does not navigate - but it can still come back with a
            // render, because an action may mark views for update. Without a
            // reference the server has nothing to compare against and sends
            // the whole body as new, which replaces every node on the page.
            // So: name the state we are showing, but do NOT markRendered -
            // nothing moves, the window keeps showing what it showed.
            xhrRootHref = sys.http.diffUrl(xhrRootHref);
        }

        var log = () => {
            if (call.event) {
                // l(call.event);
                l('XHR:' + method.toUpperCase() + ' sent to ' + xhrRootHref + ' - event:' + call.event.type, ' - data:', call.data, ' - event:', event);
            } else {
                l('XHR:' + method.toUpperCase() + ' sent to ' + xhrRootHref + ' - no event involved');
            }
        };
        xhr.open(method, xhrRootHref, true);
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        var extraHeaders = sys.core.server.headersFor(method);
        for (var headerName in extraHeaders) {
            xhr.setRequestHeader(headerName, extraHeaders[headerName]);
        }
        sys.core.server.inFlight++;
        sys.core.server.showBusy();
        switch (method) {
            case 'get':
                // anchors and forms with method get
                xhr.setRequestHeader('Content-type', 'application/json; charset=utf-8');
                // xhr.setRequestHeader('Debug-mode', 'append'); // kommt nicht an (php headers_list())
                // p("'Debug-mode', 'append'");
                // xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.send();
                log();
                // lt('stack');

                // sys.http.historyPush(url); -> moved after XHR
                changeLocation = true;

                // sys.http.renderPath = call.url.path.slice();
                break;
            case 'post':
                // forms with method post
                if (!call.data.files) {
                    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                    xhr.send(obj.getQueryString(call.data.post));
                    log();
				} else {
					// file upload – use original field names (event_image, event_header_image, file[], etc.)
					let formData = new FormData();
					for (var key in call.data.files) {
						if (!Object.prototype.hasOwnProperty.call(call.data.files, key)) continue;
						var file = call.data.files[key];
						if (file instanceof Blob && file.name) {
							formData.append(key, file, file.name);
						}
					}
					for (var key in call.data.post) {
						formData.append(key, call.data.post[key]);
					}
					xhr.send(formData);
                    log();
                    // mulitpart - no structured display in ff console
                }

                // sys.http.renderPath = call.url.path.slice();
                changeLocation = true;
                break;
            case 'put':
                // dataset based calls -> send everything
                var json = JSON.stringify(call.data);
                if (call.binary === null) {
                    xhr.setRequestHeader('Content-type', 'application/json; charset=utf-8');
                    xhr.send(json);
                    log();
                } else {
                    var jsonBuf = (new TextEncoder()).encode(json);
                    // var length = (new TextEncoder().encode(json)).length; //json.length;
                    var length = jsonBuf.length;
                    var header = '/*json:' + length + '*/';
                    var headerBuf = (new TextEncoder()).encode(header)
                    var buf = bufConcat(headerBuf, jsonBuf, call.binary);
                    xhr.setRequestHeader('Content-type', 'application/octet-stream');
                    sys.core.server.uploading++;
                    xhr.addEventListener('loadend', () => {
                        sys.core.server.uploading = Math.max(0, sys.core.server.uploading - 1);
                    });
                    xhr.send(buf);
                    log();
                }
                break;
        }
        return hooks;
    };

    /* **** HELPER ***** */

    function bufConcat(buffer1, buffer2, buffer3) {
        var tmp = new Uint8Array(buffer1.byteLength + buffer2.byteLength + buffer3.byteLength);
        tmp.set(new Uint8Array(buffer1), 0);
        tmp.set(new Uint8Array(buffer2), buffer1.byteLength);
        tmp.set(new Uint8Array(buffer3), buffer1.byteLength + buffer2.byteLength);
        return tmp.buffer;
    }

};
