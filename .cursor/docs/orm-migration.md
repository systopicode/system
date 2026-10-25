# Migration auf das ORM — Regeln, Stand, Baustellen

Für die Umstellung des restlichen CMS (`packages/system`) und der Projekte
`pwk.systopic.net` und `spaceticker.app`. Geschrieben für den Agenten, der
sie macht. Wie das ORM funktioniert, steht in `orm.md`; das hier sagt, wie
man Bestehendes dorthin bringt.

## 1 · Die Regeln

**Ein Panel, ein Durchgang.** Ein Panel wird ganz umgestellt, nicht
zeilenweise: Panel-Klasse, Actions, Templates, Prüfskript. Halb umgestellte
Panels sind die teuerste Variante.

**Erst lesen, dann schreiben.** Vor dem Umbau: jede Action des Panels
auflisten, jeden Aufrufer suchen (`data-on_click`, `href`, JS). Actions ohne
Aufrufer werden gelöscht, nicht portiert. Was kaputt ist, wird notiert und
bewusst repariert oder bewusst weggelassen — beides steht hinterher im
Bericht.

**Prüfskript zuerst grün, dann Umbau, dann wieder grün.** Für jedes Panel
ein `.cursor/tools/<panel>-check.php` nach dem Muster von
`pages-panel-check.php`: eigene Zeilen anlegen, jede Action über `index.php`
fahren, Datenbank nachsehen, aufräumen. Dazu `smoke-galerie.php` und die
`auto_prepend_file`-Prüfungen (rows, family, tree, junction, relation,
audit, parts, tags). Ein Prüfskript sieht nach einer Action nicht nur in die
Datenbank, sondern auch in die **Antwort derselben Action**: die markierte
Ansicht muss den neuen Stand zeigen. Sonst bleibt ein veralteter View
unentdeckt. `run-checks.sh <outdir>` fährt alles in einem Lauf und
legt die Berichte ab; zwei Läufe lassen sich mit `diff -r` vergleichen. Eine
Query-Zahl, die steigt, ist ein Befund.

### Im Panel

- `$this` ist das Panel. Kein `$module`, kein `$state`, kein `$action` als
  lose Variable; jede Datei beginnt mit `/** @var \…\Panel $this */`.
- **Objekte entstehen in Getter-Properties des Panels**, nicht in Actions
  und nicht in Templates: `$this->scope`, `$this->params`, `$this->tree`,
  `$this->page`. Templates lesen sie, Actions benutzen sie. Ein Getter darf
  bei jedem Zugriff rechnen — Abfragen sind gecacht, Objekte in der Scope
  dedupliziert. Kein `$lazy`, kein Memo im Panel.
- Was aus Ergebnissen gebaut wird, liegt in `$scope->objectFor(Klasse,
  Schlüssel, fn)`. Der Schlüssel enthält, wofür es gebaut wurde (Params,
  aktive id). Der Commit räumt es ab; niemand ruft ein `forget()`.
- Nach einem Schreibzugriff: `updateView('…')`. Nichts unset, nichts neu
  laden.
- Actions sind Dispatch: id lesen, Objekt vom Panel holen, Operator rufen,
  `$this->scope->save(...)`, View markieren. Ein `case` hat ein bis fünf
  Zeilen. Braucht er mehr, fehlt ein Operator oder eine Query.
- Eine Panel-Methode darf nicht heißen wie eine Action: `app.php` ruft eine
  öffentliche Methode mit dem Namen der Action **vor** `actions.php` ohne
  Argumente auf (`recoverBranch()`, nicht `recoverPage()`).
- Ein Getter darf keine Property von `PanelNode` überdecken: `$path`,
  `$name`, `$label`, `$root`, `$level`, `$parent`, `$children`, `$state`,
  `$action`, `$href` … (`$rewritepath`, nicht `$path` — sonst Fatal beim
  Laden der Klasse).
- `$scope` erbt jedes Panel unter `cms/` von `Cms\Panel`. Panels, die direkt
  von `PanelNode` erben (`auth`, `settings`, `tools/*`), deklarieren ihn
  selbst.
- Ein Unterordner ohne `panel.php` bekommt eine generische Klasse ohne
  Getter. Braucht er welche, bekommt er eine `panel.php`
  (`Root\<Pfad>\Panel`).
