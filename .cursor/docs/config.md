# `config/` — Projekt, Instanzen, Sites

Ersetzt den `switch ($_SERVER['SERVER_NAME'])` in `public/index.php`.
Klassen: `src/Config/` (`Config`, `Instance`, `Site`, `Sites`, `SiteHit`,
`SiteLinks`, `InstanceFiles`).

## Dateien

| Datei | Inhalt | Sync |
|---|---|---|
| `config.json` | **nichts Installationsspezifisches**: `constants` (DEFAULT_TABLE_PREFIX, ACCOUNT_WHITELIST, PROJECT_NAMESPACE …), `ini`; `sites` nur als Fallback ohne Datenbank | ja |
| `<stage>[.<machine>].config.json` | eine Installation. **Dateiname = Instanzname**, der Teil vor dem ersten Punkt ist die Stage (`DEV.ojoffice` → DEV, `RC` → RC). Inhalt: `matches` (Maschine und Site), `default`, `linkSites`, `db`, `debug`, `versionAppendix`/`versionDate`, eigene `constants`/`ini` | ja |
| `config.local.json` | nur diese Maschine, v. a. LIVE-Zugangsdaten. **Flach**, gilt für die erkannte Instanz: `db`, `debug`, `versionAppendix`, `versionDate`, `constants`, `ini`; `instance` pinnt optional | nein (`*.local.json` in `.sync` ignore) |

Alle Dateien sind reines JSON; ein Kommentar ist ein `"//"`-Schlüssel.
Instanznamen = `stage`-Feld in `.sync`.

## Ablauf

1. `index.php`: `Config::boot(<project>/config, $_SERVER, <project>, CONFIG_INSTANCE?)`
   liest, wählt Instanz **und Regel** (`matches`), legt `config.local.json`
   darüber; `defineConstants()` definiert `STAGE`, `INSTANCE`, `SYS_PATH`
   (Default `vendor/systopic/system/`), `DB_*`, `DB_BIND_ONLY_USED_PARAMS`,
   `DEBUG`, `DEBUGRENDER`, `DEBUGTIME`, `PUBLIC_VERSIONDATE`,
   `PUBLIC_VERSIONAPPENDIX` und alle `constants`; setzt `error_reporting`,
   `display_errors` und `ini`. Jedes `define` ist mit `defined()` geschützt.
2. `loader.php` nach dem DB-Connect: `SiteRegistry::boot()` lädt die Sites aus
   `cms_sites`, `resolveSite()` nimmt die Site der getroffenen Regel (ohne
   Regel: Name/Alias des Hosts), danach ggf. 301 von einer Alias-Domain.
3. Überall: `Config::current()?->site()`, `->instance`, `->baseUrlFor($site)`.

## Instanz und Site: `matches`

Eine Instanz-Datei hat eine Liste von **Regeln**. Eine Regel erkennt die
Maschine und — optional — die Site des Requests. In einer Regel müssen alle
Kriterien treffen (UND), ein Kriterium darf mehrere Werte haben (ODER);
zwischen den Regeln reicht eine:

```json
"matches": [
	{ "serverIp": "192.168.22.50", "path": "/projects/systopic.net/",            "site": "systopic.net" },
	{ "serverIp": "192.168.22.50", "path": "/projects/beautiful-technology.de/", "site": "beautiful-technology.de" }
]
```

| Kriterium | gegen |
|---|---|
| `serverName` | SERVER_NAME und HTTP_HOST ohne Port, `*` als Wildcard |
| `serverIp` | SERVER_ADDR, LOCAL_ADDR und der Host, wenn er eine IP ist (CLI: Socket-IP) |
| `rootDir` | Projektordner im Dateisystem (per `realpath`, eine Junction trifft) |
| `path` | Präfix des URL-Roots (`dirname(SCRIPT_NAME)`): `/projects/x/` trifft `/projects/x/public/` |
| `site` | keine Bedingung: die Site (`cms_sites.site_name`), für die die Regel steht; fehlt = Default-Site |

- **Die genaueste Regel über alle Instanzen gewinnt:** `serverName` exakt
  1000, Wildcard 500, `serverIp`/`rootDir` je 100, `path` 10 + Länge; bei
  Gleichstand die Dateireihenfolge. `*.systopic.net` fängt damit nie
  `staging.systopic.net` ab.
- Ohne URL-Root (ein CLI-Werkzeug ohne `SCRIPT_NAME`) wird `path`
  übersprungen: die Maschine wird über die übrigen Kriterien erkannt.
- Keine Regel trifft: die Instanz mit `"default": true` (LIVE, ohne
  `matches`); sonst `NotConfigured` → Einrichtungs-Seite (siehe
  [setup.md](setup.md)). `"instance": "LIVE"` im CLI-JSON pinnt.
- **Seit 2026-10-22:** ein Web-Request auf einen lokalen Host (IP,
  `localhost`, `*.local`, `*.test`, `*.localhost`) bekommt den Default
  **nicht** — eine Maschine ohne eigene Datei lief sonst still auf LIVE mit
  der Live-Datenbank. Er bekommt die Einrichtungs-Seite. Die CLI ist
  ausgenommen (ein Cron auf einem LIVE-Server kann eine private Socket-IP
  sehen). `Instance::isLocalHost()`.
- Das alte `match`-Objekt bricht mit einer Meldung ab.

Die bisherigen Instanzen:

| Datei | Regeln |
|---|---|
| `LIVE.config.json` | keine, `default` — jede Site antwortet auf ihren Namen (und ihre Aliase) |
| `DEV.*` | je Site `serverIp`/`serverName` der Maschine + `path: /projects/<site>/`, dazu `linkSites` |
| `STAGING.config.json` | je Site `serverName: staging.systopic.net` + `path` |
| `RC.config.json` | je Site eine Test-Subdomain: `rc.systopic.net`, `beautiful-technology.systopic.net` … |

## Sites

Was eine Site **ist**, steht in der Datenbank, nicht in der Config: Tabelle
`cms_sites` (Name = Domain mit TLD, Meta als JSON, Alias-Domains) und ein Node
vom Typ `site` im Seitenbaum. Gepflegt im CMS im Seiten-Panel (Site-Knoten im
Baum, Globus für die Default-Site, +-Menü zum Anlegen), siehe
`multi-site.md`. Geladen in `loader.php` nach dem DB-Connect
(`Pages\Sites\SiteRegistry::boot()` → `Config::useSites()` → `resolveSite()`).
Solange die Tabelle leer ist, gilt ein `sites`-Block in config.json als Fallback.

**Wo** eine Site antwortet, sagen die Regeln ihrer Instanz: jede Regel der
Site (ohne `site`: der Default-Site) mit Host und/oder Pfad ist eine Adresse
von ihr — `rc.systopic.net`, `/projects/x/`, `staging.systopic.net/projects/x/`.
Die **erste** ist kanonisch: `baseUrl()` baut daraus die absolute Wurzel der
Site für Links von anderen Sites (Pfad-Adressen auf dem aktuellen Host), plus
dem Rest des aktuellen Roots, den die eigene Adresse nicht abdeckt (`public/`
auf DEV). Eine Instanz ohne Regeln (LIVE) adressiert jede Site über ihren Namen.

**Site anlegen, umbenennen, löschen** schreibt die Regeln **aller**
Instanz-Dateien nach (`InstanceFiles`, gemeldet im Panel als `config/…`):

- umbenennen: `site` und das Pfad-Segment (`/projects/<alt>/` → `/projects/<neu>/`);
  Hosts bleiben, ein Host mit dem alten Namen wird gemeldet
- löschen: alle Regeln der Site fallen weg (Ordner und Links bleiben)
- anlegen: Dateien mit Pfad-Regeln bekommen eine Kopie mit dem neuen Pfad;
  Dateien nur mit Host-Regeln (RC) werden gemeldet — Host von Hand eintragen
- Dateien ohne Regeln (LIVE) brauchen nichts; Dateien mit echten Kommentaren
  werden nicht angefasst, sondern gemeldet. Zurückgeschrieben mit Tabs.

**Alias-Domains** (`cms_sites.site_aliases`, im Site-Editor gepflegt): weitere
Domains einer Site, z. B. `beautiful-technology.com`. Sie hängen auf jeder
Instanz als Adressen **hinter** den eigenen (also nie kanonisch), und ein
Request darauf geht mit 301 auf dieselbe Adresse unter der kanonischen
(`SiteRegistry::redirectAlias()`, direkt nach `resolveSite()`). Hosts einer
Stage gehören nicht hierher, sondern in die Regeln: die Alias-Liste reist mit
jeder Kopie der Datenbank. Geprüft beim Speichern: Domain mit TLD, nicht der
eigene Name, nicht Name oder Alias einer anderen Site; `www.` wird entfernt.

- `www.` wird nirgends eingetragen: `.htaccess` leitet auf die Domain ohne www um.
- Body-Klasse: `site-<slug>` (`site-beautiful-technology-de`).
- Site-eigene Templates: `sites/<domain>/templates/{document,components,pages}/`
  (z. B. `sites/beautiful-technology.de/templates/document/body.tpl.php`), siehe `multi-site.md`.

### DEV: zweite Site per Pfad

Mit `"linkSites": true` in der Instanz-Datei legt `SiteRegistry::boot()`
(`$config->linkSites($_SERVER['DOCUMENT_ROOT'], <project>)`, Klasse
`SiteLinks`) fehlende Links selbst an: pro Pfad-Adresse
`<DOCUMENT_ROOT><pfad>` → Projektordner. Nur wenn eine der Adressen schon
das Projekt selbst ist (Schema stimmt auf dieser Maschine), nur Pfad-Adressen,
Vorhandenes bleibt unangetastet. Bisher nur Windows (Junction, braucht kein
Admin); macOS/Linux geben `skipped` zurück. Ergebnis in `debugInfo()['siteLinks']`.
Umbenennen verschiebt den Link mit. Von Hand entspricht das:

```
mklink /J C:\laragon\www\projects\beautiful-technology.de C:\laragon\www\projects\systopic.net
```

Dann liefert `https://192.168.22.50/projects/beautiful-technology.de/public/`
dieselben Dateien mit `http::$root = /projects/beautiful-technology.de/public/`,
und die Regel mit `path: /projects/beautiful-technology.de/` wählt die Site.
Auf STAGING braucht es denselben Link (`ln -s systopic.net beautiful-technology.de`).

## Debug

`Config::current()->debugInfo()`: gelesene Dateien, Instanz, Kriterium
(`matchedBy`: die Kriterien der getroffenen Regel), Site, getroffene Adresse, Rest. In den Core-JS-Settings stehen
`instance` und `site`.
