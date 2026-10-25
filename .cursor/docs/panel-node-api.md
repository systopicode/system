# PanelNode API — Kompakte Referenz

Ergänzt [`panel-system.md`](panel-system.md) (Architektur) als Template-/Klassen-Cheatsheet.

---

## 1. Navigation — `with*`-Methoden

Alle Methoden geben `bool` zurück (`true` = erfolgreich geöffnet und gerendert).  
Invariante: `$nav->activePanel === $this` während der Ausführung — der Navigator ist ein
Request-scoped Single-Instance, der statisch in alle PanelNode-Instanzen injiziert wird.

| Use Case | Methode |
|----------|---------|
| Root-Body-Template öffnen | `$this->withRoot('body')` |
| URL-selektiertes Panel rendern | `$this->withSelected('main')` |
| URL-selektiertes **Kind** öffnen | `$this->withChildSelected()` → `main.tpl.php` |
| URL-selektiertes Kind, anderer View | `$this->withChildSelected('pylon')` |
| URL-selektiertes Kind, kein Template-Check | `$this->withChildSelected(false)` |
| URL-selektiertes Kind + Fallback-Logik | `if (!$this->withChildSelected('main')) { ... }` |
| Benanntes Kind öffnen | `$this->withChild('toolbar', 'main')` |
| View im **aktuellen** Panel | `$this->withView('mainheader')` |
| Custom-Callable statt Auto-Render | `$this->withChildSelected('main', fn($p) => $p->render('alt.tpl.php'))` |

> `withView()` wechselt **nicht** das active Panel — `$nav->activePanel` bleibt `$this`.
> Das macht `withView()` sicher für Ketten wie `body → withView('mainheader') → withView('navi')`.

---

## 2. Kinder iterieren

```php
// Navigation — kein Template-Check, Kinder sind lazy-built Stubs
$this->eachChild(fn($child) => print($child->getNaviItem()));

// Mit Template-Check: überspringt Kinder ohne 'main.tpl.php'
$this->eachChild(fn($child) => $child->render('main.tpl.php'), 'main');

// Fertig gebautes <ul>-Navi-HTML
echo $this->getNavi();     // Standard: 2 Levels
echo $this->getNavi(1);    // flach (kein Drill-down)
echo $this->getNavi(3);    // 3 Levels tief
```

- `getNaviItem()` kann in `panel.php` überschrieben werden für eigene `<li>`-Darstellung.
- `toNavi()` ist der Legacy-Weg desselben Prinzips (PWK-Hauptmenü: `$this->eachChild(fn ($child) => print $child->toNavi())`).
  `PanelNode` liefert bewusst `''` — das Markup gehört dem Projekt, also implementiert es die Projekt-Basisklasse
  (`Patchworkkit\Panels\Root\Panel`), und ein Panel ohne eigenen Eintrag erscheint nicht im Menü.
- Lazy-built Kinder haben nur Constructor/Builder-Properties (`name`, `label`, `href`, `path`,
  `folder`, `parent`, `inPath()`). Properties aus `onLoad()` sind **nicht** verfügbar.
- `eachChild()` setzt `ensureChildrenBuilt()` automatisch voraus.

---

## 3. Panel-Eigenschaften

| Ausdruck | Typ | Beschreibung |
|----------|-----|-------------|
| `$this->name` | `string` | Logischer Name, Ordnerpräfix entfernt (`010_pages` → `pages`) |
| `$this->path` | `string[]` | URL-Segmente von Root bis hier (`['cms', 'pages']`) |
| `$this->folder` | `string` | Ordnername auf Disk, inkl. Präfix (`010_pages`) |
| `$this->realPath` | `string[]` | Dateisystem-Pfad-Segmente (wichtig bei Multi-Root) |
| `$this->label` | `string` | `name` als Überschrift, `str::name2label()` (`user_roles` → `User Roles`); eigene Beschriftung: `$this->label = 'Tags'` im Panel setzen |
| `$this->href` | `string` | URL zu diesem Panel (`http::$root . 'cms/pages/'`) |
| `$this->getHref(http::$root)` | `string` | Explizit — bevorzugt in Klassencode |
| `$this->inPath()` | `bool` | Panel liegt auf dem aktiven URL-Pfad |
| `$this->isSelected()` | `bool` | Panel ist das tiefste URL-gewählte Panel |
| `$this->parent` | `?PanelNode` | Eltern-Panel |
| `$this->root` | `?PanelNode` | Oberster Panel im Teilbaum |
| `$this->children` | `PanelNode[]` | Kinder-Array (nach `ensureChildrenBuilt()`) |
| `$this->childrenByName['name']` | `?PanelNode` | Direktzugriff auf ein Kind |
| `$this->hasChild('name')` | `bool` | Kind mit dem Namen vorhanden? |
| `$this->childSelected` | `?PanelNode` | Das per URL gewählte Kind |
| `$this->childSelectedName` | `?string` | Name des erwarteten Kindes (nächstes URL-Segment) |
| `$this->getSiblings()` | `PanelNode[]` | Geschwister-Panels |

---

## 4. State

State wird in der User-Session persistiert:  
`user::getActive()->state.children.<name>.data`

```php
// Lesen und Schreiben
$this->state->searchString = 'foo';
$val = $this->state->filter;

// Standardwerte definieren — wird beim ersten Zugriff und nach resetState() angewendet
function getDefaultState(): object {
    return (object) [
        'filter'      => 'all',
        'hiddenViews' => [],
    ];
}

// State zurücksetzen
$this->resetState();             // alle Felder auf Default
$this->resetState(['filter']);   // nur 'filter' zurücksetzen

// State-Objekt aus einem anderen Objekt befüllen
$this->updateState($obj);        // obj::fill($this->state, $obj)
```

