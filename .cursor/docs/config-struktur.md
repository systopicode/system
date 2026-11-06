# Config-Struktur: Definitionen, Dateien, Abgleich

Stand 2026-11-06. Konzept, in Teilen umgesetzt (siehe „Nächste Sitzung“). Baut auf [config.md](config.md) auf.
Backup: siehe [backup-config.md](backup-config.md).

## Nächste Sitzung

Stand 2026-11-06. **Konzept und beide offenen Themen sind entschieden.** Am Anfang
alle Repos pullen ([git.md](git.md)).

| Stand | |
|---|---|
| Definitionen | liegen unter `<paket>/config/`, `tests/config-schemas.php` prüft sie gegen alle Config-Dateien |
| Schritt 1 | fertig: `Config\Layout`, `Config\Schema`, `Config::package()`, `Config::connection()`. Alle Lesestellen laufen über den Baum, `tests/config-tree.php` vergleicht jede Instanz mit den alten Werten |
| Sync | Build mit `composer` und `openSetup`. `composer update` lief am 2026-11-06 auf RC |
| Setup über Sync | fertig bis auf das Projekt-Tool, `tests/setup-maintenance.php` spielt jeden Zustand durch |
| Scaffold | `systopic.net` mit seinen vier Sites (Junctions) umgestellt. `galerie-lattemann.de` und `pwk.systopic.net` nicht, sie haben eigene Logik in `index.php` und `.htaccess` |
| Schritt 3 | fertig: Formulare aus den Definitionen, alle Schreibwege schreiben die neue Form, Secrets nach local |
| Dateien | `systopic.net` und `spaceticker.app` umgestellt, siehe unten. `smp.de` folgt, wenn das läuft |

| Schritt 2 | fertig: Defaults aus den Definitionen (`Config::applyIniDefaults()`), reservierte Schlüssel, Reihenfolge der Konstanten |

Als Nächstes: Schritt 4, der Abgleich beim Speichern.

**Noch in alter Form, bis zum Tag:** `config.json` und `RC.config.json` von `systopic.net`. Beide
gehen nach RC, dort läuft `v1.2.0`, das nur die alte Form liest. Am Tag des Tags:
`bin/config.php --migrate=all`.

**Secrets je Maschine:** `bin/config.php --move-secrets=<instanz>` läuft auf jeder Maschine
selbst. Erledigt ist `DEV.ojhome`. In den Dateien der anderen stehen sie noch, unter
`packages.*`. `bin/config.php --check` zeigt, was fehlt.

**Entschieden:** `packages.<paket>` als Namensraum, die drei `.schema.json`-Dateien
je Paket, JSON Schema mit `x-`-Erweiterungen, Formulare aus den Definitionen, alle
Secrets immer in local, `x-overridable`, automatisches Löschen nur in der eigenen
Instanz-Datei. Das Environment ist aus dem Backup entfernt
([backup-config.md](backup-config.md)).

### Versionierung

Entschieden am 2026-11-06. Es gibt noch keine LIVE-Instanz und nur eine RC-Instanz.
Gearbeitet wird lokal und auf STAGING, das läuft über Git und Sync, Versionen spielen
dort keine Rolle.

| Frage | Entscheidung |
|---|---|
| Wann wird getaggt? | **Einmal am Ende,** wenn alles läuft. Danach wird die eine RC-Instanz aktualisiert |
| Major-Sprung? | Nein, alles bleibt `1.x`, auch das Entfernen der Altpfade in Schritt 8 |
| Gemeinsame Versionen? | Nein, jedes Paket zählt einzeln und nennt in `composer.json` seine Mindestversion |
| Schutz vor zu neuer Config? | Keiner im Code, die Reihenfolge unter „Umzug“ reicht |
| GUI-Version in `releases.php` | bleibt eine eigene Zählung |

Für den Tag am Ende:

