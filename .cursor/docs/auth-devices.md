# Login, Geräte und Einmal-Codes

Seit `systopic/system` 1.3 (2026-10). Entstehungsgeschichte, Befunde und
Entscheidungen: [devices-auth.plan.md](../plans/devices-auth.plan.md).
Wer wen wo anmelden darf, mit allen Use Cases und Befunden:
[auth-workflows.md](auth-workflows.md).

## Begriffe

| Begriff | Was es ist |
|---|---|
| **User** (Konto) | Eine Zeile in `usm_users`. `user_type` ist `person` oder `screen`. Rechte kommen immer über Rollen. |
| **Screen** | Ein Konto ohne Menschen (Kiosk, Lobby). Hat weder Passwort noch Login-Formular und wird gekoppelt. |
| **Gerät** | Eine Zeile in `usm_devices`: ein Browser, eine installierte Web-App oder ein Screen, auf dem ein User angemeldet ist. Ein User kann viele haben. |
| **Session** | Die PHP-Session, kurz. Ist sie weg, meldet das Gerät still wieder an. |
| **Einmal-Code** | 8 Zeichen (`K7Q4-M2XD`), gilt einmal, in `usm_signincodes` nur als HMAC. |

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

Ein Code hat keinen Zweck. Es gibt zwei Arten (`Auth\SignInCodes`), wer welche
wo erzeugt, steht in [auth-workflows.md](auth-workflows.md):

| Art | Entsteht | Gilt | Eingelöst |
|---|---|---|---|
| Code | auf einem angemeldeten Gerät, für genau ein Konto | 10 min, Einladung 24 h | auf dem neuen Gerät, das dieses Konto wird |
| Gerätecode | auf dem neuen Gerät selbst (`Auth\DeviceCode`), noch ohne Konto | 2 min, erneuert sich | jemand benennt auf einem angemeldeten Gerät das Konto, das Gerät fragt alle 3 s nach |

- **Eingabe immer in der App.** Ein Link (`auth/?code=`) oder QR-Code füllt
  das Feld nur vor. Grund: Eine installierte Web-App teilt auf iOS keine
  Cookies mit Safari, ein Login dort würde die App nicht anmelden.
- Gespeichert wird der HMAC-SHA256 mit `auth.secret`. Codes werden über
  ihn gefunden, deshalb zählt nur die IP-Drossel: 10 Fehlversuche in
  15 min → 15 min Sperre (`usm_authfailures`).
- Die Einladungs-Mail (`Auth\CodeMail`, Template eines Projekts unter
  `panels/auth/emailCode.tpl.php`) landet ohne Inhalt in `var/log/email.log`.

## Rechte

