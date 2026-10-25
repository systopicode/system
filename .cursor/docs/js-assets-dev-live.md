# JS-Assets: DEV/STAGING vs LIVE

Stand nach Verschiebung der Projekt-Domain und Panel-JS aus `public/`.

## Zielstruktur

| Asset | Disk-Pfad | HTTP (DEV/STAGING) | HTTP (LIVE) |
|-------|-----------|--------------------|-------------|
| Domain-PHP | `{project}/lib/class/**/*.php` | nicht ausliefern (`.htaccess`) | nicht ausliefern |
| Domain-JS | `{project}/lib/class/**/*.js` | `{projectRoot}lib/class/…` | Bundle unter `public/js/dist/` |
| Panel-PHP | `{project}/panels/**/*.php` | nicht ausliefern | nicht ausliefern |
| Panel-JS | `{project}/panels/**/*.js` | `{projectRoot}panels/…` | Bundle unter `public/js/dist/` |
| System-JS | `{sys}/lib/class/**/*.js`, `{sys}/panels/**/*.js` | `{sysRoot}…` | Bundle oder sys-Paket |

Keine doppelte Spiegelung mehr (`public/panels/` / `public/lib/class/` entfallen).

## DEV / STAGING (aktuell)

1. Autoloader findet App-Klassen unter `fs::$projectRoot/lib/class/`.
2. `client::fileExists()` prüft Companion-JS dort.
3. `client::loadJS('app', 'lib/class/…/foo.js')` bzw. `panels/…/panel.js`.
4. `loader.js`: `root === 'app'` → `settings.projectRoot` (URL-Prefix eine Ebene über `public/`).
5. Cache-Busting: `PUBLIC_VERSIONAPPENDIX = '?' . time()`.

Voraussetzung: DOCUMENT_ROOT ist ein Parent (z.B. `/www`), sodass `/projects/…/lib/` und `/panels/` erreichbar sind. `.htaccess` in `lib/` und `panels/` erlaubt nur `.js`/`.css`.

---

## LIVE — Vorschläge

Auf LIVE ist DOCUMENT_ROOT typischerweise nur `public/`. Einzeldateien unter `lib/` und `panels/` sind dann **nicht** HTTP-erreichbar. Der alte `compressed.js`-Pfad in `fsLoader` und der CSS-Bündler `clientCompress` sind seit 2026-10-16 entfernt.

### Option A — Build-Pakete nach `public/js/dist/` (empfohlen als Basis)

**Idee:** CLI/Deploy erzeugt ein oder mehrere Bundles + Manifest.

```
public/js/dist/
  manifest.json          # id → { file, hash, classNames[] }
  core.js                # sys public/js + system lib/class companions
  app-classes.js         # project lib/class/**/*.js (Reihenfolge wie Preload)
  app-panels.js          # project panels/**/panel.js (Tree-Reihenfolge)
```

**Aktualisieren**

- Deploy-Hook oder `bin/build-js.php`: scannt dieselben Queues wie `client::loadJS` / `PanelNode::loadJS`, concateniert (optional minify), schreibt Hash in Manifest und `PUBLIC_VERSIONDATE`.
- Incremental: nur neu bauen, wenn mtime/hash der Quellen > Bundle.

**Debuggen**

- `?js=source` (nur Superuser) oder `STAGE`-Override: Loader fällt auf Einzeldateien zurück (DEV-Pfad), auch auf LIVE-Kopie mit geöffnetem Parent-Alias.
- Source-Map optional (`app-classes.js.map`).
- Manifest-Diff im Admin-Panel: welche `id`s im Bundle vs. was der Request queue’t.

**Loader-Anpassung**

```text
STAGE==LIVE && manifest vorhanden
  → loadJS ids auf Bundle-URL mappen (ein Script-Tag pro Paket, nicht pro Datei)
sonst
  → heutiges Einzeldatei-Verhalten (projectRoot / sysRoot)
```

### Option B — JS mit HTML-Diff / `updateDocument` mitsenden

**Idee:** AJAX-Response liefert nicht nur `updateDocument` + `loadJS[{src}]`, sondern bei Bedarf **Inline- oder Payload-Scripts**:

```json
{
  "command": "loadJS",
  "scriptFiles": [
    {
      "id": "app:lib/class/search/search.js",
      "root": "inline",
      "type": "class",
      "classNames": ["search"],
      "source": "/* contents of search.js */"
    }
  ]
}
```

`loader.js` / `updateDocument`-Pipeline:

1. Neue Scripts registrieren (Blob-URL oder `<script text/javascript>`).
2. Nach `load` → Klassen bauen (wie heute `onScriptsReady`).
3. Dann `panelTree.update()` / Layer-Hooks.
4. Unbenutzte Module: optional `deactivate` wenn Panel-Pfad den Class-Companion nicht mehr braucht (selten nötig; Idle-Unload eher Später).

**Vorteile**

- Kein Extra-HTTP-Roundtrip für erstmals benötigte Companions.
- LIVE braucht keine öffentlich gespiegelte Einzeldatei-Tree.
- Passt zur bestehenden Reihenfolge: `updateClientData` → `updateDocument` → `loadJS` → `onScriptsReady`.

**Nachteile / Risiken**

- Response-Größe bei vielen neuen Scripts.
- Caching schlechter als langlebige Bundle-URLs (außer Payload nur beim ersten Bedarf, danach `scripts.has(id)` skippt).
- CSP: Inline braucht Nonce oder Blob-URLs.
- Erstes Full-Page-Load braucht weiterhin Boot-Pakete (Option A) — Diff-Payload allein reicht nicht für Cold Start.

### Option C — Hybrid (empfohlene Zielarchitektur)

| Phase | Mechanismus |
|-------|-------------|
| Cold start (Head) | Option A: `core.js` + `app-classes.js` (Preloads) + aktives Panel-Paket |
| AJAX-Navigation | `loadJS` nur für **noch nicht geladene** ids; auf LIVE entweder Bundle-Chunk-URL oder Option-B-Inline für seltene Nachzügler |
| Debug | Superuser-Flag `js=source` → Einzeldateien / Source-Maps |

Das ersetzt `compressed.js` sauber: Manifest + explizite Pakete statt „Ordner zur Laufzeit zusammenschmeißen“.

---

## Konkrete nächste Schritte (Umsetzung später)

1. `client::flushResponse` / Head: LIVE-Zweig liest `public/js/dist/manifest.json`.
2. `bin/build-js.php` (Projekt) + optional System-Core-Build.
3. `loader.js`: `root: 'bundle' | 'inline'` neben `app`/`sys`.
4. Optional: Diff-Payload (`source`) nur wenn Datei nicht im Cold-Start-Bundle und noch nicht im `loader.scripts`-Map.
5. ~~Alten `FALSE && compressed.js`-Zweig in `fsLoader` entfernen~~ (erledigt 2026-10-16).
6. Deploy: Build vor Sync nach LIVE; `PUBLIC_VERSIONDATE` an Manifest-Hash koppeln.

## Debug-Checkliste LIVE

- [ ] Network: ein Bundle statt Dutzender 404 auf `/lib/class/…`
- [ ] `sys.loader.scripts` enthält erwartete ids nach Cold Start
- [ ] AJAX in Panel ohne Preload: genau ein Nachlade-Chunk / Inline, danach `panelTree.update`
- [ ] `?js=source` als Superuser: Einzeldateien, Breakpoints greifen
- [ ] CSP/Nonce ok bei Inline