- **Reihenfolge:** `db`, `system`, `cms`, `relay`. `system` wird `v1.3.0`, `cms` und
  `relay` verlangen genau das. Ein Tag von `cms` oder `relay` vor `system v1.3.0` ließe
  sich nur mit `1.x-dev` erfüllen.
- **Nummern:** Für `db`, `cms` und `relay` stehen sie noch nicht fest. Vor dem Tag die
  Änderungen je Paket zeigen und die `composer.json`-Mindestversionen prüfen.
- **Bis dahin nichts von `systopic.net` nach RC, was den Tag braucht.** RC läuft auf
  `system v1.2.0`, dort gibt es weder `bin/scaffold.php` noch `index.php`. Die
  `composer.json` mit dem Scaffold-Script ließe Composer dort scheitern, die neue
  `public/index.php` verlangte die Paket-`index.php` und nähme die Site vom Netz.

### Secrets

Entschieden am 2026-11-06:

| Frage | Entscheidung |
|---|---|
| Wie kommen Secrets auf einen Server? | Im Browser am Server eintippen, im Setup. Geöffnet wird es über Sync (siehe „Setup über Sync“). Die Werte laufen über keinen Arbeitsplatz |
| Wo liegt die Quelle der Werte? | Nur auf den Servern, keine zweite Ablage |
| Darf ein Arbeitsplatz LIVE-Secrets speichern? | Nein |

Warum das reicht:

- **Bestehende Server brauchen keinen Transport.** `--move-secrets` läuft dort selbst
  (Schritt 6). Der Weg gilt nur für neue Server und geänderte Passwörter.
- **`relay.secret` und `relay.apiKey` kommen vor Ort aus der Hub-Config.**
  `CentrifugoHub::relayBlock()` liest sie auf derselben Maschine, `connectRelay()`
  schreibt sie. Es ändert sich nur das Ziel: Sie gehören künftig nach local.
- **`auth.secret` entsteht auf dem Server selbst.**

Folgen:

- Ein anderer Arbeitsplatz kennt die Staging-Passwörter nicht mehr von selbst.
- Totalverlust eines Servers: `db.pass` und `mail.password` werden beim Hoster neu
  vergeben und am Server eingetragen. Bis dahin fallen Datenbank und Mail aus
  ([backup-config.md](backup-config.md), „Offen“).
- LIVE gibt es noch nicht. Es bekommt beim Einrichten gleich den Seed, die Secrets
  gehen von Anfang an nach local.

### Setup über Sync

Entschieden am 2026-11-06.

**Das Problem:** Das Setup läuft heute nur, wenn die Config fehlt oder `db.name` leer
ist. Nach Schritt 6 kommt ein gesyncter Server mit `db.name`, aber ohne Passwort, und
antwortet mit 503. Dasselbe gilt für ein geändertes Passwort. Der Recovery-Modus hat
kein Feld dafür, und einen Token gibt es dort nicht: Das Setup löscht ihn am Ende,
`scaffold.php` legt nur einen an, wenn die Config fehlt, und das Projekt-Tool gibt es
auf RC und LIVE nicht.

**Die Lösung:** `sync.php` läuft ohne Framework, Datenbank und Projekt-Config, und
seine Seite öffnet nur eine signierte Einmal-Challenge eines vertrauten Systems.
Wer sie öffnen darf, kann ohnehin schon Dateien schreiben, ein neues Recht entsteht
also nicht.

1. Die Sync-Seite des Servers wird wie bisher vom vertrauten System aus geöffnet.
2. Ein neuer Button „Setup öffnen“ schreibt `var/setup.token` und leitet auf
   `public/?setup=<token>` weiter.
3. `index.php` prüft den Token **vor** `Config::boot()`. Das Setup öffnet sich also
   auch bei kaputter Config, falschem Passwort oder fehlender Datenbank.
