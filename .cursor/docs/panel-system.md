# Panel System — Compact Reference

The Panel system replaces the legacy `module` / `moduleNode` / `moduleBuilder`
static singletons with explicit objects (`PanelTree`, `PanelBuilder`,
`PanelNavigator`, `PanelNode`, …). Pre-existing template/action code keeps
working through `Compat\ModuleCompat`, which forwards every legacy `module::*`
call to the new objects.

This document is the architectural overview — it ignores the migration history
and just describes how the system is supposed to behave today.

---

## 1. Mental model

A **Panel** is a logical screen unit (a CMS page like `cms`, `cms/pages`,
`cms/pages/upload`, …) backed by a folder on disk. The folder may contain:

- `panel.php` (modern) or `module.php` (legacy) — class definition
  (extends `Panels\PanelNode` directly or via an ancestor that does).
  Optional. Without it, the panel uses its parent's class (when
  `bequests=true`) or the bare `PanelNode`. Both filenames may coexist in
  the same folder; both are `include_once`d before class resolution. The
  resolver tries the `_panel` suffix first, then `_module` (see §6).
- `*.tpl.php` — view templates (`body`, `main`, `pylon`, `sidebar`,
  `controls`, `head`, `signetmenu`, …). Each panel chooses which templates
  it actually wants.
- `module.js` — optional client-side class registered as
  `cms.modules.<dotted.path>.__class`.
- Sub-folders — child panels.

A **PanelRootFolder** is a `panels/` directory whose immediate sub-folders
become top-level panels. There can be several roots; in this project we
register two (system, project — see §4).

The **PanelTree** is the per-request object that owns the registered roots,
runs the build, and tracks the URL-selected panel.

The **PanelNavigator** is the runtime engine: opens / closes panels and views
during template rendering, manages the active panel stack, and emits
DomLayer events for diff rendering.

---

## 2. Folder name → panel name

Folders may carry a numeric ordering prefix that is stripped from the logical
name. `010_pages` becomes `pages`; the prefix only affects sort order in
navigations. URL paths use the logical name (`/cms/pages/`), the on-disk
`folder` keeps the prefix (`010_pages`).

The conversion lives in `PanelTree::folderToName()` and is used by
`FolderResolver`.

---

## 3. Synthetic root + render-host resolution

`PanelTree::build()` always creates a top-level panel called `'app'`. It
is "synthetic" only in the sense that it isn't picked from a URL segment;
its identity is fixed. It may, however, behave like every other panel:

- `path = []`, `realPath = []`, `folder = ''`
- May own a `panel.php` (class `app_panel extends PanelNode`) and full
  template set when one of the registered `panels/` directories ships a
  `panel.php` / `body.tpl.php` directly at its root. Without those files
  it falls back to the bare `PanelNode` and renders nothing of its own.
- Its children are the union of every registered root's top-level folders.
- `app->absPath` points at the (last-wins) registered root that contributed
  its `panel.php` / templates, so navigation can `chdir()` into it just
  like with any other panel.

### `body.tpl.php` is the render-host template

`body.tpl.php` is special: it is **never `include`d from another template**.
It is always the entry point of the render — the navigator opens it via
`openRoot('body')`, then descends into `pylon`/`sidebar`/`main`/… via
`openChild*` / `openView*` calls inside it. As a consequence, exactly one
`body.tpl.php` participates per request, and the question is: *which one*.

### `findRenderRoot()` — walking up to find the host

The render-host is found by walking from the selected panel up the
ancestor chain (selected → parent → … → synthetic `app`) and returning
the first panel whose own folder contains the requested template:

```
PanelNavigator::findRenderRoot(string $viewName = 'body'): ?PanelNode
```

Examples (with `panels/body.tpl.php` and `panels/cms/body.tpl.php` present):

| Selected URL          | Walk                     | Render-host       | Body file rendered             |
|-----------------------|--------------------------|-------------------|--------------------------------|
| `/cms/pages/`         | `pages → cms`            | `cms`             | `panels/cms/body.tpl.php`      |
| `/cms/`               | `cms`                    | `cms`             | `panels/cms/body.tpl.php`      |
| `/otherpanel/sub/`    | `sub → otherpanel → app` | `app` (synthetic) | `panels/body.tpl.php`          |
| `/`                   | `app`                    | `app`             | `panels/body.tpl.php`          |
| `/standalone/` *(no body anywhere on chain)* | `standalone → app` (no body) | `null` | nothing — caller skips |

