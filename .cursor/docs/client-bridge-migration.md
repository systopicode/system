# Client Bridge – Migration Guide

Dieses Dokument beschreibt, wie Legacy-Klassen, die noch die alten PHP-Client-Interfaces implementieren, auf das neue typisierte System migriert werden.

## Hintergrund

Das alte System (in `lib/interfaces/client.php`) nutzte informelle PHP-Interfaces ohne Return-Types und eine statische Registry (`clientInterfaces`). Das neue System ab PSR-4-Klassen in `src/` verwendet typisierte DTOs und einen injizierbaren Service.

| Legacy | Neu (src/Client/) | Datei |
|---|---|---|
| `interface clientInstance` | `ClientExportable` | `src/Client/Contracts/ClientExportable.php` |
| `interface clientData` | `StaticClientExportable` | `src/Client/Contracts/StaticClientExportable.php` |
| `interface clientPreload` | `ProvidesClientScript` | `src/Client/Contracts/ProvidesClientScript.php` |
| `class clientInterfaces` (static) | `ClientRegistry` (injizierbar) | `src/Client/ClientRegistry.php` |
| untypisiertes `(object)[...]` | `ClientExport` (readonly DTO) | `src/Client/ClientExport.php` |

---

## Migration: `clientInstance` → `ClientExportable`

Betrifft Klassen, die pro PHP-Instanz ein JS-Objekt im `sys.*`-Namespace erzeugen.

**Legacy:**
```php
class myPanel extends moduleNode implements clientInstance, clientPreload
{
    function clientInstance_getData(): object
    {
        return (object) ['name' => $this->name, 'foo' => $this->foo];
    }

    static function clientInstance_getInstances() {}
}
```

**Neu:**  
Für `PanelNode`-Subklassen ist keine Änderung nötig – `PanelNode` implementiert `ClientExportable` bereits. Eigene Daten per Override:

```php
class myPanel extends \Panels\PanelNode
{
    public function exportClientData(): \Client\ClientExport
    {
        $base = parent::exportClientData();
        // Eigene Felder zum bestehenden data-Objekt hinzufügen:
        $base->data->foo = $this->foo;
        return $base;
    }
}
```

---

## Migration: `clientData` → `StaticClientExportable`

Betrifft Klassen, die statische Konfigurationsdaten exportieren (Settings, Strings, etc.).

**Legacy:**
```php
class http implements clientPreload
{
    static function clientData_export(): object
    {
        return (object) [
            'route' => 'singletons.http',
            'data'  => (object) ['root' => self::$root],
        ];
    }
}
```

**Neu:**
```php
use Systopic\System\Client\ClientExport;
use Systopic\System\Client\Contracts\StaticClientExportable;
use Systopic\System\Client\Contracts\ProvidesClientScript;

class http implements StaticClientExportable, ProvidesClientScript
{
    public static function exportStaticClientData(): ClientExport
    {
        return new ClientExport(
            route: 'singletons.http',
            data: (object) ['root' => self::$root, 'sysRoot' => self::$sysRoot],
        );
    }
}
```

Für Klassen, die **mehrere Routes** exportieren:
```php
public static function exportStaticClientData(): array
{
    return [
        new ClientExport(route: 'singletons.foo', data: (object) [...]),
        new ClientExport(route: 'singletons.foo.sub', data: (object) [...]),
    ];
}
```

---

## Migration: `clientPreload` → `ProvidesClientScript` ✓ ABGESCHLOSSEN

`clientPreload` wurde vollständig entfernt. Alle 12 Klassen implementieren nun `ProvidesClientScript`.

`clientInterfaces::register()` prüft nur noch auf `ProvidesClientScript::class` — die Bridge ist aktiv.

**Migriert (Jun 2026):** `moduleNode`, `http`, `fsUploader`, `menu`, `routeLayer`, `routeRenderer`, `domRenderer`, `domEditable`, `module`, `module.old`, `users`, `groups`

---

## Migration: `clientInterfaces` → `ClientRegistry`

An den drei Aufruf-Stellen (`head.tpl.php`, `site/html.tpl.php`, `app.php`):

**Legacy:**
```php
$data = clientInterfaces::clientData_export();
echo "<script>sys.lib.updateClientData(" . json_encode($data) . ");</script>";
```

**Neu:**
```php
/** @var \Systopic\System\Systopic\System\Client\ClientRegistry $clientRegistry – aus Bootstrap injiziert */
$data = $clientRegistry->collectAll();
echo "<script>sys.lib.updateClientData(" . json_encode($data) . ");</script>";
```

Die `ClientRegistry`-Instanz wird in `bootstrap.php` / `loader.php` erzeugt und über den IoC-Container oder direkte Übergabe an Templates weitergeleitet.

---

## Klassen mit noch ausstehendem Migrationsbedarf

| Klasse | Datei | Interface(s) |
|---|---|---|
| `mediaInfo` | `lib/class/media/info/mediaInfo.php` | `clientData` |
| `tag` | `lib/class/tag/tag.php` | `clientData` |
| `tagGroup` | `lib/class/tag/group/tagGroup.php` | `clientData` |
| `str` | `lib/class/str/str.php` | `clientData` |
| `users` | `lib/class/user/users.php` | `clientData` |
| `groups` | `lib/class/group/groups.php` | `clientData` |
| `routeRenderer` | `lib/class/route/renderer/routeRenderer.php` | `clientSingleton`, `clientData` |
| `domRenderer` | `lib/class/dom/renderer/domRenderer.php` | `clientSingleton`, `clientData` |
| `module` | `lib/class/module/module.php` | `clientSingleton`, `clientData` |

---

## Was nicht migriert werden muss

- **`clientSingleton`**: Reiner Marker, den `client::flushScripts()` verwendet um den Script-Typ zu bestimmen. Kann vorerst bleiben – hat keinen Einfluss auf den Datenexport.
- **Templates** (`head.tpl.php`, `html.tpl.php`): Können weiter `clientInterfaces::clientData_export()` aufrufen, solange Legacy-Klassen noch aktiv sind.
- **`lib/interfaces/client.php`**: Datei bleibt bestehen für `clientData`, `clientSingleton`, `clientClass`, `actions`.