4. Das Setup zeigt die Werte aus `config.local.json` vorausgefüllt und schreibt nur
   diese Datei. Das Formular kommt aus den Definitionen. Passwörter werden nie
   angezeigt, ein leeres Feld behält den alten Wert. Die Datenbank wird vor dem
   Speichern getestet. Die Schritte „Projekt“ und „Konto“ bleiben der
   Erstinstallation vorbehalten.

Regeln:

- **Ein Token, ein Mechanismus.** Sync und `scaffold.php` schreiben dieselbe Datei,
  `Setup::trusts()` prüft sie. Der Recovery-Modus funktioniert damit weiter.
- **Der Token läuft ab,** nach einer Stunde, gemessen am Alter der Datei
  (`Setup::TOKEN_LIFETIME`). Das Formular trägt ihn als verstecktes Feld mit, der
  Recovery-Modus merkt ihn sich in der Session.
- **Nur die Adresse der Site öffnet das Setup.** `admin/?setup=` bleibt der
  Recovery-Modus. Ein lokaler Request genügt hier nicht, die Site soll die Site bleiben.
- **`scaffold.php` behält seinen Token,** eine Erstinstallation ohne Sync bleibt möglich.
- **Das Projekt-Tool verliert seinen Token:** `create_recovery()`, `recoveryUrl()` und
  die Links in `main.tpl.php`.
- **`driver`, `host`, `port`, `name`, `user` einer Verbindung haben `x-overridable`,**
  sonst wären sie nach einer Änderung im Setup im Abgleich „falsche Datei“.
- **`sync.php` kennt das Config-Format nicht.** Es öffnet nur das Setup. Sync wird je
  Instanz ausgerollt und liefe sonst den Definitionen hinterher.

Grenzen:

- Das Setup liegt in `vendor`. Fehlt es oder ist es kaputt, repariert das Composer
  über Sync, siehe unten.
- Jede Instanz braucht den neuen Sync-Build, bevor sie den Button hat.
- Sind `.htsync` oder alle vertrauten Instanzen weg, bleibt ein neuer Seed per Plesk
  oder SSH.

**Umgesetzt:** `action/openSetup.php` in Sync, `Setup::maintenance()` vor `Config::boot()`
in `index.php`, `Setup\Maintenance` für das Formular. Das Formular zeigt jedes Secret
und jeden Wert mit `x-overridable` aus den Definitionen, alles andere der Datei als
JSON-Block. Gespeichert wird in der neuen Form. Das erste Setup schreibt das Passwort
jetzt immer nach local.

Eine kaputte `config.local.json` lässt schon `Config::boot()` scheitern. Die Seite
meldet das und legt die Datei auf Knopfdruck nach `var/scaffold/`. Eine kaputte
Instanz-Datei kommt per Sync zurück.

**Das gilt nur für Projekte mit der Paket-`index.php`,** also mit Scaffold. Offen: Im
Projekt-Tool den Token entfernen, sobald der Button auf STAGING getestet ist.

### Composer über Sync

Umgesetzt am 2026-11-06, Beschreibung in
`tools/.cursor/docs/sync-build-und-vokabular.md`. Auf einem Server läuft der Weg über
dieselbe Seite: `install`, `update` und `update <paket>`, feste Befehle, kein freies
Eingabefeld. Er hängt nicht an `vendor`, repariert also auch ein kaputtes. Auf RC
holt `composer update` nur getaggte Stände, dafür braucht es v1.3.0.

### Mehrere Datenbanken

Entschieden am 2026-11-06. Anlass ist Octopus, das interne Verwaltungstool, noch
nicht migriert. Es greift auf die Postgres-Datenbank der Buchhaltung Revolver zu.
Seine zweite Datenbank ist ein älteres CMS mit Wissensdatenbank und Benutzerverwaltung,
das bald portiert wird. Der Entwurf dazu steht in
`.cursor/archive/orm-2026-09/orm-entwurf.md`, „Mehrere Verbindungen, mehrere Engines“.

