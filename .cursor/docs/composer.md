# Composer — eine `composer.json` für alle Stages

Stand 2026-10-05. Ersetzt Abschnitt 3 von [deployment.md](deployment.md)
(Path-Repositories in der Projekt-`composer.json`).

## Grundsatz

Die `composer.json` eines Projekts beschreibt den **LIVE-Zustand**: alle
Pakete kommen aus ihrer öffentlichen Quelle (Packagist bzw. dem Git-Repo des
Pakets). Sie enthält **keine** `repositories` mit lokalen Pfaden.

Die Entwicklungsrechner lenken die Quelle um — nicht das Projekt: die
**globale** Composer-Config eines DEV-Rechners nennt Path-Repositories für
`packages/*` (eigene Pakete) und `repositories/*/*` (Spiegel fremder
Pakete). Composer nimmt dann dort die lokale Kopie als Symlink/Junction; auf
RC und LIVE fehlt die Umlenkung, dort kommt alles aus der Quelle. STAGING
lenkt seit 2026-10-12 wie ein DEV-Rechner um — auf die gesyncten
`packages/` und den eigenen, dort per `update.php` gepflegten Spiegel (bis
zum ersten RC, siehe „STAGING (Plesk)“ unten).

Dadurch:

- eine `composer.json` für alle Stages, keine `composer.rc.json` o. ä.
- RC sieht aus wie LIVE: kein Paket-Ordner neben den Projekten, kein
  Basisordner `packages` im Sync nötig
- lokal ändert man ein Paket und sieht es sofort in jedem Projekt (Symlink) —
  ein Commit im Paket-Repo braucht es erst für die Auslieferung

## Warum das funktioniert — die Reihenfolge der Quellen

Composer fragt die Repositories in dieser Reihenfolge:

1. `repositories` der Projekt-`composer.json`
2. `repositories` der globalen Config (`COMPOSER_HOME/config.json`)
3. Packagist (immer zuletzt)

Ein Repository, das ein Paket kennt, gewinnt (Path-Repositories sind
„kanonisch“) — die globale Umlenkung schlägt also Packagist. Sie schlägt
**nicht** ein Repository, das in der Projekt-`composer.json` steht. Darum
dürfen dort keine Quellen für die eigenen Pakete stehen; braucht eine Stage
eine zusätzliche Quelle (z. B. ein privates Git-Repo), gehört sie in die
globale Config **dieses Servers**.

## DEV-Rechner (Laragon, Windows)

Globale Config: `C:\Users\<user>\AppData\Roaming\Composer\config.json`
(`composer config --global home` zeigt den Ordner). Einmal je Rechner **von
Hand** so eintragen — als Objekt mit den Namen als Schlüssel:

```json
{
    "config": {},
    "repositories": {
        "systopic-packages": { "type": "path", "url": "C:/laragon/www/packages/*", "options": { "symlink": true } },
        "mirror": { "type": "path", "url": "C:/laragon/www/repositories/*/*", "options": { "symlink": true } }
    }
}
```

Nicht mit `composer config --global repositories.<name> …`: das schreibt eine
Liste mit `"name"`-Feldern, und in dieser Form lässt sich ein Eintrag nicht
per Name abschalten (siehe Spiegel unten — getestet 2026-10-05).

Absolute Pfade, weil die globale Config für jedes Projekt gilt, egal wie tief
es liegt.

**Relativ geht auch:** Composer löst einen relativen `url` gegen den Ordner
auf, in dem es läuft (den Projektordner), nicht gegen die Config-Datei.
`"url": "../../packages/*"` passt damit für jedes Projekt unter
`<basis>/projects/<name>/` mit `<basis>/packages/` daneben — auf DEV und
STAGING derselbe Text. Ein Projekt in anderer Tiefe findet die Umlenkung
dann **stillschweigend** nicht und lädt aus der Quelle; sichtbar im
Systemcheck (unten).

### Der Spiegel `repositories/` und `packages/update.php`

`packages/composer.json` ist das Manifest des Spiegels: `update.php` ruft
dort `composer update --no-install` und checkt jedes Paket aus der Lock-Datei
per Git unter `repositories/<vendor>/<paket>` aus — so kommen neue Versionen
(z. B. `tecnickcom/tcpdf`) in den Spiegel. Damit das mit der globalen
Umlenkung funktioniert, schaltet das Manifest sie für sich ab:

```json
"repositories": { "mirror": false, "systopic-packages": false }
```

Sonst fände Composer die Pakete im Spiegel selbst, die Lock-Datei nennte
Pfade statt Git-Quellen, und `update.php` übersprünge sie. Danach in den
Projekten `composer update <paket>` — sie nehmen den neuen Stand aus dem
Spiegel (Junction).

