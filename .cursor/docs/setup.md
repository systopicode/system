# Neues Projekt aufsetzen — Scaffold und Einrichtungs-Seite

Stand 2026-10-22. Plan und Begründungen:
`packages/tools/.cursor/plans/projekt-setup.plan.md`.

## Ablauf

1. **composer.json** mit den Paketen — im Projektmanager: composer-Spalte →
   `+` → Checkboxen. Die Datei bekommt den `scripts`-Eintrag:

   ```json
   "scripts": {
   	"post-install-cmd": "@php vendor/systopic/system/bin/scaffold.php",
   	"post-update-cmd": "@php vendor/systopic/system/bin/scaffold.php"
   }
   ```

   Von Hand: genau diesen Eintrag ergänzen. Ohne ihn schreibt composer
   nichts ins Projekt (die Matrix sagt im Tooltip „ohne scaffold-script").
2. **`composer update`** (Zelle → update, oder Shell). Das Script legt an,
   was zum Aufrufen fehlt, und gibt — solange `config/` keine Instanz hat —
   einen Token aus.
3. **`<projekt>/public/` im Browser** → Einrichtungs-Seite, drei Schritte:
   Projekt/Instanz → Datenbank → erstes Konto. Danach steht das Projekt:
   system zeigt „Hello World - System Root Panel", cms
   `404 - this site has no pages yet` mit Link nach `cms/pages`.

## Scaffold — `bin/scaffold.php`

Läuft im Projektordner (composer setzt das cwd). Jedes installierte Paket
kann in seiner composer.json einen Scaffold anmelden:

```json
"extra": { "systopic-scaffold": { "dir": "scaffold/", "owned": ["public/index.php", "public/asset.php", "public/.htaccess", ".htaccess"] } }
```

- **owned**: gehört dem Paket, wird neu geschrieben, wenn es abweicht. Ein
  Block `# >>> project` … `# <<< project` in der Projektkopie bleibt
  erhalten (in `public/.htaccess` vorgesehen). Eine handgeschriebene Datei
  (ohne `bin/scaffold.php` im Text) wird beim ersten Mal nach
  `var/scaffold/<pfad>.<zeit>` gesichert — `var/` synct nicht.
- **alles andere**: nur anlegen, wenn es fehlt (`public/robots.txt`).
- system zuerst, dann die übrigen Pakete in Install-Reihenfolge.
- Bricht nie den composer-Lauf ab: Fehler werden gemeldet, Exit 0.
- Ohne Instanz in `config/`: `var/setup.token` + Ausgabe
  `…?setup=<token>`.

## Front-Controller — `index.php` des Pakets

`<projekt>/public/index.php` ist ein Dreizeiler (owned) und lädt
`vendor/systopic/system/index.php`. Dort: Autoload, CLI-Harness
(`AgentCli`), **ini-Defaults vor der Config** (`upload_max_filesize`,
`memory_limit` … — `"ini"` in `config.json`/Instanz überschreibt sie jetzt),
`Config::boot()`, Einrichtungs-Seite falls nötig, `chdir` ins Projekt
(absolut, nicht mehr `../`), `sys::init`, `loader.php`. Bestehende Projekte
behalten ihre eigene `index.php`, bis ihre composer.json den
`scripts`-Eintrag bekommt und `composer update` läuft.

## Einrichtungs-Seite — `src/Setup/Setup.php`

Wann: `Config::boot()` wirft `Config\NotConfigured` (kein config.json,
keine Instanz, keine passende, lokaler Request auf LIVE-Default), die
Instanz hat keine `db`, oder `var/setup.account` liegt da.

Wer: ein lokaler Request (Host = IP/`localhost`/`*.local`/`*.test` **und**
private Client-IP) oder `?setup=<token>` aus `var/setup.token`. Sonst 403.
Auf der CLI: Meldung auf stderr, Exit 1.

| Schritt | fragt | schreibt |
|---|---|---|
| Projekt | Name (Ordnername), Namespace (`smp.de` → `Smp`), Stage (lokal → DEV, `staging.*`, `rc.*`, sonst LIVE), Maschine (Nachbarprojekt mit Regel auf dieselbe Server-IP → Projektmanager-Config → `gethostname()` → `thismachine`) | `config/config.json` (falls fehlt: `PROJECT_NAMESPACE`, `DEFAULT_TABLE_PREFIX`), `config/<STAGE>[.<maschine>].config.json` mit einer Regel aus diesem Request (`serverIp` oder `serverName` + `path`); DEV mit `debug`. LIVE: `"default": true`, aus einem lokalen Request abgelehnt. Gibt es die Datei schon, wird die Regel angehängt. |
| Datenbank | host, user, pass, name — leerer Name listet die Datenbanken, ein fehlender kann angelegt werden | `db` in die Instanz-Datei (gesynct) oder `config.local.json` (nie gesynct) |
| Konto | Name, Passwort ×2 — oder „skip" (Backup im Recovery-Modus einspielen) | alle Tabellen (verlustfreie Hälfte von `Db\Schema\Audit`), Zeile `user_id 1` (die systemeigene, die der Login nie findet), dann `UserOperator::firstSuperuser()` |

Der Konto-Schritt kommt nur, wenn die gewählte Datenbank noch kein Konto hat.
Danach werden Token und `var/setup.account` gelöscht — bei „skip" nur
`var/setup.account`: der Token bleibt für den Recovery-User und führt weiter
nach `cms/?setup=<token>`; das fertige Restore verbraucht ihn
(`Setup::spendToken()`).

## Recovery-Modus (seit 2026-10-22)

Der Recovery-User (`Auth\Session`, `isRecovery()`) ist nur, wer auch das
Setup darf: `Setup::trusts()` — lokal oder mit Token, einmal erkannt in der
Session gemerkt (`systopic_recovery`). Alle anderen sind anonym.

| Fall | Erkennung | vertrauenswürdig | sonst |
|---|---|---|---|
| kein Konto | keine `usm_users` **oder** keine Zeile mit `user_id > 1` (eine Abfrage mit Provisioner legt die Tabelle leer an — daher nach Zeilen gefragt) | Rolle `restore`: Backup-Panel, einspielen | anonym → Login |
| Datenbank nicht erreichbar | `Connection::open()` wirft (loader.php) | Rolle `recovery`: Backup-Panel, „Create Database" | **503** |
| App ohne Datenbank (`DB_NAME` leer) | — | Recovery-User wie bisher | Recovery-User wie bisher |

Die Aktionen des Backup-Panels prüfen selbst (`actionGranted`): `remove`,
`clearTempdirs` nur `admin`, `createDatabase` nur Rolle `recovery`. Backup-
Namen (`?name=`) sind Dateinamen in `var/backups/`, nie Pfade
(`Backup\Panel::backupName()`).

Session und Device-Cookie gelten pro Projekt, sobald es nicht im Host-Root
liegt (`http::projectCookieKey()`: Session-Name `SID<hash>` mit Pfad
`http::$root`, Cookie `…sys_device_<hash>`). Vorher teilten sich alle
Projekte auf `192.168.22.50` eine Session: User *N* aus Projekt A war in B
User *N*, und `UserLookup::byId()` legte in einem frischen Projekt die
`usm_users` leer an — der Recovery-Modus war damit weg. Im Host-Root (LIVE)
ändert sich nichts.

## Was ein frisches Projekt sonst noch brauchte

- **Ohne `debug.php`**: `sys::init` lädt `lib/debug.absent.php` — `p()`,
  `d()`, `t()`, `scripttime()`, `logroute()`, `DEBUG()` als stille
  Platzhalter. Vorher brach jeder Request ohne debug.php mit „undefined
  function scripttime()" ab.
- **Ohne `panels/` im Projekt**: `panels/head.tpl.php` des system-Pakets
  (Core-JS + Client-Daten) zur vorhandenen `body.tpl.php` („Hello World").
- **Ohne `settings.php`**: `loader.php` setzt nach settings.php fehlende
  Konstanten (`PUBLIC_APP_NAME`, `PAGE_TITLE_SEPERATOR`, `META_*`,
  `DEFAULT_LANG`).
- **cms ohne Seiten**: `Sys\Frontend::notFound()` (neu im Interface) —
  antwortet keiner, darf ein Frontend sagen warum. cms: keine Seite in der
  Datenbank → `nopages.php` (404). Jede andere unbekannte URL bleibt wie
  bisher.

## Fallen

| Symptom | Ursache | Lösung |
|---|---|---|
| Login „We don't know this combination" mit dem ersten Konto | `UserLookup.sql` überspringt `user_id 1`; in einer leeren Tabelle bekommt das erste Konto die 1 | Setup legt vorher die Zeile 1 an. `cms/auth/root` (Link im Login auskommentiert) hat dieselbe Lücke |
| `cms/pages` nach dem ersten Login: `Table 'usm_roles' doesn't exist` | der Provisioner legt nur an, was eine Abfrage mit Provisioner berührt | Setup legt alle Tabellen über `Audit` an |
| frisches Projekt zeigt im cms nur „BACKUP" | ohne Konto läuft ein lokaler Request (oder einer mit Token) als Recovery-User (Rolle `restore`) | Konto-Schritt der Einrichtung, oder Backup einspielen |
| frisches Projekt: Login statt Recovery | Zugriff weder lokal noch mit Token — oder (vor 2026-10-22) geteilte Session, `usm_users` leer angelegt | `cms/?setup=<token>` aus `var/setup.token` |
| leere Seite statt Hello World | keine `head.tpl.php` → keine Skripte für die Slices | `panels/head.tpl.php` im system-Paket |
