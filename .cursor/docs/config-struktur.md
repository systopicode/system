# Config-Struktur: Definitionen, Dateien, Abgleich

Stand 2026-11-05. Konzept, nichts umgesetzt. Baut auf [config.md](config.md) auf.
Backup: siehe [backup-config.md](backup-config.md).

## Nächste Sitzung

Stand Ende 2026-11-05. **Das Konzept ist abgestimmt, die Umsetzung noch nicht
begonnen.** Am Anfang alle Repos pullen ([git.md](git.md)). Die Reihenfolge der
Themen ist: erst die zwei offenen Fragen klären, dann Schritt 1 unter „Umzug“.

**Entschieden:** `packages.<paket>` als Namensraum, die drei `.schema.json`-Dateien
je Paket, JSON Schema mit `x-`-Erweiterungen, Formulare aus den Definitionen, alle
Secrets immer in local, `x-overridable`, automatisches Löschen nur in der eigenen
Instanz-Datei. Das Environment ist aus dem Backup entfernt
([backup-config.md](backup-config.md)).

### Thema 1: Versionierung

| Paket | Letzter Tag | Commits danach |
|---|---|---|
| `system` | `v1.2.0` | 118 (vor den Commits vom 2026-11-05) |
| `cms` | `v1.1.1` | 66 |
| `relay` | `v1.0.1` | 20 |
| `db` | `v1.1.0` | 6 |

DEV und Staging laufen über Symlinks auf `main`, LIVE über Packagist. Die Projekte
verlangen `^1.0` mit `minimum-stability: dev`. `deployment.md` nennt in Abschnitt 5
schon `systopic/system 1.3`, den Tag gibt es nicht. `panels/auth/releases.php` zählt
getrennt davon eine GUI-Version (`0.5.7`).

Zu klären:

- Wann wird getaggt, und wer entscheidet das? Packagist soll bisher erst vor dem
  nächsten Go-live nachziehen. Der Config-Umbau braucht aber neuen Code auf allen
  Stages, bevor die Dateien umziehen (Schritt 5).
- Ist der Umzug nach `packages.*` ein Major-Sprung? Solange die Lesestellen beide
  Orte verstehen, reicht ein Minor. Das Entfernen der Altpfade wäre `2.0`.
- Laufen die Paketversionen gemeinsam oder einzeln? `relay` verlangt heute `system ^1.2`.
- Soll eine Instanz erkennen können, dass ihre Config zu neu für ihren Code ist?
  Das wäre der Schutz vor dem Fall aus „Umzug“.
- Bleibt die GUI-Version in `releases.php` eine eigene Zählung?

### Thema 2: Wie kommen Secrets auf Staging?

Bisher gehen sie in `STAGING.config.json` per Sync mit. Ist alles in
`config.local.json`, bewegen weder Git noch Sync sie, und es braucht einen neuen
Weg. Das gilt nur für Werte mit `"x-mandatory": "input"` (`db.pass`, `mail.password`,
`relay.secret`, `relay.apiKey`). `auth.secret` entsteht auf dem Server selbst. Bei
`relay` ist zu prüfen, ob `EnvironmentFile::connectRelay()` das schon abdeckt.

| Weg | Wie | Dafür | Dagegen |
|---|---|---|---|
| A. Admin oder Einrichtungs-Seite auf dem Server | Wert dort eintippen | kein neuer Transportweg; `Setup` schreibt `db` schon wahlweise nach local | Henne-Ei bei `db.pass`: Ohne Datenbank gibt es nur Setup oder Recovery, von außen mit Token aus `var/setup.token` |
| B. Eigene Aktion „Secrets übertragen“ im Sync-Tool | je Server ausdrücklich, nie Teil des normalen Syncs; schreibt die `config.local.json` des Ziels | bequem, Vertrauen zum Server ist schon da | neuer Code. Der Sync signiert nur (`sodium_crypto_sign_*`), verschlüsselt nicht; es bliebe TLS, sonst `sodium_crypto_box_seal` nachrüsten. Ein Arbeitsplatz muss die Werte kennen |
| C. Von Hand per Plesk oder SSH | Datei auf dem Server anlegen | kein Code, heute schon so für LIVE | jedes Mal Handarbeit, fehleranfällig |

