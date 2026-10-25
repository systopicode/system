# Passkeys und Sicherheitsmeldungen

## Installation und URLs

`Auth\Passkeys` verwendet die vorhandenen Scopes, Queries, Session, LoginLog
und `lbuchs/webauthn:^2.2`. Die Abhängigkeit wird über den normalen
Composer-/Mirror-Workflow installiert; ein Quelltext-Symlink ersetzt kein
Composer-Update im Projekt.

Keine zusätzliche Konfiguration pro Installation: Origin ist standardmäßig
`http::$hostUrl` aus dem bestehenden HTTP-Bootstrap, RP-ID der Domainname.
`Origin` und Forwarded-Header dienen niemals als Konfigurationsquelle.
Virtual-Host- und Instanzregeln bestimmen weiterhin die zuständige Installation.

Passkeys benötigen eine Domain und vertrauenswürdiges HTTPS. IP-Adressen
funktionieren auch über HTTPS nicht; Passwortlogin bleibt dort verfügbar.
`http://localhost:PORT` ist für lokale Tests möglich. Ein optionaler fester
Override ist weiterhin erlaubt:

```json
{"auth":{"passkeys":{"origin":"https://cms.example.org"}}}
```

Optional: `rpId` für besondere Deployments, `name` als Projektlabel. Jede
Origin und jeder Projektpfad erhält eine eigene Credential-Bindung. Der
Passwortmanager kann bei gemeinsamem DEV-Host trotzdem Einträge anderer
Projekte zeigen: WebAuthn unterscheidet Domains, keine Pfade. Serverprüfungen
weisen solche Einträge ab; Labels enthalten Projekt, Stage und Host.

## Datenbank und Upgrade

`Tables\Passkeys\Model` deklariert `usm_passkeys` im vorhandenen TableRow-/Col-
Schema. Fehlt die Tabelle, legt sie der Provisioner automatisch an. Gespeichert
werden Benutzer, Credential-ID, SHA-256-Digest (`passkey_credential_sha`),
öffentlicher Schlüssel, RP-ID, Projekt, zufälliger User-Handle, Verwaltungsname,
Signaturzähler und Zeitpunkte. Keine privaten Schlüssel oder Biometrie.

Bei einer älteren, bereits vorhandenen Passkey-Tabelle einmal explizit
`PasskeyUpgrade::run($connection)` ausführen. Das wiederholbare Update ergänzt
die fehlenden Felder, erweitert den Namen und macht alte Pflichtfelder optional.
Vorhandene Daten bleiben erhalten; ungebundene Legacy-Credentials werden niemals
blind einem Projekt zugeordnet. Kein Schema-Scan bei jeder Anmeldung.

## Login und Rechte

- Passkey ist die primäre Loginoption; Passwortlogin bleibt verfügbar.
- Ohne E-Mail startet eine discoverable Anfrage. Eine eingegebene E-Mail
  beschränkt die Allowlist auf Konto und Projekt; unbekannte Konten erhalten
  einen zufälligen Bezeichner.
- Conditional UI wird im bestehenden `onUpdateMain`-Hook einmalig aktiviert,
  wenn der Browser sie unterstützt. Das E-Mail-Feld hat `username webauthn`.
- Expliziter Login beendet Autofill zuerst. Schnelle Doppelklicks werden schon
  vor diesem asynchronen Schritt gesperrt. Abbruch/Fehler erlaubt einen neuen
  Versuch; Panelwechsel und Passwort-Submit beenden laufende Anfragen.
- Nach Passwortlogin kann ein Konto ohne Projekt-Passkey die Einrichtung
  wählen. Ein fünf Minuten gültiger Sessionnachweis ersetzt nur hier die
  erneute Passwortabfrage. Er ist an Benutzer und Projekt gebunden.
- „Continue without a passkey“ ist normale Navigation zur authentifizierten
  `continueWithoutPasskey`-Aktion, unabhängig von WebAuthn/JSON/Origin-Prüfungen.
  Abgelaufene Einrichtung verhindert den CMS-Zugang nicht; ohne Session wird
  die normale Anmeldung gezeigt. Der ursprüngliche sichere Rücksprung bleibt
  bis zum Abschluss erhalten.
- Im Profil sind Registrierung, Umbenennen und Entfernen an das eigene
  Passwort gebunden. Superuser entfernen fremde Passkeys mit ihrem eigenen
  Passwort; fremde Registrierung/Umbenennung bleibt gesperrt.
- Profil und User-Lightbox teilen dieselbe Komponente. Benutzerlöschung
  entfernt die zugehörigen Passkeys. Sensitive Felder bleiben bis zur manuellen
  Interaktion readonly, damit Autofill keine fremden Kontodaten überschreibt.

## Prüfung und Challenge-Lebensdauer

POST/JSON erfordert exakte Request-Origin und Session-CSRF-Token.
clientDataJSON muss dieselbe Origin haben; Cross-Origin-Ceremonies sind gesperrt.
Die Bibliothek prüft Challenge, RP-Hash, Signatur, Präsenz und User Verification.
Discovery erfordert zusätzlich den passenden User-Handle. Projekt, Kontostatus
und Credential-Zuordnung werden serverseitig geprüft.

