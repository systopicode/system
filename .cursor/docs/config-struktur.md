# Config-Struktur: Definitionen, Dateien, Abgleich

Stand 2026-11-09. Baut auf [config.md](config.md) auf. Backup: siehe [backup-config.md](backup-config.md).

## Zwei Dateien (entschieden 2026-11-09)

Die reisenden Instanz-Dateien (`DEV.ojhome.config.json`, `STAGING.config.json` …) sind
abgelöst. Sie gab es aus Gründen der Struktur; seit die Definitionen Defaults, Stages
und Pflichtwerte mitbringen, unterschieden sich Instanz und `config.local.json` nur noch
in der Sicherheit.

| Datei | Hält | Reist |
|---|---|---|
| `config/config.json` | das Projekt: was für jede Installation gleich ist (`x-project`): Namen, Rechtliches, Meta, Sprachen, `ini`, Konstanten; die Sites vor der Datenbank. Das Formular schreibt hinein, die letzte Änderung gewinnt, ob aus dem Admin, Git oder Sync | Git, Sync |
| `config/config.local.json` | diese Installation: ihr Name (`packages.system.instance`), wie ihre Sites erreichbar sind (`packages.cms.siteAddress`), Datenbanken, Debug, Mail, alle Secrets | nirgends |

- **Kein Erkennen mehr.** `config.local.json` liegt im Projektordner genau einer
  Installation, sie sagt selbst, wer sie ist. Fehlt sie oder ihr Name, kommt die
  Einrichtungs-Seite (`NotConfigured::NO_INSTANCE`). Den LIVE-Default und die Sperre
  für lokale Adressen braucht es nicht mehr.
- **Die Stage** ist der Teil des Namens vor dem ersten Punkt (`DEV.ojhome` → `DEV`).
- **Mehrere Installationen in einem Ordner** gibt es nicht. Bräuchte es sie, müsste
  `config.local.json` nach Host unterscheiden.
- **Die Site** kommt aus einem Muster je Installation, siehe „Site-Adressen“.
- **Ein Schema je Paket** (`<paket>/config/config.schema.json`), der Master für Titel,
  Beschreibung, Reihenfolge, Defaults und den Ort jedes Werts.
- **Umstellen:** `php vendor/systopic/system/bin/config.php --adopt[=<instanz>]` übernimmt
  die alte Instanz-Datei dieser Maschine in `config.local.json`: Werte, Namen und die
  Site-Adressen, die ihre Regeln ausschreiben. Ohne Namen die, deren Regeln auf diese
  Maschine passen (`Config\MachineRules`, das auch die Maschinen-Dateien von syscoder
  auswählt). Die alten Dateien bleiben liegen, bis jede Maschine umgestellt ist; der
  neue Code liest sie nicht. Erledigt: `DEV.ojhome` in `spaceticker.app`, `systopic.net`,
  `smp.de`.

### Site-Adressen

Ein Projekt mit mehreren Sites (`systopic.net` mit `beautiful-technology.de`,
`expo-systems.de` …) erkennt die Site am Ordner (DEV, STAGING), an der Subdomain (RC) oder
an der Domain (LIVE). Statt einer Regel je Site und Maschine steht in
`packages.cms.siteAddress` ein Muster, ein regulärer Ausdruck mit Platzhalter:

| Stage | `siteAddress` | Platzhalter |
|---|---|---|
| DEV | `/projects(\.[a-z]+)?/{site}/` | `{site}`: der Name der Site. Auch `projects.archive/`, `projects.work/` |
| STAGING | `staging.systopic.net/projects/{site}/` | |
| RC | `rc.systopic.net`, `{name}.systopic.net` | `{name}`: der Name ohne TLD (wegen der Zertifikate). Ein Muster ohne Platzhalter gehört der Default-Site |
| LIVE | leer | jede Site unter ihrem Namen und ihren Aliasen |

