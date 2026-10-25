# Site CSS in the CMS Page Preview

How the public site's stylesheets reach the page preview inside the CMS —
and only there — using `@scope` and cascade layers.

The preview itself is `panels/cms/010_pages/main.tpl.php`: it renders the real
page through the *page* navigator into `<article class="cmsContent">`, in the
same document as the CMS chrome. See
[panel-diff-rendering.md §6c](panel-diff-rendering.md) for what that does to
the layer tree; this document is only about the styling.


## 1. The problem

Two CSS worlds in one document:

- the **CMS chrome** — `public/css/**`, panel folders. Mostly prefixed
  `.cms`, but not all of it: `article.cmsContent`, `.flexRow`, `.layout`,
  `._1_2`, `body > main > div.content` are bare and reach anything nested
  inside them.
- the **site** — `<project>/public/css/**`, written for a page that owns the
  whole document: `*` resets, `html`, `body`, `main`, `position: fixed`.

Neither was written with the other in mind.


## 2. What the legacy did, and why it could not work

`system/css.php` served a virtual route `css_parsed/<file>.css`: it read the
real stylesheet, split it into rule blocks with a regex, prefixed every
selector with `article `, and then removed the substring `body`.

Four reasons that was unfixable rather than buggy:

| | |
|---|---|
| `preg_match_all('/^[^\{]*\{[^\}]*\}/ms')` | cannot do nested braces. From the first `@media` on, the output is garbage. |
| `preg_replace('~body~','')` | blind substring removal — also hits `.bodytext`, `#body-wrap`. |
| `article :root` | never matches, so the site's custom properties were lost. |
| `@font-face` with `url('../fonts/…')` | resolved against `/css_parsed/`, so the web fonts 404'd. |

And nothing addressed the other direction: the unprefixed CMS rules kept
applying inside the preview.


## 3. What it does now

### Cascade layers decide who wins

`@layer` puts rules into bands. A later band beats an earlier one **whatever
the specificity says** — so the site can style the preview without
out-specifying `.cms .layout`, and without a single `!important`.

Declared once in `panels/cms/head.tpl.php`, before anything that names a
layer:

```css
@layer cms, project, preview;
```

| Layer | What goes in | Loaded by |
|---|---|---|
| `cms` | the system UI and panel CSS | `fsLoader::includeCSSFilesInLayer(…, 'cms')` |
| `project` | `css/cms_overrides.css`, `overrides/css/` | `includeCSSFilesScoped(…, ':root', 'project', limit: '.cmsOwn')` |
| `preview` | the site's own CSS, scoped | `fsLoader::includeCSSFilesScoped()` |

Two traps worth knowing about layers: **unlayered CSS beats every layer**, so
anything left out of this scheme silently wins over all of it; and
`!important` reverses the order.

### `@scope` decides where it applies

```css
@layer preview {
  @scope (article.cmsContent) {
    /* the site's stylesheets, unchanged */
  }
}
```

`@scope` limits which elements a block can match without rewriting a single
selector — which is exactly what the regex was for. `:scope` addresses the
root element itself, replacing the old `body` hack.

**`@scope` does not add specificity.** `@scope (article.cmsContent) { h1 … }`
counts as `h1`, not as `article.cmsContent h1`. That is why the layer is
needed as well: scoping alone would lose to `.cms h1`. The legacy prefix did
raise specificity, but by breaking the stylesheet to do it.

**CMS blocks inside the preview** (notes like "has no template") sit in a
`.cmsOwn` element, so does the placeholder of a file being uploaded into a
text. Site CSS **and** project overrides end there (`@scope (…) to (.cmsOwn)`),
so no page rule reaches it - `!important` included; what it inherits is set
back on `.cmsOwn` itself (`20_base.css`). Anything new the CMS puts into the
preview gets that class.

### Who loads what

Two `fsLoader` methods, because a `<link>` cannot name a layer:

- **`includeCSSFilesInLayer($dir, $layer, $recursive)`** — emits one `<style>`
  with an `@import … layer(x)` per file. The imports sit side by side, so the
  browser still fetches them in parallel, and `url()` inside each file still
  resolves against that file.
- **`includeCSSFilesScoped($dir, $scope, $layer, $skip, $recursive, $limit)`** —
  reads the files and inlines them into the `@scope` block. Inlining is
  unavoidable: a stylesheet cannot be scoped from the outside. Relative
  `url()` are rewritten to the file's address, `@import` is lifted in front of
  the block (it is invalid inside `@scope`) and keeps the layer.

Both share `cssFilesOf()`, which makes the same selection `includeCSSFiles()`
does (sorted, `compressed.css` and duplicates skipped) without the markup.

### What stays out of the scope

Three at-rules are document-wide by nature and do not work inside `@scope`:

| | |
|---|---|
| `@font-face` | `fonts.css` |
| `:root` custom properties | `fonts.css` (`--letterSpacing`) |
| `@keyframes` | `animate.css` |

The project names what the CMS needs at document level in
**`public/css/cms_overrides.css`** — today a single `@import 'fonts.css'`.
That file is the hook; the import is lifted out of the scope and lands in the
`project` layer, document-wide.

`x_responsive.css` is deliberately excluded as well, for a different reason:
its media queries would measure the **browser window**, not the preview pane.
The preview would claim mobile on a narrow window and desktop on a wide one,
neither being what the visitor sees. Responsive CSS stays on the site.

An inlined `url()` would resolve against the document, not against the CSS
folder - that is why the loader rewrites relative ones.


## 4. Two layout consequences of letting the site in

Both appeared the moment the preview had real dimensions, and both are fixed
in `public/css/30_layout.css`:

- **`.flexColumn { min-width: 0 }`** — the flex item of `.flexRow` is the
  column *around* the article, not the article. A flex item's `min-width`
  defaults to `auto` ("never narrower than my content"), and unlike the
  article the column is not a scroll container, so it grew to whatever the
  page demanded and pushed the sidebar out.
- **`article.cmsContent { contain: layout }`** — the site positions `.logo`,
  `nav` and `div.lightbox` `fixed`. Without a containing block they attach to
  the viewport and land on the CMS chrome. `contain: layout` makes the article
  the containing block for fixed descendants. They then scroll with the
  preview instead of sticking over the UI.


## 5. If it misbehaves

| Symptom | Look at |
|---|---|
| the whole CMS UI looks wrong | something unlayered is beating `@layer cms` |
| a site rule does not apply in the preview | is its file in the `$skip` list? is it `@font-face`/`:root`/`@keyframes`? |
| web fonts missing in the preview | `cms_overrides.css` — it is what pulls them in |
| an image in the preview 404s | a `url()` the rewrite does not catch (built with `image-set()` or a variable) |
| a CMS note in the preview takes on page styles | it is missing the `cmsOwn` class |
| the preview pushes the sidebar | a new flex item in the chain without `min-width: 0` |