Prüfen, ob die Umlenkung greift: `composer show -s` bzw. `dir /al vendor\systopic`
— die Einträge sind Junctions auf `C:\laragon\www\packages\…`. Im CMS zeigt
**Tools → System → Systemcheck** („Composer Packages (<Stage>)“) jedes
`vendor/systopic/*` mit Ziel und warnt, wenn es nicht zur Stage passt: DEV
und STAGING erwarten einen Link, RC und LIVE einen echten Ordner
(`is_link()` ist unter Windows für Junctions `false`; der Check vergleicht
deshalb `realpath()` mit dem eigenen Pfad). Darunter „3rd party“: alle
übrigen Pakete aus `vendor/composer/installed.json` mit Version und Quelle
(Link in den Spiegel bzw. Git-/Dist-URL), ohne Bewertung.

Ein DEV-Rechner ohne diese Einträge ist kein Fehler: er bekommt alles aus der
Quelle, wie ein Server.

## DEV-Rechner (macOS, ServBay)

Dieselbe Umlenkung, anderer Ort der Config und andere Pfade. Globales Home
auf diesem Rechner (Composer 2.10, keine `XDG_*`-Variable, kein `/etc/xdg`):
`/Users/sabietzki/.composer` — `composer config --global home` zeigt den
Ordner. Die Datei `config.json` liegt dort; die `repositories` sind seit
2026-10-13 eingetragen (Objekt mit Namen, siehe unten).

Composer nimmt auf macOS `~/.composer`, solange keine XDG-Umgebung gilt.
Eine gesetzte `XDG_*`-Variable oder ein vorhandenes `/etc/xdg` stellt
`$XDG_CONFIG_HOME/composer` bzw. `~/.config/composer` **davor**; von den
vorhandenen Ordnern gewinnt der erste, fehlt jeder, legt Composer den
ersten an. Deshalb immer `composer config --global home` lesen und genau
die `config.json` dort anfassen.

Einmal je Rechner **von Hand** die `repositories` ergänzen — als Objekt mit
den Namen als Schlüssel. Nicht mit
`composer config --global repositories.<name> …` (dieselbe Falle wie unter
Windows: Liste mit `"name"`-Feldern, per Name nicht abschaltbar).

```json
{
    "config": {},
    "repositories": {
        "systopic-packages": { "type": "path", "url": "/Users/sabietzki/Sites/packages/*", "options": { "symlink": true } },
        "mirror": { "type": "path", "url": "/Users/sabietzki/Sites/repositories/*/*", "options": { "symlink": true } }
    }
}
```

Absolute Pfade, aus demselben Grund wie unter Windows: die globale Config
gilt für jedes Projekt, egal wie tief es liegt. Die Projekte liegen hier
unter `/Users/sabietzki/Sites/projects/<name>/`, `packages/` und
`repositories/` daneben — dieselbe Tiefe wie `<basis>/projects/<name>/`.
Damit passt auch der relative Text aus STAGING (`../../packages/*` und
`../../repositories/*/*`). Ein Projekt in anderer Tiefe findet die
Umlenkung stillschweigend nicht.

`"symlink": true` legt echte Symlinks an (keine Junctions). Prüfen:
`composer show -s` bzw. `ls -l vendor/systopic` — die Einträge zeigen auf
`/Users/sabietzki/Sites/packages/…`. Der Systemcheck im CMS ist derselbe;
unter macOS ist `is_link()` für diese Symlinks `true`. Spiegel und das
Abschalten beider Namen in `packages/composer.json` gelten unverändert
(Abschnitt oben). Danach in den Projekten `composer update <paket>`.

## STAGING (Plesk) — Umlenkung auf die gesyncten Pakete

Bis zum ersten RC kommen die eigenen Pakete auf STAGING nicht aus einem
Release, sondern per Sync nach `httpdocs/packages/`. Die Projekte liegen
unter `httpdocs/projects/<name>/`. Die Umlenkung ist dieselbe wie auf DEV,
mit **relativen** Pfaden (gegen den Projektordner aufgelöst, siehe oben).

**Datei:** `config.json` (neu anlegen) in **`.composer/`** im Home der
Subscription — vollständig
`/var/www/vhosts/staging.systopic.net/.composer/config.json`, im
Plesk-Dateimanager „Home directory → .composer“. Daneben liegen schon
`composer.json`, `composer.lock`, `vendor/`, `cache/` vom Plesk-Tool; die
bleiben, wie sie sind (warum, siehe „Mit dem Plesk-Tool“ unten).

```json
{
    "config": {},
    "repositories": {
        "systopic-packages": { "type": "path", "url": "../../packages/*", "options": { "symlink": true } },
        "mirror": { "type": "path", "url": "../../repositories/*/*", "options": { "symlink": true } }
    }
}
```