`Config\Sites` macht aus den Mustern je Anfrage die Adressen aller Sites: Passt ein
Muster auf die Anfrage, wird das, was der Platzhalter genommen hat, gegen die andere
Site getauscht (aus `/projects.archive/expo-systems.de/` wird
`/projects.archive/systopic.net/`); passt es nicht, wird es ausgeschrieben, soweit es
reiner Text ist. Die längste passende Adresse gewinnt, die erste einer Site ist ihre
kanonische. Die Datenbank bekommt nichts Stage-Spezifisches: Sie reist mit jedem Backup.
Ein Feld für Abweichungen (Ordner ≠ Name) gibt es erst, wenn eine Site nicht ins Muster
passt. Site anlegen, umbenennen, löschen schreibt keine Config mehr.

## Nächste Sitzung

### Übergabe 2026-11-09 (Arbeitsplatz-Wechsel)

Am Anfang alle Repos pullen ([git.md](git.md)): system, db, cms, relay, spaceticker.app.

**Was an diesem Tag entstand** (Details in den Abschnitten oben und unten):

- Zwei Dateien statt Instanz-Dateien: config.json (Projekt) und config.local.json (diese
  Installation, nennt sich selbst über `packages.system.instance`). Ein
  `config.schema.json` je Paket, `x-project`, `x-tool`, `x-icon`, `x-constant`,
  `x-entry-name`.
- Sites über `packages.cms.siteAddress` (Muster), `linkSites` entfallen, die Site-Seite
  bietet den fehlenden Link an.
- Settings-Menü: Theme, Tags, dann ein Bereich je Paket (eigene Panels + Configuration,
  `admin/settings/config/?package=`). Environment-Panel und Datei-Auswahl sind weg, die
  Blöcke klappen auf. Das Formular schreibt Projektwerte nach config.json.
- Global Settings aufgelöst: Name, Rechtliches (system), Access, Meta, Sprachen (cms) in
  config.json, Konstanten per `x-constant`.

**Auf einem neuen Arbeitsplatz zuerst, je Projekt** (`spaceticker.app`, `systopic.net`,
`smp.de`), im Projektordner:

1. `php vendor/systopic/system/bin/config.php --adopt` — übernimmt die alte Instanz-Datei
   dieses Rechners (erkennt sie an IP/Pfad, sonst `--adopt=DEV.<maschine>`) in die
   config.local.json, mit Namen und `packages.cms.siteAddress`. Ohne das zeigt die Site
   die Einrichtungs-Seite. Erledigt ist nur `DEV.ojhome`.
2. `… --check`, dann im Admin Settings › System › Configuration ansehen.

**Global Settings importiert** (`--import-settings`): `spaceticker.app` (global.settings.json,
darin bleibt nur das ungenutzte `CMS_PROVIDER_URL`) und `smp.de` (settings.php, gekürzt,
Sicherung in `var/config-backup/`). **Nicht `systopic.net`:** Dessen config.json und
settings.php gehen per Sync nach RC, wo `v1.2.0` läuft und nur die alte Form liest. Der
Import (er hebt auch config.json in die neue Form) kommt dort am Tag des Tags, zusammen
mit `--migrate=all`. Vorher zurückgesetzt, die Seiten lesen weiter settings.php.

**Reihenfolge auf Servern:** erst den neuen System-Code, dann Projektdateien, die schon
umgestellt sind (gekürzte settings.php, config.json mit `packages.*`). Alter Code fände
Name, Meta und Sprachen sonst nicht. Betrifft STAGING/LIVE von `smp.de` und
`spaceticker.app`; dort außerdem `--adopt=STAGING` bzw. `--adopt=LIVE`. Ohne SSH: Sync →
„open Setup“ → „Scripts in bin/“ → `config.php` mit `--adopt` (erkennt die Installation am
Host) oder `--adopt=STAGING`, siehe [setup.md](setup.md), „Skripte aus `bin/`“.

**Offen:**

- Rechte je Wert (`x-role`), damit ein cmsAdmin Inhaltswerte, aber nicht Datenbank,
  Debug und Mail sieht. Bewusst später.