Wer wen anmelden darf, sagen die Rechte `authPersons` und `authScreens` der
Controlmap, siehe [auth-workflows.md](auth-workflows.md#rechte). Benutzer anlegen
und Geräte anderer sperren darf, wer die Rolle `cmsAdmin` hat. Superuser zählen
immer mit (`Session::hasRole()`).

## Konfiguration

Das `auth.secret` entsteht von selbst, sobald es gebraucht wird
(`AuthConfig::secret()`), in der Datei der laufenden Instanz
(`EnvironmentFile::createAuth()`, unter Sperre, zwei Anfragen zugleich lesen
denselben Schlüssel zurück). Ist die Datei nicht schreibbar, gibt es keine Codes.
Die Benutzerverwaltung sagt das dann. Passwörter, Passkeys und Geräte gehen
weiter.

`mail` je Server: [deployment.md](deployment.md) §5. Unter Settings → Environment
lässt sich der Schlüssel ersetzen. Codes, die gerade unterwegs sind, gelten dann
nicht mehr.

## Anmeldung im CMS

Eine Karte (`src/Auth/login.card.tpl.php`), Englisch, ohne Sprachumschalter.
`AuthConfig::signInMethod()` entscheidet den ersten Schritt. Leer oder
`choose` zeigt die Methoden. Passwort bleibt immer erreichbar, „Use another
method“ auch.

| Methode | Was passiert |
|---|---|
| Passwort | wie bisher, Passwörter werden nicht neu gehasht |
| Passkey | Fingerabdruck, Gesicht oder Geräte-PIN. Die RP-ID ist nur der Host, jede Datenbank hat ihre eigenen Schlüssel |
| Code | acht Felder, oder die Kamera liest den QR. Das Konto steht im Code, niemand wählt |
| Gerätecode | die Karte zeigt Code und QR (`admin/user/?deviceCode=`). Ein angemeldetes Gerät sagt, wer dieses Gerät ist. Sehen allein meldet niemanden an |

Die Kamera startet aus dem Klick (`getUserMedia({video:true, audio:false})`
in demselben Turn). Auf dem Handy öffnet „Scan code“ im Profil die
Kamera gleich, der Desktop bleibt beim Eingabefeld. Eine gespeicherte Sperre (Chrome „Block“,
Firefox mit „Remember“) fragt nicht noch einmal: der Knopf sagt das per
Alert. Solange der Browser noch fragen darf, fragt der Knopf erneut. Ein
Neuladen holt eine gespeicherte Sperre nicht zurück.

`Devices::issue()` hängt einen neuen Browser als neue Zeile an. Derselbe
Browser mit gültigem Cookie aktualisiert seine Zeile (Name, den der Nutzer
vergeben hat, bleibt). Eine Zeile nennt das erste Koppeln: Datum, Methode
und wer es ausgelöst hat (`SignInLog::paired()`, aus `var/log/signin.log`).
`Presence` färbt live, untätig oder offline, solange das Relay verbunden ist.
Abmelden eines Geräts schickt `logout` über das Relay; die offene Seite
prüft die Session und lädt neu.

Ein verbrauchter Code schließt die Lightbox (`X-Code-Live`). Die Auth-Seite
und die erste CMS-Seite nach einem neuen Gerät lassen das Web-App-Manifest
weg (`sys_plain`, zehn Minuten), damit das Handy nicht sofort „CMS
installieren“ fragt.

## Ein Projekt anbinden

Die eigene Auth eines Projekts entfällt. Nicht angemeldet geht es nach
`auth/`, die Rückkehr merkt sich `Session::rememberReturn()`.
spaceticker hat `panels/auth/` deshalb nicht mehr.

Was spaceticker dafür tut:
- „Meine Geräte“ mit den beiden Wegen wie im Profil: `SignInCodes::offer()`,
  `SignInCodes::peekDeviceCode()`, `SignInCodes::approve()`, die Konten aus
  `SignInGrant::accounts()`.
- `install/` legt mit `SignInCodes::create()` den Code für den ersten Start der
  App an.
- Der Rest kommt von `auth/`: Passwort, Passkey, Code und Gerätecode.

## Prüfen

- `php .cursor/tools/auth-workflows-check.php`: die Workflows gegen spaceticker
  auf DEV, je Gerät eine Cookie-Datei, temporäre Konten `zzz-wf-*`.
- `php .cursor/tools/devices-check.php unit`: die Regeln, ohne HTTP.
- `php .cursor/tools/devices-check.php http` stammt aus der Zeit der eigenen
  App-Anmeldung, viele Szenarien schlagen fehl. Die Abläufe prüft
  `auth-workflows-check.php`, die Geräte-Szenarien sind noch nachzuziehen.

## Sicherheitsmail nach Code-Anmeldung

Nach erfolgreicher Anmeldung auf dem Zielgerät senden beide Code-Wege über `Auth\SecurityNotice` und die bestehende `emailSmtp`-/Letter-Infrastruktur eine sensible Benachrichtigung an die Mailadresse des angemeldeten Kontos. Die Nachricht nennt Zeitpunkt, Gerät und Autorisierenden (`approvedBy`, sonst `createdBy`). Erstellen, bloße Freigabe, fehlgeschlagene Einlösung und wiederholte Polls nach Verbrauch lösen hier keine Nachricht aus. Konten ohne gültige Mailadresse werden übersprungen; Versandfehler dürfen die erfolgte Anmeldung nicht zurücknehmen. Codes, Poll-Geheimnisse und Anmeldelinks werden nicht in die Nachricht aufgenommen.

Isolierte Prüfung: `php tests/sign-in-security-notice.php` und `php tests/security-notice.php`. Der echte Zwei-Geräte-/SMTP-Test ist noch offen, da dev.local am 2026-11-05 keine HTTPS-Verbindung annimmt.
