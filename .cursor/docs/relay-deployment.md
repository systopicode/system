# Relay-Hub installieren und aktualisieren (STAGING, Plesk)

Für systopic/relay (Konzept und Stand:
[../plans/relay.plan.md](../plans/relay.plan.md)). Ein Hub (Centrifugo) je
Server für alle Projekte. PHP spricht ihn über die interne API an, der Browser
über `/connection/` auf derselben Domain. Geschrieben 2026-10-14 nach dem
Piloten auf DEV.ojhome, für **staging.systopic.net**. LIVE geht genauso, siehe
„LIVE“ am Ende.

**Reihenfolge:** zuerst den Code (ohne `relay`-Config tut er nichts), dann den
Hub, dann die Weiterleitung, zuletzt das Projekt einschalten. Jeder Schritt
ist für sich prüfbar und lässt sich zurücknehmen.

| Teil | Wer | Was |
|---|---|---|
| A | Subscription (SSH-Terminal der Domain, Plesk-Tools) | Code syncen, Composer |
| B | **root** (Plesk: Tools & Settings → SSH Terminal) | Centrifugo, systemd |
| C | Plesk-Oberfläche | nginx-Weiterleitung `/connection/` |
| D | Subscription | `config.local.json`, Test |

Ohne root-Zugang: Teil B in der Variante „ohne root“.

---

## 0. Vorher

- **Was mit dem Sync mitgeht:** `packages/db`, `packages/system`, das neue
  `packages/relay` und `projects/spaceticker.app` — **so, wie sie auf dem
  Rechner liegen**, nicht wie sie committet sind. In `packages/system` lagen am
  2026-10-14 fremde, nicht committete Änderungen (`asset.php`,
  `panels/cms/body.tpl.php`, `site/head.loader.tpl.php`,
  `site/head.system.tpl.php`, `src/Config/InstanceFiles.php`,
  `src/Http/Assets.php`). Sie gehen mit, wenn sie nicht vorher geklärt sind.
- Server: Architektur und freie Ports. Der Hub braucht zwei Ports auf
  127.0.0.1, hier **8100** (Browser, über nginx) und **8101** (API für PHP).

```bash
uname -m                                   # x86_64 → linux_amd64, aarch64 → linux_arm64
ss -ltn | grep -E ':(8100|8101)\b'         # keine Ausgabe = frei
```

Sind die Ports belegt, andere nehmen und überall unten ersetzen.

---

## A. Code und Composer (Subscription)

1. **Syncen:** `packages/` (bringt `relay/` neu mit) und
   `projects/spaceticker.app`. Ignoriert werden u. a. `vendor`,
   `composer.lock`, `*.local.json` (`.htsync`).
2. **Composer** — die `composer.json` des Projekts verlangt jetzt
   `systopic/relay`. Plesk → Websites & Domains → PHP Composer, Ordner
   `httpdocs/projects/spaceticker.app`, Mode Production, **Update**. Oder per
   SSH:

```bash
cd /var/www/vhosts/staging.systopic.net/httpdocs/projects/spaceticker.app
COMPOSER_HOME=/var/www/vhosts/staging.systopic.net/.composer \
  /opt/plesk/php/8.4/bin/php /opt/psa/var/modules/composer/composer.phar update systopic/relay --no-dev
ls -l vendor/systopic/           # relay -> ../../../../packages/relay (Link wie db, system)
```

`packages/composer.json` (Manifest des Spiegels) braucht keinen Eintrag:
`relay` hat keine Fremdpakete, und `update.php` muss nicht laufen.

3. **Prüfen:** Website und CMS laufen wie vorher; Systemcheck im CMS zeigt
   `systopic/relay` als Link nach `httpdocs/packages/relay`. Ohne `relay`-Block
   in der Config ist das Paket aus: kein Skript, kein Beobachter.

---

## B. Centrifugo installieren (root)

Das Skript `packages/relay/bin/ubuntu/install.sh` erledigt alles: Binary laden
und gegen die Prüfsummen des Releases prüfen, Systembenutzer, Config mit neuen
Geheimnissen, systemd-Dienst, Prüfung. Am Ende gibt es die nginx-Direktiven
für C und den `relay`-Block für D aus. Was es im Einzelnen tut, steht in
[relay/bin/README.md](../../../relay/bin/README.md).

```bash
bash /var/www/vhosts/staging.systopic.net/httpdocs/packages/relay/bin/ubuntu/install.sh \
	--origins=https://staging.systopic.net
```

Erwartet in der Ausgabe: `centrifugo_6.9.7_linux_amd64.tar.gz: OK`, `active`,
beide Ports auf 127.0.0.1, `health: {}`, `publish: {"result":{}}`. Den
`relay`-Block sicher notieren (oder später mit `--print` erneut ausgeben).

Ein weiterer Aufruf ohne Optionen ist ein Update (Binary, Neustart), Config
und Geheimnisse bleiben. `--uninstall` entfernt Dienst, Binary, Benutzer und
Config. Andere Ports: `--port=…` (Browser, die API liegt auf Port+1).

Gegenüber DEV fehlen bewusst: der Admin (Weboberfläche) und der Namespace
`debug` — beides nur auf DEV.

### Variante ohne root

