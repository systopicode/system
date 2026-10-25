# Panel Diff Rendering

How `app.php` produces an AJAX response that only re-renders the panels
whose content actually changed since the previous request, while telling
the client to keep the existing DOM for everything else.

This is **panel** diff (`PanelTree`, `beginDiff()`). The public website uses a
stack of its own: `site.php` and `Pages\*`, which mirrors this one rather
than sharing its openers — [pages-renderer-progress.md](../plans/pages-renderer-progress.md).
(The old `renderPagesDiff.php` replay loop is gone, removed with the `route` stack.)

Both stacks DO share the layer store and the way a request names the state
it is diffing against — [diff-url-and-references.md](diff-url-and-references.md).

This document describes the current closure-based architecture. Read
`panel-system.md` first — diff rendering reuses the same opener API
(`openSelected`, `openRoot`, `openChild*`, `withChild*`, …) that drives
full renders.


## 1. Mental model

Every panel render produces a tree of `DomLayer` nodes. Each layer
records:

- the opener that created it (`openSelected('main')`, `openChild('user',
  'signetmenu')`, `openRoot('body')`, …)
- the **caller panel** active at the time of the open call
- the **opened panel** (`panelName`) — set by `markOpened()` after the
  open succeeds
- a `viewName` (`'main'`, `'body'`, `'pylon'`, …)
- the captured HTML output (in `items`, mixed with child layers)
- a diff `type`: one of `keep`, `update`, `new` (see §2)

The layer tree is persisted to the PHP session at the end of every
render. The next request loads it back and uses it as the **reference
tree** for diffing.


## 2. Two axes, not one list

A layer in a diff is described by two independent things, and they live in
two properties on purpose.

**`$type` — what the CLIENT must do.** Only the current tree carries it, and
it is the only one the client ever receives.

| Type     | Meaning                                                  | Client action                       |
|----------|----------------------------------------------------------|-------------------------------------|
| `keep`   | Matched its reference: same owner, nothing requested.    | Keep the DOM, walk into the children |
| `update` | Same owner, but `updateView()` asked for a re-render.    | Rebuild from the shipped HTML        |
| `new`    | A different owner in this slot, or no reference at all.  | Rebuild from the shipped HTML        |

`update` and `new` trigger the same client action. They stay apart because
the debug view should say *why* a subtree was rebuilt.

**`$refState` — what happened TO a layer of the PREVIOUS render.** Set on the
reference tree, which is debug-only: never exported, never stored.

| State      | Meaning                          |
|------------|----------------------------------|
| `kept`     | this DOM is still in the browser |
| `replaced` | this DOM was thrown away         |

A reference layer *keeps the `$type` it had while it was the current tree*,
so the debug view shows both at once: the colour is last request's decision,
the outline this request's verdict. Layers the diff never reached carry no
`$refState` at all.

This used to be one field with four values, and `replace` meant two opposite
things in it — "re-render this" on a new layer, "this is gone" on an old one.


## 3. The render path

`app.php` selects between three branches in order:

1. **`OUTPUT_FORMAT === 'json'` + `http::ajax()`** → diff render
   (`beginDiff` → walk → `endDiff` → `storeReference`).
2. **`OUTPUT_FORMAT === 'json'` only** → full layer render
   (`beginLayers` → walk → `endLayers` → `storeReference`).
3. **`http::ajax()` (HTML stack)** → diff render through the legacy HTML
   slice path (same `beginDiff`/`endDiff` machinery; emits HTML slices
   instead of JSON layer data).

All three call the **same closure-based template walk**:

```php
$nav->beginDiff();             // or beginLayers / beginDocument
if ($nav->openRoot('body')) {
    $nav->getActive()->render('body.tpl.php');
    $nav->close();
}
$nav->endDiff();
$domRenderer->storeReference();
```

`openRoot('body')` uses `findRenderRoot('body')` internally, so it
always picks the deepest ancestor that ships the template — whether
that is the synthetic `app` panel, `cms`, or a direct ancestor of
the selected panel. A single canonical opener guarantees a stable
child-layer count across requests (see §6b).