`hiddenViews` ist ein reservierter State-Key — er steuert die View-Sichtbarkeit (siehe §5).

---

## 5. View-Sichtbarkeit & Re-Render

```php
// AJAX: View bei diesem Request neu rendern (markiert 'keep' → 'update' im Diff)
$this->updateView('results');
$this->updateViews(['results', 'filter']);

// Sichtbarkeit — persistiert in $this->state->hiddenViews[]
$this->viewVisible('tags');   // bool: ist der View aktuell sichtbar?
$this->viewHidden('tags');    // bool: Umkehrung

$this->hideView('tags');      // ausblenden + updateView automatisch
$this->showView('tags');      // einblenden + updateView automatisch
```

`withView('tags')` gibt `false` zurück wenn der View versteckt ist — kein Render, aber der
DomLayer-Slot bleibt erhalten (stabile Positions-Indizes für den Client-Diff).

Der View `confirmbox` ist eine Ausnahme: seine Sichtbarkeit steht **nicht** in `hiddenViews`,
sondern wird aus der offenen Rückfrage abgeleitet. Siehe
[`server-side-confirm.md`](server-side-confirm.md).

---

## 6. Lifecycle Hooks

In `panel.php` überschreiben. Reihenfolge: Build → Load → Render.

| Hook | Zeitpunkt | Typischer Einsatz |
|------|-----------|-------------------|
| `onBuild()` | Direkt nach Konstruktion, nur Ancestors bekannt | Selten; für sehr frühe Initialisierung |
| `onLoad()` | Nach vollständigem Build, auf dem gesamten URL-Pfad | DB-Queries, State lesen, Redirects |
| `onLoadSelected()` | Nur auf dem tiefsten URL-gewählten Panel | Aktionen die nur das "aktive" Panel braucht |
| `onLoadThisClass()` | Einmal pro Ordner, wenn Panel eigene Klasse hat | Setup der Klassen-eigenen Felder |
| `onLoadThisFolder()` | Einmal pro Ordner (legacy `module.php`) | Legacy-Migration |
| `onAfterRenderSelected()` | Nach dem Rendern des selektierten Panels | Cleanup, Response-Nachbearbeitung |

> Alle Hooks laufen **vor** jeglichem Render-Output — kein OB ist offen, keine Layer sind aktiv.
> Nie Output in Hooks erzeugen.

---

## 7. Access Control

```php
// Zugriff auf das Panel (Build-Phase)
function buildGranted(): bool {
    return user::known();            // Standard
    // return user::hasRole('admin');
    // return user::hasOneRole('editor', 'admin');
}

// Zugriff auf eine Aktion (AJAX-Phase)
function actionGranted($action = null): bool {
    return match ($action) {
        'delete'   => user::hasRole('admin'),
        'setOwner' => user::hasRole('admin'),
        default    => user::known(),
    };
}
```

`buildGranted()` → `false` leitet auf den Auth-Panel um (`cms/auth/`).  
`actionGranted()` → `false` bricht mit HTTP 403 ab.

---

## 8. JS laden

```php
// In onLoad() oder am Anfang von body.tpl.php / main.tpl.php:
$this->loadJS();
// Lädt panels/<realPath>/panel.js via client::loadJS()
// Root ('sys' oder 'app') wird automatisch am sourceRoot erkannt
```

Eigener Dateiname über `$this->jsClass`:
```php
public ?string $jsClass = 'my-panel.js';
```

---

## 9. Render Mode

Nur auf dem **Render-Host-Panel** wirksam (das Panel dessen `body.tpl.php` via
`findRenderRoot('body')` gefunden wird). Kinder-Panels haben keinen eigenen Render-Mode.

```php
// In panel.php überschreiben:
function getRenderMode(): string {
    return 'slices';   // Standard: AJAX-Diff, JS-Client-Rendering, storeReference()
    // 'html'          // Direktes HTML, kein Layer-Recording, kein storeReference()
    // 'markers'       // Server-side mit Kommentar-Markern, kein JS nötig
}
```

| Mode | Initial Load | AJAX-Navigation | storeReference() |
|------|-------------|-----------------|-----------------|
| `slices` | Placeholder + Layers in Head | Diff via `updateDocument` | ja |
| `html` | Vollständiges HTML direkt | Nicht vorgesehen — JS-Navigation schlägt fehl | nein |
| `markers` | Vollständiges HTML mit Markern | — | nein |

---

## 10. Aktionen (actions.php)

```php
// actions.php wird von $module->runActions() aufgerufen (app.php)
// $this = aktives Panel, $state = $this->state, $action = Request-Action

// Typisches Muster:
if ($action === 'updateFilter') {
    $this->state->filter = http::params('filter');
    $this->updateView('results');
}

// Oder mit switch:
switch ($action) {
    case 'reset':
        $this->resetState();
        $this->updateViews(['results', 'filter']);
        break;
}
```

---

## 11. Kurzreferenz — Legacy → Neu

| Legacy (`module::`) | Neu (`$this->`) |
|---------------------|----------------|
| `module::openRoot('body')` | `$this->withRoot('body')` |
| `module::openSelected('main')` | `$this->withSelected('main')` |
| `module::openChildSelected('main')` | `$this->withChildSelected('main')` |
| `module::openChild('nav', 'main')` | `$this->withChild('nav', 'main')` |
| `module::openView('sidebar')` | `$this->withView('sidebar')` |
| `while (module::openNextChild(false))` | `$this->eachChild(fn($c) => ...)` |
| `module::close()` | automatisch in `with*` / `eachChild()` |
| `$module->state->foo` | `$this->state->foo` |
| `$module->updateView('main')` | `$this->updateView('main')` |
| `$module->loadJS()` | `$this->loadJS()` |
