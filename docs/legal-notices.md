# Lizenz und System/CMS-Datenschutzhinweis

## Aktueller Stand — 29. Oktober 2026

- Systemweit: Login-/Sicherheitslog 365 Tage; technische E-Mail-, PHP- und Backup-Ausführungslogs 90 Tage. Dies sind Löschschwellen, keine garantierten Löschzeitpunkte oder gesetzlichen Mindestfristen. Ältere Abschnitte unten dokumentieren die Entwicklung.
- Vorschau löscht nichts. Automatik arbeitet bei Besuchen in begrenzten Schritten; CLI/Webcron sind optional. Keine automatische Löschung von Konten, aktiven Geräten, Inhalten, Uploads oder Backup-Archiven.
- Code-Mails verwenden den vorhandenen Sensitive-Modus ohne gespeicherten Inhalt. Der Benutzer-Tab zeigt Superusern Nachrichten an die aktuelle Empfängeradresse. HTML im globalen Mail-Log ist isoliert; Skripte und externe Ressourcen sind gesperrt.
- DiceBear-Seed bleibt für gleiche Avatare bestehen. Avatare/Flaggen unterdrücken den Referrer; das anonymisiert keine IP-Adressen. Der gemeinsame Video-Overlay benötigt im Editor einen Klick pro Ansicht, öffentlich gilt weiterhin die bestehende Anbieter-Freigabe.
- Offen: Font-Awesome-Weitergabevertrag (Anfrage durch Betreiber), installationsbezogene Betreiber-/Anbieterergänzung und Abnahmetest auf echtem Shared Hosting. Keine Aussage einer pauschalen DSGVO-Konformität.


Stand: 28. Oktober 2026.

## Lizenzentscheidung

Die systopic Software License bleibt maßgeblich. Öffentliche Repositories und Pakete gewähren keine allgemeinen Open-Source-Nutzungsrechte. Composer bezeichnet die Lizenz deshalb als `proprietary`; README, LICENSE und Login-Anzeige stimmen damit überein. Plattformrechte zum Lesen/Forken bleiben unberührt. Drittkomponenten behalten ihre eigenen Lizenzen.

Beide Repository-Historien enthalten frühere MIT-Lizenztexte (CMS `a6461ee`, System `464927b`). Bereits für solche Versionen gewährte Rechte werden durch die jetzige Vereinheitlichung nicht rückwirkend entzogen. Dieser Hinweis steht ausdrücklich auch in LICENSE. Es wurde nichts veröffentlicht und keine Git-Historie umgeschrieben.

## Datenschutzumfang

Der Hinweis beschreibt ausschließlich die Standardfunktionen von System/CMS, einschließlich externer Requests des Medieneditors. Er ist eine installationsübergreifende Basis. Betreiber, Hosting-/Mailanbieter, Beschäftigtenkontext, zusätzliche Integrationen und tatsächlich verwendete Drittlandgarantien müssen installationsbezogen ergänzt beziehungsweise geprüft werden. Öffentliche Website-Player und deren Einwilligungsverwaltung werden hier nicht dokumentiert oder geändert.

Aufgenommen sind Browser-Session, Gerätezugang und Cookie-Lebensdauer, Remote-Pairing, Anzeige-Cookies, Local/Session Storage, Sicherheitsmails, Bearbeiterzuordnung sowie direkte Requests an DiceBear, Flagcdn/Cloudflare, YouTube und Vimeo. Konfigurierbare Gerätefristen werden aus AuthConfig gerendert. Der Text behauptet weder eine Anonymisierung des E-Mail-MD5-Werts noch, dass IP-Adressen bei Browserabrufen verborgen bleiben. Einwilligungen oder Übermittlungsgarantien werden durch den Text nicht technisch hergestellt.

## Aufbewahrung

Nach ausdrücklicher Rückmeldung des Betreibers wurde die zunächst vorgeschlagene 180-Tage-Grenze zurückgenommen. LoginLog bleibt unverändert: keine neu eingeführte automatische Löschung und kein Aufräumkommando. Tests dieser Zwischenimplementierung und das Kommando wurden wieder entfernt. Es wurden keine produktiven Logdateien bereinigt.