There is **no separate replay loop**. The body template uses
`$this->withChild(…)`, `$this->withChildSelected(…)`, `$this->withView(…)`
helpers, each of which calls a navigator opener. In diff mode the
navigator transparently performs the reference comparison on every
`open*` call — without changing the template.


## 4. Where the diff actually happens

There are exactly **two places** the comparison fires:

### a) `DomRenderer::beginDiff()` — the root layer

```php
$root = $layerTree->create('getDiff', ['body'], $callerPanel, 'diff');
$root->markOpened($callerPanel);

$layerTree->setReferencesFromSession($session->loadReferencesByAddress());
$refRoot = $session->loadReferenceRoot();
$layerTree->setReferenceRoot($refRoot);
$this->addReference($refRoot);   // root vs. root comparison
```

The root is always the synthetic `app` panel, so the root almost always
matches and becomes `keep`.

> The session lookup runs here (not in `bootstrap.php`) because PHP's
> session is not yet open during bootstrap — `sessionStartOnce()` runs
> later in `app.php`.

### b) `PanelNavigator::confirm()` — every successful open

After every `open*`, when the just-created layer has its `panelName`
populated by `markOpened()`:

```php
$caller   = end($this->panelStack) ?: $this->activePanel;
$refLayer = $this->getRefLayer($method, $args, $caller);
$this->domRenderer->addReference($refLayer, $active);
```

The caller (= the panel that issued the open call, i.e. the parent of
the layer being opened) is read from the **top of `panelStack`** —
*not* from `$activePanel`. By the time `confirm()` runs, `activePanel`
already points to the just-opened child. Reference-map keys were
registered under the caller's address; lookups must use the same panel.


## 5. Reference map: two separate stores

`DomLayerTree` keeps two address → view → layer maps:

| Map                          | Populated by                | Read by                |
|------------------------------|-----------------------------|------------------------|
| `referencesByPanelAddress`   | `registerReference()` during this request | Persisted to session at end |
| `referenceLookup`            | `setReferencesFromSession()` at `beginDiff` start | `getReference()` during diff |

**Why two maps?** Because every `createLayer()` does both:

1. `registerReference($activePanel, $viewName, $layer)` — record the
   NEW layer for the next request.
2. (later, from `confirm()`) `getReference($caller, $viewName)` — look
   up the OLD layer to compare against.

If both used the same map, step 1 would overwrite the OLD entry, and
step 2 would return the SAME object that was just registered.
`addReference()` would then run

```php
$layer->type        = 'keep';
$refLayer->refState = 'kept';    // on the same object!
```

on a single object, so a layer would end up marked as its own reference,
breaking the entire diff. Keeping the lookup table read-only avoids
this aliasing trap.


## 6. `addReference()` semantics

```php
if ($layer->panelName === $refLayer->panelName) {
    $layer->type        = 'keep';
    $refLayer->refState = 'kept';
    if ($tree !== null && $layer->checkUpdate($tree)) {
        $layer->type = 'update';                    // forced re-render
        $refLayer->setRefStateRec('replaced');      // …so the old one does not survive
    }
    return true;
}
$layer->type = 'new';
$refLayer->setRefStateRec('replaced');   // recurse: the whole old subtree is gone
return false;
```

- **Match** (same owner, no update pending): new layer → `keep`
  (client keeps DOM); old layer → `kept`.
- **Match + `updateView()` called**: `DomLayer::checkUpdate($tree)`
  returns true → new layer promoted to `update`. The client rebuilds it
  from the new HTML, so the old subtree does **not** survive and is marked
  `replaced` recursively — `rebuild()` drops the children too.
- **Mismatch** (different panel in the same slot, e.g.
  `users` → `permissions`): new layer → `new`; the entire OLD reference
  subtree is marked `replaced`.

`refLayer === null` (no entry for this address/view in the previous
request) yields `new` and marks nothing — typical for openers that
don't pass a view name (e.g. `openNextChild()` walks).


