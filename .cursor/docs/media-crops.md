# Media: Master, Seitenverhältnisse, Größenstufen

Stand 2026-11-17. Wie aus einem hochgeladenen Bild die Bilder einer Seite
werden. Plan und Entscheidungen:
`.cursor/plans/media-crop-ratios.plan.md`, Bedienung des Editors:
`.cursor/plans/media-crop-editor-gui.md` (beide nicht im Repository).

## Begriffe

| Begriff | Bedeutung |
|---|---|
| **Original** | der Upload, wird nie verändert — `var/original/<id-Pfad>/<datei>` |
| **Master** | Original → gespiegelt → gedreht → Master-Crop → Look. Die Pixel des Originals 1:1, nie hochskaliert |
| **Ratio** | Seitenverhältnis aus den Settings, immer ein gekürzter Ganzzahlbruch (`16:9`) |
| **Size** | Megapixel-Stufe aus den Settings |
| **Variante** | Master oder Ratio, in eigenen Pixeln oder in einer Size |
| **Hand-Crop** | im Editor gesetzter Ausschnitt einer Ratio; alles andere wird berechnet |
| **POI** | Point of Interest: Stelle des Bildes, die in berechneten Ausschnitten bleiben soll |

Ein Crop ist **Ratio + Ausrichtung + Scale** (`Media\CropBox`):

- `align_x`, `align_y` (PHP `alignX`/`alignY`, JS und Anfragen `ax`/`ay`):
  die Ausrichtung im Bezugsbild (Ratio-Crop: der Master; Master-Crop: der
  Kasten um das gedrehte Original), wie `background-position` in Prozent.
  Der Punkt bei diesem Anteil des Rahmens liegt auf dem Punkt bei diesem
  Anteil des Bezugsbildes: 0 = bündig links/oben, 1 = bündig rechts/unten,
  0,5 = mittig. Außerhalb von 0–1 ragt der Rahmen über den Rand (gespeichert
  −10 bis 11).
- `scale`: Vergrößerung über „fill in": 1 = größter Ausschnitt der Ratio
  ohne Rand, unter 1 wird Hintergrund sichtbar. Beim gedrehten Master ist 1
  der größte Rahmen, der ganz im gedrehten Bild liegt.

**Warum Ausrichtung statt Mitte:** „Innen" heißt einfach 0 ≤ align ≤ 1 und
scale ≥ 1, egal wie groß Bezugsbild und Rahmen sind. Ändert sich der Master,
behalten die Crops Ausrichtung und Scale und bleiben innen: bündig links
bleibt bündig links.

Umrechnung je Achse, `f` = Rahmenlänge / Bildlänge: Mitte = `a + f·(0,5 − a)`,
`a = (Mitte − f/2) / (1 − f)`. Bei `f = 1` (Rahmen füllt die Achse, bei
Scale 1 immer eine) verschiebt die Ausrichtung nichts; der Wert bleibt
stehen und wirkt erst, wenn sich das Bezugsbild ändert. Der Editor rechnet
beim Ziehen und Zoomen mit der Mitte und behält dort die alte Ausrichtung
(`centredAt()`). Folge: Bei „Hintergrund darf sichtbar werden" lässt sich
ein Rahmen, der auf einer Achse fast genau so lang ist wie das Bild, auf
dieser Achse kaum verschieben — ein Prozent Zoom löst das.

## Settings

`config/media.settings.json`, im Settings-GUI unter „Aspect ratios" und
„Image sizes":

```json
"ratios": ["2:1", "191:100", "16:9", "3:2", "4:3", "1:1", "3:4", "2:3", "9:16"],
"sizes":  [0.5, 1, 2, 5, 10]
```

Fehlen die Schlüssel, gilt diese Vorgabe (`Media\Variants::RATIOS`, `SIZES`).
Ein Projekt mit `settings.php` ruft `Variants::configure([...], [...])` auf;
das GUI bearbeitet die Listen dort nicht.

**Nur Deklariertes wird gerendert.** Eine Adresse mit anderer Ratio oder Size
ist 404 — sonst ließen sich über die URL beliebig viele Dateien erzeugen.

Die kleinste Size ist die **Vorschau**: sie entsteht mit dem Upload und nach
jedem Speichern im Editor (`VariantServer::preview()`).

