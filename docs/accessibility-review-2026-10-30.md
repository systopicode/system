# GUI- und Barrierefreiheitsprüfung · 30.10.2026

Umfang: ausschließlich `systopicode/system` und `systopicode/cms`. Keine projektspezifischen Panels oder Frontend-Templates wurden geändert. Keine Commits oder Pushes.

## Erledigte Punkte und tatsächliche Prüfung

| Anforderung | Ergebnis | Nachweis |
|---|---|---|
| JSON-LD-Erzeugung ansehen | PASS | `cms/lib/class/app/site/appSite.php` erzeugt dynamisch WebPage, Sprache, URL, Beschreibung, Bild und Datumsangaben sowie gegebenenfalls BreadcrumbList. Der optionale Ergänzungsparser wurde mit Objekt, Liste, Graph, ungültigen und leeren Werten getestet. Kein neues Eingabefeld und keine Änderung der automatischen Erzeugung. |
| Multi-Upload-Übersicht in Lightbox | PASS | Reale Upload-, Dialog-, Icon- und Statusleisten-Komponenten im isolierten Browser: erfolgreich/fehlgeschlagen/ausstehend, Fehlergrund, explizites Retry, spätere Dateien bleiben nach Fehler ausstehend. Erfolgreiche Einträge werden übersprungen. Übersicht schließen und erneut öffnen; Details aus laufender Fortschrittsanzeige. Desktop 1440, iPad-Breite 768, schmal 390; zusätzlich echte Touch-Emulation mit Mehrfach-Datei-Input und Taps. |
| Wiederholung ohne doppelte abgeschlossene Uploads | PASS | Sitzungsbezogene Belege bestätigen erneut gesendete abgeschlossene Requests, gebunden an Datei und Ziel. Reale PHP-Aktionen wurden mit temporären Dateien auf wiederholte Blöcke, falsche Offsets und abgeschlossene Belege geprüft. Ein verlorener Abschluss/HTTP-Fehler wurde im Browser simuliert. Keine echte Projekt-Datenbank verändert. |
| Share-image-Reset | PASS | Originales Restore-Motiv statt Papierkorb; Klasse im laufenden CMS gelesen. Die Reset-Funktion selbst bleibt unverändert. |
| Meta-Herkunft und passende Bearbeitungslinks | PASS | Reale Templates mit gemischten Site-/globalen Vorgaben gerendert: Links zur Site beziehungsweise Page Settings. Das Share-Bild berücksichtigt den Theme-Override und führt gegebenenfalls zu Theme. Globale/Theme-Herkunft zusätzlich im laufenden CMS geprüft. |
| Site umbenennen | PASS | Unter Pages → Site/Globus → New domain. Tippen löst keine Umbenennung aus. Expliziter „Rename site…“-Button zeigt alten/neuen Namen und Folgen für Ordner, lokale Links und Host-Regeln. Abbrechen/Bestätigen im Browser mit abgefangenem Dispatcher geprüft. Keine reale Site umbenannt. |
| Site-Verwaltung auf Englisch | PASS | Neue Site, Default site, Site aliases, Rename, Folder-/Host-Aktionen und relevante Rückmeldungen. Technische Angaben in ausklappbaren Hinweisen. Das vorhandene Metadaten-JSON bleibt erhalten. |
| sitemap.xml / llms.txt | PASS | Beide Links verwenden das gemeinsame External-Link-Muster; rechtes Icon im laufenden CMS bestätigt. |
| Datenschutz / Impressum / Lizenzen | PASS | Externe Textlinks haben das gemeinsame Icon rechts. Fließtext bleibt Fließtext; lange Adressen können umbrechen. PHP-/Notice-Prüfungen bestanden; keine fremden Anbieter-Seiten für den Test aufgerufen. |
| Icon-only-Aktionen | PASS im geprüften Umfang | Gemeinsame Ergänzung übernimmt ausschließlich die vorhandene Aktionsbeschriftung in Tooltip/zugänglichen Namen, auch bei dynamisch hinzugefügten Elementen. Keine Namen aus Icon-Geometrie geraten. Explizite Close-Namen ergänzt. Statischer Scan der direkt im Template stehenden Icon-Aktionen findet keine weiteren Kandidaten ohne eigenen oder umgebenden Namen. Generiertes PHP/JS wird dadurch nicht vollständig erfasst. |
| Barrierefreiheits-Scan | PASS als Scan, offene Befunde unten | Live-DOM-/AX-Prüfung von Pages/SEO, Site-Anlage, Backup, Theme, Personenliste und Tools/Cleanup. Automatischer axe-core-Scan der neuen Upload-Lightbox: 4.14.0, WCAG 2 A/AA und 2.1 AA, keine gemeldeten Verstöße. Axe wurde nur temporär als Prüfwerkzeug geladen und nicht als Laufzeit-Abhängigkeit hinzugefügt. |

## Zusätzlich behobene Befunde