- Die id hinter einer Beziehung liest die Anwendung mit `relationId()`,
  nie mit `refId()`. `refId()` ist intern und kennt nur, was eine Abfrage
  geliefert hat: eine Zeile, die im selben Request angelegt und gespeichert
  wurde (`$link->group = $group`), hat dort NULL — sie landet beim Einsortieren
  unter 0 und fehlt in der Ansicht, obwohl sie in der Datenbank steht.
- Werte vom Legacy-Benutzer (`\user::getActive()->initials`) nie mit `??`
  lesen: `??` fragt `__isset()`, und der Legacy-Record kennt berechnete
  Werte dort nicht — das Ergebnis ist still der Ersatzwert.
- Kein `db::`, kein `page::`, `node::`, `media::`, `setting::`, kein
  `saveRecord()`/`updateRecord()`, kein rohes SQL in Panels. Rohes SQL gehört
  in eine `.sql`-Datei einer Query. Bleibt ausnahmsweise ein Statement am
  UnitOfWork vorbei (ein `DELETE … WHERE` über Zeilen, die niemand hält),
  invalidiert der Aufrufer die Tabelle von Hand:
  `$scope->results->invalidate([Schema::tableNameOf(Model::class)])`.
- Formularwerte gehen durch den Operator der Tabelle (`assign()` mit
  Whitelist). Keine Feldnamen aus dem Request in eine Zeile schreiben.

### Im Modell

- Eine Tabelle, ein Ordner `Tables/<Name>/`, Zeilenklasse `Model`. Jede
  Spalte mit `get => $this->__get__(__PROPERTY__); set => $this->__set__(__PROPERTY__, $value);`.
- Fremdschlüssel sind Beziehungen (`?Node $parentNode`), nicht ints. „Kein
  Elternteil“ ist NULL, nicht 0.
- Eine Query, ein Ordner `Queries/<Name>/` mit `<Name>.php` und `<Name>.sql`;
  weitere SQL-Dateien `<Name>.<zweck>.sql`. Signatur `(Scope $scope, …)`,
  bei Seitenabfragen `(Scope $scope, Params $params, …)`. Ausführen nur über
  `$scope->rows()`.
- Operatoren sind zustandslos und speichern nicht. Sie geben zurück, was zu
  speichern ist.
- Was für mehrere Knotentypen gilt (verschieben, Alias), liegt beim Knoten,
  nicht bei der Seite.
- Legacy-Klassen unter `lib/` werden nicht angefasst und nicht erweitert;
  sie fallen weg, sobald ihr letzter Aufrufer weg ist.

### Für ein Projekt (pwk, spaceticker)

