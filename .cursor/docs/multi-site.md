# Multi-Site — mehrere Domains aus einem Seitenbaum

Stand 2026-10-01. Pläne: `.cursor/plans/multi-site.plan.md`,
`.cursor/plans/multi-site-zuordnung.handover.md`. Konfiguration: `config.md`.

## Begriffe

- **Site** = eine Website, benannt nach ihrer Domain mit TLD
  (`beautiful-technology.de`). Zeile in `cms_sites` (`site_name`, `site_meta`
  als JSON, `site_node_id`) plus ein **Node vom Typ `site`** auf oberster Ebene
  des Seitenbaums. Ihre Seiten hängen unter diesem Node.
- **Default-Site** = die Site ohne Node (`site_node_id` NULL). Ihre Seiten sind
  die obersten Seiten (`node_parent_node_id` NULL, Typ `page`).
- **Site-Zuordnung eines Pfads** = `cms_rewritepaths.rewritepath_site_node_id`:
  der Site-Node über der Seite, NULL für die Default-Site. Wird **nie von Hand
  gesetzt**, sondern vom Baum kopiert (siehe unten). Dient der Eindeutigkeit
  pro Site und der Suche Host + Pfad → Seite.
- **Tree-Root** = die `RouteNode` ohne Record am Anfang jeder Route
  (Layer-Segment `root`). Ein **Site-Node** auf der Route ist ebenfalls eine
  `RouteNode` ohne Seite (`$siteNodeId`, Segment `site<id>`).

## Ablauf eines Requests

1. `index.php`: `Config::boot()` wählt die **Instanz** (config/).
2. `loader.php` nach dem DB-Connect: `SiteRegistry::boot()` lädt die Sites aus
   `cms_sites`, legt auf DEV fehlende Links an (`linkSites`) und wählt die
   **Site** über `host + http::$root` (Muster aus der Instanz-Datei).
3. `UrlResolver::resolve()`: Pfad → `cms_rewritepaths` **innerhalb der Site**
   (Site-Node-Id, NULL für Default). Nicht gefunden, aber in einer anderen
   Site vorhanden → **301** auf deren kanonische Adresse. Leerer Pfad ohne
   Treffer → bei einer Site die erste Seite unter ihrem Node, bei der
   Default-Site die erste oberste Seite.
4. `Route::build()`: Route `root → [site-Node] → Seiten`. `getSiteRoot()`
   ist der Site-Node der Route, sonst der Tree-Root. Body-Klasse
   `site-<slug>` (`site-beautiful-technology-de`).
5. `document.tpl.php` öffnet `head` und `body` per `withRenderHost()`: immer
   auf dem **Site-Root**, außer ein Seiten-Template auf dem Weg nach oben
   bringt die View selbst mit (Vollbild, Vorschau). Auf dem Site-Root gilt
   `sites/<domain>/templates/document/` vor `templates/document/`. In der View
   ist `$this` der Site-Root ohne Seite; `eachChild()` iteriert die Seiten der
   Site, Links zur Site-Wurzel sind `http::$root`.
6. Links: relativ innerhalb der Site, absolut mit der Adresse der Ziel-Site
   sonst (`NodeSite::linkRootOf()`/`hrefRootOf()`).

## Ordner

```
<project>/
  templates/
    document/     document, head, head.loader, body   — für alle Sites
    components/   navi, header, footernavi …           — für alle Sites
    pages/        Seiten-Templates (100_home …)        — für alle Sites
    layout/       grid, rows, columns — geplant, noch nicht registriert
    page.php      RouteNode-Klasse des Projekts
  sites/
    <domain>/
      readme.txt
      templates/{document,components,pages,layout}/   — nur für diese Site, je mit readme.txt
```

Reihenfolge (last wins): `<sys>/site/` → `templates/document/` →
`templates/components/` → `sites/<domain>/templates/{document,components}/`
(an die Site gebunden, `PageRootFolder::$site`). Seiten-Templates:
`sites/<domain>/templates/pages/` vor `templates/pages/`, gesucht nach dem
Template-**Namen** (`ViewResolver::templateFolder()`). `template::scanDir()`
kennt beide, damit das CMS auch site-eigene Templates zuweisen kann.
Projekte ohne `templates/pages/` laufen mit der alten Struktur (`pages/`).

## Site-Zuordnung der Pfade

- `rewritepath_site_node_id INT UNSIGNED NULL` (FK-Relation `siteNode` im
  Model), Index `(rewritepath_site_node_id, rewritepath_string(191))`.
  Eindeutigkeit pro Site erzwingt der Code (`RewritepathLookup`,
  `pageRewritepathFuncs`); ein UNIQUE über den vollen String ist mit InnoDB
  nicht möglich. „Alle Sites“ heißt im Code `RewritepathLookup::ANY_SITE` (-1),
  weil NULL die Default-Site ist.
- **Geschrieben** wird der Wert nur aus dem Baum (`NodeSite::siteNodeIdOf()`:
  nächster Vorfahre vom Typ `site`, sonst NULL):
  - beim Anlegen/Neubauen eines Pfads (`Rewritepaths::rebuildAuto/newCustom`,
    `pageRewritepathFuncs::createOrUpdate`),
  - beim Verschieben im CMS für den ganzen Ast (`NodeSite::rebuildBranch()`
    in `010_pages/actions.php`),
  - für alles auf einmal per Wartungs-Button **„Site-Zuordnung neu setzen“**
    unter Tools › Wartung › Rewrite (`NodeSite::rebuildAll()`). Der macht die
    Spalte auch nullable, falls sie noch `NOT NULL DEFAULT 0` ist, und ist
    wiederholbar.
