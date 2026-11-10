# `config/` — Projekt, Installation, Sites

Ersetzt den `switch ($_SERVER['SERVER_NAME'])` in `public/index.php`.
Klassen: `src/Config/` (`Config`, `Instance`, `Schema`, `Explain`, `Site`, `Sites`,
`SiteHit`, `SiteLinks`, `MachineRules`).

Definitionen je Paket, Abgleich, Formular: [config-struktur.md](config-struktur.md).

## Dateien

| Datei | Inhalt | Sync |
|---|---|---|
| `config.json` | das Projekt, **nichts Installationsspezifisches**: unter `packages.<paket>` die Werte mit `x-project` (`system.constants`, `system.ini`, `system.auth` ohne Secret), freie `constants` des Projekts, `sites` nur als Fallback ohne Datenbank | ja, und Git |
| `config.local.json` | **diese Installation**: ihr Name `packages.system.instance` (`DEV.ojhome`, `STAGING`, `LIVE`; der Teil vor dem ersten Punkt ist die Stage), `packages.cms.siteAddress`, `db.connections.<name>`, `system` (`debug`, `mail`, `versionAppendix` …), `relay`, **alle Secrets**, und Werte des Projekts mit `x-overridable` | **nein** (`*.local.json`) |

Alle Dateien sind reines JSON; ein Kommentar ist ein `"//"`-Schlüssel.

Was in welche Datei gehört, sagt die Definition des Pakets (`<paket>/config/config.schema.json`).
Gelesen wird über `Config::package('db')` und `Config::connection('main')`. Dateien in der
alten Form (`db`, `debug` … oben) gehen weiter, `Config\Layout` hebt sie beim Boot.
Installationsnamen = `stage`-Feld in `.sync`.

Die früheren Instanz-Dateien (`<stage>[.<machine>].config.json` mit `matches`) liest der
Code nicht mehr. `bin/config.php --adopt[=<instanz>]` übernimmt die der eigenen Maschine
in `config.local.json`.

## Ablauf

1. `index.php`: `Config::boot(<project>/config, $_SERVER, <project>, CONFIG_INSTANCE?)`
   liest beide Dateien; ohne `config.json` oder ohne Namen in `config.local.json` kommt
   `NotConfigured` → Einrichtungs-Seite ([setup.md](setup.md)). `defineConstants()`
   definiert `STAGE`, `INSTANCE`, `SYS_PATH` (Default `vendor/systopic/system/`), `DB_*`,
   `DB_BIND_ONLY_USED_PARAMS`, `DEBUG`, `DEBUGRENDER`, `DEBUGTIME`, `PUBLIC_VERSIONDATE`,
   `PUBLIC_VERSIONAPPENDIX` und alle `constants`; setzt `error_reporting`, `display_errors`
   und `ini`. Jedes `define` ist mit `defined()` geschützt. Bei `constants` und `ini`
   gewinnt `config.local.json` über `config.json`. `error_log`, `display_errors` und
   `error_reporting` sind in `ini` reserviert. Die ini-Defaults setzt
   `Config::applyIniDefaults()` vor dem Boot. `"instance": "STAGING"` im CLI-JSON läuft
   unter diesem Namen, also mit dessen Stage.
2. `loader.php` nach dem DB-Connect: `SiteRegistry::boot()` lädt die Sites aus
   `cms_sites`, `resolveSite()` macht aus `siteAddress` die Adressen aller Sites für
   diese Anfrage und nimmt die längste passende, danach ggf. 301 von einer Alias-Domain.
3. Überall: `Config::current()?->site()`, `->instance`, `->baseUrlFor($site)`.

## Sites

Was eine Site **ist**, steht in der Datenbank, nicht in der Config: Tabelle
`cms_sites` (Name = Domain mit TLD, Meta als JSON, Alias-Domains) und ein Node
vom Typ `site` im Seitenbaum. Gepflegt im CMS im Seiten-Panel (Site-Knoten im
Baum, Globus für die Default-Site, +-Menü zum Anlegen), siehe
`multi-site.md`. Geladen in `loader.php` nach dem DB-Connect
(`Pages\Sites\SiteRegistry::boot()` → `Config::useSites()` → `resolveSite()`).
Solange die Tabelle leer ist, gilt ein `sites`-Block in config.json als Fallback.