- Eigene Tabellen liegen im Projekt unter `src/Tables/<Name>/` mit eigenem
  Namespace; Eigene Queries entsprechend unter `src/Queries/`. Angemeldet
  werden sie wie die des Systems: `src/boot.php` mit
  `Registry::addTables(__DIR__ . '/Tables', '<Projekt>\Tables')`, in der
  Projekt-`composer.json` als `autoload.files`, dazu `psr-4`
  `<Projekt>\` → `src/`. Vorbild: spaceticker.app (seit 2026-10-12).
- Eine Tabelle, die eine Datenmigration braucht (Werte aus einer alten
  Spalte übernehmen), liest ihre erste Query mit einem Provisioner samt
  `Migrations(<src>/Migrations, '<Projekt>\Migrations')`; die Migration
  hängt an `ON_CREATE = '<tabelle>'` (Vorbild:
  `Spaceticker\Migrations\UserAppsFromUsers`).
- Projekttabellen mit eigenem Präfix (`mod_events`) setzen `TABLE_PREFIX`.
  **Achtung, solange die Legacy-Datenbankklasse im selben Request läuft:**
  sie hält jede Tabelle `xxx_<plural>` für eine Record-Tabelle mit
  `<singular>_id`; eine neue Tabelle ohne diesen Schlüssel (`mod_user_apps`
  mit `uapp_user_id`) gibt dort Warnungen (`dbSchemaTable.php:109`).
- Medien unter Projekt-Knoten (Logo, Bilder, Dokumente): lesen über
  `Queries\MediaNodes`, schreiben über `Medias\Operator` + `Files::store()`
  (die Datei braucht die Medien-Id: erst speichern, dann ablegen), löschen
  über `Medias\Removal`; der Wert für Templates ist
  `Systopic\System\Tables\Medias\Media` (seit 2026-10-12 im System, vorher
  `Systopic\Cms\Tables\Pages\Media`).
- Verbindungszeilen (KEY-Tupel) lassen sich nur als **geladene** Zeilen
  entfernen; eine neu gebaute mit denselben Schlüsseln ist „nie in der
  Datenbank gewesen“ und wird übersprungen. Für das Löschen eine Query, die
  die Verbindungen selbst liefert.
- Der Tabellenpräfix kommt aus `DEFAULT_TABLE_PREFIX` der Installation
  (`pwk_`, …); nur Tabellen, die installationsübergreifend gleich heißen,
  setzen `TABLE_PREFIX`.
- Wo ein Projekt dieselbe Tabelle wie das System benutzt (`pwk_tags`), ist
  die Klasse die des Systems: eine Klasse je Tabelle.
- `public/.htaccess` des Projekts prüfen (Projekte mit `public/`-Ordner):
  - `~` muss in der Zeichenliste der Catch-all-Regel **und** der
    `QUERY_STRING`-Bedingung stehen (`[a-z0-9/_\-\&=\.~]`), sonst antwortet
    jeder Klick im CMS mit 403 (Diff-Adressen `…/cms/~/cms/events/`, siehe
    diff-url-and-references.md).
  - `sync.php` liegt im neuen Build in `public/` und wird direkt
    durchgelassen: `RewriteRule ^sync.php$ - [L,QSA]`. Die alte Zeile
    `RewriteRule ^sync.php* ../sync.php [L,QSA]` leitet auf eine
    `sync.php` im Projekt-Root um. Gibt es die nicht mehr, kommt 404.
    Vorbild: systopic.net, spaceticker.app (seit 2026-10-12).
- Vor dem ersten Panel: `db status` und `db audit` im CMS laufen lassen und
  die Abweichungen lesen. Die Zeilenklassen beschreiben die Tabelle, wie sie
  sein soll.

### Im Seiten-Template (Website)

Seit 2026-10-01 baut die Render-Route die Seiten aus dem ORM:
`RouteNode::$page` ist `Tables\Pages\Page`, nicht mehr `\page`. Der Renderer
gibt **nur `$this`** in das Template, also kein `$page`, kein `$lang` und kein
`$GLOBALS['page']` mehr. Ein Projekt, dessen Templates noch das alte
Seitenobjekt lesen, bricht ab („Undefined variable $page“, danach ein Fatal).
Umgestellt ist systopic.net (Vorbild); offen ist galerie-lattemann.de
(Übergabe: `../plans/orm-template-access.handover.md`). Die Kurzfassung für
Template-Autoren steht in `pages-layers.md`.

**Jede Datei** unter `templates/`, `sites/*/templates/` und im alten Layout
`pages/` bekommt:

```php
<?php
/**
 * @var \<PROJECT_NAMESPACE>\Pages\RouteNode $this
 */