- Sortierbare Listen und Flaggen der Sprachen im Formular (bisher nur Einträge einer Map).
- Media Settings: kommt etwas Neues, bis dahin unangetastet.
- Die alten Instanz-Dateien löschen, wenn alle Maschinen und Stages `--adopt` hinter sich
  haben. RC liest sie noch.
- Klassennamen `EnvironmentForm`/`EnvironmentFile` passen nicht mehr, umbenennen ohne
  Wirkung nach außen.
- `cms_sites` von systopic.net hat eine zweite Default-Zeile mit leerem Namen (Datenrest).
- Im CLI-Harness liefern Branch-Sites (`/projects/beautiful-technology.de/public/`) eine
  leere Ausgabe, über den Webserver laufen sie. Nicht untersucht, ob das vorher auch so
  war.

### Stand 2026-11-06

**Konzept und beide offenen Themen sind entschieden.** Am Anfang
alle Repos pullen ([git.md](git.md)).

| Stand | |
|---|---|
| Definitionen | liegen unter `<paket>/config/`, `tests/config-schemas.php` prüft sie gegen alle Config-Dateien |
| Schritt 1 | fertig: `Config\Layout`, `Config\Schema`, `Config::package()`, `Config::connection()`. Alle Lesestellen laufen über den Baum, `tests/config-tree.php` vergleicht jede Instanz mit den alten Werten |
| Sync | Build mit `composer` und `openSetup`. `composer update` lief am 2026-11-06 auf RC |
| Setup über Sync | fertig bis auf das Projekt-Tool, `tests/setup-maintenance.php` spielt jeden Zustand durch |
| Scaffold | `systopic.net` mit seinen vier Sites (Junctions) umgestellt. `galerie-lattemann.de` und `pwk.systopic.net` nicht, sie haben eigene Logik in `index.php` und `.htaccess` |
| Schritt 3 | fertig: Formulare aus den Definitionen, alle Schreibwege schreiben die neue Form, Secrets nach local |
| Dateien | `systopic.net`, `spaceticker.app` und `smp.de` umgestellt, auf STAGING getestet. Ausnahme siehe unten |
| Projekt-Tool | Token entfernt, Setup und Recovery laufen über Sync |

| Schritt 2 | fertig: Defaults aus den Definitionen (`Config::applyIniDefaults()`), reservierte Schlüssel, Reihenfolge der Konstanten |

| Schritt 4 | fertig: `Config\Reconcile` beim Speichern, „Check“-Widget im Admin, `bin/config.php --check`, Meldung im Composer-Hook |

Als Nächstes: der Tag, siehe „Versionierung“, danach Schritt 8.

**Noch in alter Form, bis zum Tag:** `config.json` und `RC.config.json` von `systopic.net`. Beide
gehen nach RC, dort läuft `v1.2.0`, das nur die alte Form liest. Am Tag des Tags:
`bin/config.php --migrate=all`.

**Secrets je Maschine:** Sie ziehen auf jeder Maschine selbst um, im Admin per „Move to this
machine“ im Check-Widget oder mit `bin/config.php --move-secrets=<instanz>`. Erledigt ist
`DEV.ojhome` in allen drei Projekten. In den Dateien der anderen stehen sie noch, unter
`packages.*`.
`bin/config.php --check` zeigt, was fehlt.

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
Schema, eine Datei je Paket: `<paket>/config/config.schema.json`. Jeder Wert gehört
der Installation (`config.local.json`), außer er oder ein Block über ihm sagt
`x-project`: dann ist er für jede Installation gleich und steht in `config.json`.
`Schema::load()` markiert jeden Wert mit seiner Datei (`x-file`: `project` oder
`local`), damit niemand den Baum wieder hinaufgehen muss.

Beispiele:

