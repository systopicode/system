# Lizenz und System/CMS-Datenschutzhinweis

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