$page = $this->page;   // nur, wenn die Datei die Seite liest
```

Die alten Hinweise `@var \page $page` und `@var string $lang` werden entfernt.
Für Root und Site-Knoten ist `$this->page` NULL. Templates, die dort laufen
(document, head, body der Site), lesen die Seite über die Route:
`Route::getInstance()?->getSelected()?->page`.

**Zugriffe alt → neu.** Die Tabelle ist vollständig für alles, was in
systopic.net und galerie-lattemann.de vorkommt.

| Alt (`\page`) | Neu (`Tables\Pages\Page` / `RouteNode`) | Hinweis |
|---|---|---|
| `$page` (vom Renderer) | `$page = $this->page;` | |
| `$lang` | `$this->lang` | |
| `$GLOBALS['page']`, `global $page` | `Route::getInstance()?->getSelected()?->page` | nur in `lib/`-Code; im Template `$this` |
| `page::get($id)` / `page::get($id, $lang)` | `$this->pageById($id)` / `$this->pageById($id, $lang)` | gibt NULL zurück, wenn es keine Zeile in der Sprache gibt: `?->link` |
| `page::get($child->node_id, 'de')` für ein Kind | `$child` (schon ein `Page`) | |
| `page::getLanguages('activated')` | `$this->languages()` | iso => `label`, `code`; außerhalb von Templates `Tables\Pages\Languages::activated()` |
| `$page->node_id` | `$page->id` | |
| `$page->language_iso` | `$page->language` | |
| `$page->is_published` | `$page->meta->published` | |
| `$page->is_navi_element` | `$page->meta->isNaviElement` | |
| `$page->template`, `->template->name` | `$page->templateName` | `''` statt NULL, wenn keine Vorlage: `!$page->template \|\| $page->template->name !== 'x'` wird `$page->templateName !== 'x'` |
| `$page->link` | bleibt | relativ; im CMS die Panel-Adresse |
| `http::$siteUrl . $page->rewritepath` | `$page->href` | absolut, Host der **Site der Seite** (nicht des Requests) |
| `$page->rewritepath` (als String) | `$page->rewritepaths->path` | mit Schrägstrich am Ende |
| `$page->rewritepath->name` | `$page->rewritepaths->name` | ohne führendes `#` (Anker-Ids) |
| `$page->redirect_id`, `page::get($page->redirect_id)` | `$page->redirectsTo` (`?Page`) | NULL, wenn das Ziel fehlt oder im Papierkorb liegt; `->link` folgt der Weiterleitung wie bisher |
| `$page->meta_description`, `meta_keywords`, `meta_robots`, `meta_share_title`, `meta_share_image`, `meta_json_ld` | `$page->meta->description`, `->keywords`, `->robots`, `->shareTitle`, `->shareImageId`, `->jsonLd` | |
| `<?= $page->text01 ?>` … `html09`, `label` | bleibt | typisiertes `TextField`; live Tag + Wert, im CMS der Editor (`Pages\Fields\FieldOutput`) |
| `->tag('h2')`, `->class('x')`, `->editorLabel('x')` | bleibt, gibt aber eine **Kopie** zurück | Eine alleinstehende Anweisung `$page->text02->tag('h2');` ohne Ausgabe wirkt nicht mehr, sie muss in das `echo` |
| `->tag('plain')` | bleibt | ohne Tag; im CMS weiter der Editor |
| `->value` als sichtbarer Text oder Attribut (`href`, `class`, `aria-label`) | `->plain` | ohne Tags und **escaped**: `&` erscheint als `&amp;` (gewollt) |
| `strip_tags($page->html03->value ?? '')` | `$page->html03->plain` | |
| `->value` ungefiltert, ausdrücklich gewollt | `->raw` | nie Editor |
| `!empty(trim(strip_tags($page->html01->value ?? '')))` | `$page->html01->isVisible` | ein Bild zählt als Inhalt |
| `$page->label->value` | `$page->label->plain` | |
| `$page->medias->main` (Gruppe per Name) | `$page->medias->group('main')` | `->main` geht als Alias noch, ist aber nicht typisiert |
| `->medias->main->first`, `->first->src/width/height/path/setFormat('x')` | `->medias->group('main')->first->…` | Die Gruppe ist weiter die Legacy-`\mediaGroup`, die Medien sind `\media`. Eine leere Gruppe hat `first === null`, daher vorher `has()` |
| `$page->medias->hasGroup('main')` | `$page->medias->has('main')` | |
| `$media->media_mimetype`, `->media_id`, `->media_filename` … (Spaltennamen am Legacy-Objekt) | `$media->mimetype`, `->id`, `->filename`, `->label`, `->width`, `->height`, `->group`, `->type` | seit 2026-10-03 ist ein Medium `Tables\Pages\Media` (unveränderlich: `setFormat()`/`addClass()` geben eine Kopie); `media::get($nodeId)` → `Media::byNodeId($scope, $nodeId)` (null statt Zeile 1) |
| `$page->settings->name->value` | `$page->settings->isChecked('name')` (Checkbox), `->value('name')`, `->key('name')` | |
| `$page->tags->keys()` | `$page->tags->ids` | |
| `$page->tags->toHtml()` | `$this->fetchView('tags', ['tags' => $page->tags->all])` | Markup in `templates/components/tags.tpl.php` des Projekts |
| `$page->tags->toSearchHtml($selected)` | `$this->fetchView('tagFilter', ['tags' => $page->tags->all, 'selectedTags' => $selected])` | ebenso `components/tagFilter.tpl.php`; **keine Inline-Farben** mehr, die Gruppe steht als `data-group_id` für CSS |
| `!empty($page->tags->keys())` | `!$page->tags->isEmpty` | |
| `$page->children` | bleibt | `list<Page>` in `node_ordering`, ohne Rendern |
| `$page->inPath()` | `$child->inPath()` | gehört zum `RouteNode`, nicht zur Seite |
| `include 'x.tpl.php'` mit Variablen aus dem Aufrufer | `$this->fetchView('x', ['a' => $a])` (als String, ohne Layer) oder `$node->render('x.tpl.php', ['a' => $a])` (direkt ausgeben) | Includes sehen keine lokalen Variablen mehr; was sie brauchen, geht per Array hinein |
| `$this->withPage((int) $child->node_id, 'de')` | `$this->withPage($child->id, $this->lang)` | |
| Partials aus einer Action (`lib/actions.php`) | `Route::getInstance()?->getSelected()?->fetchView('x', [...])` | Die Action rendert über den Knoten der Seite, also mit denselben Template-Wurzeln |
| `lib/`-Funktionen mit `\page`-Parameter (`printSVG($media, $page)`) | bekommen `Tables\Pages\Page` und lesen die neue Oberfläche | |