## 6b. Stable layer structure via failed-open placeholders

The JS client (`domRenderer.js updateLayer`) walks the new
`childLayers` array by index and indexes into the existing
client-side `layer.children[i]`. If the new template emits a
**different number of sub-layers** — or the same count in a
**different panel/view order** — than the reference, the client
crashes with

```
Cannot read properties of undefined (reading 'shiftOutRootNodes')
```

The fix is **not** to detect divergence after the fact and force the
parent to re-render. The fix is to make the layer structure stable
**by construction**: every `open*` call — whether it succeeds or
fails — emits exactly one `DomLayer` in the same position.

Failed opens (no template, no selected child, hidden view, …) emit a
**placeholder layer** with `result=false`, `panelName=''` and
`html: ''` (empty string, never `null`). A full page reload would
also render nothing in that slot; the AJAX client must do the same
and **remove** any previous DOM (e.g. a lightbox overlay). Empty
host templates (`lightbox.tpl.php` with only a comment) are not
needed for this.
Diff comparison then handles the cases naturally:

| previous request | this request | comparison       | new layer type |
|------------------|--------------|------------------|----------------|
| failed (`''`)    | failed (`''`)| `'' === ''`      | `keep` (placeholder kept) |
| failed (`''`)    | opened (`X`) | `X !== ''`       | `new` (placeholder filled with real content) |
| opened (`X`)     | failed (`''`)| `'' !== X`       | `new` (real content collapses to placeholder) |
| opened (`X`)     | opened (`X`) | `X === X`        | `keep` |
| opened (`X`)     | opened (`Y`) | `X !== Y`        | `new` (panel changed in same slot) |

Concrete example — `/cms/` → `/cms/pages/`:

The `cms/body.tpl.php` body unconditionally calls
`withChildSelected('pylon')`, `withChildSelected('main')`,
`withChildSelected('sidebar')`, … At `/cms/` (no child selected) all
of these fail and emit `[]:pylon`, `[]:main`, `[]:sidebar`
placeholders. At `/cms/pages/` they all succeed and emit
`pages:pylon`, `pages:main`, `pages:sidebar`. The diff sees the
**same number of children in the same positions**; the parent
`cms:body` stays `keep` (no DOM rewrite for the chrome) and only
the placeholder layers get filled in with their new content.

Implemented in `PanelNavigator::cancel()`: every cancel path also
creates a placeholder layer (in `document` / `layers` / `diff`
modes), runs `addReference()` against it, and immediately closes it.
No `markOpened()` call → `panelName` stays empty → the layer is
recognisable as a placeholder by both the diff comparison and the
JS client.

### Implications for app.php and templates

- **Single render-host opener.** `app.php` uses `openRoot('body')`
  only — never `openSelected('body')` as a fallback. The fallback
  would emit different layer counts depending on which branch fired,
  breaking positional alignment. `findRenderRoot()` inside
  `openRoot()` already walks selected → … → synthetic root and
  picks the deepest ancestor that ships the template, so a selected
  panel that has its own `body.tpl.php` still wins.

- **Unconditional `with*` chains in body templates.** Wrapping an
  `openSelected('lightbox')` in `if ($cond) { … }` makes the layer
  structure depend on `$cond`, which then differs between requests
  and breaks the diff. Always issue the open; let the navigator turn
  a no-op call into a placeholder. Do **not** add empty
  `lightbox.tpl.php` hosts to force a successful open — a failed
  open already occupies the slot and exports empty HTML, which the
  client applies like a reload (previous overlay is removed).

- **Conditional rendering belongs INSIDE the closure.** Use
  `$this->withSelected('lightbox', function ($p) use ($cond) { if ($cond) { … } })`
  — the layer slot exists either way; only the captured HTML differs.
  And HTML differences alone are fine: the layer stays `keep` if
  the panelName matches, and the client keeps the DOM (no re-render
  on HTML-only changes).


## 6c. Mixed trees: page layers inside the panel tree

