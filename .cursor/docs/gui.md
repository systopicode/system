# fluxo GUI — how to work on it

This is the hand-over for anyone (human or AI) who continues the admin GUI
("fluxo cms") — the panels behind `https://<host>/cms/`. Read it once before
you change a panel, a stylesheet or a script.

A project's own look (colours, logo, share image, mails) and the files of
a panel at a glance: `theming.md`.

## 1. The two packages

| Package | Role |
|---|---|
| `systopic/system` | The framework of every project: boot, database, auth, users, media, panels engine, assets — and the generic admin GUI (frame, menu, login, users, settings, backup, tools, tree, WYSIWYG, context menu, image editor, all shared CSS/JS). |
| `systopic/cms` | The website: page tree, page editor, preview, sites, frontend rendering, and what only pages have (template and page settings, page trash, duplicates, URL rewrite) — plus the CSS/JS that only pages need. |

The line was drawn when the monolith was split (5 Oct 2026): **framework
for every app** vs. **website feature** — not "core vs. GUI". Apps without
pages (spaceticker, pwk) get the whole admin GUI from `systopic/system`.

The rule: **a system runs without the cms, never the other way round.**
`systopic/cms` may use everything in `systopic/system`; `systopic/system`
must not need `systopic/cms`. Some places still break that rule (see
section 11). Do not add new ones.

**Generic in system, cms only adds or overrides what differs for pages.**
Example: `system/public/css/27_tree.css` defines the tree,
`cms/panels/cms/010_pages/pages.tree.css` only what the page tree does
differently. Same for `cms/public/css/pages.css` (page header, preview).

`panels/cms/` in both packages is the URL `/cms/`, not the package: the two
trees are merged by folder name (`030_settings` gets Media and Environment
from system, Template and Page Settings from cms).

Projects that use only `systopic/system` (and so the GUI in it):
`pwk.systopic.net` (loads `00_theme` … `18_ui` from `system/public/css/` and
`system/public/js/gui/` directly and uses `copyToClipboard`) and `spaceticker.app.src` (its panels
extend `Systopic\System\Panels\Root\Cms\Panel`). Check both before you move
or rename anything in `system/public` or `system/panels/cms`.

In a project, `vendor/systopic/*` are symlinks to `~/Sites/packages/*`, so an
edit in the package is live in every project at once.

## 2. How packages plug in

Each package registers itself in its `autoload.php` through
`Systopic\System\Sys\Packages` and `Systopic\System\Sys\Hooks\Hooks`:

```php
Packages::addLib('systopic/cms');              // lib/ folders, classes
Packages::addPanels(__DIR__ . '/panels/');     // panel tree, merged by folder name
Packages::addPanelAssets(__DIR__ . '/public/'); // public/css/ and public/js/ in every panel head
Packages::addFrontend(new \Systopic\Cms\Frontend());
Hooks::on('afterConnect', static fn() => ...);
```

- Panel trees of all packages are merged by folder name. `cms/panels/cms/010_pages`
  and `system/panels/cms/080_users` both end up under `/cms/`.
- `addPanelAssets()` is how a package brings GUI CSS/JS that must be there
  on every panel page (example: `cms/public/css/pages.css` for the page
  header and preview). `system/panels/cms/head.tpl.php` loads them after the
  system CSS/JS.

## 3. Panels

A panel is a folder. Folder names map to URLs after the sort prefix is
stripped: `080_users/010_users/` → `/cms/users/users/`.

| File | Purpose |
|---|---|
| `panel.php` | Class `Panel` in the namespace of its path, e.g. `Systopic\System\Panels\Root\Cms\Users\Users`. Extends `Systopic\System\Panels\Root\Cms\Panel`. State, computed properties (`get =>` hooks), defaults (`getDefaultState()`). |
| `actions.php` | `switch ($this->action)` — one `case` per action. Ends with `$this->updateView('main')` (or `updateViews([...])`) for what must be re-rendered. |
| `*.tpl.php` | Views. `main.tpl.php` is the content, `controls.tpl.php` the header bar, `pylon.tpl.php` the left column, `sidebar.tpl.php` the right column, `lightbox.tpl.php` a dialog, `footer`/`status` the bottom. |
| `panel.js` | Client code for the panel (`sys.lib.add(setup, fn)`, `route: 'panels.cms.users.users'`). |
| `*.css` | Styles only this panel needs (see section 5). |