- Übergang: Die Suchen lesen 0 wie NULL (`NULLIF(…, 0)`), bis der Button lief.

## Sites im Seiten-Panel (`panels/cms/010_pages/`)

- **Bearbeiten** im Hauptbereich statt der Seitenvorschau (`site.tpl.php`):
  Site-Knoten im Baum anklicken (`?id=<node>`), die Default-Site über den
  **Globus in der Baumleiste** (`?site=default`). Zeigt Knoten, Anzahl Seiten,
  Ordner und die Meta als JSON (gespeichert beim Verlassen des Felds, Aktion
  `updateSiteMeta`). Oben ein Link-Symbol, das die Site in neuem Fenster
  öffnet (`baseUrlFor()` der Site auf dieser Maschine). Darunter die
  **Alias-Domains**, eine pro Zeile (`updateSiteAliases`,
  `SiteRegistry::saveAliases()`): leiten mit 301 auf die Site um, siehe
  `config.md` (Sites). Unten:
  - **Umbenennen** (`renameSite`, `SiteRegistry::rename()`): Name in der
    Datenbank, Ordner `sites/<alt>/` → `sites/<neu>/` und die Links dieser
    Maschine (`<DOCUMENT_ROOT>/projects/<alt>` → `/projects/<neu>`, nur
    Junctions/Symlinks aufs Projekt, nie ein echter Ordner wie der
    Projektordner der Default-Site). Prüft vorher Domainform, doppelte Namen
    und ob der Zielordner frei ist. Einträge in Instanz-Dateien, die die
    Site namentlich nennen, passt es nicht an.
  - **Site löschen** als Button: nur ohne Seiten darunter und nie die
    Default-Site; der Ordner bleibt (Code).
- **Hinzufügen** über das **+-Menü** oben im Pylon: Seite, Site (später Layout).
  „Site“ öffnet `?site=new`, Domain eingeben, Enter (`createSite`): Knoten vom
  Typ `site` am Ende der obersten Ebene, Zeile in `cms_sites`, Ordner
  `sites/<domain>/templates/{document,components,pages,layout}/` je mit
  `readme.txt`. Die erste Site einer Installation wird Default-Site (ohne Knoten).
- **Verschieben** wie jede Seite per Drag, auch in eine Seite hinein
  (verschachtelte Site). Seiten und Sites sind beim Sortieren eine Familie
  (`NodeChildren.sql`). `NodeSite::rebuildBranch()` setzt danach die Pfade des
  Asts: der Ast-Kopf nimmt die Site des neuen Parents, eine Site behält sich
  selbst, tiefer liegende Sites bleiben unberührt. Kein Alias einer Site.
- Im Baum: Klasse `site`, Globus-Icon, kein Kontextmenü.
- `app.php` legt pro Login per `db::createDefaultRecords()` eine leere Zeile 1
  in `cms_sites` an; `Queries/Sites/Sites.sql` ignoriert sie (`site_name <> ''`).
- `SiteRegistry::importFromConfig()` (Sites aus einem alten `sites`-Block der
  config.json) gibt es noch als Methode, ohne Oberfläche.

## Eine Site anlegen

1. CMS › Seiten › +-Menü › Site: Domain eingeben; Meta (title, appName, …) eintragen.
2. Seiten in den neuen Site-Ordner im Seitenbaum ziehen.
3. Einer Seite der Site den leeren Pfad geben (Home), sonst ist die erste Seite Home.
4. DEV/STAGING: Pfad-Link anlegen lassen (`"linkSites": true`) oder von Hand
   `mklink /J …\projects\<domain> …\projects\systopic.net`; LIVE: Domain aufs
   selbe Docroot zeigen lassen.
5. Optional `sites/<domain>/templates/…` füllen, sonst gilt `templates/`.

## Testen (CLI)

```jsonc
// zweite Site über den Pfad-Root
{ "path": "/projects/beautiful-technology.de/public/", "output": "json",
  "server": { "SCRIPT_NAME": "/projects/beautiful-technology.de/public/index.php", "HTTP_HOST": "192.168.22.50", "SERVER_NAME": "192.168.22.50" } }
// Wartungs-Button
{ "path": "/projects/systopic.net/public/cms/tools/maintenance/rewrite/", "output": "json", "user": 2, "put": { "action": "assignSites" } }
```

Erwartung auf DEV: Route `root > site=747 > id=741`, `bodyClasses` mit
`site-beautiful-technology-de`; ein Pfad der anderen Site liefert im
Debug-JSON `UrlResolver: 301 to owning site` mit `target`. Verschieben im CMS
braucht im CLI eine Session, in der der Baum den Ast geöffnet hat
(`sessionId`, erst `?id=<seite>` aufrufen).

## Offen / bekannt

- `sys_migrations` fehlt in der DEV-DB: angewendete Statements werden nicht protokolliert.
- Ein Alias in Site B auf ein Original in Site A: der Pfad gehört zur Site
  des Originals, unter Host B leitet er nach A um.
- Verschachtelte Sites gehen (Site-Knoten unter einer Seite); die Route beginnt dann beim inneren Site-Knoten.
- Sprachen bleiben global (`LANGUAGES`, `DEFAULT_LANG`).
