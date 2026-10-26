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

## Ein Projekt anbinden

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
