/* global sys,c,p,t,l,obj,location */


sys.core.server = new function () {
    var server = this;
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
        var hooks, doneFunc, ondonefirstFunc, onloadstartFunc, onupdateFunc, changeLocation;
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

        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = () => {

            if (xhr.readyState === XMLHttpRequest.DONE) {
                if (xhr.status === 200) {
                    var responseStr = window.sysdebug ? sysdebug.trimXhrResponse(xhr.responseText) : xhr.responseText;
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
                        for (var index in call.responses) {
                            call.response = call.responses[index];
                            call.response.index = index;
                            l(call.response.command);
                            new sys.core.server.command(call);
                        }
                        if (doneFunc) {
                            //l('***************** ondone call', call.dataPassedToCallbackFunction);
                            doneFunc(call.dataPassedToCallbackFunction);
                        }

                        if (method !== 'put') {
                            sys.http.historyPush(sys.http.url2string(call.url));
                            if (call.url.hashChanged && call.url.hash !== '') {
                                var target = document.getElementById(call.url.hash.replace('#', ''));
                                if (target) {
                                    target.scrollIntoView(true);
                                }
                            } else {
                                if (typeof onAfterHisotryPush === "function") {
                                    onAfterHisotryPush(call.url);
                                } else {
                                    //	l("scrollto top");
                                    /*var target = document.getElementById('top');
                                     if (target) {
                                     target.scrollIntoView(true);
                                     } */
                                    document.body.scrollTop = document.documentElement.scrollTop = 0;
                                }
                            }
                        }
                    }
                } else {
                    p('XHERRor! Status ' + xhr.status);
                }
            }
        };

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
            query = obj.extend(obj.extend({}, call.url.query));
        } else {
            query = obj.extend(obj.extend({}, call.data.get), call.url.query);
        }
        xhrRootHref += obj.getQueryString(query, '?');

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