## Daten

`cms_media_crops`, eine Zeile je Medium und Hand-Crop:

| Spalte | Inhalt |
|---|---|
| `media_crop_format` | `original` = Master, sonst der Name der Ratio (`16:9`) |
| `media_crop_ratio` | Ratio des Zielbildes; beim Master seine freie (`753:1364`) |
| `media_crop_align_x`, `_align_y` | Ausrichtung im Bezugsbild, `decimal(15,12)` |
| `media_crop_scale` | über „fill in" |
| `media_crop_edit` | nur Master, JSON: `{"b":100,"c":100,"s":100,"r":0,"f":0}` — Helligkeit, Kontrast, Sättigung (100 = unverändert), Winkel in Grad, gespiegelt |
| `media_crop_version` | bei jedem Speichern +1 |

`cms_medias`: `media_pois` (JSON, `[{"x":0.31,"y":0.22,"w":0,"h":0,"weight":2}]`
— Mitte und Größe als Anteil des **Originals**), `media_master_width`,
`media_master_height`.

Die Spalten `left`, `top`, `width`, `height`, `auto` und Zeilen mit anderen
Formatnamen stammen vom alten Renderer; der neue liest sie nicht. Ebenso
`media_crop_center_x`/`_center_y` (Mitte statt Ausrichtung, bis 2026-11-17):
nicht umgerechnet, ein Crop nur mit ihnen gilt als automatisch.

**Anlegen der Spalten:** Speichern allein legt nichts an. `Queries\MediaCrops`
nennt die neuen Spalten in seinen Abfragen; eine Datenbank von vorher bekommt
sie dort vom Provisioner, beim ersten Bild.

## Welcher Ausschnitt gilt (`Media\CropResolver`)

Für eine Ratio, in dieser Reihenfolge:

1. Hand-Crop dieser Ratio — unverändert, auch wenn er Hintergrund zeigt.
2. Zwischen zwei Hand-Crops: interpoliert (Ausrichtung linear, Scale geometrisch,
   nach dem Logarithmus der Ratio).
3. POIs vorhanden: größter Ausschnitt, so verschoben, dass sie darin liegen;
   passen nicht alle, gewinnt das Fenster mit dem meisten Gewicht.
4. Hand-Crop nur auf einer Seite: der ganze Master zählt als Anker bei
   seiner eigenen Ratio. Außerhalb aller Anker gilt die Ausrichtung des nächsten,
   die Scale läuft auf „fill in" zu und erreicht es bei der äußersten
   deklarierten Ratio dieser Seite (die Enden des Reglers zeigen so viel Bild
   wie möglich). Ohne Liste der deklarierten Ratios: gleich „fill in".
5. Sonst zentriert, Scale 1.

Berechnete Ausschnitte liegen immer im Master. Die Fälle stehen als Daten in
`tests/media-crop-resolver.cases.json`; PHP und Browser müssen sie bestehen.

## Dateien und Adressen

Ein Ordner je Medium, die Variante im Dateinamen:

```
var/formats/000/42/bild--master.webp       Master, eigene Pixel
var/formats/000/42/bild--2mp.webp          Master, ca. 2 MP
var/formats/000/42/bild--16by9.webp        16:9, eigene Pixel
var/formats/000/42/bild--2mp16by9.webp     16:9, ca. 2 MP
var/formats/000/42/bild--original.webp     unbearbeitetes Original, ca. 2 MP (für den Editor)
```

- Typen: `webp`, `jpg`, `png` — die Endung der Anfrage entscheidet.
- Eine fehlende Datei entsteht bei der ersten Anfrage: `media.php` →
  `Media\VariantServer` → `Media\VariantRenderer`. Die nächste Anfrage
  liefert der Webserver direkt, mit `immutable` für ein Jahr.
- Deshalb trägt jede Adresse **`?v=<Token>`** (`CropSet::version()`): das
  Token ändert sich mit jedem Crop, Look, POI und neuem Original.
- Jede Änderung löscht **alle** Varianten des Mediums
  (`Files::removeVariants()`), weil ein Hand-Crop die berechneten Nachbarn
  mitbewegt.

### Platzgrenze