Eine pauschale gesetzliche Höchstfrist für diese Logs wird nicht behauptet. Der Betreiber muss einen fortbestehenden Zweck dokumentieren, die Erforderlichkeit regelmäßig prüfen und nicht mehr erforderliche personenbezogene Daten löschen/anonymisieren. Längere Aufbewahrung wegen konkreter Sicherheitsvorfälle oder Rechtsansprüche braucht einen entsprechenden Zweck. Vorhandene 90-Tage-Bereinigung der separaten Session-Namensmarker wurde nicht geändert.

## Prüfung

- `php tests/legal-notices.php`: Rendering ohne Projektkonfiguration, Lizenzkonsistenz beider Pakete, tatsächliche Cookie-Laufzeit, Speicher-/Anbieter-/Videoangaben und URL-Präfixe geprüft.
- `tests/auth-mobile-ui.mjs`: zusätzlich die echten Privacy-/License-Templates auf mobiles Innenscrollen und horizontale Überläufe geprüft.
- Der bestehende `tests/security-notice.php` scheiterte in der vorhandenen Standalone-Umgebung bereits bei der ersten Mail-Erstellung; `tests/passkeys.php` kann ohne `vendor/autoload.php` nicht starten. Beide Tests wurden nicht verändert; daraus wird keine bestandene vollständige Auth-Abnahme abgeleitet.

## Quellen

- GitHub: https://docs.github.com/en/repositories/managing-your-repositorys-settings-and-features/customizing-your-repository/licensing-a-repository
- DSGVO Art. 5, 13 und 17: https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX%3A32016R0679
- TDDDG § 25: https://www.gesetze-im-internet.de/ttdsg/__25.html
- DiceBear: https://www.dicebear.com/legal/privacy-policy/
- Flagcdn: https://flagcdn.com/ und https://flagpedia.net/privacy-policy
- YouTube/Google: https://policies.google.com/privacy
- Vimeo: https://vimeo.com/privacy

## Betreiberregel für Aufbewahrung und Hinweise

Es wird keine fiktive allgemeine gesetzliche Tagesgrenze und keine automatische Login-Log-Löschung eingeführt. Ebenso wenig bedeutet das Fehlen einer festen Spezialfrist eine Erlaubnis zur unbegrenzten Speicherung. Maßgeblich sind Rechtsgrundlage, Zweck, Erforderlichkeit und dokumentierte Prüf-/Aufbewahrungsregeln der konkreten Installation.

An der Benutzerverwaltung unter **Sign-ins** steht nun direkt beim Protokoll, dass das Protokoll keine automatische zeitbasierte Löschung hat, regelmäßig geprüft werden muss und die vorhandene Funktion **Clean User Log** zu verwenden ist, wenn der Zweck entfällt. Unter **Trusted devices** wird der Unterschied zwischen Ablauf/Widerruf des Zugangs und Löschung des Datensatzes erklärt.

| Standarddaten | Bestehende Routine / Verhalten | Einordnung |
| --- | --- | --- |
| Verbrauchte/abgelaufene Einmalcodes | `LoginCodes::purge()` bei Erzeugen/Einlösen | Technischer Ablauf, keine behauptete gesetzliche 10-Minuten-/24-Stunden-Frist. Die Bereinigung läuft anlassbezogen, nicht per Hintergrunddienst. |
| Geräte-Cookies | Browserablauf, serverseitige Ablauf-/Widerrufsprüfung | Ablauf beendet die Berechtigung; er löscht nicht automatisch Gerätezeilen. |
| Temporäre Session-Namensmarker | Bestehende 90-Tage-Bereinigung bei Session-Erstellung | Technische Aufbewahrungsregel, keine gesetzlich vorgegebene Zahl; kein neuer Mechanismus. |
| Login-Protokolle | Vorhandenes manuelles Entfernen von Benutzerzeilen | Keine neue pauschale Automatik; der Betreiber muss eine konkrete Prüf-/Aufbewahrungsregel verantworten. |
| Kontodaten, Bearbeiterzuordnung, Backups | Konto-/Inhaltsverwaltung und Betreiberverfahren | Zweckabhängige Löschung und eventuelle gesetzliche Aufbewahrungspflichten müssen je Installation geklärt werden. Eine allgemeine sichere Höchstfrist lässt sich aus dem Paketcode nicht ableiten. |