- Aufklapp-Icons des Seitenbaums haben einen eindeutigen Namen, Tooltip, Button-Rolle, `aria-expanded` und Tab-Zugang. Enter öffnet, Leertaste schließt. Live geprüft und ursprünglichen Faltzustand wiederhergestellt. `data-focus-id` und eine auf die Tastaturaktion begrenzte Rückführung erhalten den Fokus nach AJAX. Ein zwischenzeitlich bewusst anders gesetzter Fokus wird nicht überschrieben; beides im Regressionstest geprüft.
- Meta-Felder, Seitenadressen, Add-tag-Felder, CSS-Editoren und Suchfelder in Benutzer-/Log-Ansichten haben explizite Namen.
- Der neue Upload-Dialog hat auch auf mobilen Layouts einen sichtbaren Schließen-Button; zusätzlich wird die bestehende mobile Schließen-Konvention unterstützt.
- Meta-Hilfetexte waren im geprüften hellen Theme bei ca. 4,34:1. Für den Meta-Dialog wurde der Sekundärtext angepasst; danach live ca. 8,13:1 gemessen. Das ist keine Aussage über alle Farben aller Themes.
- Fortschritts-Details funktionieren auch, wenn die gemeinsame Dialogverwaltung die Statusleiste verschiebt oder wiederherstellt.

## Noch offene Befunde und sinnvolle Folgeprüfungen

1. **Tabs vollständig per Tastatur bedienen.** Sidebar- und Link-Dialog-Tabs verwenden `role="tab"`, aber noch kein durchgängiges Muster aus Pfeiltastensteuerung, `aria-controls`, zugehörigen Tabpanels und gesteuertem Tabindex. Quellen: `cms/panels/cms/010_pages/sidebar/main.tpl.php`, `cms/panels/cms/010_pages/editor/editLink/lightbox.tpl.php`.
2. **Ältere mobile Dialoge: sichtbaren Schließen-Zugang vereinheitlichen.** Die gemeinsame responsive CSS blendet X-Aktionen aus und setzt auf den Griff beziehungsweise Escape. In der neuen Upload-Übersicht bleibt der Close-Button sichtbar. Für die übrigen Dialoge sollte dieselbe erreichbare Alternative geprüft werden. Quelle: `system/public/css/x_responsive.css`.
3. **Aktions-Links ohne Zieladresse durch native Buttons ersetzen.** Ein zugänglicher Name allein liefert noch keine vollständige Button-Semantik. Beispiel: Delete in der Backup-Liste; die AX-Ausgabe zeigt die Aktion als Generic. Quelle: `system/panels/cms/040_backup/backup.tpl.php`. Die destruktive Aktion wurde nicht ausgelöst.
4. **Zeigerlose Alternativen für Sortierung systematisch prüfen.** Seitenbaum, Medien und Sprachreihenfolge benötigen einen separaten Test der kompletten Aufgabenfolge ohne Drag-and-drop. Dieser Scan ist kein Nachweis, dass alle Sortieraufgaben bereits per Tastatur möglich sind.
5. **Manuelle Screenreader- und Theme-Prüfung erweitern.** VoiceOver-/NVDA-Abläufe, Zoom, erzwungene Farben und alle Theme-Kontraste wurden nicht vollständig abgenommen. Die Live-Namensprüfung meldete in den geprüften Pages-/SEO-, Backup-, Theme-, Personen- und Tools-Ansichten zuletzt keine unbenannten sichtbaren Felder/Aktionen; das ist kein vollständiger WCAG-Konformitätsnachweis.

## Grenzen und Testhinweise

- Upload-Resultate und File-Objekte bleiben im aktuellen Dokument. Reload/Schließen des Tabs verwirft die Übersicht; sie ist kein dauerhafter Upload-Verlauf und keine Wiederaufnahme nach Browser-Absturz.
- Abschlussbelege gelten in derselben Serversitzung. Es gibt keine neue Datenbank-Spalte oder verteilte Exactly-once-Garantie bei einem Server-Absturz mitten im Speichern.
- Produktive Uploads, Löschungen oder Umbenennungen wurden nicht zu Testzwecken auf Projektdaten ausgeführt. Die funktionalen Fehler- und Rename-Tests verwenden isolierte Fixtures.
- Gemeinsame Icon-, Info-Dialog-, Upload-Policy-, Fortschritts- und Notice-Prüfungen bestanden. Der gemeinsame Feedback-Test hatte initial einen fehlgeschlagenen Durchlauf („saving A must not clear B“); diagnostischer Lauf und anschließender unveränderter Wiederholungslauf bestanden. Keine Änderung am Save-/Feedback-Code für diesen Punkt.
- Das relevante lokale PHP-Fehlerlog wurde geprüft: letzte Änderung am 20.10.2026, keine neuen Einträge aus dieser Prüfung.
- Während der Live-Prüfung gab es bereits bestehende Warnungen zur Panel-Vererbung (`duplicates`, `tags`). Diese wurden nicht durch Projektänderungen umgangen.

## Reproduzierbare Prüfungen

Im CMS-Verzeichnis mit PHP 8.4+:

```sh
php tests/json-ld-parser.php
php tests/media-upload-receipts.php
php tests/info-dialogs.php
node tests/media-upload-client.mjs
node tests/block2-upload-progress.mjs
FLUXO_PLAYWRIGHT_MODULE=/path/to/playwright/index.mjs \
FLUXO_PHP=/path/to/php \
FLUXO_AXE_PATH=/path/to/axe.min.js \
node tests/gui-followup-ui.mjs
```

`FLUXO_AXE_PATH` ist optional; ohne das temporäre Prüfwerkzeug läuft der funktionale GUI-Test weiter. Screenshots und Scan-Ausgaben landen im temporären Ordner `fluxo-gui-followup`, optional konfigurierbar über `FLUXO_GUI_ARTIFACTS`.