Alle Verbindungen sind benannt, auch die des Projekts:

```json
"packages": { "db": { "connections": {
    "main":     { "host": "localhost", "name": "octopus", "user": "octopus" },
    "revolver": { "driver": "pgsql", "host": "db.example", "port": 5432, "name": "revolver", "user": "octopus" }
} } }
```

In `config.local.json` steht nur `connections.<name>.pass`, das Zusammenführen legt
beides übereinander. Die Form ist für alle Projekte gleich, der heutige `db`-Block
wird in Schritt 7 zu `connections.main`.

Regeln:

- **`main` ist Pflicht.** `Setup::hasDatabase()`, der Boot und die `DB_*`-Konstanten
  hängen nur an `main`.
- **Jede andere Verbindung öffnet sich bei der ersten Benutzung.** Fehlt sie oder
  schlägt sie fehl, gibt es weder Setup noch Recovery noch 503. Es scheitert nur die
  Funktion, die sie braucht. Sonst nähme ein Ausfall von Revolver das ganze Tool mit.
- **`driver`** nennt die Engine, Default `mysql`. `charset` gilt nur für `mysql`.
- **Keine Transaktion über zwei Verbindungen,** und der Migrator erzeugt über eine
  Verbindungsgrenze hinweg kein `FOREIGN KEY`.
- **Das Formular im Setup zeigt alle Verbindungen,** die Pflichtprüfung gilt nur `main`.

**Fremde Datenbanken** (entschieden am 2026-11-06, nur Konzept): Revolver gehört uns
nicht, Octopus liest dort und schreibt auch. Die Struktur bestimmt Revolver, unser
Modell folgt ihr, nicht umgekehrt:

- **Der `Provisioner` greift dort nie ein.** Sonst legt er bei einem Fehler fehlende
  Tabellen und Spalten an oder ändert Spalten. Die Verbindung läuft ohne
  `ProvisioningConnection`.
- **Bei einer Abweichung gibt es einen Fehler,** der Tabelle und Spalte nennt. Danach
  wird von Hand das Modell nachgezogen, nie die Datenbank.
- **`Audit` darf vergleichen,** `apply()` ist für so eine Verbindung gesperrt.
- **Schreibzugriff bleibt erlaubt,** es geht nur um die Struktur, nicht um die Daten.

Vorschlag für den Schlüssel: `"managed": false` an der Verbindung, Default `true`. Er
steht noch nicht in der Definition.

Offen, gebaut wird das mit Octopus, nicht vorher:

- `Connection::open()` ist fest auf `mysql:` verdrahtet und liest noch kein `driver`.
- Verbindungen nach Namen verwalten, `CONNECTION` an der Tabellenklasse.
- `pdo_pgsql` fehlt auf dem Arbeitsplatz. `Platform\Postgres` gibt es, sie ist aber
  gegen keine echte Datenbank gelaufen.
- Der `Provisioner` kennt nur MySQL-Fehlercodes. Für Postgres ist das bei einer
  fremden Datenbank kein Problem, für eine eigene schon.

## Ziel

- Was in welche Config-Datei gehört, ist vordefiniert, und zwar von den Paketen.
- Der Admin baut seine Formulare aus diesen Definitionen und zeigt auch, was in
  der Datei noch fehlt.
- Ein Wert, den es im Code nicht mehr gibt, verschwindet aus den Dateien.
- Secrets liegen nur noch in `config.local.json`.
- Die `ini_set`-Werte aus `index.php` wandern in die Config und bekommen Defaults.

## Stand heute

Die Struktur steht an vier Stellen, und keine ist vollständig:

| Stelle | Kennt |
|---|---|
| `EnvironmentFile::GROUPS`, `SCALARS`, `MATCH` | was der Admin zeigt; nicht `db.bindOnlyUsedParams`, `auth.secret`, `linkSites`, `assets`, `matches[].rootDir` |
| `Config::LOCAL_INSTANCE_KEYS` | was `config.local.json` überschreiben darf |
| `Setup.php:378` | was eine neue Instanz bekommt |
| `?? default` im Code | die eigentlichen Defaults (`Config.php`, `AuthConfig`, `Relay`, `loader.php`) |

Erhebung über alle 53 Config-Dateien in `projects/*/config`:

- `ini` steht in keiner Datei. Es gibt also nichts zu migrieren.
- `constants` steht nur in `config.json`: drei Systemkonstanten in allen 7 Projekten
  und `KIOSK_IDLE_SECONDS` bei spaceticker.
- `sysPath` und `assets` werden gelesen, stehen aber in keiner Datei.
- `defaultSignInMethod` ist überall `device_code`, `deviceDays` überall 180.
- Secrets liegen heute noch auf allen Stages auch in Dateien, die Git oder Sync
  bewegen. Die `config.local.json` der Server sind nicht geprüft.
- `matches`: `serverName` ist in 25 Regeln eine Liste, `serverIp` in einer.
  `EnvironmentFile::setMatch()` schreibt nur Strings. `rootDir` wird gelesen, aber nirgends benutzt.

Konstanten: Die Reihenfolge ist `config.json`, Instanz, `config.local.json`, die spätere
Datei gewinnt. Bis Schritt 2 gewann die erste, eine Instanz konnte nichts überschreiben.

## Kernidee

**Das Paket definiert, das Projekt speichert.** Die Definition steht in JSON
Schema, die Zieldatei ergibt sich aus dem Dateinamen, der Besitzer aus dem Pfad.

| Definition im Paket | Landet in | Unter | Gilt für |
|---|---|---|---|
| `<paket>/config/project.schema.json` | `config/config.json` | `packages.<paket>` | alle Instanzen |
| `<paket>/config/machine.schema.json` | `config/<stage>[.<machine>].config.json` | `packages.<paket>` | eine Installation |
| `<paket>/config/local.schema.json` | `config/config.local.json` | `packages.<paket>` | diese Maschine, weder Git noch Sync |
| `system/config/header.schema.json` | Instanz-Datei | oberste Ebene | `matches`, `default` |

Beispiele:

- `db/config/machine.schema.json`: `connections.<name>` mit `driver`, `host`, `port`, `name`, `user`, `charset`; `db/config/local.schema.json`: `connections.<name>.pass`
- `system/config/project.schema.json`: `ini`, `constants`, `auth.defaultSignInMethod`, `auth.deviceDays`, `auth.screenDays`
- `system/config/local.schema.json`: `auth.secret`, `mail.password`
- `relay/config/machine.schema.json`: `api`, `client`, `enabled`, `plainTopics`, `prefix`; `relay/config/local.schema.json`: `secret`, `apiKey`

Eine Datei gibt es nur, wenn sie etwas definiert. `cms` hat keine: Es liest keine
eigenen Config-Werte, nur `sites` über `Config::current()`.

Regeln:

- **Warum `.schema.json`:** `config.local.json` im Paket fiele unter `*.local.json`
  in `.gitignore` und Sync und würde nie ausgeliefert (geprüft). `*.schema.json` ist
  außerdem das übliche Muster für JSON Schema.
- **Die Definitionen liegen neben `scaffold/`, nicht darin.** Das Scaffold kopiert
  ins Projekt, die Kopie würde veralten.
- **Das System findet sie selbst:** über die installierten `systopic/*`-Pakete
  (`Composer\InstalledVersions`). Eine Anmeldung ginge nicht, `db` kennt `system`
  nicht und hat keine `autoload.php`.
- **Jeder Schlüssel ist in genau einer Definition.** Ein Test prüft das.
- **Ein Baum:** Alle Dateien haben dieselbe Form und werden in der Reihenfolge
  Defaults, `config.json`, Instanz, `config.local.json` zusammengeführt.
  `LOCAL_INSTANCE_KEYS` entfällt. Zugriff: `Config::package('db')`.