Für eine künftig konkret festgelegte Frist gehört die Einstellung samt Zweck-/Rechtsgrundlagenhinweis zur jeweiligen Funktion; die zugehörige Wartungsroutine muss auch bei wenig Nutzung ausführbar sein. Benötigte Vorfalldokumentation ist gesondert und zugriffsbeschränkt aufzubewahren. Historische Sicherungskopien und Wiederherstellung sind Teil dieses Verfahrens. Nicht stillschweigend auf alle Daten dieselbe Frist anwenden.

## Präzisierung des Basistextes

Die Beschreibung nennt jetzt konkrete Kriterien für die Aufbewahrungsentscheidung (Erkennungs-/Untersuchungszeitraum, offener Vorfall, Datenumfang, mildere Mittel), notwendige installationsbezogene Prüfintervalle und zweckgebundene Einzelaufbewahrung bei konkreten Rechtsansprüchen. Sie behauptet keine allgemeine Erlaubnis zur Aufbewahrung bis zur längsten denkbaren Verjährung. Die 90-Tage-Markerregel wird korrekt als anlassbezogene Bereinigung beschrieben, nicht als garantierte physische Löschfrist.

Widerspruch, Widerruf, Datenherkunft, erforderliche Angaben, Drittlandgarantien und die Grenzen bloßer Datenschutzhinweise wurden konkretisiert. Der Text führt weder Einwilligungsblockaden noch neue Löschroutinen ein. Betreiberangaben und tatsächliche Aufbewahrungs-/Einwilligungsverfahren bleiben Voraussetzungen für einen einsatzfertigen installationsbezogenen Hinweis.

## Projektweite CMS-Ziele für rechtliche Angaben

Unter **Global Settings → System** stehen `CMS_IMPRINT_URL` und `CMS_PRIVACY_URL`. Sie werden im Block `legal` der projektweiten `config/global.settings.json` gespeichert, nicht in einer Instanzkonfiguration. Auch JSON-Projekte ohne diesen Block erhalten beide leeren Felder in der Oberfläche; beim Speichern werden sie ergänzt.

Zulässig sind absolute HTTP(S)-Adressen ohne Zugangsdaten oder Pfade mit einem führenden `/`. Leere oder ungültig manuell eingetragene Werte verwenden den Frontend-Fallback `http::$root + imprint/` beziehungsweise `privacy/`. Damit ersetzen diese Fallbacks die zuvor fest verdrahteten deutschen Pfade. Die CMS-Basisinformationen bleiben in den internen Legal-Ansichten erhalten; deren Verweise auf die Betreiberinformationen verwenden zentral die Projektziele. Die Felder wurden bei Spacewood (`spaceticker.app.src`) und `stefanpohlfilm.de` leer ergänzt.

Die rechtlichen Linkfelder stehen gemeinsam mit Default sign-in im Abschnitt **System**, unterhalb der Anmeldeeinstellung. Sichtbare Labels verwenden System statt CMS; die bestehenden JSON-Schlüssel bleiben kompatibel.

## Prüfung vom 29. Oktober 2026

Der Basis-Datenschutzhinweis ergänzt den konfigurierten WebSocket-Hub, projektbezogene Konto-/Verbindungskennungen und Live-Signale sowie die optionale Remote-Diagnose. Dokumentiert sind sysdebug_client (bis zu ein Jahr), Browserliste (30 Minuten Sichtbarkeit), anlassbezogene Registry-Bereinigung nach 24 Stunden Inaktivität und Diagnose-Sitzungsablauf nach zwei Minuten ohne Erneuerung. Debug kann bereits vor dem Login und außerhalb der Administration aktiv werden. Diese Beschreibung ersetzt keine erforderliche Einwilligung, Zweckprüfung oder technische Datensparsamkeit; die tatsächlichen Hosting-/Hub-Empfänger und Logfristen bleiben installationsbezogen zu ergänzen.

Der Betreiber bestätigt einen Font-Awesome-Pro-Account. Eine Berechtigung zur Veröffentlichung der Pro-Dateien im öffentlichen Repository ist damit nicht festgestellt; hierfür gelten die konkreten Vertragsbedingungen. Keine Änderung der Lizenzbehauptung oder Entfernung von Assets auf Basis dieser Information.

## Gemeinsame Bereinigung (29. Oktober 2026)

