# Panel-Klassennamen: Modul-basiertes Namespace-Schema

## Konzept

Jede `panels/`-Root wird zu einem **benannten Modul** mit funktionalem Namespace. Panel-Klassen werden nach folgendem Schema adressiert:

```
<ModuleName>\Panels\Root\<PfadInPascalCase>\Panel
```

| Teil | Bedeutung |
|---|---|
| `<ModuleName>` | Projekt-Root-Namespace (z.B. `Systopic\System`, `Spaceticker`) |
| `\Panels\` | Sub-Namespace für alle Panel-Klassen des Moduls |
| `\Root\` | Kennzeichnet den Haupt-Panel-Tree (trennt Infrastructure von Implementierungen) |
| `<Pfad>` | prefix-gestrippter Panel-Pfad in PascalCase (`040_backup` → `Backup`) |
| `\Panel` | Klasse ist immer `Panel`, Datei immer `panel.php` |

Die `\Root\`-Ebene ist dabei die entscheidende Trennung:
- `Systopic\System\Panels\PanelNode` — Infrastructure-Klasse (in `src/`)
- `Systopic\System\Panels\Root\Cms\Panel` — Konkrete Panel-Implementierung (in `panels/`)

### Beispiele

| Ordner | Legacy-Klasse | Neue FQCN |
|---|---|---|
| `panels/cms/` | `cms_module` | `Systopic\System\Panels\Root\Cms\Panel` |
| `panels/cms/040_backup/` | `backup_module` | `Systopic\System\Panels\Root\Cms\Backup\Panel` |
| `panels/cms/040_backup/create/` | `backup_create_module` | `Systopic\System\Panels\Root\Cms\Backup\Create\Panel` |
| `spaceticker/panels/cms/020_contacts/` | `cms_contacts_module` | `Spaceticker\Panels\Root\Cms\Contacts\Panel` |
| `spaceticker/panels/cms/010_events/` | `cms_events_module` | `Spaceticker\Panels\Root\Cms\Events\Panel` |

## Warum kein PSR-4

PSR-4 verlangt exakten Pfad-Match. Panel-Ordner tragen Sortier-Prefixe (`040_backup`) die im Namespace nicht vorkommen. `PanelTree::folderToName()` strippt `^\d+_` beim Aufbau des Namespace-Segments. Der `PanelAutoloader` bildet diese Lücke mit einem Ordner-Walk.

## Implementierte Komponenten

### `PanelRootFolder` (erweitert)

`src/Panels/PanelRootFolder.php` — neues Feld `namespaceRoot`:

```php
readonly class PanelRootFolder {
    public function __construct(
        public \fsDir $dir,
        public string $namespaceRoot = '',  // z.B. 'Systopic\System\Panels' oder 'Spaceticker\Panels'
    ) {}
}
```

### `PanelAutoloader`

`src/Panels/Autoload/PanelAutoloader.php` — registriert sich via `spl_autoload_register`.

**Auflösungsalgorithmus:**
1. Prüfe ob FQCN mit `<namespaceRoot>\Root\` beginnt
2. Extrahiere Pfad-Segmente (alles nach dem Infix, ohne `Panel` am Ende)
3. Walk durch `panels/`-Dir: für jedes Segment alle Unterordner globben, `folderToName()` strippen, case-insensitive matchen
4. Am Ende den gefundenen Ordner nach `panel.php` durchsuchen und einbinden

**Registrierung in `loader.php`:**
```php
\Systopic\System\Panels\Autoload\PanelAutoloader::registerRoots($panelTree->getRoots());
```
Muss nach `addRoot()`-Aufrufen stehen, aber vor `$panelTree->build()`.

### `PanelClassResolver` (erweitert)

`src/Panels/Builder/PanelClassResolver.php` — neuer erster Auflösungsschritt:

```php
public function resolve(
    string $name,
    ?PanelNode $parent,
    bool $hasOwnFile,
    ?PanelRootFolder $sourceRoot = null,  // NEU
): ResolvedClass
```

**Reihenfolge:**
1. **Neu (bevorzugt):** FQCN aus `sourceRoot->namespaceRoot + \Root\ + Pfad + \Panel` bilden, `class_exists($fqcn, true)` → Autoloader greift
2. **Fallback:** bisherige Underscore-Kandidaten (`app_cms_backup_create_module`, ...)
3. **Fallback:** Bequests / `PanelNode`

Beide Schemata koexistieren — Legacy-`module.php` läuft unverändert weiter.

### `loader.php` — Modul-Registrierung

```php
// PROJECT_NAMESPACE: in index.php des Projekts definieren, z.B.:
// define('PROJECT_NAMESPACE', 'Spaceticker');
$projectPanelNs = defined('PROJECT_NAMESPACE') ? PROJECT_NAMESPACE . '\Panels' : '';

$panelTree->addRoot(new PanelRootFolder($sysPanels,     'Systopic\System\Panels'));
$panelTree->addRoot(new PanelRootFolder($projectPanels, $projectPanelNs));

PanelAutoloader::registerRoots($panelTree->getRoots());
```

## Panel migrieren (Schritt-für-Schritt)

### Pro Ordner

1. **Neue Datei `panel.php` anlegen** (neben bestehender `module.php`):

```php
<?php
declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Backup;

class Panel extends \cms_module          // Schritt 1: Legacy-Basis
// oder: class Panel extends \Systopic\System\Panels\Root\Cms\Panel  // Schritt 2: nach CMS-Migration
{
    // Inhalt aus module.php übernehmen
    public bool $bequests = true;
    // ...
}
```

2. **Externe Referenzen prüfen:**
```bash
rg "backup_module|backup_create_module" src/ panels/ -t php
```
Treffer → FQCN oder `class_alias` ergänzen.

3. **`module.php` behalten** bis alle Referenzen migriert sind, dann löschen.

### Vererbung über Modul-Grenzen

Projekt-Panel das System-Panel erweitert:

```php
namespace Spaceticker\Panels\Root\Cms\Contacts;

use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;

class Panel extends CmsPanel { ... }
```

### Projekt-Namespace aktivieren

In `spaceticker/public/index.php` nach `sys::init()`:

```php
define('PROJECT_NAMESPACE', 'Spaceticker');
```

## Bereits migrierte Panels (System)

- `panels/cms/040_backup/` → `Systopic\System\Panels\Root\Cms\Backup\Panel`
- `panels/cms/040_backup/create/` → `Systopic\System\Panels\Root\Cms\Backup\Create\Panel`
- `panels/cms/040_backup/restore/` → `Systopic\System\Panels\Root\Cms\Backup\Restore\Panel`

## Offene Folgeschritte

### Inkrementell (Ordner für Ordner)
- Restliche 31 System-Panels (`pages`, `settings`, `users`, etc.) migrieren
- Basisklasse `cms_module` nach `Systopic\System\Panels\Root\Cms\Panel` überführen
- Projekt-Panels (spaceticker: 19 `module.php`) nach `Spaceticker\Panels\Root\...` migrieren

### Langfristig
- `moduleNode`/`cms_module` Hooks (`onBuild`, `updateView`, `state`, ...) in `PanelNode` integrieren → `lib/class/module` auflösen
- Underscore-Fallback im `PanelClassResolver` entfernen, wenn alle Panels migriert sind
- `PROJECT_NAMESPACE` als Pflichtkonstante behandeln (kein leerer String mehr)
