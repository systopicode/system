# fsUploader

Chunk-Upload und Dateiauswahl für CMS-Formulare und Lightboxes. Eine Klasse, zwei Zeitpunkte: **instant** (sofort hochladen) und **onsubmit** (erst mit Speichern). Drops von anderen Websites (`text/uri-list`) werden mitgeliefert.

Dateien: `lib/class/fs/uploader/fsUploader.php` + `fsUploader.js`. Staging: `public/var/uploader/{id}`.

## Modi

| `data-upload` | Button | Verhalten |
|---|---|---|
| `instant` (Default) | „Upload“ | Preview → Chunks (`storeSpan`) → `onFinish` legt die Datei am Ziel ab. PWK-Library, Lightbox. |
| `onsubmit` | Formular „Speichern“ | File bleibt im JS. Beim Submit zuerst Chunks, dann Form-Submit mit `{feld}_uploader` / `{feld}_uploader_name`. |

Attribut am Wrap um das File-Input (nicht nur am Input):

```html
<div class="contactImageUpload" data-drop-files data-upload="onsubmit">
  <input type="file" name="contact_image" accept="image/jpeg,image/png,image/webp">
</div>
```

`data-drop-files` macht den ganzen Block zur Drop-Zone (lokal + URL von anderen Seiten). Einfügen (Screenshot) geht denselben Weg: Fokus auf die Zone (nicht aufs File-Input), oder Strg+V in der Lightbox außerhalb von Textfeldern.

## Instant (Lightbox, wie PWK Brand)

```php
$this->state->uploadId = $this->state->uploadId ?? str::randomHex();
$this->uploader = fsUploader::get($this->state->uploadId);
echo $this->uploader->toForm();
```

JS nach Lightbox-Update: `uploader = new sys.fsUploader(doc);`

Action `sendData` (Panel):

```php
$this->uploader
  ->onAfterStoreSpan(function ($u) { client::done($u->response()); })
  ->onFinish(function ($u) {
    $file = $u->file; // fsFile in var/uploader/
    // an Ziel verschieben, Record speichern
    $u->reset();
  })
  ->storeSpan();
```

Default bleibt instant — bestehende PWK-Uploads brauchen keine Änderung.

## Form-Integration {#form-integration}

Zum Formular-Modell (GET/POST/PUT, XHR-Default): [form-data.md](./form-data.md).

`onsubmit`: Wrap um das File-Input, nicht nur am Input:

```html
<form action="saveContact">
  <div class="contactImageUpload" data-drop-files data-upload="onsubmit">
    <input type="file" name="contact_image" accept="image/jpeg,image/png,image/webp">
  </div>
  <button type="submit">Speichern</button>
</form>
```

Ablauf:

1. Submit der Form (XHR, sofern kein `target`).
2. `processEvent` → `fsUploader.flushForm(form)`: File/Drop nach `var/uploader/{id}` per **PUT** `storeSpan` (relativ am offenen Panel, nicht `cms/storeSpan`).
3. Hidden `{name}_uploader` / `{name}_uploader_name`.
4. Danach normaler Submit: **ohne `method`** = PUT (`http::form()` + `postedFile()`); `method="post"` = URL + Diff (`http::post()` + `postedFile()`).

```php
$file = fsUploader::postedFile('contact_image');
if ($file) {
  $media = media::create($nodeId);
  $media->filename = str::getSafeName($file['name']);
  $media->moveFile($file['tmp_name']);
  $media->saveRecord();
  if (!empty($file['uploader'])) {
    $file['uploader']->reset();
  }
}
```

`postedFile()` liest die Hidden-Felder aus PUT `form`, POST und PUT-Top-Level, sonst `$_FILES`. New-Records: Insert (ID), dann `postedFile` + `moveFile`.

Instant-Widget (PWK): eigenes `sendData`, kein umgebendes Datensatz-Form — siehe unten.

## Web-Drop und Zwischenablage

`fsUploader.js` bindet document-weit:

1. `dataTransfer.files` / `items` (lokale Dateien)
2. sonst `text/uri-list`, HTML-`img src`, `https?://` in `text/plain`
3. Client-`fetch` (CORS), sonst relative Action `fetchFromUrl` (Server lädt, schreibt nach `var/uploader/`)
4. **Paste:** `clipboardData.files` / `items` (`image/*`). Nicht in `input`/`textarea`/`contenteditable`. Ohne Fokus auf der Zone: erste Bild-Zone in `.cmslightbox`. Screenshot ohne Namen → `screenshot.png`.

`accept` des Inputs wird eingehalten. Loopback / Link-Local / Metadata-IPs sind serverseitig gesperrt.

Die Zone bekommt bei Klick (außer Datei-Input) `tabindex="0"` — Dateidialog bleibt am File-Input.

## Actions

`storeSpan` / `fetchFromUrl` laufen am **aktuell ausgewählten Panel** (`PanelNode::runActions` → `fsUploader::handleAction`). JS sendet relativ (`call.send('storeSpan')`), analog zu PWK `sendData`. Nicht gegen `cms/storeSpan` — das würde die CMS-Root selektieren und die Lightbox/das Formular ersetzen.

| Action | Zweck |
|---|---|
| `storeSpan` | Eine Chunk-Scheibe; Antwort `{ id }`. |
| `fetchFromUrl` | PUT `{ url, accept?, id? }` → `{ ok, id, name, type, href }` oder `{ ok:false, error }`. |

Panel-eigenes `sendData` (PWK) hat Vorrang für Instant-Widgets.

## JS-API

```javascript
new sys.fsUploader(doc, { upload: 'onsubmit' })  // Widget (Lightbox)

sys.fsUploader.resolveDataTransfer(dt, accept)   // Promise<File[] | staged>
sys.fsUploader.fetchRemoteUrl(url, accept)
sys.fsUploader.storeFile(file)                   // Promise<{id,name,type}>
sys.fsUploader.needsFlush(form)
sys.fsUploader.flushForm(form)
```

MML-Dropzonen ohne File-Input: CustomEvent `fsUploaderDrop` mit `{ files, group }`.

## PHP-API

```php
fsUploader::get($id)
fsUploader::isValidId($id)
fsUploader::fetchFromUrl($url, $accept = null, $id = null)
fsUploader::postedFile('field_name')  // ?array tmp_name, name, uploader
$uploader->storeSpan()
$uploader->file       // fsFile
$uploader->reset()    // Temp löschen
```

## Hinweise

- Staging-IDs sind Hex (`str::randomHex`). Nicht raten lassen; nur eingeloggte User können `storeSpan` / `fetchFromUrl`.
- Nach erfolgreichem `moveFile` `reset()` aufrufen. Cancel in Instant-Lightboxes ebenfalls `reset()`.
- Mehrere Felder im selben Formular: jedes Wrap `data-upload="onsubmit"`, unterschiedliche `name`.
- `$_FILES` bleibt Fallback, solange das Attribut fehlt.
- Klasse wird im CMS-Root geladen (`fs::load('fsUploader')` in `panels/cms/panel.php`), damit das JS auf jeder CMS-Seite da ist.
