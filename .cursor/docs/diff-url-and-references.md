# Diff URLs and the Reference Store

How an AJAX navigation tells the server which state it is showing, so the
diff compares against the right tree — and why the reference used to be
wrong as soon as a second browser window was open.

Applies to **both** render stacks: `site.php` (pages) and `app.php` (panels)
share `DomRendererSession`, so they share this mechanism. Read
[panel-diff-rendering.md](panel-diff-rendering.md) first for what a diff
actually compares.


## 1. The problem

A diff needs the previous render to compare against. That render was kept in
the session under two fixed keys:

```php
$_SESSION['referenceDomLayers']        = $tree->root();
$_SESSION['referencesByPanelAddress']  = $tree->getReferencesByPanelAddress();
```

One slot. But a session is not a window. With the CMS open in one window and
the public site in another, every request overwrote the other window's
reference, and the next diff was computed against a tree that window never
had on screen. The client then kept DOM for layers reported `keep` that it
did not actually have.

Two more things came out of the same store:

- **The full rendered HTML travelled with it.** A diff never reads it —
  `DomRenderer::addReference()` compares identities and
  `DomLayerTree::getReference()` looks layers up by address and view name.
  It was a debugging leftover.
- **Each stored tree dragged its predecessor along.** `DomLayer::$reference`
  points at the previous render's layer, and that layer came out of the
  session with *its* `$reference` still attached. Six navigations grew the
  store from 25 KB to 575 KB, and it kept going.


## 2. The URL

A diff request addresses two urls at once, separated by a tilde:

```
/projects/x/public/kontakt/~/galerie/
└ reference ──────────────┘ └ target┘

/projects/x/public/cms/pages/?id=19~/cms/pages/?id=20
└ reference ───────────────────────┘ └ target ──────┘
```

Left is what the window is showing, right is what it wants.

The **reference keeps its full spelling** — it is the url this window
actually has, and reads as such in a log. The **target is shortened against
the project root**, which otherwise doubles the url for no information;
`splitDiffUrl()` puts the root back before anything downstream sees it. A
target that carries the root anyway is accepted unchanged, so both
spellings work.

**The separator is `~/`** — a tilde followed by a slash. It sits *between*
the two urls and belongs to neither, so each keeps its own slashes. That is not cosmetic:
`appRequest::parsePathString()` tells a module path from an action by the
trailing slash alone, so a reference that lost it would make `settings` look
like an action.

The one rule the scheme needs: **no rewritepath segment may be exactly `~`**,
and no reference query value may contain an unencoded `~/`. Properly encoded
parameters cannot, since `/` becomes `%2F`.

Why the path and not a query parameter: applications use query parameters
freely and a reserved name could collide. Why not `..` for a relative
reference: WAFs and hosting middleware routinely reject any path containing
`..`, whatever the context.

### Apache

The catch-all in each project's `public/.htaccess` must let the tilde
through, and the loop-catcher must let the second `?` through:

```apache
RewriteRule ^[a-z0-9/_\-\&=\.~]*$ index.php [NC,QSA,L]
...
RewriteCond %{QUERY_STRING} ^[a-z0-9/_\-\&=\.~?]*$ [NC]
```

Without them the request falls through to `RewriteRule .* - [F]` and the
browser gets a **403** — which looks like a broken server, not a missing
route. Both lines are patched in galerie-lattemann.de, pwk.systopic.net and
spaceticker.app.


## 3. Server: where the split happens

`http::setRoot()`, before anything reads the request — it is the single point
where `REQUEST_URI` enters the system. `http::splitDiffUrl()` puts the
reference half in `http::$referenceUrl` and leaves the target in place, so
**the whole system only ever sees the target**: `UrlResolver`, the panel
dispatcher, `parsePathString()` and everything downstream need no special
case.

It also rebuilds `$_GET`. PHP fills that from the *whole* query string, so on
the second form above it holds `id="19~/cms/pages/?id=20"`. Fixing it at the
source rather than in `http::readGet()` also covers the two places that read
the superglobal directly (`httpHelper`'s `ajax`, `mediaImage`'s `rebuild`).
Keys that did **not** come from the url are preserved — that is what saves
the parameters the CLI test harness injects.