Layout (`system/panels/cms/body.tpl.php`): menu → `pylon` → content
(`controls` header, `main`, `footer`) → `sidebar` → status bar → `lightbox`.
A parent hands a slot down with `$this->withChildSelected('controls')`,
`$this->withChildSelected()` (main) etc. `withView('users')` includes a view
of the same panel, `withChild('user', 'signetmenu')` a view of a named child.

Useful members of `PanelNode`: `$this->state` (persisted per user),
`$this->action`, `$this->scope` (database), `child('edit')`,
`updateView()`, `updateViews()`, `onLoadSelected()` (runs when the panel is
opened by GET — read query filters there), `loadJS()`.

Request helpers: `\http::get('user_id')` (query), `\http::dataset('user_id')`
(the `data-*` of the clicked element and its ancestors), `\http::dragdata()`
(drop payload), `\http::redirect('edit/?user_id=1')`.

Messages: `\message::error('...')`, `\message::flush()` in the view that
should show them.

**A lightbox** is a child panel with `public bool $inNavi = false;` and a
`lightbox.tpl.php`:

```php
<div class="cms cmslightbox" data-size="2">   <!-- 1, 2 or 3 -->
	<section>
		<header><h1>…</h1><ul class="barmenu"><li><a class="ajax" href="../" title="Close"><i class="fa-regular fa-xmark"></i></a></li></ul></header>
		<main>…</main>
	</section>
</div>
```

Open it with a plain link `<a class="ajax" href="edit/?user_id=3">`, close it
with `href="../"`. Example: `system/panels/cms/080_users/010_users/edit/`.
Only a change of panel redraws a lightbox: `?x=` on the same panel does not.

**Dashboard** (`/cms/`, the logo): `system/panels/cms/home.tpl.php`, shown
when no child is selected or the selected one has no `main` (e.g.
`cms/legal/`, the login footer texts as a lightbox). Any panel adds widgets
with `dashboard.tpl.php` or `dashboard.<name>.tpl.php` (first two levels
below `cms`), rendered by that panel. The first `<h2>` is the title in the
"Add:" row. Key: the path below `cms` plus the name, e.g. `pages.todo`.
"Customize" (`dashEdit`, session) turns on dragging, the insert lines
(`insertWidgetBefore`/`After`) and the crosses (`hideWidget`, `showWidget`).
Order and hidden keys are stored per user in `sessiondata.dashboard`; new
widgets go to the end. Tabs inside a widget: `nav.dashTabs` with
`<button data-on_click="dashTab" data-tab>`, the open one from
`Panel::widgetTab($this, key, tabs)`. Version and revision:
`Systopic\System\Sys\Release`. Texts of login and lightbox share
`panels/cms/texts.css`.

Two traps: `data-on_click` on an `<a>` navigates to the action's name instead
of running it - use a `<button>`. And app.php calls a panel method named like
the action instead of `actions.php`, so no method may share an action's name.

The menu opens up wide whenever the content holds `.dashboard` (the brand
anchor is not redrawn by AJAX, so never key layout on it). Times for the
user: `Sys\LocalTime` (zone from the `cmsTimezone` cookie).

**Search** (`system/panels/cms/search/`, Cmd+K or `a.cmsSearchLink` in the
menu): `Sys\Search::run()` walks every panel the user may open. Without code
a panel is found by its menu name and the headings, `<label>`s and legends
of its `*.tpl.php`. Its own records it adds in a method (pages, tags, users,
settings do it so):

```php
public function searchHits(\Systopic\System\Sys\Search $search): void {
	foreach (Contacts::matching($this->scope, $search->like()) as $c) {
		$search->add('Contacts', 'fa-address-book', $c->name, $c->company, $this->getHref(\http::$root) . '?id=' . $c->id);
	}
}
```