Pro Session höchstens acht unabhängige, einmalige Ceremonies mit Kennung,
Zweck, Benutzer, Projekt und 120 Sekunden Ablaufzeit. Abbruch verwirft nur die
eigene Ceremony; Autofill erneuert vor Ablauf. AuthFailures drosselt Fehler.
Signaturzähler werden unter MySQL-Lock gelesen/geprüft/geschrieben; Null-Zähler
synchronisierter Passkeys sind zulässig. Erfolgreicher Login verwendet
`Session::login()` und den vorhandenen LoginLog.

## Oberfläche und Passwortmanager

Bestehende `widget`-, `button`-, Formular- und `cmslightbox`-Klassen bleiben die
Basis. Während des Logins ersetzt die biometrische Animation das Formular;
Meldungen stehen im regulären oberen `message::flush([], true)`-Container.
Fehler/Abbruch stellt die Loginoptionen wieder her. Das Profil verwendet den
vorhandenen globalen Message-Container, der Setup-Dialog seinen lokalen.

`fingerprint.tpl.php` ist die bewusste Ausnahme zu Font Awesome: das gelieferte
Regular-SVG. Dieselbe Geometrie in `passkey-icons.tpl.php` ist in helle und dunkle
Liniengruppen aufgeteilt. Fingerprint, Face und Schloss wechseln während der
Einrichtung; erst nach Serverbestätigung bleibt das Schloss stehen. Farben
folgen `cmsMainColor`/`cms-main`, reduzierte Bewegung wird berücksichtigt.

Nach Widerruf meldet der Client die entfernte RP-/Credential-ID optional über
`PublicKeyCredential.signalUnknownCredential`. Fehlende Unterstützung oder
Providerfehler ändern den erfolgreichen CMS-Widerruf nicht. Löschen/Ausblenden
und Synchronisierung liegen beim Manager. Ein einzelner lokaler Passkey-Name
kann darüber nicht umbenannt werden; der CMS-Verwaltungsname bleibt separat.

## Sicherheitsmails

`SecurityNotice` beobachtet erfolgreiche ORM-Commits. Passwortänderungen sowie
Hinzufügen/Entfernen/Umbenennen von Passkeys melden sich an die Kontoadresse;
E-Mail-Wechsel an alte und neue Adresse. Ein gemeinsames `SecurityNotice.tpl.php`
verwendet `Mail\Letter`, Projektlogo und Theme. Optionaler Projekt-Override:
`panels/auth/emailSecurity.tpl.php`. Die Mitteilung ersetzt keine Verifikation
der neuen Adresse.

Automatische Hash-Upgrades und normale Passkey-Anmeldungen senden nichts.
SMTP-Fehler werden ohne sensible Inhalte protokolliert und machen den Commit
nicht rückgängig. Der vorhandene Mailtransport bestimmt DEV-Catcher und
DEBUG-Empfängerumleitung; diese Einstellung gilt auch für Sicherheitsmails.

## Tests

Nur isolierte MySQL-Datenbanken mit Präfix `fluxo_test_` verwenden:

```sh
FLUXO_TEST_DSN='mysql:unix_socket=/tmp/test/mysql.sock;dbname=fluxo_test_passkeys' \
FLUXO_WEBAUTHN_AUTOLOAD='/path/to/project/vendor/autoload.php' \
FLUXO_TEST_HTML=/tmp/passkeys.html \
FLUXO_TEST_LOGIN_HTML=/tmp/passkey-login.html \
FLUXO_TEST_ONBOARDING_HTML=/tmp/passkey-onboarding.html \
php system/tests/passkeys.php

FLUXO_TEST_HTML=/tmp/passkeys.html \
FLUXO_TEST_LOGIN_HTML=/tmp/passkey-login.html \
FLUXO_TEST_ONBOARDING_HTML=/tmp/passkey-onboarding.html \
FLUXO_DOM_MODULE=/path/to/happy-dom/lib/index.js \
node system/tests/passkeys-ui.mjs

FLUXO_TEST_DSN='mysql:unix_socket=/tmp/test/mysql.sock;dbname=fluxo_test_passkey_upgrade' \
php system/tests/passkeys-upgrade.php

php system/tests/messages.php
php system/tests/security-notice.php
```

Optional: `FLUXO_TEST_USER`, `FLUXO_TEST_PASSWORD`. Happy DOM ist ausschließlich
Testhilfe. Tests verwenden echte EC-Signaturen und prüfen unter anderem Replay,
UV, Projekt-/Kontobindung, Zähler, Admin-Rechte, parallele Tabs, Wiederholungen,
IP-Zugriff, abgelaufene Sessions, Überspringen, Legacy-Upgrade und Message-Ort.
Mailtests verwenden ein SMTP-Testdouble und versenden keine echten Mails.

## Referenzen

- https://github.com/lbuchs/WebAuthn
- https://web.dev/articles/passkey-form-autofill
- https://github.com/w3c/webauthn/blob/main/explainers/signal-api.md
- https://www.passkeycentral.org/design-user-journeys/required-patterns/sign-in-with-a-passkey