**Der Spiegel `repositories/` wird nicht gesynct** — STAGING pflegt ihn
selbst mit `update.php` (entschieden 2026-10-12). Das ist Pflicht, nicht
Kür: ein Path-Repository ist für seine Pakete kanonisch, Composer fragt
für ein Paket aus dem Spiegel nicht mehr bei Packagist. Fehlt dort die
Version, die eine `composer.json` verlangt, scheitert `composer update`,
statt auszuweichen. Also nach jeder Änderung an `packages/composer.json`
(oder um neue Versionen zu holen), per SSH:

```bash
cd /var/www/vhosts/staging.systopic.net/httpdocs/packages
/opt/plesk/php/8.4/bin/php update.php
```

`update.php` ruft auf Plesk das PHAR mit dem PHP auf, mit dem es selbst
läuft (der `composer`-Wrapper im PATH kann ein anderes PHP starten), und
braucht `git`. `packages/composer.json` schaltet beide Umlenkungen für sich
ab (`"mirror": false, "systopic-packages": false`) — die Namen in der
STAGING-Config müssen deshalb genau diese sein. Ein Paket, das ein Projekt
braucht, muss im Manifest `packages/composer.json` stehen, sonst kommt es
von Packagist (das ist kein Fehler).

**Mit dem Plesk-Tool** (Websites & Domains → PHP Composer):

- Das Ordner-Dropdown („Go to the application folder [change]“) wählt den
  **Arbeitsordner** — jede `composer.json`, die Plesk in der Domain findet,
  auch `packages/system`, `repositories/…`, alte `<projekt>/system`. Es ist
  **nicht** `COMPOSER_HOME`. Zu wählen ist `httpdocs/projects/<name>`.
- `COMPOSER_HOME` muss nicht gesetzt werden: ohne Variable nimmt Composer
  unter Linux das erste vorhandene von `$XDG_CONFIG_HOME/composer` (nur wenn
  eine `XDG_*`-Variable gesetzt ist) und `~/.composer`. Das Home der
  Subscription ist `/var/www/vhosts/staging.systopic.net/`, und dort liegt
  bereits `.composer/` mit einer `composer.json`, die das Plesk-Tool
  angelegt hat (`plesk/composer-command-info` als globales Plugin) — das
  Tool benutzt also genau diesen Ordner. Die Umlenkung kommt als
  **`config.json`** daneben (`.composer/config.json`), nicht in die
  vorhandene `composer.json`: die ist das „globale Projekt“
  (`composer global require`), die `config.json` die globale Config.
  Notfalls eindeutig über „Environment variables → Specify“
  `COMPOSER_HOME=/var/www/vhosts/staging.systopic.net/.composer`.
  Prüfen per SSH: `composer config --global home` bzw.
  `composer config --global repositories`.
- **Mode: Production** (= `--no-dev`).
- `httpdocs/packages` selbst (das Manifest des Spiegels) dort **nicht**
  installieren/updaten: es ist für `update.php` (`update --no-install` +
  Git-Checkouts), ein Klick legt `packages/vendor/` an. Der Hinweis „lock
  file is not up to date“ dort ist egal.
- Danach: Systemcheck im CMS — `vendor/systopic/*` müssen Links nach
  `httpdocs/packages/…` sein, unter „3rd party“ stehen die Links nach
  `httpdocs/repositories/…`. Die `.htaccess` im Projekt-Root
  (`Options +SymLinksIfOwnerMatch`) ist für genau diesen Fall da.
- Die `composer.lock`, die dabei entsteht, nennt Pfade — bleibt auf STAGING
  (siehe unten).

Per SSH dasselbe mit `COMPOSER_HOME=… /opt/plesk/php/8.4/bin/php
/opt/psa/var/modules/composer/composer.phar update --no-dev` im Projektordner.

## Server (RC, LIVE)

Keine globalen `repositories` — oder nur die, die für private Quellen nötig
sind (siehe „Offen“). Composer mit der richtigen PHP-Version aufrufen, siehe
[deployment.md](deployment.md) Abschnitt 2:

```bash
cd /var/www/vhosts/<domain>/projects/<projekt>
/opt/plesk/php/8.4/bin/php /opt/psa/var/modules/composer/composer.phar update --no-dev --optimize-autoloader
```

## `composer.lock` — je Rechner eine eigene

Die Lock-Datei hält fest, **woher** jedes Paket kam. Eine lokal erzeugte
`composer.lock` nennt für umgelenkte Pakete einen Pfad
(`"dist": {"type": "path", "url": "../../packages/system"}`) — auf einem
Server ohne diesen Pfad scheitert `composer install` damit. Deshalb:

- `composer.lock` bleibt auf dem Rechner, auf dem sie entstand. Der Sync
  überträgt sie nicht (`composer.lock` steht in den Standard-Ignores) — nicht
  ändern.
- Auf einem Server erzeugt `composer update` seine eigene Lock-Datei; danach
  reicht dort `composer install`, bis sich `composer.json` ändert.