At most 8 hits per group; Pages, Media, Tags, Users, Settings first, a
project's groups next, Sections last. Classes are `cmsFind…`
(`cmsSearchResults` is the dropdown of the tree search, 40_nav.css).

**Widget columns** (`public/js/gui/widgetColumns.js`): every `.widgets`
group keeps each card in the column it got when built; a card that grows
pushes down only its own column. The count comes from the CSS `columns`
of the group.

**Link check** (`Systopic\Cms\Queries\LinkCheck`): `broken()` lists links of
live pages to pages, aliases or `var/` files that are gone (To do
`brokenlinks`, Tools → Maintenance → Links). External URLs are tested in
batches of 10 by `checkNext()` (curl, HEAD then GET), the result is
`var/cache/linkcheck.json` (`lastCheck()`, To do `deadlinks`). Page links
are stored as `?id=<node>&lang=<lang>`; `TextField::rendered` turns them into
the page address on the site.

## 4. Everything is AJAX

No full page loads inside the GUI. The client sends the action, the server
answers with the views to replace.

- Links: `class="ajax"` → loads the panel without reload.
- Clicks: `data-on_click="addUser"` calls the action `addUser` of the panel
  the element is in (`"../hijackUser"` or `"sidebar/updateParam"` addresses
  another panel relative to it). If `panel.js` defines a method with that
  name, the method runs instead (e.g. `filterUsers`).
- Also `data-on_change`, `data-on_blur`, `data-on_input`, `data-on_drop`
  (+ `data-drop_accept`), `data-on_draginit`.
- Ids travel as `data-*` on the element or an ancestor: `<tr data-user_id="3">`
  → `\http::dataset('user_id')`.
- Confirm before destructive actions:
  `data-on_click="userConfirm" data-action="removeUser" data-confirm="Delete this user? This cannot be undone."`
- Inputs inside a form that save on change use `name="value"` and an
  `data-on_change`/`data-on_blur` action.
- `call.send(…)` returns hooks: `.done(data)` (data from `client::done()`),
  `.fail(xhr)` when the answer is no 200 or no JSON. Anything that shows
  progress (`cmsJobs`) clears it in `.fail` too.
- Uploads: a PUT with `call.binary` counts in `sys.core.server.uploading`.
  While that or the `cmsJobs` slot `upload` is busy, `beforeunload` asks
  before the tab closes (`gui/jobs.js`).
- Files into the page (`010_pages/upload/panel.js`): drops on the media
  library and drops or pastes into a text editor (`[data-on_drop="insertMediaInEditor"]`)
  upload by themselves. Without files, `mediaIn()` takes the image and video
  addresses of the drag (another browser window) and `upload/fetch` loads
  them on the server. Text uploads go to the group `Panel::TEXT_GROUP`
  ("In text") of the page — the original's for an alias — and answer with the
  editor tag (`Panel::editorTag()`). The tag is a reference
  (`src="media:293?format=…"`, `href="page:43"`) resolved for the editor by
  `Cms\Refs\Resolver::renderForEditor()`; the url is what the browser shows,
  `data-ref-*` is what `Resolver::normalize()` stores again. The save-time
  cleaning (`wysiwyg.html`) still strips stray spans and empty links, and
  leaves `span.ref` and `data-ref-*` in place. Older texts are
  converted under Tools → Migration (`HtmlMigration`). A placeholder `span.cmsUploading`
  holds the spot meanwhile. The upload panel never renders: an answer from
  there would replace the page, the client reloads instead.
- Large files: never read whole (`readAsDataURL`, one `ArrayBuffer`) - each
  span reads its own `file.slice()`, a preview points at
  `URL.createObjectURL(file)`.
- The upload address is fixed when the queue starts (`target.from`): a
  relative `upload/upload` resolves against the panel on screen, so after a
  switch the spans went to that panel and each came back with a render. The
  closing reload only runs when the user is still on that page.