`openRoot($view)` uses `findRenderRoot($view)` to pick its target, so the
same walk applies to any view that follows the "host template" pattern
(`body`, optionally `head` or future ones). For ordinary view names that
are emitted from inside `body.tpl.php` (e.g. `pylon`, `sidebar`), the
template-file existence check inside `openRoot` short-circuits naturally
when only one ancestor on the chain ships them.

`head.tpl.php` follows the same rule: `app.php` resolves it via
`findRenderRoot('head')` (or `findRenderRoot('body')` then re-checking
for `head.tpl.php` next to the body) so `head` and `body` always come
from the same panel folder. There is no separate "mount panel" concept
anymore — the legacy `getMountRoot()` (= first ancestor whose parent is
the synthetic root) is kept only for backward-compat callers; new code
uses `findRenderRoot()`.

There is **no URL-prefix mounting** anywhere in the configuration — the
`/cms/` mount emerges purely because a `cms/` folder exists in some root.

### Dispatcher hand-off (`PanelTree::inApp()`)

`loader.php` decides between `app.php` (panel render) and `site.php`
(Pages) based on `module::inApp()` → `PanelTree::inApp()`. It returns
true in two cases:

1. A real panel below the synthetic root was URL-selected (e.g.
   `/cms/pages/` → selectedPanel ≠ rootPanel).
2. The synthetic root itself is "render-real" — it ships its own
   `panel.php` / `module.php` class file (`hasOwnClass=true`) **or** a
   `body.tpl.php` in any registered root. In that case even a bare `/`
   URL (`selectedPanel === rootPanel === app`) is dispatched to
   `app.php` so `findRenderRoot('body')` can render it.

Without case 2, a project shipping only a global `panels/body.tpl.php`
would never see it on `/`, because the loader would always fall through
to `site.php`.

---

## 4. Multi-root resolution (FolderResolver)

`PanelTree` holds an ordered list of `PanelRootFolder`s. Each root contributes
its top-level folders. `Builder\FolderResolver::discover($parentRealPath,
$roots)` performs a single pass:

1. For every root, in registration order, glob
   `<root>/<parentRealPath>/*` and collect matching folders by logical name.
2. Build a name → list-of-sources map (so a panel can have entries in
   multiple roots).
3. Pick a winning source per name with this policy:
   - If at least one source contains `panel.php` or `module.php`, the
     **last such source** wins. → that file defines the canonical class +
     templates.
   - Otherwise, the **last source** wins (pure last-wins fallback).
4. Return one `FolderHit` per logical name carrying:
   - `folder`, `absPath`, `root` (the winning source).
   - `altPaths[]` — every source's absolute path, in registration order.
     `PanelBuilder` `include_once`s `panel.php` and `module.php` from every
     entry, so a class defined in sys still applies when project only adds
     an empty same-named folder.

Loader registration (system → project → user) means later roots can override
earlier ones — useful for project and user-specific replacements of system panels.

---

## 5. Build lifecycle (PanelBuilder)

`PanelTree::build($urlPath)`:

1. Construct synthetic `app` root (`PanelBuilder::constructAppRoot`). This
   includes any `panel.php` / `module.php` found directly in a registered
   `panels/` directory and resolves the `app` class candidates exactly like
   for any other panel (`app_panel`, `app_module`, `app`). `app->absPath`
   is set to the (last-wins) registered root that owns the class file or
   templates, so subsequent `chdir()` works.
2. `PanelBuilder::buildChildren($root, $urlPath, $roots)` recurses down
   `$urlPath`, asking the FolderResolver for each level's children.
3. For each FolderHit:
   - `chdir(absPath)` (Windows-safe via `fs::toNative`).
   - `include_once` every `altPaths[]/module.php` (last wins on class
     definition).
   - `PanelClassResolver::resolve()` finds the class to instantiate (see §6).
   - `new $class($name, $parent)`. Constructor sets up identity, parent,
     state attachment.
   - Tag `sourceRoot`, `absPath`, `jsClass`, `hasOwnClass`.
   - Hook into siblings (linked list + `childrenByName`).
   - Call `$child->onBuild()`.
4. Walk into the URL-selected child and recurse with the rest of the path.
5. Selected leaf still has its children expanded **eagerly** if it sets
   `buildChildPanels`, `loadChildPanels`, or the legacy
   `loadChildModules` / `buildChildModules` flags. Eager-expanded
   children participate in `runAfterBuildHooks()` (step 6) — their
   `onLoad`/`onLoadSelected`/`onLoadThisFolder` runs before any
   rendering output. Set this flag on parents whose children must
   initialise (DB lookups, message dispatch, redirect) before render.
