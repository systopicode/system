# Referenzen in Inhalten — `page:`, `media:`, `{…}`

Stand 2026-10-24. Code: `packages/cms/src/Refs/`.

Inhalte (Text- und HTML-Felder der Seiten) speichern **keine Pfade**. Bilder,
Videos, Dateien und interne Links sind Referenzen; erst die Ausgabe macht
daraus Adressen. Ein anderer Projektordner, eine andere Ordnerstruktur der
Medien oder später ein CDN ändern deshalb keinen einzigen gespeicherten Text.

## Schreibweise

```html
<a href="page:43">…</a>                         Seite 43, Sprache der Anfrage
<a href="page:43?lang=en#kontakt">…</a>         andere Sprache, Anker
<img src="media:293?format=collection" alt="…"> Bild in einem Format (ohne: Original)
<a href="media:85">Broschüre</a>                Datei (Original)
<video src="media:85" poster="media:85" controls></video>
<a href="page:43"><img src="media:293"></a>     Kombination - jedes Attribut für sich

{page:43}                                       Link mit dem Titel als Text
{page:43.label}                                 eine Eigenschaft als Text
{page:43.meta.description}
{media:85.label}  {media:293?format=x.src}
```

Attribute mit Referenzen: `href`, `src`, `poster` (`Resolver::URL_ATTRIBUTES`).
Werte im Query sind Wörter (`[\w-]+`), damit ist der Punkt danach eindeutig;
eine gewöhnliche `{` im Text bleibt stehen. Punkt statt `->`: der Browser
speichert ein `>` im Text eines bearbeitbaren Felds als `&gt;`.

**Lesbar ist nur, was der Typ freigibt** — kein `eval`, keine beliebigen
Eigenschaften oder Methoden; Inhalte schreibt die Redaktion, nicht der Code:

| Typ | Attribut | Text ohne Pfad | Eigenschaften |
|---|---|---|---|
| `page` | `$page->link` (+ `#anker`) | `<a href>Titel</a>` | `label`, `link`, `href`, `language`, `meta.description`, `meta.shareTitle` |
| `media` | `src` des Formats; `poster`: Standbild des Videos | `<img>` bzw. Link | `label`, `src`, `filename`, `width`, `height` |

Ausgabe immer escaped. Eine Seite, die in der Sprache fehlt oder unter den
Parametern nicht sichtbar ist (unveröffentlicht, Papierkorb), gilt als nicht
gefunden.

## Drei Durchgänge — `Cms\Refs\Resolver`

| | wer | was |
|---|---|---|
| `render()` | `TextField::$rendered` → Website | Adressen und Werte eingesetzt. Fehlt das Ziel: `<img>`/`<video>`/`<iframe>` entfällt, `<a>` wird Text, `{…}` wird leer |
| `renderForEditor()` | `TextField::$editorContent` → `FieldOutput::htmlEditor()`, `insertMediaInEditor` | Adresse im Attribut (der Browser zeigt das Bild), die Referenz daneben in `data-ref-src` / `data-ref-href`; `{…}` als nicht bearbeitbares Kästchen `<span class="ref" data-ref="…">Wert</span>`; Fehlendes mit `data-ref-missing` (rot gestrichelt, `HTMLeditor.css`); `data-ref-mimetype` für das Kontextmenü |
| `normalize()` | `Pages\Panel::updateField()` (jedes Speichern) | zurück zur Referenz: `data-ref-<attr>` gewinnt, Kästchen werden `{…}`, `data-ref-*` fällt weg — ohne Datenbank |

Je Feld ein Regex-Durchlauf plus **eine Abfrage je Typ** für alle Ziele des
Felds (`Page::byIds`, `MediaNodes::byNodeIds`). Ein Text ohne `typ:ziffer`
kostet einen `preg_match`.

`->raw` und `->plain` lösen **nicht** auf (roh bzw. reiner Text). Wer ein Feld
mit Referenzen ausgibt, nimmt `<?= $page->html01 ?>` oder `->rendered`.

## Editor

* Medium in den Editor ziehen: `insertMediaInEditor` baut die gespeicherte Form
  (`media:…`) und lässt `renderForEditor()` daraus die Editor-Form machen —
  dasselbe Markup wie nach einem Neuladen.
* JS (`system/public/js/cms/HTMLeditor.funcs.js`, `HTMLeditor.menus.js`) liest
  Medium und Format aus `data-ref-src` (`editorMediaRef()`); Formatwechsel
  setzt `data-ref-src` neu.
* Interner Link: im Link-Dialog `page:43` als URL eintragen.

## Eigene Typen

```php
\Systopic\Cms\Refs\Resolver::register('event', fn($scope, $params) => new EventRefs($scope));
```

`EventRefs implements RefType`: `load()` gebündelt, `key()`, `url()`, `text()`
(mit eigener Freigabeliste), `editorData()`.

## Alte Inhalte — `cms/tools/migration`

„convert html links and images“ (`Cms\Refs\HtmlMigration`) wandelt einmalig
um, was vor den Referenzen geschrieben wurde: `<img data-node_id data-format>`,
Bildpfade auf eigene Medien (Medien-Id aus den Ordnern, Dateiname geprüft),
`?id=42&lang=en`, Links auf eigene Pfade (Rewritepath-Suche, alte Wurzeln wie
`/smp.de/` werden weggelassen), Links auf eigene Dateien, die Marker des alten
Editors. Das Panel zeigt bei jedem Aufruf den Probelauf (was, wo, was offen
bleibt); „convert“ schreibt nach Rückfrage. Die Ausgabe kennt die alten Formen
nicht — was nicht konvertiert ist, wird so ausgegeben, wie es steht.