- Quiet actions: `$renderStyle = 'none'` in an `actions.php` sets
  `PanelNode::$quiet`. `app.php` then renders nothing, stores no panel state
  (an upload span to `cms/pages/upload/upload` would leave that panel
  selected) and `client::flushResponse(false)` ships no css or js. The end of
  an upload asks for what changed: `mediaAdded` (pages) draws only the
  `mml`, the backup upload only `backups`.
- `eachChild()` layers (no view name) are kept across renders by the
  child's name (`'#' . name`), but only while their own html is the same
  (`DomLayer::$keepIfSame`, checked in `DomLayerTree::close()`). Before, the
  main menu came back `new` with every ajax render.
- Media groups limited to templates: `'templates' => 'a;b'` in
  `$media_groups` (Media Settings, `ProjectForm::TEMPLATE_FIELDS`). The
  library leaves such a group out on other templates; what it holds there
  moves to `unassigned`.
- Dashboard columns: the stored order has `'|'` between the columns
  (`Panel::DASH_COLUMNS`, `dashboardColumns()`); `insertWidgetEnd` with
  `data-column` drops at the end of one.
- Switches (`input.switch + div`) take a sideways swipe on touch
  (`panels/cms/panel.js`); the track has `touch-action: pan-y`.
- Template picker (`details.projectTemplates`, `ProjectForm::templatePicker`):
  while open, its list (`.projectTemplatesMenu`) is parked on `document.body`
  with `position: fixed` (`panels/cms/panel.js`). A settings row has
  `transform: translate(0)`, which traps a menu inside it under the next
  card, and `overflow: visible` on the widget would drop the card's radius.
  `data-some` on the `details` marks "Only the ticked": the inputs are not
  inside it while the list is open, so `:has(input:checked)` would not see
  them. A render that replaces the picker drops the parked list
  (`cms:rendered`).
- Progress where it started: `cmsJobs.update(id, {…, el, progress})` puts
  `.is-busy` and `--jobProgress` on `el` (a bar along its bottom edge,
  `18_ui.css`) besides the status bar; `clear()` takes it off again.
- Menus parked on the body close on `cms:rendered` (`domRenderer` fires it
  after every render).
- Thumbs: an `img[data-broken="image-slash"]` that does not load gets that
  crossed-out icon (`i.thumbBroken`, its parent `.thumbMissing`; `gui/ui.js`).
  `media.php` answers 404 when it cannot make a format - never a picture of
  the error, which would be stored and never fire `error`.

## 5. CSS

Layers, declared in the panel head: `@layer cms, project, preview;`.
GUI CSS is in `cms`, project overrides in `project`, the page preview in
`preview`.

Where styles live:

| Path | Content |
|---|---|
| `system/public/css/00_theme.css` | Colours a project may override. |
| `system/public/css/10_dropdown.css` … `15_markups.css` | Dropdown/context menu, icons, WYSIWYG editor, image editor — generic, any GUI may use them. |
| `system/public/css/18_ui.css` | Tokens and basic GUI rules. |
| `system/public/css/20_base.css` … `x_responsive.css` | Shared GUI building blocks: base, pylon, table, widgets, spinner, tree (`27_tree.css`), layout, nav, sidebar, forms, lightbox, components, then chart, elements, menu, tags, responsive. |

One flat folder, loaded by name: the number prefix is the cascade order.
A new file gets the number of where it belongs, never a subfolder.
| `cms/public/css/pages.css` | Page header (crumbs, title, language and device switch) and the page preview. |
| `<panel>/*.css` | Only rules for that one panel. |

Rules:

- **Module CSS is for module-specific rules only.** If two panels need it,
  it belongs in the shared system CSS.
- **Use tokens, never raw colours**: `--cmsBgColor`, `--cmsTextColor`,
  `--cmsMainColor`, `--cmsHighlightColor`, `--cmsLine`, `--cmsMuted`,
  `--cmsSuccess`, `--cmsError`, `--cmsWarning`, `--cmsRadius`,
  `--cmsShadow`, … (all in `18_ui.css`).
- **There are two button styles, no third**: `.button` (grey,
  `--cmsButtonBg`, derived from `--cmsBgColor`) and `.button.submit`
  (highlight). `.button.icononly` is the square icon variant. Need a
  different look? Change the token, not the markup.