6. Resolve the `selectedPanel` (deepest `inPath()` child) and run
   `runAfterBuildHooks()`:
   - `onLoad()` everywhere on the selection chain.
   - `onLoadSelected()` on the deepest selected.
   - `onLoadThisFolder()` on every panel whose class came from its own
     `module.php`.

### 5a. Lazy children (render-time stubs)

If a navigation template calls `eachChild()` / `openChild()` /
`openFirstChild()` / `openNextChild()` on a panel whose children were
**not** eagerly built (no flag, not on URL path), `PanelNode::ensureChildrenBuilt()`
is invoked automatically and asks `PanelTree::ensureChildrenBuilt($panel)`
to construct them on the spot via `PanelBuilder::buildChildren($panel, [], $roots)`.

Lazy-built children are **stubs**:

- `__construct` runs (so identity, parent, path, folder, state are set).
- `onBuild()` runs (per builder contract).
- `onLoad()` / `onLoadSelected()` / `onLoadThisFolder()` do **NOT** run.

This preserves the hard invariant: *all* lifecycle hooks complete before
*any* render output begins. Templates iterating lazy stubs may rely on
constructor-set / Builder-set properties (`name`, `label`, `icon`,
`path`, `folder`, `parent`, `realPath`) but MUST NOT depend on
properties that are typically populated by hooks. If a parent needs its
children's hook-driven properties, opt into eager build with
`public bool $buildChildPanels = true;` (or `loadChildPanels = true`).

The `$childrenBuilt` flag on `PanelNode` is set by both code paths so
empty children-arrays are not re-scanned on every iteration.
CWD is saved and restored around the lazy build to avoid leaking
`chdir()` side effects into the calling template.

CWD is always restored to the value `getcwd()` returned at the start of the
recursion level — every `chdir()` round-trips.

---

## 6. Class resolution (PanelClassResolver)

When a folder has `panel.php` and/or `module.php`, the resolver builds a
leaf-to-root list of ancestor names (`['app', 'cms', 'pages']`) and tries
class candidates per level. At each level it tries `_panel` first (modern),
then `_module` (legacy), then the bare prefix:

```
app_cms_pages_panel
app_cms_pages_module
app_cms_pages
app_cms_panel
app_cms_module
app_cms
app_panel
app_module
app
```

The suffix list is hard-coded in `PanelClassResolver::SUFFIXES` —
`PanelRootFolder` no longer carries a configurable suffix. The first
existing class (`class_exists($candidate, false)`) wins. If none exists,
fall back to the parent's class (only when `parent->bequests=true`) or to
`Panels\PanelNode`.

`hasOwnClass` is set on the panel iff a class was found via the
class-file-driven candidate list (vs. a bequest/PanelNode fallback).

---

## 7. Navigation (PanelNavigator) and rendering

Every template rendering operation goes through `PanelNavigator`. It keeps
a stack of opened panels and owns the working directory.

Open methods (each pushes the active panel + DomLayer slice and `chdir`s):

| Method                          | Picks                                     |
|---------------------------------|-------------------------------------------|
| `openRoot($view)`               | Mount panel of the current selection.     |
| `openSelected($view)`           | The URL-selected panel.                   |
| `openChildSelected($view)`      | Active panel's URL-selected child.        |
| `openChild($name, $view)`       | A specific named child.                   |
| `openFirstChild($view)` / `openNextChild($view)` | Iterates children.       |
| `openView($view)`               | Stays on the current panel, just emits a layer for an extra view. |

Each opener calls `relativeOpenPath($panel)` to compute a path from the
current CWD to the target panel's `absPath`. This works across roots
(walks up `..` then down) and is junction-aware — important on Windows
because `realpath()` resolves symlinks/junctions and would desync chdir.

`close()` pops the stack and restores the previous CWD via `fs::closeDir()`.

`render($file)` on a `PanelNode` is the actual `include`. It mirrors the
active panel into `$GLOBALS['module']` / `$GLOBALS['state']` and aliases
the locals `$module` / `$state` **by reference** to those globals — so when
a template calls `module::openChild('sidebar', FALSE)` mid-template (which
goes through `ModuleCompat::syncModuleGlobal()`), the locals follow the new
active panel/state automatically. `runWith()` (used by all `with*` helpers)
re-syncs globals on close so the outer template snaps back.

