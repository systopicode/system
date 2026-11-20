# Editor-Präsenz der Pages: über relay statt Abfrage alle 2 Sekunden

Von: Oliver · Für: Lars · Status: offen (abgesprochen am 2026-11-17, umsetzen nach dem Bild-Editor) · 2026-11-17

**Nachtrag 2026-11-20 (Oliver):** Umgesetzt **ohne relay** — kein Timer, keine
eigene Abfrage. `pages.js` hängt per `sys.core.server.addHeaders` an jeden
Request `X-Editor-Client` / `X-Editor-Page`; `Panel::onLoadThisClass` →
`Panel::editorPresence()` antwortet mit `client::callFunction('cmsPresence', …)`.
Andere sehen es erst bei ihrem nächsten Request. `pagehide` schickt
`X-Editor-Leave` an `admin/pages/editorPresence` (die Action tut sonst nichts
mehr). TTL 30 min (`EditorPresence::TTL`). Die relay-Variante unten ist
damit nicht geplant. Zur Durchsicht für Lars: der JS-Block in `pages.js`.

## Anlass

Jede offene Pages-Ansicht fragt alle 2 Sekunden `admin/pages/editorPresence` ab
(`setInterval(check, 2000)` in `cms/panels/admin/010_pages/pages.js`). Jede
Abfrage ist ein PHP-Request, schreibt `var/tmp/page-editors.json` und macht 2–3
DB-Abfragen (Seite, Junctions für den Revisions-Hash, Namen der anderen) — auch
wenn niemand sonst auf der Seite ist. In der JS-Konsole läuft das sichtbar mit.

Die Funktion selbst bleibt: Hinweis „Also open: …“ und „This page was updated.
Reload …“, beratend, ohne Sperre (`Systopic\Cms\Pages\EditorPresence`).

## Abgesprochen

Die Präsenz ist eigentlich für relay gedacht.

**Mit relay** (`Systopic\Relay\Relay` aktiv, `relay`-Block in der Instanz-Config):

- Der Client meldet sich **ereignisgesteuert**: beim Öffnen der Seite, bei
  `focus` / `visibilitychange` (sichtbar), bei `blur` / verborgen und bei
  `pagehide` (verlassen). Kein Timer.
- Der Server veröffentlicht die Änderung auf dem Kanal der Seite
  (Knoten + Sprache); alle Clients dieser Seite bekommen „wer ist da“ und, nach
  einem Speichern, die neue Revision sofort.
- Das Speichern einer Seite veröffentlicht die neue Revision ohnehin —
  dort andocken, wo relay heute schon ein Formular neu rendert, wenn jemand
  speichert.

**Ohne relay:**

- **Keine Abfrage per Timer.** Präsenz und Revision reisen beim nächsten
  ohnehin fälligen Request mit (Antwort-Header oder `client::done`-Daten des
  Pages-Panels), der Client wertet sie dort aus.
- Melden (da / weg) geht weiter über einen Request bei focus/blur/pagehide,
  nur eben ohne Rückkanal zu den anderen; die sehen es bei ihrem nächsten
  Request.

## Betroffene Dateien

- `cms/panels/admin/010_pages/pages.js` — Block „Advisory only …“, `check()`,
  `setInterval(check, 2000)`, `pagehide`.
- `cms/panels/admin/010_pages/actions.php` — `case 'editorPresence'`.
- `cms/src/Pages/EditorPresence.php` — Datei `var/tmp/page-editors.json`.
- relay: `relay/src/Relay.php` (Kanäle, `publish`), `relay/src/relay.js`;
  Fokus-Hooks gibt es schon in `system/public/js/core/focus.js`.