- Building blocks to reuse before writing new CSS:
  `section.widget` (with `header > h2`, optional `.is-list` for a table,
  `.is-wide`), `.widgets` (grid of widgets), `label.field > span + input`,
  `td.icons > ul > li > a.button.icononly`, `.barmenu`, `.cmslightbox[data-size]`,
  `.tree`, `.dropdown`/`.contextmenu`, chips/tags (`tags.css`).
- Icons: Font Awesome 7 Pro (`fa-regular`, `fa-duotone fa-regular`).
- Flags: `<img src="https://flagcdn.com/{code}.svg">` (see
  `cms/panels/cms/010_pages/controls.tpl.php`).

Fonts in use (`system/public/fonts/`): `InterVariable.woff2` (GUI),
`fontawesome/fa-regular-400.woff2`, `fontawesome/fa-duotone-regular-400.woff2`.
Nothing else — placeholder images drawn by `mediaImage.php` use the Imagick
default font.

## 6. JavaScript

- `system/public/js/` — core (`sys.js`, `lib.js`, `panelNode.js`,
  `panelTree.js`, `core/`), loaded on every page.
- `system/public/js/gui/` — GUI scripts on every panel page (not in `js/`
  or `js/core/`: the site frontend loads those too):
  `ui.js`, `dropdownMenus.js` (dropdowns, split buttons), `HTMLeditor.*.js`
  (WYSIWYG + its context menu), `imageEditor.js`, `jobs.js`, `portrait.js`.
- `<package>/public/js/` — registered with `addPanelAssets()`.
- `<panel>/panel.js` — loaded with the panel, also on AJAX navigation.
  **Other `.js` files in a panel folder only load on a full page render**;
  put shared code in a public folder instead.
- Shared panel methods (`toggleMenu`, `inputPrompt`, `getContextmenu`,
  `userConfirm`) live on `classes.panelNode` (`system/public/js/panelNode.js`).

### WYSIWYG

- Rich fields (`printHtml`) are `div.editor[contenteditable=true]`. Text
  fields with a fixed tag (`printText`, `<h1 class="editor plaintext">`) are
  `contenteditable=true` too, but `wysiwyg.inline(editor)` keeps them to inline
  formats: the bar offers bold, italic, underline, strikethrough, link and
  clear only, `blockOf()` never makes a `<p>` in them, Enter is a `<br>`,
  pasted blocks become lines. Page title and tree label stay
  `contenteditable="plaintext-only"`.
- No `execCommand` for formatting: `HTMLeditor.format.js` (`wysiwyg.*`)
  changes the DOM itself — `toggle('STRONG'|'EM'|'U'|'S')`, `setBlock`,
  `toggleList`, `clearFormat`, links, paste — and keeps its own undo history
  per field. The menus (`HTMLeditor.menus.js`) call it through
  `formatHTMLeditor`.
- What the menus offer is what a field may hold: p, h1–h6, ul/ol/li,
  strong/em/u, a, br, tables. Pasted HTML is cut down to that (`PASTE` in
  `HTMLeditor.format.js`; Word lists become lists, colours/fonts/classes go).
  Saving sends `wysiwyg.html(editor)`: b/i → strong/em, spans and fonts
  unwrapped, links without href and empty formatting removed.
- One floating bar (`contextMenuWW` in `HTMLeditor.contextmenu.js`, items
  from `HTMLeditorRightClick` in `HTMLeditor.menus.js`): at a selection, at
  the caret after a click or tap (block bar), on hover over a/img/video/iframe
  (mouse) or a tap on them (touch), and on right click. It opens at the
  pointer (`lastPoint` / `pointOf`), not at the element. Links in a field
  must keep their pointer events (a tap has to hit the `a`); the click is
  prevented instead. `placeEditorMenu`
  keeps it fixed inside `visibleArea` (window or visual viewport cut down by
  scrolling boxes): above the point, else below, else inside
  (`no-arrow`). On touch a text bar docks at the edge
  away from the text — the system copy/paste menu can't be switched off and
  would cover it. The arrow follows the target; submenus flip with
  `fitEditorSubmenu`. A mouse press on an image is prevented (no selection);
  a click on media opens its bar, never the block bar. Scroll or
  typing closes it. Items: `sep` starts a group, `selected`, `disabled`,
  `reopen` (bar comes back after the command), `subs`; a command returning
  `false` keeps the bar open (e.g. the class form).