Es werden höchstens drei Bilder gleichzeitig gemacht, für alle Besucher
zusammen (`VariantServer::SLOTS`, `MEDIA_RENDER_SLOTS` überschreibt;
Lock-Dateien in `var/formats/.slots/`). Eine Anfrage mit `?queue=1` (der
Lader) bekommt ohne freien Platz sofort `503` + `Retry-After`; jede andere
wartet bis 25 Sekunden.

## Templates

```php
$media->getCrop('16:9')->toImgHtml()                 // <img> mit srcset, sizes, width, height
$media->getCrop('16:9')->toImgHtml(['class' => 'hero', 'sizes' => '(min-width: 900px) 50vw, 100vw'])
$media->getCrop('16:9')->toPictureHtml(['(max-width: 600px)' => '1:1'])   // andere Ratio je Media Query
$media->getCrop('16:9')->getUrl(2)                   // eine Adresse, ca. 2 MP
$media->getCrop()->toImgHtml()                       // ohne Ratio: der Master
$media->toAutoHtml(['style' => 'width:100%;height:40vh'])   // die Box entscheidet Ratio und Size
```

- Die Seite nennt die Ratio, **der Browser wählt die Größe** (`srcset` mit
  Breiten).
- Eine nicht deklarierte Ratio wird in der nächsten deklarierten gezeigt.
- `toAutoHtml()`: das Template gibt dem Element per CSS eine Größe; das Bild
  startet erst nach dem Script — für das Hauptbild einer Seite die Ratio
  nennen.
- Ein svg oder Dokument ist sein Original.
- Die Crops aller Medien einer Seite werden zusammen gelesen
  (`MediaCrops::announce()` / `lazy()`).

### Benannte Formate

`$media->setFormat('fullWidth')` gibt es weiter, als **Alias**: der Name
steht für Ratio + Size (`Variants::alias()`), ohne eigenen Crop und ohne
exakte Pixelmaße.

| Format in den Settings | wird zu |
|---|---|
| `megapixels: 4` | Master, `5mp` |
| `megapixels: 3, aspect_ratio: 16:9` | `5mp16by9` |
| `width: 1920, height: 1920` | `5mp1by1` |
| `width: 1788, height: 2160` | nächste deklarierte Ratio: `5mp3by4` |
| `width: 1200, height: 630` (Share-Bild) | `1mp191by100` |

Alte Adressen (`var/formats/<format>/…`) leitet `media.php` auf die neue
weiter (302), sofern die alte Datei nicht mehr auf der Platte liegt — eine
vorhandene liefert der Webserver weiter aus.

**Schalter zurück:** `"legacyFormats": true` in `media.settings.json` oder
`define('MEDIA_LEGACY_FORMATS', true)`. Dann werden benannte Formate wieder
vom alten Renderer gemacht, mit ihren alten Crops, und der alte Editor ist
erreichbar. Es wird in keiner Richtung etwas umgerechnet.

## Im Browser

`public/js/core/media.js` (`sys.media`), auf jeder Seite:

- **Wartende Bilder:** fehlt die Datei, schreibt `toImgHtml()` einen
  Platzhalter mit der kleinen Master-Stufe als Hintergrund, per CSS auf den
  Ausschnitt geschoben. Der Lader fragt die echten Dateien an — sichtbare
  zuerst, drei gleichzeitig, bei 503 später wieder.
- **`sizes`:** Bilder mit `sizes="auto, …"` bekommen ihre gemessene Breite.
- **`toAutoHtml()`:** nächste deklarierte Ratio zur Box, kleinste Size mit
  genug echten Pixeln.

Bilder über `setFormat()` haben nur ein `src`: ohne Vorschau, sie warten auf
einen freien Platz.

## Editor

Datei-Editor (`admin/pages/editor/file/`), `public/js/gui/cropEditor.js`
(`sys.cropEditor`), Rechnung in `public/js/gui/cropMath.js`
(`sys.cropMath` — dieselbe wie auf dem Server).

- **Master-Modus:** zeigt das Original; Ratio per Klick oder frei am Regler,
  Drehen, Spiegeln, Helligkeit / Kontrast / Sättigung. Der Dreh-Knopf links
  nimmt nur das Werkzeug auf (ziehen = freier Winkel um den Fixpunkt); der
  Knopf oben rechts neben ✓ dreht auf den nächsten rechten Winkel im
  Uhrzeigersinn (30,4° → 90°, nicht 120,4°) — die verlustfreien Drehungen.