The two render models meet in one tree. `panels/cms/010_pages/main.tpl.php`
renders its page preview through the *page* navigator, so panel layers can
have page layers below them — deliberately, because that gives the preview
its own diff slot.

They are not interchangeable. `DomLayer::$panelPath` means different things
depending on who opened the layer:

| owner | `panelPath` | example |
|---|---|---|
| `PanelNode` | the folder path | `cms.pages.sidebar` |
| `RouteNode` | the node-id chain | `1.18.112.67.243` |

`DomLayer::$source` says which, set in `markOpened()` from
`RenderNode::getRenderSource()` and exported to the client. Anything that
reads `panelPath` must check it first.

**What went wrong without it:** `core/domLayer.js addToLibnode()` resolved
every layer's path to a libnode under `sys.panels`. A page path did that too,
so `/cms/pages/?id=18` built `sys.panels['1']['18']['112']…` — 145 nodes with
neither a class nor data. `panelTree.build()` walks that tree and logged
`panelTree: empty libnode — no class and no data` for each of them, and since
libnodes are never removed they accumulated over every page switch. Page
layers are now skipped there: there is no panel object to notify, which is
what `addToLibnode()` exists for.

The same question will come up the other way round — panels inside a page,
for on-page editing. `$source` answers it in advance.

**In the debug view**, background colour already means diff type, so the
render model gets the edge instead: the outermost page layer is drawn with a
dashed border (`css/promptDomLayers.css`, `:not(.source-page) > .source-page`).
Only the outermost one — everything inside it is a page as well, and 145
dashed frames would say nothing. `source` is also a row in the hover table,
on both sides.

## 7. Worked example — `/cms/users/users/` → `/cms/users/permissions/`

Selected leaf changes from `users` (inner) to `permissions`. The chrome
stays the same; only the panel under `cms/users/main` changes.

```
Layer                            type        why
─────────────────────────────────────────────────────────────────────
app:body            (root)       keep        beginDiff: app === app
cms:body            openRoot     alias       cms === cms
  user:signetmenu   openChild    alias       user === user
  cms:menu          openView     alias       cms === cms
    pages, media…   openNext*    original    no view name → no ref
  users:pylon       openChild*   alias       users === users
  users:controls    openChild*   alias       users === users
  users:main        openChild*   alias       users === users  ← parent of the change
    permissions:main openChild*  original    permissions ≠ users → replace ref subtree
    permissions:groups openView  original    no ref entry
    permissions:roles  openView  original    no ref entry
```

The client receives `app:body alias`, walks into it, sees `cms:body
keep`, sees the chrome layers all `keep`, and finally hits
`users:main keep` whose only `new` child is `permissions:main`.
Net DOM mutation: the contents of `users:main`'s render slot get
replaced with the new `permissions` subtree. The pylon, signetmenu,
top menu, etc. are untouched.


## 8. Reference persistence

After every render path that wants to enable a future diff:

```php
$domRenderer->storeReference();   // session: REF_LAYERS + REF_BYADDR
```

This is called from:

- the JSON branch (both diff and full)
- the HTML AJAX diff branch
- the slices HTML render branch

Skipping `storeReference()` (e.g. for a transient JSON-only
non-session render) means the next AJAX request can't diff and will
fall back to a full `new` tree.


## 9. Output cardinality

The diff payload is the new layer tree only. Reference layers do not
appear in the output (their HTML is implied by the `keep` flags on
the corresponding new layers). Replaced reference subtrees survive
only as a `replaced` root somewhere in the OLD tree, but since
the OLD tree is no longer serialised into the response, the client
infers removal from the absence of the old panel-address in the new
tree.


## 10. Things that bite

- **Forgetting `storeReference()` in a render path.** Next request
  loads `null` as the reference root → `addReference(null, …)` →
  every layer becomes `new`. Symptom: full-size diff payload.

- **Caller vs. active panel in lookups.** Use `end($panelStack)` in
  `confirm()` — `$activePanel` is already the just-opened child.
  Symptom: every layer becomes `new` (lookup misses).