- Undo/redo buttons use `wysiwyg.step`/`canStep`. Tables: `wysiwyg.table(action,
  cell)` (rows, columns, header row, delete), `insertTable`, Tab moves through
  cells. Block classes: `wysiwyg.blockFor` / `setBlockClass` on p, h1–h6,
  ul, ol, table.
- The site reset lives in the `preview` layer, above the `cms` layer: helpers
  for the edited content (frames, table cells) go unlayered into
  `panels/cms/head.tpl.php`, and CMS table styles skip `.editor table`.
- iOS keyboard format buttons arrive as `beforeinput` and go through the same
  formatter.
- Media formats: `editorFormats` (head.tpl.php) holds the image formats an
  editor may pick (`mediaFormats::editorImages()`, no system formats, no
  `cms_display: none`) and the project's `VIDEO_FORMATS`.
  `changeMediaFormat` rewrites `var/original/…` ↔ `var/formats/<format>/…`;
  a missing image format is made by `media.php` (the `.htaccess` sends a
  missing file under `var/` to the script), a missing video format by the
  project while the original plays.
- The link dialog (`cms/pages/editor/editLink`) re-renders the page: the field
  is found again by its data attributes and the place by text offsets. Its
  button saves the field, then closes the dialog. Tabs: address, page
  (`searchLinkPages`), file (the page's media library, thumbs from
  `Panel::thumbOf`). A call that opens or closes a lightbox keeps the scroll
  positions (`server.scrollPositions` / `restoreScroll`). A POST that renders
  (a lightbox opened by POST) is marked as rendered, so the next diff starts
  from what the window shows.
- HTML mode (`cms/pages/editor/htmlmode`): `HtmlSource::pretty()` lays the
  field out (blocks on their own lines, indented, a line after `<br>` and per
  image/video), `compact()` drops those line breaks on save. Line breaks in
  the source are layout only.
- Actions come from the last URL segment (`../../updateHTML`), never from a
  posted `action` field. `historyPush` leaves the action out of the address.
- `insertTable` splits the top-level paragraph or list at the caret and puts
  the table in between. `Panel::updateField` drops leading line breaks.
- Hiding the iPad keyboard keeps the focus: a `visualViewport` resize that
  uncovers the screen ends typing (`data-typing` off, `inputmode="none"`).
- CSS classes (`editBlockClass`, `wysiwyg.setBlockClass`) work on p, h1–h6,
  ul, ol, table, img, video and iframe; the table bar sets the table's.
- Touch keeps the keyboard down with `inputmode="none"`, set on pointerdown.
  A re-render that keeps the focused field (`core/focus.js`) gives it the new
  attributes and drops that, so a dialog that writes into a field
  (`applyHTMLeditorLink`) takes the caret out of it on touch first.

### Page preview and layout

- The preview is `.cmsPreview` (around `article.cmsContent`), the container
  `cms-preview` that `x_responsive.php` turns the site's `@media` into.
  `Systopic\Cms\Pages\PreviewWidths::of()` reads the media queries of the
  site CSS (unminified files): phone = widest ≤ 640, tablet = widest
  641–1100, desktop = one above. Only defined sizes get a device button;
  the widths go in as `--cms-{desktop,tablet,mobile}-width`, the choice as
  `body[data-cms-device]` (localStorage `cmsDevice`). Desktop wider than the
  column scrolls sideways.
- Touch or mouse: `html[data-pointer]` holds the input used last (set in
  `head.tpl.php` on pointerdown) - desktops with a touch screen have both.
  Touch hints and tap behaviour go by `:root[data-pointer="touch"]`, finger
  sizes by `@media (any-pointer: coarse)`, JS by `event.pointerType`.
  `(hover: none)` is only for layout.
