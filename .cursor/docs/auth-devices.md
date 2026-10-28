# Login, Geräte und Einmal-Codes

Seit `systopic/system` 1.3 (2026-10). Entstehungsgeschichte, Befunde und
Entscheidungen: [devices-auth.plan.md](../plans/devices-auth.plan.md).

## Begriffe

| Begriff | Was es ist |
|---|---|
| **User** (Konto) | Eine Zeile in `usm_users`. `user_type` ist `person` oder `screen`. Rechte kommen immer über Rollen. |
| **Screen** | Ein Konto ohne Menschen (Kiosk, Lobby). Hat weder Passwort noch Login-Formular und wird gekoppelt. |
| **Gerät** | Eine Zeile in `usm_devices`: ein Browser, eine installierte Web-App oder ein Screen, auf dem ein User angemeldet ist. Ein User kann viele haben. |
| **Session** | Die PHP-Session, kurz. Ist sie weg, meldet das Gerät still wieder an. |
| **Einmal-Code** | 8 Zeichen (`K7Q4-M2XD`), gilt einmal, in `usm_logincodes` nur als HMAC. |

## Wie ein Gerät angemeldet bleibt

- Das Cookie `__Host-sys_device` (über http: `sys_device`) enthält
  `<device id>.<secret>`. In der DB liegt nur der sha256 des Secrets.
  Liegt das Projekt nicht im Host-Root (DEV: `/projects/<name>/public/`),
  hängt `_<hash>` am Namen (`http::projectCookieKey()`, siehe
  [setup.md](setup.md#recovery-modus-seit-2026-10-22)) — die Session ebenso.
- `Auth\Devices::restore()` läuft in `afterConnect`, also vor dem Panel-Baum:
  - Ohne Session meldet das Cookie seinen User an.
  - Mit Session wird das Gerät alle 60 s geprüft. Ist es gesperrt oder
    abgelaufen, endet die Session.
  - **Mit relay sofort:** `Devices::revoke()`/`revokeAllOf()` schicken
    `{"op": "logout"}` in den Gerätekanal (`Relay::deviceTopic()`,
    Hub-Namespace `device`). Jede offene Seite des Geräts (App, CMS, Screen)
    fragt dann mit `X-Session-Check` nach (erzwingt die Prüfung) und lädt neu:
    Anmeldung. Ohne relay bleibt es bei den 60 s bzw. dem nächsten Request.
- Jeder Login legt ein Gerät an, auch der im CMS (Passwort, Code, Passkey) -
  sonst stünde die Session in keiner Liste, und keine Sperre erreichte sie.
- Das Secret rotiert höchstens einmal am Tag. Das alte bleibt 60 s gültig
  (parallele Requests). Kommt es danach wieder, war das Cookie kopiert →
  das Gerät wird gesperrt (`revoked_reason = reuse`).
- Laufzeit: Personen 180 Tage gleitend ab letzter Nutzung, Screens
  unbegrenzt (`auth.deviceDays`, `auth.screenDays`).
- `Session::logout()` sperrt **dieses** Gerät. Hijack und Zurückwechseln im
  CMS übergeben `false`.
- Ob ein Gerät eine installierte Web-App ist, meldet das Seitenskript über
  das Cookie `sys_display=app|browser` (spaceticker: `app.js`).

## Einmal-Codes

| Zweck | Wer erzeugt | Gilt | Eingelöst |
|---|---|---|---|
| `invite` | Benutzer-Admin im CMS (Mail oder Code/QR vorzeigen) | 24 h | in der App, Konto wird aktiv |
| `recover` | Benutzer-Admin oder „Code anfordern“ per Mail | 10 min | in der App |
| `pair` | „Gerät hinzufügen“ auf einem angemeldeten Gerät | 10 min | in der App des neuen Geräts |
| `pair_screen` | der Screen selbst (Login-Seite oder `auth/screen/`) | 10 min | ein Benutzer-Admin bestätigt ihn im CMS oder in der App (Code oder QR), der Screen fragt alle 3 s nach |

- **Eingabe immer in der App.** Ein Link (`auth/?code=`) oder QR-Code füllt
  das Feld nur vor. Grund: Eine installierte Web-App teilt auf iOS keine
  Cookies mit Safari, ein Login dort würde die App nicht anmelden.
- Gespeichert wird der HMAC-SHA256 mit `auth.secret`. Codes werden über
  ihn gefunden, deshalb zählt nur die IP-Drossel: 10 Fehlversuche in
  15 min → 15 min Sperre (`usm_authfailures`).
- Code-Mails (`Auth\CodeMail`, Template eines Projekts unter
  `panels/auth/emailCode.tpl.php`) landen ohne Inhalt in `var/log/email.log`.

## Rechte

Benutzer anlegen, Codes für andere erzeugen, Geräte anderer sperren und
Screens koppeln darf, wer die Rolle `userAdmin` hat. Superuser zählen
immer mit (`Session::hasRole()`).

## Konfiguration

Siehe [deployment.md](deployment.md) §5: `mail` und `auth.secret` je Server,
nie in Git oder im Sync. Ohne `auth.secret` ist das CMS wie vor 1.3
(Aktivierungs-Mail), damit Projekte, deren Login keine Codes kennt,
unverändert bleiben.

**Auch jeder DEV-Platz braucht ein eigenes Secret**, in
`config/config.local.json` des Projekts: `{"auth": {"secret": "<64 hex>"}}`
(erzeugen mit `php -r "echo bin2hex(random_bytes(32));"`). Die Datei ist in
git und im Sync ignoriert, kommt also auf keinem anderen Platz an.

Seit `909e013` (Lars, 2026-10-27) geht es auch im CMS: Settings →
Environment → Sign-in key anlegen (`EnvironmentFile::createAuth()`). Der
Schlüssel landet dann aber in der **Instanzdatei**
(`config/DEV.<platz>.config.json`). Ob die in git liegt, hängt vom Projekt
ab (bei spaceticker ja). Dann wäre der Schlüssel im Repo. Ins Backup kommt
er nicht. Für DEV daher `config.local.json` nehmen. Sie überschreibt die Instanzdatei
(`Config::load()`, `LOCAL_INSTANCE_KEYS` enthält `auth`).

- *Symptom:* Auf DEV fehlen Code und QR des Screens auf der Login-Seite,
  Code-Login und „Code per E-Mail“ gehen nicht. Auf staging geht alles. Das
  sieht aus wie ein verlorener Merge (so geschehen am 2026-10-27).
- *Ursache:* `LoginCodes::available()` ist falsch, weil `auth.secret` fehlt.
  `screenPairing()` kehrt dann still zurück, ohne Fehler und ohne Log.
- *Lösung:* `config.local.json` wie oben anlegen. Prüfen per CLI:
  `cd public && php index.php @auth.json` mit `{"path": "/projects/<p>/public/auth/", "output": "html"}`.
  Im Ergebnis muss `appScreenCode` vorkommen.

## Anmeldung im CMS

Eine Karte (`src/Auth/login.card.tpl.php`), Englisch, ohne Sprachumschalter.
`AuthConfig::loginMethod()` entscheidet den ersten Schritt. Leer oder
`choose` zeigt die Methoden. Passwort bleibt immer erreichbar, „Use another
method“ auch.

| Methode | Was passiert |
|---|---|
| Passwort | wie bisher, Passwörter werden nicht neu gehasht |
| Passkey | Fingerabdruck, Gesicht oder Geräte-PIN. Die RP-ID ist nur der Host, jede Datenbank hat ihre eigenen Schlüssel |
| Code | acht Felder, oder die Kamera liest den QR. Ein Screen-QR (`?screen=`) führt auf das Profil und wartet auf Erlaubnis |
| Gerät hinzufügen | das angemeldete Profil zeigt einen Recover-QR (`cms/auth/?code=…&auto=1`). Darunter steht der Code, zehn Minuten |
| Screen | der Screen zeigt seinen QR. Ein angemeldetes Gerät erlaubt ihn. Sehen allein meldet niemanden an |

Die Kamera startet aus dem Klick (`getUserMedia({video:true, audio:false})`
in demselben Turn). Auf dem Handy öffnet „Add device“ die Kamera gleich,
der Desktop bleibt beim QR. Eine gespeicherte Sperre (Chrome „Block“,
Firefox mit „Remember“) fragt nicht noch einmal: der Knopf sagt das per
Alert. Solange der Browser noch fragen darf, fragt der Knopf erneut. Ein
Neuladen holt eine gespeicherte Sperre nicht zurück.

`Devices::issue()` hängt einen neuen Browser als neue Zeile an. Derselbe
Browser mit gültigem Cookie aktualisiert seine Zeile (Name, den der Nutzer
vergeben hat, bleibt). Eine Zeile nennt das erste Koppeln: Datum, Methode
und wer es ausgelöst hat (`LoginLog::paired()`, aus `var/log/login.log`).
`Presence` färbt live, untätig oder offline, solange das Relay verbunden ist.
Abmelden eines Geräts schickt `logout` über das Relay; die offene Seite
prüft die Session und lädt neu.

Ein verbrauchter Code schließt die Lightbox (`X-Code-Live`). Die Auth-Seite
und die erste CMS-Seite nach einem neuen Gerät lassen das Web-App-Manifest
weg (`sys_plain`, zehn Minuten), damit das Handy nicht sofort „CMS
installieren“ fragt.

## Ein Projekt anbinden

Die eigene Auth eines Projekts entfällt. Nicht angemeldet geht es nach
`cms/auth/`, die Rückkehr merkt sich `Session::rememberReturn()`.
spaceticker hat `panels/auth/` deshalb nicht mehr.

Was spaceticker dafür tut:
- Nach einem Passwort-Login `Devices::issue($user)`.
- Code-Login: `LoginCodes::redeem($code, [INVITE, RECOVER, PAIR])`, dann
  `Session::login()` und `Devices::issue()`. Bei `invite` außerdem
  `Users\Operator::joined()`.
- „Code anfordern“: `LoginCodes::create(RECOVER, …)` und `CodeMail::send()`.
- Eine Seite „Meine Geräte“ (`Devices::revoke()`, `revokeAllOf()`,
  `LoginCodes::create(PAIR, …)`).
- Eine Screen-Seite: `LoginCodes::startScreenPairing()` und
  `pollScreen()`, Poll-Secret im Cookie. spaceticker zeigt den Code auch auf
  der Login-Seite (ein abgemeldeter Screen braucht nach dem Neustart keine
  Tastatur) und koppelt in „Meine Geräte“ → „Screen koppeln“ mit
  `LoginCodes::approveScreen()`. Der QR des Screens öffnet dort
  `devices/?screen=<code>`, auf iOS liest der Scanner der App ihn
  (Kamera-App öffnet Safari mit eigenen Cookies).

## Prüfen

- `php .cursor/tools/devices-check.php unit`: die Regeln, ohne HTTP.
- `php .cursor/tools/devices-check.php http`: 15 Szenarien gegen
  spaceticker auf DEV (Cookies je Projekt: `__Host-sys_device_<key>`,
  `SID<key>`, Schlüssel = `http::projectCookieKey()`). Mails liest der Test aus Mailpit; eines der
  Szenarien wartet 61 s.