- **Sharing one address-map for both registration and lookup.** See §5.
  Symptom: every matched layer comes out marked `kept` instead of
  `keep`.

- **`bootstrap.php` loads session-backed reference data too early.**
  Re-load it in `DomRenderer::beginDiff()` after `sessionStartOnce()`
  has run.

- **Templates that depend on `$_SESSION` mutation between requests.**
  The diff walk re-executes the same closures; if a template sets
  state during render that the next render reads back, the state must
  survive `storeReference()` (which only persists DomLayer objects, not
  arbitrary template-side data).

- **`openNextChild` / `openFirstChild` without a view name.** These
  pass `viewName=''`, so `getReference()` returns `null` and the layer
  is always `new`. This is the legacy behaviour for menu walks
  and is fine — the menu HTML is small and idempotent.

- **Conditional `with*` calls in body templates.** Wrapping
  `$this->withChildSelected('lightbox')` in `if ($cond)` makes the
  child layer count depend on `$cond` and breaks positional alignment
  with the reference. Symptom: client throws
  `Cannot read properties of undefined (reading 'shiftOutRootNodes')`.
  Fix: always issue the open; put `$cond` *inside* the closure.

- **Multiple opener fallback chains** (`if (openSelected) elseif (openRoot)`).
  Same root cause: different branches fire in different requests,
  emitting different layer counts. Pick one canonical opener per
  render entry point — `app.php` uses `openRoot('body')` only.


## 11. File / namespace map

| File                                                  | Role                                    |
|-------------------------------------------------------|-----------------------------------------|
| `app.php`                                             | Branch selection (json+ajax / json / html+ajax / slices). |
| `src/Dom/Renderer/DomRenderer.php`                    | `beginDiff` / `endDiff`, `addReference`, `storeReference`. |
| `src/Dom/Renderer/DomRendererSession.php`             | `$_SESSION` access for reference data.  |
| `src/Dom/Tree/DomLayerTree.php`                       | Layer stack, separate `referencesByPanelAddress` (write) and `referenceLookup` (read) maps. |
| `src/Dom/Layer/DomLayer.php`                          | Layer node, `type` / `refState`, `setRefStateRec`, `export`. |
| `src/Panels/Navigator/PanelNavigator.php`             | `confirm()` runs the per-layer diff comparison; `getRefLayer($method, $args, $caller)` resolves the old layer. |
| `src/Panels/PanelNode.php`                            | `with*` helpers — closure-based opener pattern that powers the walk. |


## 12. Quick recipes

### Test a diff scenario from the CLI

```jsonc
// var/cursor/cli-test-step1.json — initial render, populates session
{
  "path": "/projects/<app>/public/cms/users/users/",
  "user": 2,
  "sessionId": "diff-1",
  "sessionSavePath": "V:/www/projects/<app>/var/cursor/sess",
  "output": "json"
}

// var/cursor/cli-test-step2.json — AJAX diff, same session
{
  "path": "/projects/<app>/public/cms/users/permissions/",
  "user": 2,
  "sessionId": "diff-1",
  "sessionSavePath": "V:/www/projects/<app>/var/cursor/sess",
  "ajax": true
}
```

Run both and grep the layer types in step 2's output. See
`cli-testing.md` §"ajax" for the `ajax: true` shortcut.

### Add a new opener (must participate in diff)

1. Implement it on `PanelNavigator`, ending in
   `return $this->confirm($method, $args, $panel, $tplFile);`
2. That's it. `confirm()` does the reference comparison automatically;
   no new switch-case is needed anywhere.

### Force a layer to be re-rendered even when alias

Call `$panel->updateView('viewName')` (typically in `onLoad()` /
`onLoadSelected()`) before the corresponding `open*` call.
`DomLayer::checkUpdate()` consults the panel tree's update set in
`addReference()` and promotes the `keep` to `update`.
The JS client treats `update` like `new` and re-renders the
layer from the server HTML.