- `system`: `instance`, `debug`, `versionAppendix`, `assets`, `mail` gehören der Installation; `ini`, `constants`, `auth.defaultSignInMethod`, `auth.deviceDays`, `auth.screenDays` dem Projekt; `auth.secret`, `mail.password` sind Secrets der Installation
- `db`: `connections.<name>` mit `driver`, `host`, `port`, `name`, `user`, `pass`, `charset`
- `relay`: `api`, `client`, `enabled`, `plainTopics`, `prefix`, `secret`, `apiKey`
- `cms`: `siteAddress`

Jedes Schema trägt `title`, `description` und `x-icon`: Titel und Symbol seines Bereichs im Settings-Menü.

Regeln:

- **Warum `.schema.json`:** `config.local.json` im Paket fiele unter `*.local.json`
  in `.gitignore` und Sync und würde nie ausgeliefert (geprüft). `*.schema.json` ist
  außerdem das übliche Muster für JSON Schema.
- **Die Definitionen liegen neben `scaffold/`, nicht darin.** Das Scaffold kopiert
  ins Projekt, die Kopie würde veralten.
- **Das System findet sie selbst:** über die installierten `systopic/*`-Pakete
  (`Composer\InstalledVersions`). Eine Anmeldung ginge nicht, `db` kennt `system`
  nicht und hat keine `autoload.php`.
- **Ein Baum:** Beide Dateien haben dieselbe Form und werden in der Reihenfolge
  Defaults, `config.json`, `config.local.json` zusammengeführt. Zugriff:
  `Config::package('db')`.
- **Oben bleiben** die freien `constants` des Projekts. Sie werden nicht abgeglichen.
- **Alle Secrets liegen in `config.local.json`.** Ein Secret mit `x-project` lehnt der
  Test ab.

`global.settings.json`, `media.settings.json` und `controlmap.json` bleiben, wie sie sind.

## Definitionsformat

Standard ist JSON Schema 2020-12. Unbekannte Schlüsselwörter sind dort erlaubt,
Eigenes bekommt ein `x-` wie bei OpenAPI. Nötig ist nur ein kleiner Teil, den
ein eigener Prüfer in wenigen Zeilen abdeckt, eine Bibliothek braucht es nicht.

| Schlüsselwort | Bedeutung |
|---|---|
| `type`, `enum`, `default`, `minimum`, `maximum`, `pattern`, `format` | Standard |
| `title`, `description` | Standard; Beschriftung und Hilfetext im Admin. Jeder Hilfetext steht hier, nicht im Code: bei Block und Wert (auch einer Rules-Spalte) als (i) mit Tooltip |
| `required`, `items`, `minItems`, `additionalProperties` | Standard; `additionalProperties: true` = offener Block wie `ini`, ein Schema = Block mit Namen (`connections`), `required` darin = Einträge, die bleiben (`main`) |
| `propertyNames` | Standard; bei einem Block mit Namen: was ein Name sein darf (`pattern`, `description` als Hinweis). Nur dann bietet der Admin „New …“ an |
| `writeOnly` | Standard; Passwortfeld, Wert wird nie angezeigt oder geloggt |
| `x-mandatory` | `"generate"`: entsteht bei Bedarf (`auth.secret`). `"input"`: muss jemand eintragen, der Admin fragt danach (`relay.secret` muss zum Hub passen, `instance`); ein solcher Wert lässt sich nicht leeren |
| `x-stages` | Default je Stage: `{ "DEV": true }` |
| `x-computed` | Default kommt aus dem Code und wird nie eingetragen (`bindOnlyUsedParams` hängt an der PHP-Version) |
| `x-optional` | Block wird nur ergänzt, wenn es ihn schon gibt (`mail`, `relay`) |
| `x-project` | an einem Block oder Wert: gehört dem Projekt, steht in `config.json`. Ohne: gehört der Installation |
| `x-overridable` | ein Wert des Projekts, den eine Installation in `config.local.json` abweichend setzen darf (`ini.*`, `auth.deviceDays`) |
| `x-options` | Auswahl zur Laufzeit, zum Beispiel `"sites"` aus `cms_sites` |
| `x-backup` | siehe [backup-config.md](backup-config.md) |
| `x-tool` | an einem Block: Klasse, die `Settings\SettingsTool` umsetzt. Sie zeigt unter den Werten, was mehr ist als ein Wert (Testmail, Sign-in-Key, Hub), und nimmt ihre Aufrufe an. Das Paket bringt sie mit: `system` `Tools\MailTool` und `Tools\AuthTool`, `relay` `Settings\HubTool` |
| `x-constant` | an einem Wert oder einer Map: die PHP-Konstante, die `Config::defineSettingConstants()` daraus macht (nach einer evtl. noch vorhandenen settings.php, die gewinnt). So lesen die bisherigen Stellen weiter `PUBLIC_PAGE_NAME`, `META_*`, `LANGUAGES` |
| `x-icon` | an der Wurzel: das Symbol des Pakets im Settings-Menü (`server`, `database`, `sitemap`, `bolt`) |
| `x-setup` | ohne diesen Wert startet die Installation nicht: das Setup über Sync zeigt nur ihn (`system.instance`, Host, Port, Name, User, Passwort der Verbindung `main`). Alles andere im Admin, Settings › Config |
| `x-entry-name` | an einem Wert eines Eintrags: ein neuer Eintrag beginnt mit seinem Namen darin (eine Verbindung `revolver` mit der Datenbank `revolver`) |