- Reproduzierbarkeit kommt aus der `composer.json`: Versionen der eigenen
  Pakete dort **genau** angeben (siehe „Offen“), fremde Pakete mit einer
  engen Constraint (z. B. `"tecnickcom/tcpdf": "6.11.2"`), wenn es auf die
  exakte Version ankommt.

Das ersetzt den Workflow aus deployment.md („lokal `update`, Lock committen,
Server `install`“) — der setzt voraus, dass lokal und auf dem Server dieselben
Quellen gelten, und das ist mit der Umlenkung gerade nicht so.

## Umstellung eines Projekts

1. Globale Umlenkung auf dem DEV-Rechner einrichten (oben).
2. Die eigenen Pakete sind in ihrer Quelle veröffentlicht (siehe „Offen“).
3. In der Projekt-`composer.json` die `repositories` entfernen, die
   Versionen der eigenen Pakete auf die veröffentlichten setzen.
4. Lokal `composer update` — `vendor/systopic/*` bleiben Junctions (Umlenkung
   greift), die Lock-Datei bleibt lokal.
5. Auf dem Server `composer update --no-dev --optimize-autoloader`.

Reihenfolge wichtig: Schritt 3 vor Schritt 2 lässt die Server ins Leere
greifen bzw. den veralteten Stand ziehen, der heute unter `systopic/system`
bei Packagist liegt.

## Scaffold-Script (seit 2026-10-22)

Jede Projekt-composer.json soll den `scripts`-Eintrag tragen, der nach
install/update `vendor/systopic/system/bin/scaffold.php` ausführt —
`public/index.php`, `public/.htaccess`, `.htaccess`, `public/asset.php`
kommen dann aus dem Paket. Details: [setup.md](setup.md). Neue Projekte aus
der composer-Spalte des Projektmanagers haben ihn; bei bestehenden fehlt er
(Tooltip „ohne scaffold-script"). Nachtragen ersetzt beim nächsten
`composer update` deren eigene `index.php`/`.htaccess` (die alte Fassung
landet in `var/scaffold/`) — vorher prüfen, ob die `.htaccess` projekteigene
Regeln hat, und die in den `project`-Block übernehmen.

Der Weg über composer hat Folgen: läuft unter dem Benutzer, der composer
startet (auf Plesk nie als root), auch auf LIVE bei jedem Update, und
`--no-scripts` überspringt ihn.

## Stand und offen

- **Packagist** kennt `systopic/system` — nur `dev-main` aus
  `github.com/systopicode/system`, Stand 2026-06-18, ohne Releases, noch
  nicht aufgeteilt (`db`, `cms` fehlen, `system-legacy` steckt noch drin).
  Die lokalen Paket-Ordner (`packages/system`, `db`, `cms`,
  `system-legacy`, `tools`) sind keine Git-Repositories.
- **Entschieden 2026-10-05**, Umsetzung in
  [package-publishing.plan.md](../plans/package-publishing.plan.md):
  öffentlich (GitHub `systopicode/*` + Packagist), Releases mit
  Versionsnummern, `systopicode/system` wird weiterverwendet, `.cursor/`
  bleibt aus den Repos, MIT. Lokal löst `branch-alias` (`dev-main` →
  `1.x-dev`) zusammen mit `minimum-stability: dev` + `prefer-stable` den
  Widerspruch zwischen `"^1.0"` im Projekt und dem Arbeitsordner auf.
- **Globale Umlenkung auf diesem Rechner eingerichtet** (2026-10-05, als
  Objekt mit Namen), Sicherung der vorherigen Config:
  `config.json.bak-2026-10-05`. `packages/composer.json` schaltet sie für
  den Spiegel ab.
- **DEV.lsmacbook** eingerichtet 2026-10-13, Home `~/.composer`, Pfade
  `/Users/sabietzki/Sites/packages/*` und
  `/Users/sabietzki/Sites/repositories/*/*`.
- **DEV.ojoffice** (`V:\www`) eingerichtet 2026-10-06, Pfade
  `V:/www/packages/*` und `V:/www/repositories/*/*`. Fehlte sie, zeigte
  sich das nach der Paketaufteilung als `Class "Systopic\Db\Connection" not
  found` in `system/loader.php` — die `composer.json` war per Sync neu, das
  `vendor/` nicht. Nach dem Einrichten im Projekt `composer update`.
- **Veröffentlicht 2026-10-05:** `systopic/system`, `db`, `cms` als `v1.0.0`
  bei Packagist; `systopic.net` umgestellt (lokal), RC folgt.
- `repositories/` (Spiegel fremder Pakete): alles daraus gibt es bei
  Packagist — `systopic.net` nutzt nur `tecnickcom/tcpdf` (6.11.2 ist dort).