**Wo** eine Site antwortet, sagt das Muster der Installation,
`packages.cms.siteAddress` (Settings › CMS › Configuration): reguläre Ausdrücke mit Platzhalter, mehrere als Liste.

| Stage | `siteAddress` | Adressen |
|---|---|---|
| DEV | `/projects(\.[a-z]+)?/{site}/` | `/projects/beautiful-technology.de/` auf dem Host der Anfrage; liegt das Projekt in `/projects.archive/`, dort |
| STAGING | `staging.systopic.net/projects/{site}/` | Host und Pfad |
| RC | `rc.systopic.net`, `{name}.systopic.net` | `{name}` ist der Name ohne TLD (Zertifikate); das Muster ohne Platzhalter gehört der Default-Site |
| LIVE | leer | jede Site unter ihrem Namen |

`Config\Sites` macht daraus je Anfrage die Adressen aller Sites: Passt ein Muster auf
die Anfrage, wird das, was der Platzhalter genommen hat, gegen die andere Site getauscht;
passt es nicht, wird es ausgeschrieben, soweit es reiner Text ist. Die **erste** Adresse
einer Site ist kanonisch: `baseUrl()` baut daraus die absolute Wurzel der Site für Links
von anderen Sites (Pfad-Adressen auf dem aktuellen Host), plus dem Rest des aktuellen
Roots, den die eigene Adresse nicht abdeckt (`public/` auf DEV). Ein Ordner, der keine
Site ist, landet auf der Default-Site.

**Site anlegen, umbenennen, löschen** schreibt keine Config: Das Muster nimmt jede
Site unter ihrem Namen. Umbenennen verschiebt den Ordner `sites/<name>/` und die Links
dieser Maschine.

**Alias-Domains** (`cms_sites.site_aliases`, im Site-Editor gepflegt): weitere
Domains einer Site, z. B. `beautiful-technology.com`. Sie hängen auf jeder
Installation als Adressen **hinter** den eigenen (also nie kanonisch), und ein
Request darauf geht mit 301 auf dieselbe Adresse unter der kanonischen
(`SiteRegistry::redirectAlias()`, direkt nach `resolveSite()`). Hosts einer
Stage gehören nicht hierher, sondern in `siteAddress`: die Alias-Liste reist mit
jeder Kopie der Datenbank. Geprüft beim Speichern: Domain mit TLD, nicht der
eigene Name, nicht Name oder Alias einer anderen Site; `www.` wird entfernt.

- `www.` wird nirgends eingetragen: `.htaccess` leitet auf die Domain ohne www um.
- Body-Klasse: `site-<slug>` (`site-beautiful-technology-de`).
- Site-eigene Templates: `sites/<domain>/templates/{document,components,pages}/`
  (z. B. `sites/beautiful-technology.de/templates/document/body.tpl.php`), siehe `multi-site.md`.

### DEV: zweite Site per Pfad

Fehlt auf dieser Maschine der Link einer Site, sagt es ihre Seite im CMS oben
(„Site link not created“) und bietet „Create site link“ an: Er legt den Link an und, falls
nötig, `sites/<name>/`. Windows bekommt eine Junction, alle anderen einen Symlink, relativ,
wenn er neben dem Projekt liegt (`SiteLinks`). Gesucht wird pro Pfad-Adresse
`<DOCUMENT_ROOT><pfad>` → Projektordner, nur wenn eine der Adressen schon das Projekt
selbst ist (das Muster stimmt auf dieser Maschine); Vorhandenes bleibt unangetastet.
Umbenennen verschiebt den Link mit. Beim Booten wird nichts angelegt (die Einstellung
`linkSites` ist 2026-11-09 entfallen). Von Hand entspricht das:

```
mklink /J C:\laragon\www\projects\beautiful-technology.de C:\laragon\www\projects\systopic.net
```

Dann liefert `https://192.168.22.50/projects/beautiful-technology.de/public/`
dieselben Dateien mit `http::$root = /projects/beautiful-technology.de/public/`,
und das Muster `/projects(\.[a-z]+)?/{site}/` wählt die Site.
Auf STAGING braucht es denselben Link (`ln -s systopic.net beautiful-technology.de`).

## Debug

`Config::current()->debugInfo()`: gelesene Dateien, Installation, Stage,
`siteAddress`, Site, getroffene Adresse, Rest. In den Core-JS-Settings stehen
`instance` und `site`.