Die zuvor ausschließlich anlassbezogenen Regeln für Einmalcodes, 90-Tage-Session-Zuordnungsmarker und Relay-Diagnosedaten werden zusätzlich durch gemeinsame begrenzte Wartungsaufgaben ausgeführt. Standardauslöser sind normale GET-Requests; ohne Requests oder externen Scheduler gibt es weiterhin keine Ausführung. Superuser können Vorschau und Einzelbereinigung nutzen. Optionaler CLI-/Webcron-Aufruf ist kein automatisch installierter Cronjob. Die Privacy-Beschreibung wurde entsprechend angepasst. Neue pauschale Fristen für Login-, Mail-, PHP- oder Backup-Logs wurden nicht aktiviert. Technische Details und Grenzen: shared-hosting-maintenance.md.

## 365-Tage-Systemstandard und Lizenzübersicht

Der Betreiber hat am 29. Oktober 2026 365 Tage als festen Systemstandard ohne Projektkonfiguration für das Login-/Sicherheitslog gewählt, mit dokumentierter Zweckprüfung. `Maintenance\LogRetention::LOGIN_DAYS` ist die gemeinsame Quelle für Aufgabe, Privacy und UI-Hinweise. Es ist keine gesetzliche Mindestfrist; frühere Löschung bleibt erforderlich, wenn der Zweck früher entfällt. Die Aufgabe entfernt maximal 100 abgelaufene Zeilen pro Batch unter derselben Dateisperre wie der Log-Schreiber. Vorschau ist nicht schreibend. Zeitbudgetüberschreitung verändert die Quelldatei nicht; unlesbare, unbekannt formatierte und über 16 MiB große Logs erfordern beaufsichtigte Prüfung. Der Betreiber muss fehlgeschlagene/ausbleibende Läufe überwachen. Konkrete notwendige Vorfalldokumentation ist vor Ablauf getrennt und zugriffsbeschränkt zu behandeln, nicht durch pauschales Behalten des ganzen Logs. Anschließend wurden für E-Mail-, PHP- und Backup-Logs 90 Tage als Systemstandard bestätigt (siehe unten).

`legal/third-party/` enthält die lokal ausgelieferten Lizenztexte und Herkunft/Prüfsummen. Die Lizenzansicht zeigt jQuery, jsQR, WebAuthn, Inter, TCPDF (einschließlich GPL-/LGPL-Texten), optional Three.js und Centrifuge sowie separat lizenzierte systopic-Pakete. Composer-Abhängigkeiten behalten zusätzlich ihre eigenen mitgelieferten Quell-/Font-Hinweise. Ein Font-Awesome-Pro-Account ist bestätigt; konkrete Weitergaberechte im öffentlichen Repository und buildabhängige FFmpeg-Lizenz-/Quellpflichten sind damit nicht abschließend belegt. Die Ansicht gewährt hierfür keine neuen Rechte.

Die tatsächlichen Hosting-/Mail-/Hub-Anbieter und installationsbezogenen Garantien müssen weiterhin vom Betreiber geliefert werden; keine Namen oder Verträge wurden erfunden. Die Projekt-Privacy-Ziele bleiben über LegalLinks konfigurierbar.

Preview-Fehler: Das UI sendet normale Serveraktionen per PUT; die neue POST-only-Prüfung blockierte die Vorschau. Die autorisierte UI akzeptiert nun POST/PUT, verweigert weiterhin GET und zeigt Vorschauergebnisse als persistenten Block. Der maschinelle Webcron-Endpunkt bleibt ausschließlich HTTPS/POST mit Schlüssel.

## Standalone-Installation / SMTP und Font Awesome (29. Oktober 2026)

Jede Installation bringt ihre Pakete mit und verwendet den lokalen `mail`-Block der gewählten Environment-Konfiguration; `config.local.json` kann ihn überschreiben. Die Privacy-Basis beschreibt deshalb keine zentrale systopic-SaaS- oder SMTP-Infrastruktur. Tatsächliche Providerangaben bleiben installationsbezogen und gehören in die verlinkten Betreiberinformationen, nicht aus SMTP-Zugangsdaten erraten in den Pakettext. Zugangsdaten werden nicht öffentlich ausgegeben.

Beim Codeabgleich wurde die bestehende Debug-Ausnahme ausdrücklich dokumentiert: `emailSmtp::redirected()` leitet bei aktivem DEBUG und echtem SMTP Empfänger/Antwortadressen auf systopic-Testpostfächer um; ein lokaler DEV-Mailcatcher umgeht dies. Der SMTP-Code wurde nicht verändert und keine Mail versendet.