- Window title: `WebApp::stageMark()` puts 🟢 (DEV), 🟡 (STAGING) or 🟠 (RC)
  in front, in the CMS and on the site (`appSite::buildTitle`).
- Debug: `main[data-debug]` (body classes are rewritten at runtime) puts
  the CMS on a checkerboard, padded top and sides, open at the bottom
  (`30_layout.css`). The petrol system frame stays as it is. The main menu ends above the status bar.
- Signing in ends with a full load (`auth Panel::enterCms()`, a `redirect`
  with method `load`): the login's css would otherwise stay in the document.
- `LoginLog::entries($names)` reads `var/log/login.log`, newest first; the
  user editor shows the user's last five and links to the login log with
  `?q=<name>` (the log template prefills the search and hides the rest).
- Return after sign-in: a request without a session stores the page it
  came from (`Session::rememberReturn`, loader.php, site guard);
  `Panel::enterCms()` takes it (`Session::takeReturn`). Only paths below
  the site root, never an auth path.
- `LoginLog::write($name, $action, $method)`: methods as constants
  (`PASSWORD`, `EMAIL_LINK`, `DEVICE`, `SWITCH`, `oauth:<provider>` ...),
  `methodLabel()` for lists. Lines of before have no method.
- Site settings `PAGE_IS_PUBLIC` / `AUTO_LANGUAGE_DETECTION`:
  `Systopic\Cms\Pages\Route\SiteAccess`, asked in `Frontend::claims()`.
  Not public: the project's `panels/auth` if it has one, else cms/auth.
- A drag of our own blocks the browser's native dragstart while it runs.
- Device switch: `fit` (no fixed width) beside the widths from `PreviewWidths`.
- Debug needs the project's `debug.php` (tools/debug, rolled out by the
  tools manager, `rollouts` in `tools/debug/builder/config.local.json`);
  without it `DEBUG()` is false whatever the environment says.
- The pylon width is `--pylonWidth` on `div.pylon`; `30_layout.css` caps it
  (30rem, 50vw up to 1200px window width). `panel.js` sets it while dragging.
- A view that depends on GET parameters needs `updateView`/`updateViews`
  (settings: `main` and `controls`, the crumbs) or it is kept as it was.
- `.flexColumn > *` grows; messages in it (`.flexColumn > .message`) don't.
- Image editor (`imageEditor.js`): touch gets 32px corners and an 8px drag
  threshold, a tap beside the frame keeps it open; only Apply saves,
  Cancel closes. Free-height formats get `.flexHandle` below the frame.
- Search fields are `input[type=search]` — `50_forms.css` gives them the
  magnifier. The page search sits in `.pylonTitle` of the Pages module.

## 7. Texts and header

