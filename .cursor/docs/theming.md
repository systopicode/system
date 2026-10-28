# fluxo — a project's own look, and how a panel is built

For people and agents. This is about fluxo cms (the GUI under `/cms/`, the
login, the system mails, the edit bar on the website), not the website's
own frontend.

## 1. Where a project puts its look

Everything lives in the project, never in the packages:

| Project path | What it does |
|---|---|
| `overrides/css/*.css` | GUI overrides, loaded after the system CSS in the `project` layer. Theme colours go here. The theme editor writes `overrides/css/system_overrides.css` (it renames `cms_overrides.css` on save). |
| `css/system_overrides.css` | Same layer, when a project keeps the file next to the site CSS. Also the place for `@font-face`/`@import` of fonts (they cannot be scoped, see `cms-preview-css.md`). `css/cms_overrides.css` is still read while the new file does not exist yet. |
| `overrides/img/logo.svg` | Logo on the login card and at the top of every system mail. |
| `overrides/img/<name>` | Replaces the system image of the same name, see the table below. |
| `overrides/js/*.js` | Extra GUI scripts, loaded last. |
| `PUBLIC_PAGE_NAME` (config) | Site name in the window title and the installed web app ("Site // fluxo cms"). |

Images a project may replace by name (`Packages::panelAsset()` looks in
`overrides/` first for `img/…`):

| File | Where it shows |
|---|---|
| `img/signet.svg` | The mark in the menu, the edit bar on the website. |
| `img/logo-horizontal-dark.png` | Word mark in the wide menu on the dashboard; mail logo without `logo.svg`. |
| `img/logo-horizontal-light.png` | Login card without `logo.svg`. |
| `img/share_default.jpg` | Share image (`og:image`) of the CMS pages. |
| `img/favicons/*` | Favicons, home screen and web app icons. |
| `img/icon.svg` | SVG favicon. |

## 2. Theme colours

The tokens are in `system/public/css/00_theme.css`. A project overrides
only these:

```css
/* overrides/css/theme.css */
:root {
	--cmsMainColor: #1f3a45;       /* menu, headings, lines */
	--cmsHighlightColor: #fcb60c;  /* submit buttons, active marks */
	--cmsBgColor: #f0f3f4;         /* page background, grey buttons */
	--cmsTextColor: #161c1f;
	--cmsRadius: 6px;
}
```

Everything else (`--cmsLine`, `--cmsMuted`, `--cmsButtonBg`, …) is derived
from these in `18_ui.css`, so a theme stays four colours.

The same four colours also reach places without the GUI stylesheet.
`Systopic\System\Sys\Theme` reads them on the server from `00_theme.css`,
`css/system_overrides.css` (or `css/cms_overrides.css` until that file is saved under the new name) and `overrides/css/*.css` (the last value wins):

- system mails (`Systopic\System\Mail\Letter`: top line, heading, button),
- drawn portraits of accounts without a photo (`Auth\Portrait`),
- the web app manifest and `theme-color` (`Sys\WebApp`, `head.tpl.php`),
- the edit bar on the website (`Systopic\Cms\Pages\EditBar`). Its text colour
  follows `Theme::textOn()`, so a light main colour gets dark text.

The server only understands a plain hex value: `--cmsMainColor: #1f3a45;`.
`var()`, `rgb()` or `color-mix()` work in the GUI but are not seen by mails
and the edit bar.

## 3. Rules for overrides

- Change tokens, not markup. Override CSS is for the look of one project;
  a fix every project needs belongs in the package.
- Override CSS sits in the `project` layer, so it beats the GUI without
  `!important` and without matching the GUI's specificity.
- The GUI has two button styles (`.button`, `.button.submit`). A theme
  recolours them through the tokens; it adds no third.
- Check the result in four places: dashboard, login, a system mail (reset
  password), the edit bar on the website.

## 4. How a panel is built

A panel is a folder below `panels/cms/` of a package (`system`, `cms`, …).
The trees of all packages are merged by folder name; the sort prefix
(`080_`) orders the menu and is not part of the URL.

```
cms/panels/cms/010_pages/       → /cms/pages/
	panel.php                    class Panel, state, getters
	actions.php                  switch ($this->action) { case 'x': … }
	main.tpl.php                 the content
	controls.tpl.php             header bar above the content
	pylon.tpl.php                left column (tree)
	sidebar.tpl.php              right column
	status.tpl.php               bottom bar
	lightbox.tpl.php             a dialog (child panel with $inNavi = false)
	dashboard.tpl.php            widget on the dashboard, key "pages"
	dashboard.todo.tpl.php       a second widget, key "pages.todo"
	panel.js                     client code of the panel
	module.css, pages.tree.css   styles only this panel needs
	sidebar/, editor/, …         child panels
```

- Dashboard widgets: any panel in the first two levels below `cms` adds
  `dashboard.tpl.php` or `dashboard.<name>.tpl.php`. It starts with
  `<section class="widget"><header><h2>Title</h2>…`; that title also names it
  in the "Add:" row. The user orders and hides widgets himself.
- Views, actions, state, AJAX and the traps around them: `gui.md`
  sections 3 and 4, `panel-actions.md`, `panel-node-api.md`.
- Times shown to the user go through `Systopic\System\Sys\LocalTime`
  (the browser's zone); stored times stay in the server zone (UTC).
- The search (Cmd+K) finds every panel by its menu name and the headings
  and labels of its views; its records it adds in `searchHits(Search $search)`.
- Checks for the dashboard To do (broken links, images without label) are
  queries in `Dashboard.live.sql`; a panel shows the details, like
  Tools → Maintenance → Links (`gui.md`, Search and Link check).