Der Betreiber bestätigt das größte Font-Awesome-Paket. Die am 29. Oktober 2026 geprüften öffentlichen Pro-/Pro+-Hinweise unter https://fontawesome.com/support unterscheiden Projekt-/Seat-Nutzung von öffentlicher Weitergabe der Pro-Dateien; größere Pakete/Add-ons gewähren nicht automatisch öffentliche Redistribution. Das Bündeln aller Abhängigkeiten für eine Standalone-Installation hebt fremde Lizenzbedingungen nicht auf. Im Repository getrackte Pro-CSS-/Font-Dateien wurden nicht entfernt, freigegeben oder veröffentlicht. Ein abweichender individuell vereinbarter Vertrag liegt nicht vor; der Hinweis behauptet dafür keine Sonderrechte.

## Technical log policy — 2026-10-29

User approved 90 days system-wide for email delivery logs (including logged bodies), PHP errors and backup execution logs. Login/security remains 365 days. Backup archives and legally required business correspondence are separate; this technical log is not the business archive. Unknown dates stop cleanup for administrator review. User E-Mail Log is superuser-only and matches exact current recipient email addresses, not sending-user attribution. Bodies are displayed as escaped text.

## External request minimisation — 2026-10-29

CMS portrait and flag images omit the Referer header. DiceBear email-derived MD5 seeds remain unchanged for cross-installation avatars; requests are not anonymous. CMS YouTube/Vimeo player embeds are now loaded only by an explicit per-view button, without persistent approval. The player retains strict-origin-when-cross-origin for provider compatibility. This change does not alter public website embed rules or establish the operator’s legal basis.

The CMS editor reuses the existing CMS EmbedConsent markup and embedConsent.js loader with scope=view. Public provider-wide stored consent remains unchanged and never auto-activates editor-view placeholders. The separate editor player renderer was removed.

## Minimale Betreiberkonfiguration — korrigierte Umsetzung

Global Settings → System bietet die beiden vorhandenen URL-Overrides sowie zwei einfache Textfelder: CMS_OPERATOR_DETAILS (Name/Firma, Anschrift, Kontakt) und CMS_DPO_DETAILS (Kontakt des Datenschutzbeauftragten, falls benannt). Kein Zusatzlink, keine separate Hosting-/Mail-/Live-Update-Konfiguration. Beide Textfelder werden als reine, escaped mehrzeilige Texte auf der System-Privacy ausgegeben; keine HTML-Ausführung. Keine Zugangsdaten eintragen.

Die zusätzliche Dashboard-Kachel wurde auf Wunsch entfernt. Die Angaben bleiben in Global Settings → System und auf der System-Privacy verfügbar. Ein leeres DPO-Feld ist zulässig, wenn kein Datenschutzbeauftragter benannt ist. Gespeicherte Angaben sind kein Nachweis rechtlicher Vollständigkeit. Bestehende URL-Overrides und Frontend-Fallbacks bleiben erhalten.

Angaben zum Verantwortlichen und gegebenenfalls zum Datenschutzbeauftragten müssen zugänglich sein; separate Pflichtfelder sind dafür nicht zwingend, wenn die verlinkten Informationen dies bereits abdecken. Erforderliche installationsbezogene Ergänzungen bleiben Teil der Betreiber-Privacy. Live-Updates laufen auf dem Hosting der Installation ohne zentrale systopic-Live-Verbindung; SMTP-Zugangsdaten bleiben in der Environment-Konfiguration.

## Dauerhafte Dashboard-Hinweise

Kacheln bleiben frei ausblendbar. Unabhängig von der Kachelanordnung zeigt home.notices.tpl.php ausschließlich Superusern nicht wegklickbare Hinweise auf Bereinigungsprobleme oder fehlende lokal eingetragene Betreiberangaben. Diese verschwinden, sobald die jeweilige Bedingung nicht mehr besteht. Betreiberhinweise fordern zum Prüfen auf; sie behaupten keinen Verstoß, wenn Angaben bereits auf den verlinkten Seiten stehen. Kein eigener Warnungszustand, keine neue Datenbanktabelle und keine zusätzliche Bestätigungsabfrage. Impressum/Privacy in der Kachel verwenden vorhandene transparente Buttons.