**Vorgehen** (so lief es in systopic.net):

1. Vorher-Stand rendern: `.cursor/tools/golden-pages.php <label>` (für ein
   anderes Projekt `$public` und die Site-Pfade im Skript anpassen). Zweimal
   laufen lassen; was zwischen zwei Läufen schon abweicht, ist gemischter
   Inhalt (Shuffle) und gilt als Rauschen.
2. Felder vergleichen: alt gegen neu je Seite, Feld für Feld (Vorlage:
   `.cursor/tools/template-migration/compare-fields.php`, Bericht in
   `compare.report.txt`). In systopic.net: 4752 Vergleiche, 2 erklärte
   Abweichungen.
3. Umstellen per Skript mit exakten Ersetzungen; jede muss genau einmal
   passen, sonst Abbruch (Vorlagen in `.cursor/tools/template-migration/`:
   `migrate-pages.php` für die Seiten-Templates, `migrate-chrome.php` für
   document und components, `patchlib.php` für exakte Patches). Zeilenenden erhalten: In systopic.net sind die
   Seiten-Templates CRLF, `templates/document/*` und `components/*` LF.
   **PHP mit `$` nie über `bash php -r "…"` schreiben.** Bash ersetzt `$page`,
   und die Datei wird leer geschrieben (am 2026-10-01 so passiert). Immer
   eine Skriptdatei verwenden.
4. Nachher-Stand rendern und vergleichen. Erwartete Unterschiede: Tags ohne
   `style=`, `&amp;` in Labels und Tags, Shuffle. Alles andere ist ein Befund.
5. Actions mit Partials (Tag-Filter) per CLI fahren, zum Beispiel
   `public/var/cursor/orm-filter.json`: Pfad `/projekte/filter`, `"ajax": true`,
   `put.dataset`.
6. CMS-Vorschau mit Editoren prüfen (`cms/pages/?id=…`, `"user": 2`): Das
   Editor-Markup muss gleich bleiben.

## 2 · Was auf dem ORM läuft (Stand 28.09.2026)

```
packages/db/src/           Paket systopic/db, Namensraum \Systopic\Db (seit 2026-10-04, vorher
                           src/Db, \Systopic\System\Db): Scope, Results, Rows, RowReader,
                           LoadGroup, UnitOfWork, TableRow, IdentityMap, Resolver, Schema/*,
                           Type/*, Provisioner, Audit, Schema/Restore (Backup-Panel)
                           Seit 2026-10-04 in drei Paketen: systopic/system (src/), systopic/cms
                           (packages/cms/src/, \Systopic\Cms\…: Pages, Rewritepaths, Keywords,
                           KeywordsInPages, Sites, Settings; Abfragen der Seiten, Settings,
                           Translations, Params; src/Pages; die Seiten-Panels) und
                           systopic/system-legacy (alte Klassen). Die Liste unten nennt beide.
src/Tables/                24 Tabellen als Model; Operatoren: Nodes, Pages, Rewritepaths, Users,
                           Roles, Groups, RolesOnGroups, Settings, Tags, TagGroups;
                           Facetten: Pages/{Page, Meta, Texts, TextField, Rewritepaths, Languages}
src/Queries/               PageTree (+Level, Node, FoldMode), PageDetail, Branch, PageSearch,
                           Translations, NodeChildren, RewritepathLookup, RewritepathIndex,
                           SettingTree, SettingValues, TagGroups (+Panel), TagLinks,
                           UserAdmin, UserLookup, LastLogin, TrashedPages, Duplicates, Sites, SiteOfNode; Params
panels/cms/                Wurzel (logout, Release-Note-Check), user/, auth/ (+ root/)
panels/cms/010_pages/      ganz, außer mml/, upload/, editor/file/ und den Actions
                           insertMediaInEditor, getImageEditor (Legacy media)
panels/cms/030_settings/   010_page (+delete), 040_tags (+edit, join)
panels/cms/040_backup/     kein ORM-Stoff außer dem Restore (Db\Schema\Restore)
panels/cms/080_users/      users/ (+edit), permissions/ (+edit)
panels/cms/090_tools/      020_database/{status,audit}, 030_maintenance/{trash,duplicates,rewrite},
                           010_system, 060_polyfill, logs/php — ohne DB
```

