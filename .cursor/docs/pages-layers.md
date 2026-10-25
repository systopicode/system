# Seiten: Daten, Abfragen, Route — wer macht was

Stand 2026-10-01.

## Die Schichten

| Schicht | Wo | Was |
|---|---|---|
| Zeilen | `src/Tables/<Tabelle>/Model.php`, `Operator.php` | eine Zeile je Objekt (`Tables\Pages\Model` = `cms_pages`, `Tables\Nodes\Model` = `cms_nodes`, Rewritepaths, Sites); der Operator schreibt |
| Seite im Baum | `src/Tables/Pages/Page.php` (+ `Texts`/`TextField`, `Meta`, `Rewritepaths`, `Medias`, `Tags`) | Knoten + Zeile als eine Seite an ihrem Ort im Baum; Datenquelle von CMS und Website |
| Abfragen | `src/Queries/*` | benannte SQL über mehrere Tabellen, Ergebnis sind Tables-Objekte (`PageTreeLevel`, `PageDetail`, der CMS-Baum `Queries\PageTree\PageTree` mit `PageTreeNode`) |
| Ausgabe | `src/Pages/Fields/FieldOutput.php` | ein Feld als HTML: live der Wert im Tag, im CMS das Editor-Markup |
| Legacy | `lib/class/page/page.php`, `pages.sql` | `\page`, `page::get()`; noch genutzt von Sitemap und galerie-lattemann.de |
| Route | `src/Pages/Route/` | das Rendern eines Requests: `Route` (root → Site → Seiten), `RouteNode` (ein Glied, Host der Templates), `RouteBuilder` (baut die Kette über `Page::byId` / `PageTreeLevel`) |
| Drumherum | `src/Pages/{Router,Navigator,Builder,Renderer,Sites}` | `UrlResolver` (URL → Id), `PageNavigator` (Views öffnen, DOM-Layer), `ViewResolver` (Template-Datei), `PagesRenderer` (Export an den Client), Sites |

Die Projektklasse (`<ns>\Pages\RouteNode` aus `templates/route.php`) setzt nur,
wie gerendert wird (`$alwaysUpdateViews`, Hooks `onLoad`/`onLoadSelected`),
nicht was. Root und Site-Knoten haben keinen Inhalt (`page === null`).

## Im Template gibt es nur `$this`

`$this` ist das Routen-Glied, sonst nichts — kein `$page`, kein `$lang`, kein
`$GLOBALS['page']`. Wo die Seite oft gebraucht wird, holt das Template sie sich:

```php
<?php
/**
 * @var \SystopicNet\Pages\RouteNode $this
 */
$page = $this->page;
```

| Brauche … | Schreibe |
|---|---|
| Sprache | `$this->lang` |
| andere Seite | `$this->pageById(7)?->link` (NULL, wenn es sie in der Sprache nicht gibt) |
| Feld ausgeben | `<?= $page->text01 ?>` (live: Tag + Wert, im CMS: Editor) |
| Feld anpassen | `$page->text03->tag('h2')->class('x')->editorLabel('Untertitel')` — liefert eine Kopie |
| Feld als Text | `->plain` (escaped, ohne Tags), roh `->raw`, Test `->isVisible` |
| Label | `$page->label->plain` |
| Adresse | `$page->link` (relativ), `$page->href` (absolut, Host der Site der Seite) |
| Medien | `$page->medias->group('main')`, `->has('main')` |
| Tags | `$page->tags->all` (Modelle), `->ids`, `->isEmpty` |
| Einstellung | `$page->settings->isChecked('invert_project_header')`, `->value('x')` |
| Meta | `$page->meta->isNaviElement`, `->published`, `->description` … |
| Kinder (nur Daten) | `$page->children` |
| Kinder öffnen | `$this->eachChild(fn($child) => …, 'main')`, inline mit `false` |
| Teil-Template | `$this->fetchView('tags', ['tags' => …])` — ohne Layer, als String; Variablen per Array |
| Template eines anderen Glieds | `$member->render('main.tpl.php', ['i' => $i])` |

HTML-Bausteine (Tags, Filter) sind Komponenten in `templates/components/`,
nicht Methoden am Modell; sie geben neutral aus (keine Inline-Styles, Daten
als `data-*`).

## Trennbarkeit

Das CMS soll später ein eigenes Paket werden (`\Systopic\Cms\Pages\Route\RouteNode`
statt `\Systopic\Cms\Pages\Route\RouteNode`). Deshalb liegen Pages und Panels
nicht in einem gemeinsamen Ordner: Panels ist das allgemeine App-Gerüst und
bleibt im System, Pages gehört zum CMS. Abhängigkeiten nur in eine Richtung:
CMS-Code darf System-Code nutzen (Dom, Panels, Db, Config), System-Code braucht
keine CMS-Klassen. Nahtstellen, die heute noch quer gehen (`loader.php` baut
die Route auf, `site.php`), werden beim Trennen geschnitten.