- **Oben bleiben** `matches`, `default`, `instance` (wählen die Instanz, bevor
  zusammengeführt wird) und die freien `constants` des Projekts. Sie werden nicht
  abgeglichen. `debug`, `versionAppendix`, `versionDate`, `linkSites`, `assets`,
  `ini` und die Systemkonstanten wandern unter `packages.system`.
- **Alle Secrets liegen immer in local**, ohne Ausnahme je Stage. Das ersetzt die
  Entscheidung vom 2026-11-04, dass DEV-Configs `auth.secret` enthalten dürfen.

`global.settings.json`, `media.settings.json` und `controlmap.json` bleiben, wie sie sind.

## Definitionsformat

Standard ist JSON Schema 2020-12. Unbekannte Schlüsselwörter sind dort erlaubt,
Eigenes bekommt ein `x-` wie bei OpenAPI. Nötig ist nur ein kleiner Teil, den
ein eigener Prüfer in wenigen Zeilen abdeckt, eine Bibliothek braucht es nicht.

| Schlüsselwort | Bedeutung |
|---|---|
| `type`, `enum`, `default`, `minimum`, `maximum`, `pattern`, `format` | Standard |
| `title`, `description` | Standard; Beschriftung und Hilfetext im Admin |
| `required`, `items`, `minItems`, `additionalProperties` | Standard; `additionalProperties: true` = offener Block wie `ini` |
| `writeOnly` | Standard; Passwortfeld, Wert wird nie angezeigt oder geloggt |
| `x-mandatory` | `"generate"`: entsteht bei Bedarf (`auth.secret`). `"input"`: muss jemand eintragen, der Admin fragt danach (`relay.secret` muss zum Hub passen) |
| `x-stages` | Default je Stage: `{ "DEV": true }` |
| `x-computed` | Default kommt aus dem Code und wird nie eingetragen (`bindOnlyUsedParams` hängt an der PHP-Version) |
| `x-optional` | Block wird nur ergänzt, wenn es ihn schon gibt (`mail`, `relay`) |
| `x-overridable` | darf in einer später geladenen Datei abweichen (`ini.*`, die Zugangsdaten einer Verbindung) |
| `x-options` | Auswahl zur Laufzeit, zum Beispiel `"sites"` aus `cms_sites` |
| `x-backup` | siehe [backup-config.md](backup-config.md) |

### Formular aus dem Schema

| Schema | Feld |
|---|---|
| `string` | Textfeld |
| `string` mit `enum` oder `x-options` | Auswahl |
| `string` mit `writeOnly` | Passwort |
| `boolean` | Schalter |
| `integer` | Zahl mit `minimum`, `maximum` |
| `array` aus `string` | Liste, ein Wert je Zeile |
| `array` aus `object` | Zeilen mit Hinzufügen und Entfernen, je Eigenschaft eine Spalte |
| `object` | Karte oder Gruppe |
| `object` mit `additionalProperties` als Schema | eine Karte je Name, mit Hinzufügen und Entfernen (`connections`) |

Die letzte Array-Zeile ersetzt `setMatch`, `addMatch` und `removeMatch`:

```json
{
	"$schema": "https://json-schema.org/draft/2020-12/schema",
	"title": "Installation",
	"type": "object",
	"properties": {
		"matches": {
			"title": "Rules",
			"description": "Detect this machine and the site of a request. One rule has to match.",
			"type": "array",
			"default": [],
			"items": {
				"title": "Rule",
				"type": "object",
				"additionalProperties": false,
				"anyOf": [
					{ "required": ["serverName"] },
					{ "required": ["serverIp"] },
					{ "required": ["rootDir"] },
					{ "required": ["path"] }
				],
				"properties": {
					"serverName": {
						"title": "Host",
						"description": "* as a wildcard",
						"type": ["string", "array"],
						"items": { "type": "string" }
					},
					"serverIp": {
						"title": "IP",
						"type": ["string", "array"],
						"items": { "type": "string", "format": "ip" }
					},
					"rootDir": { "title": "Project folder", "type": "string" },
					"path": { "title": "URL path", "type": "string", "pattern": "^/" },
					"site": {
						"title": "Site",
						"type": "string",
						"x-options": "sites",
						"x-backup": "stage"
					}
				}
			}
		},
		"default": {
			"title": "Default instance",
			"type": "boolean",
			"default": false
		}
	}
}
```

`["string", "array"]` hält die 26 bestehenden Listen gültig. Das Formular zeigt
solche Felder immer als Liste, ein einzelner Wert ist eine Liste mit einem
Eintrag. `anyOf` verlangt mindestens ein Kriterium, eine leere Regel kann also
nicht gespeichert werden.

## Abgleich

`Config\Reconcile` vergleicht eine Datei mit den Definitionen:

| Status | Bedeutung | Admin | Beim Speichern |
|---|---|---|---|
| ok | definiert und vorhanden | Wert, Markierung wenn ≠ Default | bleibt |
| fehlt | definiert, nicht in der Datei | Default, als „nicht gespeichert“ markiert | wird eingetragen, außer `x-optional`, `x-computed`, Secrets |
| verwaist | unter `packages.<x>`, `x` installiert, nicht definiert | durchgestrichen | Regeln unten |
| fremd | unter `packages.<x>`, `x` nicht installiert | Hinweis | bleibt |
| falsche Datei | gehört in eine andere Datei, kein `x-overridable` | „gehört nach …“, verschieben per Klick | bleibt |
| Secret ungeschützt | `writeOnly`-Wert außerhalb von local | Warnung | bleibt |

**Wann:** beim Speichern, und nur die gespeicherte Datei. Nie beim Booten. Nicht
in `composer update`, das änderte alle Instanz-Dateien auf einmal und erzeugte
Konflikte mit Lars. Der Hook **meldet** nur, `bin/config.php --check` ebenso. Die
Defaults wirken trotzdem sofort, weil sie zuunterst im Baum liegen.

**Defaults nur in der zuständigen Datei.** Stünde einer auch in einer später
geladenen Datei, würde er die Werte darunter verdecken.

**Löschen ist enger als Ergänzen:**

| Datei | Verwaiste Schlüssel |
|---|---|
| eigene Instanz-Datei | werden beim Speichern gelöscht, vorher angezeigt |
| fremde Instanz-Datei | bleiben. Der DEV-Code kann neuer sein als der auf LIVE (Tag `v1.2.0` ist älter als `main`) |
| `config.json` | nur per Klick. Die Datei gilt für alle Stages, derselbe Grund |
| `config.local.json` | nie. Die Datei liegt nirgends sonst |

Offene Blöcke und `"//"`-Schlüssel werden nie gelöscht.

**Grenzen:**

- Ein eingetragener Default bleibt stehen, auch wenn das Paket ihn ändert. Der
  Admin markiert „≠ Default“ und bietet „zurücksetzen“ an.
- Dateien mit echten Kommentaren (JSONC) werden nur gemeldet, wie heute.
- Die Reihenfolge der Datei bleibt erhalten, Neues kommt ans Ende des Blocks.
- Was verwaist ist, sagt die Definition, nicht der Code. Ein Test prüft deshalb
  alle Lesestellen gegen die Definitionen.
- LIVE-Secrets lassen sich nur noch auf LIVE selbst ändern, im Admin oder im Setup.

## ini

Geprüft mit `ini_get_all()` und `ini_set()` (PHP 8.5.6, CLI):