### Formular aus dem Schema

**Menü:** Settings zeigt oben Theme und Tags, darunter einen Bereich je Paket: Titel und
`x-icon` aus seinem Schema, die Panels, die das Paket mitbringt (welches, sagt der Ordner,
in dem das Panel liegt), und als letztes seine „Configuration“
(`admin/settings/config/?package=<paket>`, Panel `035_config`, ein Paket je Ansicht).
Panels des Projekts selbst stehen in einem Bereich „Project“ am Ende. Das frühere
Environment-Panel mit allen Paketen untereinander ist entfallen.

**Global Settings sind aufgelöst** (2026-11-09). Was die Pakete lesen, steht in ihren
Schemas, mit `x-project` und `x-constant`, und liegt in config.json:

| Paket | Block | Konstanten |
|---|---|---|
| system | Website | `PUBLIC_PAGE_NAME`, `PUBLIC_APP_NAME`, `PAGE_TITLE_SEPERATOR` |
| system | Legal | `CMS_IMPRINT_URL`, `CMS_PRIVACY_URL` (Ziele der Links auf den Anmeldeseiten), `CMS_OPERATOR_DETAILS`, `CMS_DPO_DETAILS` (Betreiber und Datenschutzbeauftragter auf der Datenschutzseite der Anmeldung) |
| cms | Access | `PAGE_IS_PUBLIC`, `PAGE_ACL`, `FRONTEND_EDIT_BAR` |
| cms | Meta | `META_AUTHOR`, `META_KEYWORDS`, `META_DESCRIPTION`, `META_PAGE_TOPIC`, `META_ROBOTS` |
| cms | Languages | `DEFAULT_LANG`, `AUTO_LANGUAGE_DETECTION`, `LANGUAGES` (eine Map: Code → Name, Code, an) |

Eine neue Sprache wird damit auf allen Installationen nachgezogen; wo die Datenbank sie
noch nicht hat, sind die Seiten leer. `bin/config.php --import-settings` überträgt
global.settings.json oder die `define()` einer settings.php (auch mehrzeilige
Literal-Arrays) nach config.json und nimmt sie dort heraus. Was übrig bleibt (freie
Konstanten des Projekts in settings.php), zeigt das Panel Global Settings weiter; ohne
Quelle verschwindet es aus dem Menü. Media Settings bleiben, wie sie sind. Noch offen:
sortierbare Listen und die Flaggen der Sprachen im Formular.