---

## 8. Template helpers on PanelNode

Closure-based wrappers that subsume the `if (open…) { render; close(); }`
pattern:

```php
$this->withRoot('body');                       // openRoot + render
$this->withSelected('body');                   // openSelected + render
$this->withChildSelected('pylon');             // openChildSelected + render
$this->withChild('user', 'signetmenu');        // openChild + render
$this->withView('tree');                       // openView + render
$this->eachChild(fn($child) => …);             // openFirstChild + iterate
```

Each accepts an optional callable to override the default
`render("{$view}.tpl.php")`. `withChildSelected` accepts a callable as first
arg to skip the view-name template-existence check.

---

## 9. State

Per-panel user state is rooted in the user session:
`user::getActive()->state.children.cms.children.pages.data` etc.
`PanelNode::initState()` walks down to the panel's path, calls `attachState`
which fills in missing keys from `getDefaultState()` and strips away
unknown keys (1 level deep). Panels expose state via `$this->state` and
in templates as `$state` (alias by reference, see §7).

`updateView('main')` marks a view to be re-rendered on this request;
`updateViews([...])` for multiple. `viewVisible()` / `hideView()` /
`showView()` toggle visibility (persisted in state's `hiddenViews[]`).

---

## 10. DOM layer / diff rendering

Every opener creates a layer in `Dom\Tree\DomLayerTree` (via
`createLayer()`); successful template includes emit slices into the active
layer's buffer. The render flow has three modes (`PanelNode::getRenderMode()`):

- **`slices`** (default) — full page render captures the layer tree, which
  is then stored in the session (`DomRendererSession`) and inlined into
  `head.tpl.php` via `clientData_export()`. The HTML body just contains a
  placeholder `<div id="systemDomRendererPlaceholder"></div>` that the
  client-side `cms.dom.body.createRootLayer()` replaces with content built
  from the layer slices.
- **`markers`** — server-side renders the full page with marker comments
  delimiting layers; no JS bootstrap needed.
- **`html`** — plain HTML, no layers.

For AJAX requests (`http::ajax()`), `app.php` runs the same closure-based
template walk as a full render but wraps it in `beginDiff()` / `endDiff()`
instead of `beginLayers()` / `endLayers()`. The navigator's `confirm()`
method performs the reference comparison on every `open*` call automatically.
No separate replay loop or switch-case file is needed — adding a new opener
to `PanelNavigator` (ending in `$this->confirm(…)`) makes it participate in
diff rendering for free. See `panel-diff-rendering.md` for the full
algorithm.

---

## 11. Compatibility shim (Compat\ModuleCompat)

`ModuleCompat::install($tree, $nav, $domRenderer, $layerTree, $tplRenderer)`
wires the legacy `module` API onto the new objects. Every `module::open*`
forwards to the navigator and then calls `syncModuleGlobal()` so legacy
code that reads `$GLOBALS['module']` / `$GLOBALS['state']` keeps seeing the
right panel mid-template. Legacy properties (`module::$rootModule`,
`module::$selectedModule`, `module::$activeModule`, …) are emulated via
`__get_static`-style match.

The legacy `module` class in `lib/class/module/module.php` is now a thin
delegate: each static call forwards to `ModuleCompat::*`.

---

## 12. File / namespace map

```
src/Panels/
├── PanelNode.php                  – base class for all panels
├── PanelRootFolder.php            – describes one panels/ root
├── Builder/
│   ├── FolderHit.php              – one resolved folder + altPaths
│   ├── FolderResolver.php         – multi-root merge, last-wins
│   ├── PanelBuilder.php           – tree construction, recursion
│   ├── PanelClassResolver.php     – class-name candidate walk
│   ├── ResolvedClass.php          – {className, hasOwnClass}
│   └── ChildBuildResult.php       – build-recursion return value
├── Tree/
│   └── PanelTree.php              – per-request orchestrator
├── Navigator/
│   └── PanelNavigator.php         – runtime open/close + CWD
└── Renderer/
    ├── PanelRenderContext.php     – render-time context (debug, etc.)
    └── TemplateRenderer.php       – isolated-scope include helper

src/Compat/
└── ModuleCompat.php               – legacy module::* shim
```

Bootstrap entry:

- `src/bootstrap.php` — wires DomRenderer/Tree, PanelTree, TemplateRenderer.
- `loader.php` — registers roots, calls `$panelTree->build()`, creates
  `$nav`, installs `ModuleCompat`.
- `app.php` — selects render mode and runs `openSelected`/`openRoot` +
  `render('body.tpl.php')`.

---

## 13. Things that keep biting

- **CWD types.** `getcwd()` returns native paths (`C:\…` on Windows);
  `fs::cwd()` returns internal forward-slash form (`/C/…`). Pass native to
  `chdir()`; pass internal to `fs::*` helpers. Use `fs::toNative()` /
  `fs::toInternal()` to convert. `realpath()` resolves junctions and will
  produce paths that don't match the dev's expectation — avoid it; prefer
  `fs::toInternal()` + string-based comparison (see
  `PanelTree::computeSelectedRoot` and `PanelNavigator::relativeOpenPath`).
- **Cross-root chdir.** Always navigate via `relativeOpenPath($panel)` so
  the path reaches the panel's `absPath`, never via the bare folder name.
  Otherwise children from a non-CWD root silently fail to open and the
  navigator's panel-stack desyncs from the dir-stack.
- **Class-file resolution.** When two roots both contribute the same
  panel, every `panel.php` and `module.php` is included in registration
  order (`include_once`); the last one defines the winning class. A root
  that only adds sub-folders (no `panel.php` / `module.php`) does **not**
  displace the class from another root.
- **`$state` in templates.** `render()` aliases `$state` by reference to
  `$GLOBALS['state']`; this means the variable follows the active panel
  during nested `module::openChild()` calls. Don't shadow `$state` with a
  local assignment if you want this behaviour.
- **`updateView()` wiring.** Calling `$this->updateView('viewName')` in
  `onLoad()` marks the view for re-render. The promotion from `keep` to
  `update` happens in `DomRenderer::addReference()` via
  `DomLayer::checkUpdate()`. The JS `domRenderer.js updateLayer` treats
  `update` identically to `new`. Forgetting either side (PHP not
  calling `updateView`, or JS not handling `update`) silently leaves the
  old DOM in place.
- **JS root attribution.** `moduleNode::loadJS()` looks at
  `$this->sourceRoot->dir` to decide whether to send `root: 'sys'` or
  `'app'` for the `<script>` URL. Don't rely on `$this->root->name === 'cms'`
  — that check predates the synthetic `'app'` root.
- **`body.tpl.php` lives on the render-host, not the mount panel.** Use
  `PanelNavigator::findRenderRoot()` to figure out where it actually is
  for a given selection. The legacy `getMountRoot()` (= first ancestor
  whose parent is the synthetic root) returns a different panel whenever
  an outer ancestor (e.g. the synthetic `app` itself) is supposed to host
  the body. New code should not call `getMountRoot()` for template
  resolution.
- **`fsAutoloader::evaluateFileInfoByClassname()`.** The "site class" branch
  must check `fs::$siteRoot` (not `fs::$sysRoot`), otherwise every system
  class is reported as `root='app'` and `client::loadJSfromFileInfo()`
  silently skips its preload `*.js` file.

---

## 14. Quick recipes

### Add a new top-level mount panel

Drop a folder into one of the registered roots' `panels/` directories. The
new panel appears in the nav and is reachable at `/<foldername>/`. Add a
`panel.php` (or legacy `module.php`) if you need a custom class, plus the
templates you want (`body.tpl.php`, `main.tpl.php`, …).

### Override a system panel from the project

Place a `panels/<same-name>/` folder in `projects/<project>/panels/`.
Without `panel.php` / `module.php` you only override templates /
sub-folders. With one of those class files you also override the class.

### Add a new with* helper

Add a method to `PanelNode` that delegates to a navigator open*-call via
`runWith()`. Add the matching opener to `PanelNavigator` if needed — end
it with `return $this->confirm($method, $args, $panel, $tplFile)` and the
new opener automatically participates in diff rendering. No additional
replay code is required.

### Debug a missing render

1. Look at the `domLayersServer` debug panel — does the layer for the
   missing view exist? If yes, the render happened but its slices aren't
   reaching the client.
2. If not, check `getActive()` at the point of failure — an open* call
   probably returned false. Look at the cancellation reasons in
   `PanelNavigator` (template not found, view hidden, child not found).
3. For multi-root issues, dump `FolderResolver::discover()` output.
4. For cross-root chdir issues, `var_dump(fs::cwd())` before each open*
   and compare to `$panel->absPath`.