| `index.php` | Wert | Änderbar zur Laufzeit | Plan |
|---|---|---|---|
| `upload_max_filesize` | 50M | **nein**, `ini_set` liefert `false` | raus aus `ini` |
| `post_max_size` | 50M | **nein**, `ini_set` liefert `false` | raus aus `ini` |
| `max_execution_time` | 60 | ja | Definition |
| `memory_limit` | 1G | ja | Definition |
| `serialize_precision` | 14 | ja | Definition, Wert bleibt; wirkt auch auf `json_encode` |
| `log_errors` | 1 | ja | Definition |
| `html_errors` | false | ja | Definition |
| `error_log` | `var/log/PHP_errors.log` | ja | bleibt im Code |

- Die Definitionen stehen in `system/config/project.schema.json` unter `ini`,
  mit `x-overridable` und `additionalProperties: true`.
- **Die Upload-Werte wirkten nie**, es gilt der Serverwert. Die Zeilen sind aus
  `index.php` entfernt, `systemcheck` prüft sie (Upload ab 50 MB, Post ab 128 MB). Eine
  generierte `public/.user.ini` könnte sie setzen, das ist auf Plesk und der Synology
  aber ungetestet.
- **`error_log`** setzt `index.php` vor der Config. Er ist reserviert, das zweite Setzen
  nach dem Boot ist entfallen.
- **Reserviert** in `ini`: `error_log`, `display_errors`, `error_reporting`. Sonst
  könnte ein Eintrag den Debug-Schalter aushebeln.
- **Reihenfolge bleibt:** Die Defaults wirken vor `Config::boot()`
  (`Config::applyIniDefaults()`), weil die Einrichtungs-Seite läuft, wenn es noch keine
  Config gibt. Danach setzt `defineConstants()` nur noch, was in den Dateien steht.

## Umzug

Die Reihenfolge ist zwingend. Der Sync überträgt die Config, aber nicht `vendor`.
Liegt `db` schon unter `packages.db`, während auf einem Server alter Code läuft,
ist `Setup::hasDatabase()` false, und statt der Site kommt die Einrichtungs-Seite.
Im Code gibt es dagegen keinen Schutz, nur diese Reihenfolge.

1. **Definitionen, nichts schreiben.** Erledigt. Die Lesestellen verstehen beide Orte
   über `Config\Layout`, das jede Datei beim Boot in die neue Form hebt. Prüfung: Alle
   45 Instanzen liefern dieselben Werte wie vorher.
2. **ini und Konstanten.** Erledigt.
3. **Admin.** Erledigt. Die Optik gehört Lars, die Formulare nutzen die vorhandenen
   Klassen. Neu ist nur `small.envDefault` für „not saved“, ohne eigene Regel.
4. **Abgleich beim Speichern.** Erst Ergänzen, dann Löschen. Dazu Hook-Meldung,
   `bin/config.php --check` und die Tests.
5. **Code auf allen Stages.** Die Pakete bis LIVE ausrollen, also Packagist
   aktualisieren. Dazu gehört „Setup über Sync“ samt neuem Sync-Build auf jeder
   Instanz. Ohne das kommt nach Schritt 6 niemand mehr an ein geändertes Passwort.
6. **Secrets nach local, je Maschine.** `bin/config.php --move-secrets` läuft auf
   jeder Maschine selbst: erst nach local schreiben, dann aus der gesyncten Datei
   entfernen. Sonst nimmt ein Sync dem Server das Passwort weg. `connectRelay()`
   schreibt `apiKey` und `secret` ab dann nach local.
7. **Dateien umstellen.** Blöcke nach `packages.*`, je Projekt. `db` wird zu
   `packages.db.connections.main`.
8. **Altpfade entfernen,** `config.md` und `setup.md` nachziehen. Das bleibt `1.x`.
   Die Projekte verlangen `^1.0`, jedes `composer update` zieht diesen Schritt also
   mit. Er kommt deshalb erst, wenn alle Projekte auf allen Stages umgestellt sind.