Stillgelegt (nicht umgebaut, weil tot oder überholt) liegt unter
`panels/.maybelater/cms-2026-09/` mit einer README, die sagt warum.

**Sitzung und Rechte** laufen seit 2026-10-04 über `Auth\Session` (wer
eingeloggt ist, Rollen, Gruppenrechte, Fake-Rollen, Login/Logout, Panel-State
in der Session) mit `Auth\Identity` und `Auth\Password` (`password_hash`, md5
von früher wird beim Login ersetzt). `\user` ist eine Hülle darüber für die
Projekte (seit 2026-10-04 im Paket `systopic/system-legacy`, das systopic.net
nicht installiert); `user::getActive()` liefert die `Identity`, die die alten
Feldnamen versteht. Nur noch die Finder für **andere** Benutzer
(`user::get()`, `byUsername()` …) sind Legacy-Records. An den Browser geht nur
der angemeldete Benutzer — `Auth\ClientData` (mit `src/Auth/users.js`), vom
Loader in jedem Request registriert, nicht mehr über die Klasse `users`.

| Alt | Neu |
|---|---|
| `user::id()`, `known()`, `active()`, `hasRole()`, `hasOneRole()`, `isSuperuser()`, `mayRead()`, `mayWrite()`, `login()`, `logout()`, `addFakeRole()`, `removeFakeRole()` | `Auth\Session::` dieselbe Methode |
| `user::unknown()` | `!Auth\Session::known()` |
| `user::systemRecovery()`, `setSystemRecoveryUser()` | `Auth\Session::isRecovery()`, `::recover()` |
| `user::getActive()` | `Auth\Session::current()` (`->id`, `->user`, `->roleNames`, `->readGroupIds` …) |
| `user::getActive()->state`, `->storeState()` | `Auth\Session::state()`, `::storeState()` |
| `user::byLoginData($name, $pw)` | `Auth\Session::check($name, $pw)` (ORM-Zeile oder null) |
| `user::get($id)` für den Login | `Auth\Session::login($row | $id)`; andere Benutzer: `UserLookup::byId()` |
| `md5($pw)` / `user::getPasswordHash()` | `Auth\Password::hash()`, `::verify()` |
| `pages::checkDatabase()`, `::checkAccess()`, `::$selectedPage`, `::$accessGranted`, `::$lastQueryResult` | `Pages\Route\PageAccess::databaseReady()`, `::check()`, dieselben Eigenschaften (die Klasse `pages` ist gelöscht) |
| `record::id2path($id)` | `Tables\Medias\Files::idPath($id)` |
| `media::storeMediaFile($img, $name, $id, $format)` | `Tables\Medias\Files::writeFormat($img, $id, $format, $name)` |

Die Vorschau im Pages-Panel (`main.tpl.php`) und die Website rendern die
Projekt-Templates über die Render-Route `Pages\Route\Route`. Seit 2026-10-01
baut sie `Tables\Pages\Page` (siehe „Im Seiten-Template“).

## 3 · Was noch nicht auf dem ORM läuft

**Nächster Schritt:** der Nutzer entscheidet zwischen Medien (1) und
Sitzung/Rechte (3). Vorher `run-checks.sh` für einen Ausgangsstand laufen
lassen und ein Backup nach Abschnitt 1 anlegen.

In der Reihenfolge, in der es sinnvoll ist:

1. **Medien**: `panels/cms/010_pages/{mml,upload,editor/file}`, `lib/class/media`.
   Es gibt `Tables/Medias/Model` und `Tables/MediaCrops/Model`, aber keine
   Query und keinen Operator. Der Medienbaum sortiert Knoten wie der
   Seitenbaum — `Nodes\Operator` ist fertig dafür.
2. **Frontend**: Route, Templates (systopic.net), Meta-Tags (`appSite`) und
   `site.php` laufen seit 2026-10-01 auf `Tables\Pages\Page`. Noch offen:
   die Templates von galerie-lattemann.de (nach „Im Seiten-Template“),
   `sitemap.php` (liest `\page`) und die Medien hinter
   `Page::$medias` (Legacy `\mediaGroup`/`\media`, kommt mit Punkt 1).
3. **Sitzung und Rechte**: erledigt 2026-10-04 (`Auth\Session`, siehe oben;
   Prüfskripte `session-check.php`, `user-shell-check.php`). Die Renderer
   `users::toUserList()`, `groups::toGroupList()`, `userRoles::toRolesList()`
   haben keinen Aufrufer mehr.
4. **Übrige Panels**: `020_shop`, `050_statistics`, `070_email`,
   `090_tools/030_maintenance/media`, `090_tools/logs/email`.
5. **pwk**: eigene Tabellen und Panels, nach Abschnitt 1. **spaceticker**:
   erledigt 2026-10-12 (`../plans/spaceticker-orm.plan.md`), läuft ohne
   `systopic/system-legacy`.

## 4 · Bekannte Baustellen

Klein, gefunden und nicht angefasst:

- `lib/class/tree` (Legacy-Seitenbaum) findet keine Wurzeln mehr, seit sie
  NULL sind; `tree-check.php` vergleicht deshalb gegen einen leeren
  Legacy-Baum. Der neue Baum ist richtig. Den Vergleich aus dem Skript
  nehmen, wenn er stört.
- `family-check.php` 5.2d bricht mit *Invalid parameter number*: der Aufruf
  gibt `parentIds` ohne `roots`. Vorbestehend.
- `settings-panel-check.php` benutzt die echten Knoten 17 und 18 und löscht
  am Ende die Werte von 18. Nicht laufen lassen, während jemand im Browser
  Einstellungen pflegt; Golden-Dateien sind veraltet (Knoten 17, Einstellung
  „test“).
- `var/golden/smoke-baseline.json` kommt vom anderen Rechner; 12 Befunde
  und 7 fehlende Nutzlasten sind hier der Normalzustand. Mit `--baseline`
  neu abnehmen, wenn der Stand als richtig gilt.
- `cms_settings` 54, 55 (leere Deklarationen) und Knoten 486 (Einstellung
  ohne Namen, Elternwert 0) in galerie: Datenmüll, über das Panel löschbar.
- `Scope::default()` ist je Prozess. In einem Worker (nicht in FPM/CLI) muss
  `Scope::setDefault(null)` je Request laufen.
- `PageTree` baut sich nach einer Strukturänderung aus frischen Ergebnissen
  neu, statt die Änderung über `onChange()` einzupflegen. Das ist korrekt
  und kostet einen Baumaufbau; feiner nur, wenn eine Messung es rechtfertigt.
- `cms_rewritepaths` in galerie (Stand 28.09. abends): nach „delete All
  Paths“ + „Create Auto Rewritepaths“ im Browser nur noch die 135
  automatischen Pfade; die eigenen Pfade und die 122 leeren Altzeilen sind
  weg (bei Bedarf aus dem DB-Backup). Zeile 1 (ohne Seite, ohne String) hat
  „delete All Paths“ überlebt — nicht untersucht. Die Startseite braucht
  seitdem im Smoke-Test 63 statt 22 Abfragen (Legacy-Frontend); die
  Smoke-Baseline neu abnehmen, wenn der Datenstand so bleiben soll.
- 5 Seiten hatten keinen Pfad, darunter eine ohne Label (Knoten 261) und
  „##New Page“ (308): „Create Auto Rewritepaths“ meldet solche einzeln,
  statt abzubrechen.