Wenn es keinen root-Zugang gibt: Binary nach `~/bin/centrifugo`, Config nach
`~/.centrifugo/config.json` (Home der Subscription, nicht unter `httpdocs`!),
gestartet durch einen Plesk-Cronjob (Scheduled Tasks, alle 5 Minuten), der
nur startet, wenn er nicht läuft:

```bash
pgrep -u "$(id -u)" -x centrifugo >/dev/null || \
  nohup ~/bin/centrifugo -c ~/.centrifugo/config.json >> ~/.centrifugo/centrifugo.log 2>&1 &
```

Nachteile: bis zu 5 Minuten Ausfall nach einem Neustart des Servers, kein
Journal, ein Hub je Subscription statt je Server.

---

## C. Weiterleitung `/connection/` (Plesk, nginx)

Plesk → Websites & Domains → staging.systopic.net → **Apache & nginx
Settings** → „Additional nginx directives“:

```nginx
# systopic/relay - the browser's socket. With a slash: our urls without one
# are actions; the hub itself serves /connection/websocket.
location = /connection/websocket/ {
	proxy_pass http://127.0.0.1:8100/connection/websocket;
	proxy_http_version 1.1;
	proxy_set_header Upgrade $http_upgrade;
	proxy_set_header Connection "upgrade";
	proxy_set_header Host $host;
	proxy_read_timeout 1h;
}
location /connection/ {
	proxy_pass http://127.0.0.1:8100;
	proxy_http_version 1.1;
	proxy_set_header Upgrade $http_upgrade;
	proxy_set_header Connection "upgrade";
	proxy_set_header Host $host;
	proxy_buffering off;
	proxy_read_timeout 1h;
}
```

Gilt für die ganze Domain, also für alle Projekte unter `/projects/…`. Apache
und die `.htaccess` der Projekte sehen diese Pfade nie.

**Prüfen** (von irgendwo):

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://staging.systopic.net/connection/websocket/
```

| Code | Bedeutung |
|---|---|
| 400 | richtig: der Hub antwortet, nur ohne WebSocket-Upgrade |
| 404 | die nginx-Regel greift nicht (Tippfehler, nicht gespeichert, Apache antwortet) |
| 502 | nginx erreicht den Hub nicht (Dienst aus, falscher Port) |

---

## D. Projekt einschalten (Subscription)

`httpdocs/projects/spaceticker.app/config/config.local.json` — wird nie
gesynct, darum stehen die Geheimnisse hier und nicht in
`STAGING.config.json`. Gibt es die Datei schon, nur den Block ergänzen:

```json
{
	"relay": {
		"api": "http://127.0.0.1:8101",
		"apiKey": "<apiKey aus B>",
		"secret": "<secret aus B>",
		"client": "/connection/"
	}
}
```

Der Kanal-Präfix wird `spaceticker.staging` (aus `PROJECT_NAMESPACE` und der
Stage) — ein LIVE auf demselben Hub hört davon nichts.

**Testen:**

1. `https://staging.systopic.net/projects/spaceticker.app/public/project/?id=…`
   öffnen, Konsole: `relay: connected` und `relay: channels` (auf STAGING ohne
   Debug-Tool erscheint `p()` in der Konsole).
2. Zweites Fenster, im CMS das Projekt umbenennen und speichern: im ersten
   `relay: message` auf `…/mod_projects`, dann `relay: refresh`, der Titel
   wechselt.
3. Optional, mit dem Token aus dem Quelltext der Seite (`"token":"…"`):
   `centrifugo checktoken -c /etc/centrifugo/config.json <token>` →
   `valid token for user spaceticker.staging:<id>`.

**Wenn nichts kommt:**

| Symptom | Ursache |
|---|---|
| kein `relay:` in der Konsole | `relay`-Block fehlt oder ist kein gültiges JSON; `relay.js` nicht geladen (Systemcheck: Paket da?) |
| `relay: hub error` / `disconnected` | C prüfen (404/502); `allowed_origins` passt nicht zur Domain |
| verbunden, aber keine `message` beim Speichern | `api`/`apiKey` falsch — PHP schreibt `systopic/relay: …` ins PHP-Fehlerlog der Domain |
| Token abgelehnt | `secret` ≠ `hmac_secret_key` des Hubs |

---

## Aktualisieren

- **Code** (`relay`, `db`, `system`): syncen wie immer. Composer nur, wenn sich
  eine `composer.json` geändert hat.
- **Hub:** das Skript aus B noch einmal aufrufen (optional `--version=…`), es
  tauscht das Binary und startet neu. Die Browser verbinden sich von selbst neu.
  Vorher den Changelog auf Änderungen der Config prüfen
  (`centrifugo checkconfig`).
- **Geheimnis wechseln:** neues `hmac_secret_key`, das alte als
  `hmac_previous_secret_key` mit `hmac_previous_secret_key_valid_until`
  daneben, Hub neu starten, dann die `config.local.json` der Projekte
  anpassen. Offene Seiten bleiben verbunden, bis das alte abläuft.

## Zurücknehmen

- Ein Projekt: `relay`-Block aus der `config.local.json` entfernen. Das Paket
  bleibt installiert und tut nichts.
- Der Hub: `install.sh --uninstall` (siehe B), nginx-Direktiven löschen.

## LIVE

Wie STAGING, mit `allowed_origins` aller Projekt-Domains auf dem Server und
der nginx-Regel je Domain. Offen bis dahin (Plan): Ursprungs-Tab,
Token-Erneuerung über einen Endpunkt.