`app::reference()` returns the reference half as a full `appRequest`, next to
`app::request()` and `app::referrer()`, so it can be read with the same
accessors (`->rawpath`, `->modulePath`, `->alias`).

A diff url that arrives **without** AJAX — pasted, bookmarked, crawled — is
redirected to its target in `loader.php`. Nothing to diff against, and
rendering would put a JSON body in the address bar.


## 4. The reference store

`DomRendererSession`, one entry per url:

```php
$_SESSION['domReferences']['/cms/pages/?id=18'] = [
    'byAddress' => [...],   // address → view name → DomLayer
    'root'      => DomLayer,
    'time'      => 1789644232,
];
```

- **Keyed by the url that was rendered.** Host and project root are stripped,
  the query is kept — readable in a session dump, and unique per window
  state. `?id=18` and `?id=20` are different keys, which is what lets two CMS
  windows coexist.
- **Used once.** An entry is removed as it is read. The render that consumes
  it files its own result as the next reference. Left-overs are windows that
  never navigated again.
- **Capped** at `MAX_ENTRIES = 20`, oldest first.

The session is only read once the session is actually started — `bootstrap.php`
builds the renderer before `sessionStartOnce()` runs, so an early call returns
null *without* caching the miss, and `DomRenderer::beginDiff()` does the real
read.

### What is not stored

| | |
|---|---|
| Slice HTML | `DomLayerSlice::__serialize()`. With `DEBUG` on it keeps the 300-byte excerpt `debugInfo()` displays — so the reference side of the debug comparison looks exactly as before. `byteSize` is the original length either way, which is what the debug view sizes itself by. |
| `DomLayer::$reference` | dropped in `__sleep()`. It points into the *previous* tree; keeping it chained every generation into the session. |
| `$panel`, `$callerPanel` | already dropped — resolved again via `RenderTree::findByLayerPath()`. |

Measured on `/kuenstler/` (180 layers, the biggest page on the site): 235 KB
with `DEBUG` and `DEBUGRENDER` on, of which 6 KB is HTML excerpts and roughly
150 KB is `opener` backtrace info that only exists under `DEBUGRENDER`.


## 5. Client: which url is the reference

`http.rendered` in `lib/class/http/http.js` holds what this window has on
screen; `http.markRendered()` updates it on every navigation and
`http.diffUrl()` builds the composite url from it.

`location` would nearly do — `historyPush()` keeps the address bar in step in
the response handler. But it spells the url its own way (`url2string()`
rebuilds the query from an object, in object order) and the key has to match
what the server filed the render under character for character. So the exact
href that was sent is remembered instead. It also keeps the diff independent
of *when* the address bar is updated, which has moved around before.

`server.js` composes for `method === 'get'` only — a PUT carries commands, not
a render. `diffUrl()` runs before `markRendered()`: the reference is where the
request is coming *from*. Marking on send rather than on response is
deliberate; a request that never arrives leaves the server without a matching
reference, and that means a full render.


## 6. Failure is a full render

Every way this can go wrong ends in the same place: the server finds no
reference, every layer compares against nothing, comes out `new`, and
the client rebuilds. Slower, never wrong.

That covers a missing tilde, an unknown or already-consumed reference, a
cross-origin href, a tilde in the url that is not the separator, and a client
and server that disagree about what is on screen.


## 7. Testing

`.cursor/tools/diff-render-test.sh panels|pages` — step 2 uses a composite
url. Without the reference half the diff finds nothing and everything comes
back `new`, which is also what a genuinely broken step 2 looks like, so
check the outline and not just the exit code.

Worth re-checking after changes here:

- two windows in one session: the same diff, once alone and once with a
  foreign render interleaved, must produce an identical outline
- the same for two CMS windows on one panel path with different `?id=`
- a multi-hop walk: the session size must stay flat, not grow per hop
- `compare-legacy.sh`: eleven pages, visible text, against the legacy site