Eine Ansicht: Jeder Wert wird in die Datei geschrieben, der er gehört
(`EnvironmentFile::target()`): ein Wert des Projekts nach config.json, einer der
Installation und jedes Secret nach config.local.json; einen Wert des Projekts, den die
Installation schon überschreibt (`x-overridable`), dort. Check prüft beide Dateien. Jeder Wert und jeder
Abschnitt trägt ein graues (i) für die Definition. Ein Wert trägt dazu ein farbiges (i)
je Datei, in der er steht: blau `config.json`, grün `config.local.json`. Deren Tooltip
zeigt, was die Datei sagt (bei Secrets nur „set“). Die Herkunfts-Chips tragen dieselben
Farben (Package, Project, Installation). Ein Wert des Projekts mit `x-overridable` ist
in der Installation ein normales Feld; steht er dort, heißt es „overrides the project“.

Für Superuser, wo syscoder liegt (DEV), öffnet ein Klick auf ein (i) die
Stelle in syscoder, je an der Zeile: das graue die Definition, ein farbiges
den Wert in seiner Datei. Ein Secret öffnet sich nicht. Die Zeile liefert
`Config\JsonLines`, das den Text neben `json_decode()` noch einmal durchgeht;
der Link kommt aus `Debug\Syscoder`.

Jeder Block (`ini`, `constants`, `auth`, eine Verbindung …) ist ein Kasten
mit eigener Tabelle; die Überschrift ist deren erste Zeile (`th` über alle
Spalten, grau, im `tbody`, weil das Handy `thead` ausblendet). Erst die Werte
des Blocks, dann seine Unterblöcke als Kästen darin, eingerückt. Der Pfad
(`packages.system.constants.…`) und die Beschreibung stehen im Tooltip des
grauen (i), nicht in der Zeile, bei Paket, Block, Eintrag und Wert gleich.
Sichtbar unter einer Überschrift bleiben nur `"//"`-Notizen der Dateien.
Alle Kästen kommen aus einer Funktion (`EnvironmentForm::block()`): ein Block
mit Werten (`mail`), ein Block mit Namen (`connections`) und jeder Eintrag
darin. Was neben dem Titel steht (optional, Remove), entscheidet
`aside()` für alle gleich. Ein Paket kennt das Formular nirgends beim Namen:
Was ein Block mehr braucht, kommt über `x-tool`. Ein Paket hat oben keine losen
Werte: Sie stehen im Block „General“, was keine Definition kennt im Block
„Defined nowhere“. Eine Tabelle ist so breit wie ihr Inhalt, die Spalten
rechnet der Browser je Tabelle (`table-layout:auto`, keine %-Breiten); Felder
zählen mit dem, was in ihnen steht (`field-sizing:content`), leer sind sie
mindestens 8rem breit.

Die Blöcke der ersten Ebene (General, Debug, PHP settings …) sind zugeklappt, bis man
ihren Kopf anklickt; ebenso „Defined nowhere“, fremde Pakete und „More in the files“
(diese beiden beim ersten Paket). Offen bleibt, was offen war (Panel-State
`open`, Aufruf `fold`). Die Kopfzeile trägt die Auswahl der Config (State
`file`, Aufruf `pick`), „Open all“/„Close all“ und „Check“: was zu prüfen ist
(Wiederholtes, Unbekanntes, Falschplatziertes, Secrets, Fehlendes) als
Lightbox (`035_environment/check/`), mit der Anzahl am Knopf. Die Legende der
Herkunft steht unten in der Navi der Settings.

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

`siteAddress` ist `["string", "array"]`: im Formular ein Feld, mehrere Muster durch
Kommas getrennt.

## Abgleich

> Geändert am 2026-11-07: Speichern trägt keine Defaults mehr ein, Verwaistes geht nur per
> Klick, das Admin zeigt die Herkunft je Wert (`Config\Explain`). Siehe
> [settings-gui.plan.md](../plans/settings-gui.plan.md).

`Config\Reconcile` vergleicht eine Datei mit den Definitionen:

| Status | Bedeutung | Admin | Beim Speichern |
|---|---|---|---|
| ok | definiert und vorhanden | Wert, Markierung wenn ≠ Default | bleibt |
| fehlt | definiert, nicht in der Datei | Wert der Ebene darunter, mit Herkunft (`Package`, `Project` …) | bleibt draußen, der Default kommt zur Laufzeit aus der Definition |
| überflüssig | in der Datei, gleich dem Wert darunter | „same as below“ | bleibt; „Remove them“ bzw. `--prune-defaults` nimmt ihn heraus |
| verwaist | unter `packages.<x>`, `x` installiert, nicht definiert | durchgestrichen, am Ende des Paket-Blocks | bleibt bis zum Klick, Regeln unten |
| fremd | unter `packages.<x>`, `x` nicht installiert | Hinweis | bleibt |
| falsche Datei | ein Wert der Installation in `config.json`, oder einer des Projekts ohne `x-overridable` in `config.local.json` | „belongs into …“ | bleibt |
| Secret ungeschützt | `writeOnly`-Wert in `config.json` | Warnung | bleibt |

**Wann:** im Admin beim Anzeigen, und nur die gezeigte Datei. Gespeichert wird nur die
Änderung. Nie beim Booten. Nicht
in `composer update`, das änderte alle Instanz-Dateien auf einmal und erzeugte
Konflikte mit Lars. Der Hook **meldet** nur, `bin/config.php --check` ebenso. Die
Defaults wirken trotzdem sofort, weil sie zuunterst im Baum liegen.

**Defaults stehen in keiner Datei.** Bis 2026-11-07 trug jedes Speichern sie ein. Diese
Kopien blieben stehen, wenn das Paket den Default änderte, und waren von bewusst gesetzten
Werten nicht zu unterscheiden. Das Admin zeigt sie jetzt als „same as below“.

**Löschen ist enger als Ergänzen:**

| Datei | Verwaiste Schlüssel |
|---|---|
| `config.local.json` | per Klick, einzeln oder alle, mit Bestätigung |
| `config.json` | nur Hinweis. Die Datei gilt für alle Stages, und der DEV-Code kann neuer sein als der auf LIVE |

Offene Blöcke und `"//"`-Schlüssel werden nie gelöscht.

**Grenzen:**

- Ein Wert in der Datei bleibt stehen, auch wenn das Paket seinen Default ändert. Das Admin
  zeigt daneben, was darunter gilt, und bietet das Zurücksetzen an. Ein geleertes Feld
  setzt ebenso zurück.
- Verwaistes in config.json bleibt beim Hinweis, auch wenn das Admin sonst hineinschreibt.
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

- Die Definitionen stehen in `system/config/config.schema.json` unter `ini`,
  mit `x-project`, `x-overridable` und `additionalProperties: true`.
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
4. **Abgleich beim Speichern.** Erledigt.
5. **Code auf allen Stages.** DEV und STAGING: erledigt. RC bekommt ihn mit dem Tag, LIVE
   gibt es noch nicht.
6. **Secrets nach local, je Maschine.** Das Werkzeug ist fertig, im Admin per Klick oder mit
   `bin/config.php --move-secrets`, erst nach local schreiben, dann aus der gesyncten Datei
   entfernen. Es läuft auf jeder Maschine selbst, offen sind die übrigen Maschinen.
7. **Dateien umstellen.** Erledigt bis auf `config.json` und `RC.config.json` von
   `systopic.net`, die am Tag des Tags folgen (`--migrate=all`).
9. **Zwei Dateien.** 2026-11-09, siehe oben. Offen: `--adopt` auf den anderen Maschinen,
   STAGING und LIVE; danach die alten Instanz-Dateien löschen.
8. **Altpfade entfernen.** Die Doku ist nachgezogen. Offen im Code: `Config\Layout`,
   `Config::LOCAL_INSTANCE_KEYS`, `Instance::db()`, `debug()`, `get()`. Das bleibt `1.x`. Die
   Projekte verlangen `^1.0`, jedes `composer update` zieht diesen Schritt also mit. Er
   kommt deshalb erst, wenn RC umgestellt ist und die Secrets aller Maschinen umgezogen sind.
