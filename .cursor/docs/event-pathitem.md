# `pathItem` — Event-Daten aus dem DOM

`public/js/core/event/event.pathItem.js`.  
Aufruf: `processEvent` → `collectElementData()` startet am `sourceElement` (`e.target`, sonst `currentTarget`).  
Transport danach: [form-data.md](./form-data.md), [server-client-communication.md](./server-client-communication.md).

## Walk

Von dem Element nach oben bis `HTML` (max. 50, nur Element-Knoten). Jeder Knoten wird ein `pathItem`.

`getData()` merkt **unten nach oben**, dann **oben nach unten** zusammen:

```text
parent.getData()  ←  this.getElementData()
```

Merge: `obj.extendIfNotEmpty(ancestor, descendant)`. **Der tiefere Knoten gewinnt.**

Jeder Knoten klont `defaultData` (`method`/`html`/`files`: `null`). Deshalb: **`null` / `undefined` = nicht gesetzt** → Ancestor bleibt. Sonst überschreibt der Nachfahre, auch `''`, `0`, `false`.

Reine `{}`-Objekte (`get`, `post`, `form`, `dataset`, …) werden rekursiv gemerged. `FileList` und andere Host-Objekte nicht — die ersetzt der Nachfahre ganz, sofern nicht `null`.

## Buckets in `event.data`

| Key | Wer füllt | PHP |
|---|---|---|
| `get` | `method=get`, Query aus `href` | `http::get()` |
| `post` | `method=post` (+ files) | `http::post()` |
| `form` | kein `method` / `method=put` (+ files) | `http::form()` |
| `values` | Control **ohne** `element.form` | `http::values()` |
| `dataset` | `data-*` des Knotens (Merge entlang des Walks) | `http::dataset()` |
| `method` | siehe unten; `null` → Server default **PUT** | |
| `files` | `input[type=file]` | `$_FILES` / Chunks |
| `html` | `contenteditable` am Knoten selbst | `http::put('html')` |
| `href` | aus Trigger `on_<event>` | Action-URL |

`dataset.on_*` → Trigger (`on_click`, `on_submit`, `on_change`, …). Mehrere Ancestors: tieferer `data-on_*` gewinnt.

## `method`

| Markup | `event.data.method` | Felder |
|---|---|---|
| `a` click, kein `.put` | `get` | Query aus `href` |
| `form method="get"` | `get` | Controls → `get` |
| `form method="post"` | `post` | Controls → `post` |
| `form` ohne `method` oder `method="put"` | `put` | Controls → `form` |
| Control ohne Form | `null` → PUT | `values` |

`method="put"` ist kein HTML, nur Alias.

## Sonderfälle

**FORM** sammelt alle Controls über `getFormData()` (nicht nur das geklickte Feld). `action` wird `dataset.on_submit` — auch wenn `action` ein Control-`name` ist (`form.action` vs. Attribut).

**INPUT / BUTTON / TEXTAREA / SELECT** in einer Form: Bucket nach Form-`method` (Tabelle oben). Ohne Form: `values`. Checkbox: `1`/`0`.

**contenteditable** in einer Form: zählt mit, wenn `data-name` gesetzt ist; Wert = `innerHTML`, gleicher Bucket wie Controls. Am Knoten selbst zusätzlich `html`.

**LABEL:** erstes inneres `INPUT` → `values` (nicht der Form-Bucket).

**A:** `href` → `on_click` + Query, außer `data-on_click` steht schon. Klasse `put` oder Nicht-`click` (z. B. Contextmenu): kein `method=get` (URL bleibt).

**`data-href`:** wie Anchor, wenn kein `data-on_click`.

**HTML:** Query der Browser-URL nach `get` (PUT-Requests: Query-Reste in der URL).

**Files:** `FileList` vor `extend` löschen (sonst Müll-Props). Mehrere Dateien: `name[0]`, `name[1]`.

**Abbruch:** kein `tagName`, `parentNode === null` (abgehängtes Lightbox-DOM), Nicht-Element-Parent.

## `getFormData` vs. Walk

Submit: Source oft der Button, Walk schließt das `FORM` ein → volle Felder im FORM-Knoten.

Einzel-Event (`data-on_change` am Input): Walk nimmt trotzdem das FORM mit; FORM überschreibt den Bucket mit **allen** Controls. Der Input legt denselben Bucket nochmal für sich — Merge, tieferer Key gewinnt.