Vorschlag für das Gespräch: B für STAGING und RC, A oder C für LIVE.

Zu klären:

- Wo liegt die Quelle der Werte? `*.local.json` wandert nicht zwischen den
  Arbeitsplätzen. Der andere Rechner kennt die Staging-Passwörter dann nicht mehr von
  selbst. Das spricht für einen Passwortmanager als Quelle.
- Darf ein Arbeitsplatz LIVE-Secrets überhaupt speichern?
- Totalverlust eines Servers: siehe [backup-config.md](backup-config.md), „Offen“.

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

Nebenbefund: `Config::defineConstants()` definiert erst `config.json`, dann die
Instanz, jeweils mit `defined()`-Schutz. Die erste gewinnt, eine Instanz kann also
nichts überschreiben, obwohl der Kommentar das sagt. Es fällt nur nicht auf, weil
keine Instanz `constants` hat.

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

- `db/config/machine.schema.json`: `host`, `name`, `user`; `db/config/local.schema.json`: `pass`
- `system/config/project.schema.json`: `ini`, `auth.methods`, `auth.defaultSignInMethod`
- `system/config/local.schema.json`: `auth.secret`
- `relay/config/machine.schema.json`: `api`, `client`, `enabled`; `relay/config/local.schema.json`: `secret`, `apiKey`

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
| `x-overridable` | darf in einer später geladenen Datei abweichen (`ini.*`) |
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
- LIVE-Secrets lassen sich nur noch im Admin auf LIVE selbst ändern.

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
- **Die Upload-Werte wirken heute nicht**, es gilt der Serverwert. Sie werden zu
  Mindestwerten, die `systemcheck` prüft. Eine generierte `public/.user.ini` könnte
  sie setzen, das ist auf Plesk und der Synology aber ungetestet.
- **`error_log`:** `index.php:78-79` setzt ihn nach der Config immer zurück. Der
  Kommentar in Zeile 49 beschreibt das falsch.
- **Reserviert** in `ini`: `error_log`, `display_errors`, `error_reporting`. Sonst
  könnte ein Eintrag den Debug-Schalter aushebeln.
- **Reihenfolge bleibt:** Die Defaults müssen vor `Config::boot()` wirken, weil die
  Einrichtungs-Seite läuft, wenn es noch keine Config gibt. Die Zeilen 41-47 werden
  zu `Config::applyIniDefaults()`.

## Umzug

Die Reihenfolge ist zwingend. Der Sync überträgt die Config, aber nicht `vendor`.
Liegt `db` schon unter `packages.db`, während auf einem Server alter Code läuft,
ist `Setup::hasDatabase()` false, und statt der Site kommt die Einrichtungs-Seite.

1. **Definitionen, nichts schreiben.** `Config\Schema`, Definitionen für system, db,
   cms, relay. Die Lesestellen verstehen beide Orte. Prüfung: Alle Projekte verhalten
   sich wie vorher.
2. **ini und Konstanten.** `applyIniDefaults()`, reservierte Schlüssel, Upload-Werte
   in `systemcheck`, Reihenfolge in `defineConstants()`.
3. **Admin.** Formulare aus den Definitionen, Status und Herkunft. Die Optik gehört
   Lars, neues CSS geht als Vorschlag in `team/`.
4. **Abgleich beim Speichern.** Erst Ergänzen, dann Löschen. Dazu Hook-Meldung,
   `bin/config.php --check` und die Tests.
5. **Code auf allen Stages.** Die Pakete bis LIVE ausrollen, also Packagist
   aktualisieren.
6. **Secrets nach local, je Maschine.** `bin/config.php --move-secrets` läuft auf
   jeder Maschine selbst: erst nach local schreiben, dann aus der gesyncten Datei
   entfernen. Sonst nimmt ein Sync dem Server das Passwort weg. Setzt Thema 2 unter
   „Nächste Sitzung“ voraus.
7. **Dateien umstellen.** Blöcke nach `packages.*`, je Projekt.
8. **Altpfade entfernen,** `config.md` und `setup.md` nachziehen.
