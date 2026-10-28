# Deployment — Compact Reference

---

## 1. PHP-Version

Beide `composer.json`-Dateien (`systopic/spaceticker.app` und `systopic/system`)
setzen `"php": "^8.4"`. Alle Umgebungen (Dev, Staging, Prod) müssen PHP 8.4+
bereitstellen.

---

## 2. Staging / Plesk — Composer mit korrekter PHP-Binary aufrufen

### Problem

Plesk stellt mehrere PHP-Versionen bereit, aber der System-CLI (`/usr/bin/php`)
zeigt oft auf eine ältere Version (z.B. 8.3), während das Projekt `^8.4` erfordert.
`composer install` schlägt dann mit einer PHP-Versions-Constraint-Fehlermeldung fehl,
weil Composer die PHP-Version der Binary prüft, mit der es selbst gestartet wurde.

### PHP-Binary finden

```bash
ls /opt/plesk/php/               # Plesk-Standard: /opt/plesk/php/<version>/bin/php
ls /usr/bin/php*                 # Distributions-Pakete (php8.4, php8.5 …)
/opt/plesk/php/8.4/bin/php -v   # Versionscheck
```

### Composer finden

```bash
which composer                        # oft nicht im PATH auf Plesk
find / -name "composer" -o -name "composer.phar" 2>/dev/null | head -20
```

### Composer lokal herunterladen (empfohlen wenn nicht gefunden)

```bash
cd /pfad/zum/project
/opt/plesk/php/8.4/bin/php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
/opt/plesk/php/8.4/bin/php composer-setup.php --quiet
rm composer-setup.php
# Jetzt liegt composer.phar im aktuellen Verzeichnis:
/opt/plesk/php/8.4/bin/php composer.phar install
```

### Composer aufrufen wenn global vorhanden

`which composer` zeigt `/usr/local/bin/composer` — das ist jedoch ein Shell-Wrapper,
kein PHAR. PHP kann Shell-Scripts nicht direkt ausführen. Der Wrapper verrät den
echten PHAR-Pfad (`COMPOSER_BIN=...`). Diesen direkt übergeben:

```bash
# Plesk-Standard: PHAR aus dem Wrapper-Script auslesen
cat /usr/local/bin/composer | grep COMPOSER_BIN

# Dann direkt aufrufen (Plesk-typischer Pfad):
/opt/plesk/php/8.4/bin/php /opt/psa/var/modules/composer/composer.phar install
```

### `install` vs. `update`

| Befehl             | Was passiert                                                                 |
|--------------------|------------------------------------------------------------------------------|
| `composer install` | Liest `composer.lock`, installiert exakt diese Versionen. Kein Versions-Upgrade. |
| `composer update`  | Löst Versionen neu auf (innerhalb der `composer.json`-Constraints), aktualisiert `composer.lock`, installiert dann (implizit). |

**Empfehlung für Staging/Prod:** `install` verwenden — reproduzierbare Builds,
kein Risiko ungetesteter Versions-Upgrades.

**Workflow:** Lokal `composer update` ausführen, `composer.lock` committen,
auf dem Server nur `composer install`.

**Immer `update` verwenden:** Möglich, aber `update` kann neuere Paketversionen
ziehen, die lokal noch nicht getestet wurden. Bei diesem Projekt geringes Risiko
(alle Pakete sind lokale Path-Repos mit fixen Versionen), auf Staging/Prod
mit Packagist-Paketen aber nicht empfohlen.

### `--no-dev`

Schließt alle Pakete aus dem `require-dev`-Block der `composer.json` aus
(Testframeworks, Linter usw.). Auf Staging/Prod empfohlen, um die
Vendor-Größe zu reduzieren. Weglassen wenn Dev-Dependencies ebenfalls
gebraucht werden.

---

## 3. Composer — Path Repositories (lokale Entwicklung)

> **Überholt (2026-10-05):** siehe [composer.md](composer.md). Die
> Path-Repositories gehören nicht mehr in die Projekt-`composer.json`, sondern
> in die globale Composer-Config der DEV-Rechner. Die Aussage unten, auf dem
> Server lade Composer „normal von Packagist“, stimmt nicht, sobald dort eine
> lokal erzeugte `composer.lock` liegt — sie nennt die lokalen Pfade.

In der lokalen Dev-Umgebung liegen Vendor-Pakete als Junction auf lokale
Git-Repositories unter `V:\www\repositories\<vendor>\<package>`.

Die `composer.json` des Projekts und des System-Packages binden diese via:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../../repositories/*/*",
      "options": { "symlink": true }
    }
  ]
}
```

Composer legt die Packages als **Junction** (Windows) bzw. Symlink (Linux/macOS)
an statt sie von Packagist herunterzuladen. Erkennung: `dir /al vendor\<vendor>`
zeigt `[V:\www\repositories\...]` als Ziel.

Auf dem Staging/Prod-Server fehlt `V:\www\repositories\` — dort lädt Composer
normal von Packagist.

## 4. Daten zwischen Stages (DEV → STAGING …)

Datenbank, Page Settings (`page.settings.php`), Media Settings
(`media.settings.php`) und die Environment-Dateien (`config/*.config.json`,
`config.local.json`, nicht `config.json`) wandern über das Backup-Panel im CMS
(`cms/backup`, `panels/cms/040_backup`). Mediendateien unter
`public/var/original/` nur, wenn „Media files“ an ist. Die Liste zeigt unter
Contents, was im Archiv liegt. Zurückspielen bietet dieselben Teile an, gelesen
aus dem ZIP, und lässt sich einzeln abwählen. Die Datenbank bleibt beim
Anlegen immer drin und ist beim Zurückspielen abwählbar. Datenbank, Settings
und Environment stehen in der ersten Datei. Weitere Teile gibt es nur für
Medien. Dass `.sync` `var/` ignoriert, betrifft nur den Code-Sync, nicht diesen
Weg. Mediendateien also nicht gesondert kopieren oder als fehlend melden
(Benutzer, 2026-10-12, mehrfach gesagt).

`composer update` auf dem Server führt der Benutzer selbst aus.

## 5. Je Server: Mail und Login (seit systopic/system 1.3)

In `config/<Instance>.config.json` oder `config/config.local.json` des
Servers, nie in Git (Doku: [auth-devices.md](auth-devices.md)):

```json
"mail": {"host": "smtp.systopic.net", "port": 587, "secure": "tls",
         "user": "…", "password": "…", "from": "noreply@…", "fromName": "…"},
"auth": {"secret": "<64 zufällige Hex-Zeichen>"}
```

- Ohne `mail` versendet ein Server **nichts** (Fehler im Log); DEV ohne Block
  schickt an Mailpit (`127.0.0.1:1025`).
- Ohne `auth.secret` gibt es keine Einmal-Codes; das CMS zeigt dann die alte
  Aktivierungs-Mail. Ein Secret erzeugen:
  `php -r "echo bin2hex(random_bytes(32));"`
- Der Systemcheck im CMS (Tools → System) zeigt beides.
- Erst die Blöcke eintragen, dann die neue `system`-Version ausrollen.
