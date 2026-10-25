# Panel `actions.php` files

Jede Panel-Folder kann eine `actions.php` enthalten. Sie wird vom Framework
für die im Request adressierte Action ausgeführt — typischerweise als zweite
Stufe nach den eingebauten Action-Methoden auf dem Panel selbst.

## Wer included `actions.php`?

`app.php` löst die Action-Pipeline so auf:

```php
$module = $nav->getActive(); // tiefstes URL-selektiertes Panel

if (!empty($action)) {
    if (!$module->actionGranted($action)) { /* 403 */ }

    if ($module->$action instanceof \Closure)         { $module->$action->call($module); }
    if (method_exists($module, $action))               { $module->$action(); }

    $module->runActions(); // <-- führt actions.php im Panel-Folder aus
}
```

`runActions()` ist auf `Panels\PanelNode` definiert (siehe
`src/Panels/PanelNode.php`). Es bindet das aktive Panel als `$this` an die
include-Datei — analog zu `PanelNode::render()` für Templates.

## Konvention für neue / migrierte `actions.php`

`$this` ist die aktive Panel-Instanz. Greife immer über `$this` auf den
Panel-Kontext zu. Globale Variablen sind **veraltet** und nur noch aus
Kompatibilitätsgründen aktiv.

| Alt (global)            | Neu (`$this`)                    |
| ----------------------- | -------------------------------- |
| `$module`               | `$this`                          |
| `$module->state`        | `$this->state`                   |
| `$module->tree`         | `$this->tree` *(panel-spezifisch)* |
| `$state`                | `$this->state`                   |
| `$action`               | `$this->action`                  |
| `$modulePath`           | `$this->path`                    |
| `$user`                 | `\user::getActive()`             |
| `$module->update(...)`  | `$this->update(...)`             |
| `$module->updateView()` | `$this->updateView()`            |

Typ-Hint am Dateianfang für IDE-Support:

```php
/** @var pages_module $this */
```

Beispiel-Skelett:

```php
<?php

/** @var pages_module $this */

if (http::method() === 'PUT' && http::dataset('node_id')) {
    $actionPage = page::get(http::dataset('node_id'), $this->state->lang);
}

switch ($this->action) {
    case 'fold':
        $this->tree->fold(http::dataset('id'), $this);
        $this->update('tree');
        break;

    case 'showTrash':
        $this->state->treeMode = 'trash';
        $this->update('tree');
        $this->updateView('pylon');
        break;

    // ...
}
```

## Sub-Includes

Manche Sub-Panels bündeln ihre Actions, indem sie das `actions.php` eines
Vorfahren einbinden, z. B.:

```php
// panels/cms/010_pages/editor/editLink/actions.php
include '../../actions.php';

switch ($this->action) {
    case 'selectImageFormat': ...
}
```

Da `runActions()` `$this` an das tiefste selektierte Panel bindet, bleibt
`$this` über den `include` stabil — der Sub-Include sieht denselben
Panel-Kontext wie die einschließende Datei. Achte aber darauf, dass die
inkludierte parent-`actions.php` keine Properties anspricht, die nur auf der
übergeordneten Panel-Klasse existieren (z. B. `$this->tree`), wenn das
tatsächlich aktive Panel diese gar nicht trägt — `switch` fällt für
nicht-passende Cases sowieso durch, also reichen `case`-Guards normalerweise.

## Backwards-Compat-Shims

`PanelNode::runActions()` setzt vor dem `include` zusätzlich:

- `$GLOBALS['module']` / lokales `$module` (by-reference) → `$this`
- `$GLOBALS['state']`  / lokales `$state`  (by-reference) → `$this->state`
- lokales `$action` (by-value) → `$this->action`

So funktionieren noch nicht migrierte action-Dateien unverändert weiter. Für
neue Dateien gilt: **niemals** auf diese Shims verlassen — immer `$this`
verwenden.

## Was läuft *nicht* über `runActions()`?

`site.php` (Frontend-Pipeline) included weiterhin `lib/actions.php` und
`<template>/actions.php` direkt im globalen Scope — dort gibt es kein
Panel-`$this`. Diese Dateien bleiben global-basiert. Das Konzept hier
betrifft ausschließlich **Panel**-Actions unter `panels/.../actions.php`,
die durch `app.php` dispatched werden.

## Migration einer existierenden `actions.php`

1. `$module` → `$this` (auch in Method-Calls: `$module->update(...)` →
   `$this->update(...)`).
2. `$state` → `$this->state`.
3. `$action` (im `switch`) → `$this->action`.
4. `$modulePath[N]` → `$this->path[N]`.
5. `$user` → `\user::getActive()` (oder besser: explizit den User holen).
6. `@var`-Hint am Dateianfang ergänzen (IDE-Typ für `$this`).
7. Linter laufen lassen — verbleibende Globals sind entweder echte Globals
   (z. B. `$image_formats`) oder pre-existing Dead-Code-Bugs (z. B. nicht
   initialisiertes `$id` / `$selPage`), die nicht Teil dieser Migration
   sind.

Referenz-Migration: `panels/cms/010_pages/actions.php`.