- **Gedreht ohne eigenen Master-Crop** gilt der Rahmen mit der größten
  Fläche im gedrehten Bild (`MasterGeometry::largest()`, `cropMath.largest`),
  geschlossen in zwei Fällen: nahe dem rechten Winkel berühren alle vier
  Ecken eine Kante, nahe der Diagonale stoßen zwei Ecken an die langen
  Kanten. Die Fälle gehen ohne Sprung ineinander über. Das Format folgt dem
  Winkel: bei 0° das Bild, erst breiter, bei 45° 1:1, bei 90° hochkant.
- **Festes Format bei Scale 1** bleibt beim Drehen Scale 1: der größte
  Rahmen dieses Formats im gedrehten Bild. Ein anderer Scale behält die
  Pixel des Rahmens.
- **Crops-Modus:** zeigt den Master; eine Ratio je Klick am Regler oder durch
  Ziehen des Knopfs. Beim Ziehen folgt der Rahmen der Interpolation zwischen
  den beiden Nachbar-Ratios (`cropAt()`), beim Loslassen gleitet der Knopf
  auf die nächste deklarierte Ratio. Dunkle Symbole sind Hand-Crops, das
  gelbe ist das gezeigte.
- **Regler:** die Kreise stehen in einer Linie und überlappen; 1:1 liegt
  ganz hinten, die Enden vorn, Hover und Auswahl holen nach vorn. Links der
  Name; überdeckte Namen erscheinen nur beim Hover.
- **Zurücksetzen:** jeder Farbregler hat einen Knopf „unverändert"; der
  Knopf unten in der linken Leiste setzt den ganzen Master aufs Original
  (kein Crop, nicht gedreht, nicht gespiegelt, kein Look) — als Änderung,
  die mit ✓ übernommen wird.
- **CSS-Falle:** `50_forms.css` kommt nach `14_cropEditor.css` und setzt für
  `.cms .button` / `.cms button` `display: inline-flex`, Ränder und
  `box-shadow: none !important`. Eigene Regeln für Knöpfe brauchen mehr
  Spezifität (`.ceStage > .ceMore`), Schatten gehen nur über `filter`.
- Der Rahmen steht, das Bild bewegt sich: ziehen, Rad oder zwei Finger.
- **Zoom-Werkzeug** und **Dreh-Werkzeug** arbeiten um einen Fixpunkt
  (Fadenkreuz, rastet an den neun Punkten des Rahmens); Klick auf das
  Fadenkreuz oder Escape legt sie weg.
- **POIs:** Klick setzt, ziehen verschiebt, die Leiste gewichtet und löscht;
  sie werden sofort gespeichert. Beim ersten POI fragt der Editor, ob die
  Hand-Crops gelöscht werden sollen.
- „Bildrand bleibt außerhalb" / „Hintergrund darf sichtbar werden", fit-in,
  fill-in, „Crop auf Automatik" (ohne Rückfrage).

Endpunkte: beantwortet von `Media\CropEditorEndpoint` (system), aufgerufen
aus dem Panel der Anwendung, das vorher die Rechte prüft — in cms
`…/editor/file/<aktion>?target_id=<Media-Node>`. Jeder antwortet mit dem
Zustand danach (`Media\CropEdits::state()`):

| Aktion | Parameter |
|---|---|
| `cropState` | — |
| `saveMasterCrop` | `ratio ax ay scale` oder `auto=1`; `brightness contrast saturation angle flip` |
| `saveRatioCrop` | `ratio ax ay scale` |
| `resetCrop` | `format=<ratio>` oder `format=original` |
| `savePois` | `pois=<json>`, optional `drop_crops=1` |

Eine andere Anwendung (z. B. spaceticker, ohne cms) bindet den Editor so ein:

```php
// in ihrem Panel, nach der eigenen Rechteprüfung
if (CropEditorEndpoint::handles($action)) {
    CropEditorEndpoint::send(CropEditorEndpoint::answer($action, $scope, $mediaId,
        static fn(string $name, mixed $default = null): mixed => http::get($name, $default)));
}
```