- Im Db-Kern behoben am 28.09.: (1) Spalten mit Vorgabewert (`$label = ''`)
  galten auf Teilzeilen als geladen und wurden nie nachgeladen —
  `TableRow::$known`/`knows()` merkt sich jetzt, was geliefert wurde.
  (2) `refId()` in Anwendungscode ersetzt durch `relationId()` (siehe Regeln).
  Am 02.10.: (3) `RowReader::fillGaps()` hat beim Vervollständigen einer
  Zeile durch eine reichere Abfrage die **Fremdschlüssel** verworfen.
  `relationId()` lieferte NULL („keine Beziehung“) für eine Spalte, die gerade
  geliefert worden war, etwa den Site-Knoten eines Pfads, den der Seitenbaum
  zuerst mit vier Spalten geladen hatte. Jetzt kommen sie in die `refIds`.
  `relationId()` holt eine teilweise geladene Zeile einmal nach, statt NULL zu
  raten, und `wire()` überschreibt keine im Request geänderte Beziehung mehr.
  Prüfung: `relation-check.php` B7.
  (4) `LoadGroup::complete()` holte beim Nachladen nur die eine fehlende
  Spalte, also eine Abfrage je Spalte über dieselben Ids (bis zu 13 je
  Request). Jetzt holt die erste Lücke die **ganzen Zeilen** der Gruppe
  (`SELECT * … WHERE id IN (…)`), danach ist die Klasse in dieser Gruppe
  vollständig. Bei `Single` gilt das für die eine Zeile. Gemessen: ORM-Abfragen
  `/projekte/` 25 → 17, CMS `pages?id=173` 13 → 10. Prüfwerkzeug:
  `legacy-db-probe.php` (protokolliert auch die ORM-Abfragen).
  Wer an `RowReader`, `Resolver`, `LoadGroup` arbeitet: alles drei mitdenken.
- `cms/users`: `UserAdmin.sql` sind fünf Anweisungen in einem Roundtrip —
  in der MySQL-Zählung (`Questions`) +4 gegenüber vorher, in Roundtrips
  gleich.
- Die Panel-JS in `080_users/panel.js` (`onUpdateRoles`) ist Legacy und
  unangetastet.
- Der Legacy-Rest in `lib/` liest Wurzeln als `= 0` (`nodeSql.php`,
  `tree.sql`, `setting.sql`); das Frontend-Fallback in `UrlResolver` ist auf
  `COALESCE(…, 0) = 0` umgestellt.

## 5 · Prüfbefehle

```
cd packages/system/.cursor/tools
./run-checks.sh <outdir>          alles auf einmal, Berichte in <outdir>

php events-check.php
php pages-panel-check.php    C:/laragon/www/projects/galerie-lattemann.de/public
php users-panel-check.php    <public>     Benutzer, Rollen, Gruppen, Rechte
php auth-user-check.php      <public>     Login, Profil, Passwort, Reset per Schlüssel, Logout
php tags-panel-check.php     <public>     Gruppen, Tags, Umbenennen, Zusammenführen, Löschen
php maintenance-check.php    <public>     Papierkorb, Duplikate, Rewrite-Pfade
php settings-write-check.php C:/laragon/www/projects/galerie-lattemann.de/public
php settings-check.php       galerie-lattemann.de systop systop
php smoke-galerie.php        C:/laragon/www/projects/galerie-lattemann.de/public

php replay.php <public> "<Pfad aus dem Netzwerk-Tab, mit ~/>" --put='<json>' [--view=<name>]
   eine Browser-Anfrage nachspielen: mit einer Kopie der Browser-Sitzung und
   dem Referenzpfad vor `~/`, damit der Diff so rechnet wie im Browser.
   Zeigt je Layer new / keep / update und mit --view das HTML.
   Ohne Sitzung und Referenz ist per CLI jeder Layer `new` — ein View, der
   im Browser stehen bleibt, sieht dann richtig aus. Die Action schreibt echt.

cd C:/laragon/www/projects/galerie-lattemann.de/public
php -d auto_prepend_file=<tools>/<name>-check.php index.php @cli-front.json   (tags-check: @cli-tags.json)
  -> Bericht in ../var/golden/<name>-check.txt
```

Auf diesem Rechner liegt `www` unter `C:/laragon/www`; ältere Skripte und
Notizen sagen `V:/www`.

## 6 · Archiv

Die Entwürfe, Protokolle und Messskripte des Umbaus (Juni bis September
2026) liegen in `.cursor/archive/orm-2026-09/`. Sie beschreiben den Weg,
nicht den Stand; für den Stand gelten `orm.md` und dieses Dokument.