- All GUI texts are **English**, short and plain ("Delete this user? This
  cannot be undone.", "Add user", "Search").
- The panel header (`controls`) holds **only global buttons** for the
  panel. Row actions sit in the row (`td.icons`), section actions in the
  widget header.
- Every list page has a one-line hint under the `h1`
  (`<p class="tagsHint">The pen opens the user.</p>`).

## 8. `_legacy/`

Nothing is deleted outright. Dead files and rules move to `_legacy/` in the
same package, under their original path, and are no longer loaded:

- `system/_legacy/public/css/dead-rules.css` — CSS rules whose classes no
  template or script uses anymore (grouped by source file).
- `system/_legacy/public/js/cms/`, `_legacy/lib/…`.

Outdated assets are deleted, not kept: old fonts, old icon sets. Only
`fa-regular` and `fa-duotone fa-regular` have a font — `fa-light`,
`fa-solid` etc. fall back to the regular glyphs, so do not use them.

Before you call a class dead, search **all** packages and projects
(`system`, `cms`, `db`, `relay`, `pwk`, `spaceticker`, the project) and look
for built class names (`'is-' + state`, `type + 'Dragging'`). Known built
ones: `*Dragging`, `*Dragged`, `cmsPane-*`, `dz-*`, `is-unknown/online/offline`.

## 9. Assets and URLs

Never write `/vendor/...` or package paths into templates. Use the helpers:

```php
fsLoader::includeCSSFilesInLayer(fs::toInternal($dir . 'css/'), 'cms');
fsLoader::includeScriptFiles(fs::toInternal(__DIR__ . '/public/site/pagesRenderer.js'));
\Systopic\System\Sys\Packages::panelAssetHref('img/signet.svg');
```

They resolve the right URL for the asset mode (source or published
`/assets/<package>/`). Tools → Assets shows what is served from where.

## 10. Checking your work

Every GUI change is checked in the browser before it counts as done:
open the panel, use the feature, look at a screenshot. At minimum go through
Pages (tree, page header, language menu, preview, sidebar), Settings, Users
(list + user lightbox), Tools (logs + a lightbox). Test with the session
language set to one that has pages — an Italian session shows an empty tree.

Every change a user notices gets a line in the release notes,
`system/panels/cms/auth/releases.php` (newest version first). The login
footer shows that version and links to the notes.

Commit only when asked. Commit `system` and `cms` separately, each in its own
repository.

## 11. Open work

Known places where the core still knows the cms (fix step by step):

- `system/loader.php` and `public/js/core/server.js` — login URL `cms/auth/`.
- `public/js/core/event.js` — checks `body.cms`.
- `server.command.js`, `clientDocument.php` — call `insertHtmlAtCursor`,
  `openContextMenu`.
- `site/head.system.tpl.php` — `findByName(['cms','pages'])`.
- `Settings/ProjectForm.php`, `ProjectFile.php`, `EnvironmentForm.php`,
  `EnvironmentFile.php` — the settings forms (Media, Environment in system;
  Page Settings in cms uses the same `ProjectForm`). Environment shows one
  `section.widget` per group. `EnvironmentFile::update()` does read-change-write
  under an exclusive lock (lock file in the system temp dir, not in the synced
  `config/`) and replaces the file by rename; unreadable JSON is never written.
- Frontend edit bar: `cms/src/Pages/EditBar.php` (echoed by `cms/site.php` on a
  full render, define `FRONTEND_EDIT_BAR`, editors only) and
  `cms/public/site/editBar.js` (custom element, shadow DOM). The section list
  comes from the `editBarMenu` action in `panels/cms/actions.php`; with debug
  on the reply goes through `sysdebug.trimXhrResponse`. Logout:
  `cms/logout?to=<path>`. Every link but logout opens in a new tab. Icons:
  the panel's `$icon` without `fa-duotone`; the shadow root links
  `css/11_fontawesome.min.css`, the `@font-face` rules sit in the document
  (a shadow root ignores them).
- Pages sidebar (`cms/panels/cms/010_pages/sidebar/`): section headers carry a
  link icon (`a.sidebarInfo` in `main.tpl.php`: Meta → default values,
  Settings → `cms/settings/pagesettings/`, Tags → `cms/settings/tags/`). The
  share image drop zone is drawn by `Panel::shareImage()` from the page row
  (not `app::site()->shareImage`, whose page is the one of the request), with
  a hover overlay `.dropActions` holding the crop pen.
- `Mail/Letter.php`, `Auth/Portrait.php` — read `00_theme.css` and the logo.
- `app.php` — `$renderStyle = 'cms'`; `messageGuide.php` links to `cms/pages/…`.
- `core/event/event.pathItem.js` — saves WYSIWYG fields through `wysiwyg.html()`.
- `HTMLeditor.funcs.js` — endpoints `cms/pages/editor/…`;
  `imageEditor.js` — `cms/pages/getImageEditor`, `updateMediaCrop`.
- `panels/cms/head.tpl.php` — inline `<style>` for `article.cmsContent
  [contenteditable]` (unlayered on purpose) belongs to the cms.

Tried and dropped (24 Oct 2026): moving the GUI assets into `systopic/cms`.
Apps without pages lost their styling (spaceticker's login), and giving them
the cms would also bring pages, sites and the frontend. A separate
`systopic/gui` package only pays off once a project runs the system without
any backend — none does. The GUI stays in `systopic/system`.