```js
sys.cropEditor.open({stage: element, url: '<Adresse dieses Panels>/', nodeId: <wie das Panel das Medium findet>});
```

Ein neuer Master lässt die Hand-Crops stehen: Ausrichtung und Scale bleiben,
sie gelten jetzt im neuen Master (`CropEdits::master()`). Nur Drehen um
Vierteldrehungen und Spiegeln nehmen die Ausrichtung mit: Ein Crop links im
Bild sitzt nach 90° oben (`CropEdits::reoriented()`, ein schräger Winkel
zählt als nächste Vierteldrehung). `clipped` bleibt leer.

## Klassen

| Klasse | Aufgabe |
|---|---|
| `Media\Ratio`, `CropBox` | Werte: Ratio; Crop als Ratio + Ausrichtung + Scale |
| `Media\CropResolver` | Ausschnitt einer Ratio aus Hand-Crops und POIs |
| `Media\MasterGeometry` | Original ↔ Master, mit Spiegeln und beliebigem Winkel |
| `Media\Variants` | deklarierte Ratios und Sizes, Dateinamen, Aliasse, Schalter |
| `Media\VariantRenderer` | Master, Ausschnitt, Skalieren, Schreiben (Imagick) |
| `Media\VariantServer` | Anfrage einer fehlenden Datei, Platzgrenze, Vorschau, Umleitung |
| `Media\CropEdits` | was der Editor speichert und erfährt |
| `Media\CropEditorEndpoint` | die fünf Anfragen des Editors, für jede Anwendung gleich |
| `Queries\MediaCrops\MediaCrops`, `CropSet` | Medien mit ihren Crop-Zeilen |
| `Tables\MediaCrops\Operator` | `put()` / `box()`, `look()` / `withLook()` |
| `Tables\Medias\Picture` | Ausgabe: `<img>`, `<picture>`, Adressen |
| `Tables\Medias\Files` | `variantPath()`, `variants()`, `removeVariants()` |

## Tests

```
php tests/media-crop-geometry.php       Ratio, CropBox, Resolver, MasterGeometry
php tests/media-variants.php            Ratios/Sizes, Dateinamen, Aliasse, Crop-Zeilen, Look
php tests/media-variants-settings.php   die Listen im Settings-GUI
php tests/media-variant-render.php      Renderer mit echtem Imagick auf gezeichneten Bildern
php tests/media-picture.php             Ausgabe: srcset, Vorschau, toAutoHtml
php tests/media-crop-edits.php          Editor-Logik ohne Datenbank
node tests/media-loader.mjs             Lader: Auswahl, Warteschlange, 503
node tests/media-crop-math.mjs          Browser-Rechnung gegen die des Servers
node tests/media-crop-editor.mjs        Bedienlogik des Editors ohne Browser
```

## Offen

- **Drehen:** Modell „größte Fläche" seit 2026-11-17. Offen, ob das Format
  zusätzlich zwischen dem bei 0° und 90° begrenzt werden soll (dann geht das
  Format nicht erst breiter, kostet bei kleinen Winkeln 3–7 % Fläche).
- **Aufräumen:** alter Renderer (`mediaImage`, `mediaEditor`,
  `imageEditor.js`), Alias-Ordner und Formatordner, `var/adjust/look.json`,
  die alten Crop-Zeilen und Spalten, Live-Area — erst, wenn der Schalter
  zurück nicht mehr gebraucht wird. Dann auch 301 statt 302.
- **Templates** von `setFormat()` auf `getCrop()` umstellen.
- **Zuschneiden direkt in der Seite** (`runImageEditor`, inline): erkennt die
  neuen Adressen nicht und tut nichts.
- **Erster Aufruf nach dem Umschalten:** jedes Medium braucht einmal seinen
  Master (etwa eine Sekunde je großem Bild, drei gleichzeitig). Eine
  Medienliste mit hunderten Bildern zeigt beim ersten Mal Lücken, bis alle
  gemacht sind; ein Vorwärm-Lauf fehlt.
- **Backup:** der Master in der kleinsten Size soll mitreisen.
- Der Lader und der Editor sind nur teilweise in einem echten Browser
  geprüft (kein Playwright in der Agenten-Umgebung).
