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

Ein Crop ist **Ratio + Ausrichtung + Scale + Basis** (`Media\CropBox`),
gelesen gegen ein Bezugsbild (Ratio-Crop: der Master; Master-Crop: der Kasten
um das gedrehte Original). Die Basis sagt, worauf sich Ausrichtung und Scale
beziehen:

| | `fill` (Bildrand bleibt außerhalb) | `fit` (Hintergrund darf sichtbar werden) |
|---|---|---|
| Berührpunkt | Punkt a des Rahmens auf Punkt a des Bildes (wie `background-position` in Prozent) | Punkt a des Rahmens auf Punkt 1 − a des Bildes |
| `a = 0` | bündig links / oben | Bild liegt links / oben neben dem Rahmen, berührt ihn (leer) |
| `a = 1` | bündig rechts / unten | Bild liegt rechts / unten neben dem Rahmen (leer) |
| `a = 0,5` | mittig | mittig |
| `scale = 1` | „fill in": größter Ausschnitt der Ratio ohne Rand | „fit in": das ganze Bild im Rahmen |
| erlaubt | 0 ≤ a ≤ 1, scale ≥ 1 — dann liegt der Rahmen innen | 0 ≤ a ≤ 1, scale beliebig |

- `align_x`, `align_y` (PHP `alignX`/`alignY`, JS und Anfragen `ax`/`ay`),
  in beiden Basen 0–1: über diesen Bereich geht der Rahmen von einem Ende
  zum anderen.
- `scale`: Vergrößerung über Scale 1 der Basis. Beim gedrehten Master ist
  „fill in" der größte Rahmen, der ganz im gedrehten Bild liegt, „fit in"
  der Rahmen um den Kasten des gedrehten Bildes.
- `basis`: `fill` oder `fit`, ohne Angabe `fill`. Berechnete Crops sind
  `fill`, außer zwischen lauter `fit`-Crops (siehe Resolver).

**Warum Ausrichtung statt Mitte:** Ändert sich das Bezugsbild (ein neuer
Master unter den Crops), behält ein Crop Ausrichtung, Scale und Basis:
bündig links bleibt bündig links und innen, „fit in" mittig bleibt „fit in"
mittig.

**Warum zwei Basen:** Umrechnung je Achse, `f` = Rahmenlänge / Bildlänge:

```
fill:  Mitte = a + f·(0,5 − a)        a = (Mitte − f/2) / (1 − f)
fit:   Mitte = 1 + f/2 − a·(1 + f)    a = (1 + f/2 − Mitte) / (1 + f)
```

In `fill` ist `f = 1` singulär: Der Rahmen füllt die Achse (bei Scale 1
immer eine), es gibt nichts zu verschieben, der Wert bleibt stehen und wirkt
erst, wenn sich das Bezugsbild ändert. In `fit` ist `1 + f` nie 0: Der
Rahmen lässt sich auf jeder Achse verschieben, auch wenn er genau so lang ist
wie das Bild (Master = Original). Der Editor rechnet beim Ziehen und Zoomen
mit der Mitte (`centredAt()`).

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
| `media_crop_align_x`, `_align_y` | Ausrichtung im Bezugsbild, 0–1, `decimal(15,12)` |
| `media_crop_scale` | über Scale 1 der Basis |
| `media_crop_basis` | `fill` oder `fit`, `varchar(4)`, Vorgabe `fill` |
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
   nach dem Logarithmus der Ratio). Beide in derselben Basis: in dieser
   Basis. Verschieden: beide erst als `fill` gelesen (`CropBox::asFill()`,
   derselbe Rahmen), das Ergebnis ist `fill` und liegt innen.
3. POIs vorhanden: größter Ausschnitt, so verschoben, dass sie darin liegen;
   passen nicht alle, gewinnt das Fenster mit dem meisten Gewicht.
4. Hand-Crop nur auf einer Seite: der ganze Master zählt als Anker bei
   seiner eigenen Ratio, in der Basis des nächsten Hand-Crops (mittig bei
   Scale 1 ist er in beiden derselbe Rahmen). Außerhalb aller Anker ist die
   äußerste deklarierte Ratio dieser Seite **automatisch**: mittig, Scale 1,
   in der Basis des nächsten Hand-Crops („fill in" bzw. „fit in"); sie erbt
   nicht dessen Ausrichtung. Dazwischen wird vom nächsten Hand-Crop zu ihr
   hin interpoliert. Ohne Liste der deklarierten Ratios: gleich automatisch.
5. Sonst zentriert, Scale 1, `fill`.

Berechnete Ausschnitte in `fill` liegen immer im Master; `fit` entsteht nur
aus `fit`-Hand-Crops. Die Fälle stehen als Daten in
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

- **Zwei Modi als Reiter:** „Edit master" und „Edit crops" (Symbol +
  Beschriftung, die Reiter von `50_forms.css`, `[role="tab"]`). Sie stehen im
  Kopf des Fensters hinter dem Titel (`.cmslightbox > section > header`,
  Option `header`: ein anderes Element, `null` = oben im Editor); auf dem
  Telefon nur die Symbole.
- **Linke Leiste, von oben:** Basis fill/fit (setzt zurück), Zurücksetzen
  im Modus (`arrow-rotate-left`), Zoom, Drehen, Spiegeln — Trennlinie —
  Helligkeit, Kontrast, Sättigung; ganz unten „zurück aufs Original". Die
  rechte Leiste ist nur noch der Regler. Rechts oben im Bild: 90°, ✓, ✕.
- **Was nicht geht, wird nicht gezeigt** (statt ausgegraut): Look, Drehen,
  Spiegeln nur im Master-Modus, „zurück aufs Original" nur, wenn es etwas
  zurückzusetzen gibt, ✕ nur, wenn etwas geändert ist; ✓ dann und zusätzlich
  bei einem berechneten Crop (s. u.).
- **Speichern:** ✓ oben rechts speichert alle Änderungen des Modus, ✕ verwirft
  alle. Im Crops-Modus warten Änderungen beim Wechsel der Ratio
  (`pending`); die berechneten Crops daneben rechnet der Editor sofort neu,
  nach denselben Regeln wie der Server (`cropMath.resolve`, POIs wie
  `poisToMaster()`). ✓ schickt alle in einer Anfrage (`saveRatioCrops`).
  Zurücksetzen auf Automatik ist ebenfalls eine solche Änderung.
  **Berechneten Crop behalten:** ist nichts geändert und der Crop auf dem
  Schirm berechnet (interpoliert, automatisch …), zeigt sich ✓ allein — er
  speichert den Crop so, wie er zu sehen ist, als Hand-Crop; Änderungen der
  Nachbarn bewegen ihn dann nicht mehr (`pinnable()`, auch Enter). Rückfrage nur
  beim Verlassen: Moduswechsel, Schließen, ein Link oder Schließknopf
  außerhalb des Editors (Klick-Wächter in der Capture-Phase), Verlassen der
  Seite (`beforeunload`). Escape mit Werkzeug legt es weg, sonst schließt es
  wie gewohnt (mit Rückfrage).
- **Master-Modus:** zeigt das Original; Ratio per Klick oder frei am Regler,
  Drehen, Spiegeln, Helligkeit / Kontrast / Sättigung. Der Dreh-Knopf links
  nimmt nur das Werkzeug auf (ziehen = freier Winkel um den Fixpunkt); der
  Knopf oben rechts neben ✓ (Tabler `file-orientation`) dreht auf den
  nächsten rechten Winkel im Uhrzeigersinn (30,4° → 90°, nicht 120,4°) —
  die verlustfreien Drehungen. Gedreht wird immer um die Mitte des Rahmens;
  dieses Fadenkreuz lässt sich nicht verschieben, ein Klick legt das
  Werkzeug weg.
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
  (Fadenkreuz; beim Zoom verschiebbar, rastet an den neun Punkten des
  Rahmens); Klick auf das Fadenkreuz oder Escape legt sie weg. Mit
  gedrückter **Shift**-Taste ist es in beiden Werkzeugen die Hand: Ziehen
  verschiebt das Bild.
- **Rad:** zoomt stufenlos. **Strg + Rad:** in Schritten von 0,05 der
  Scale, auf das nächste Vielfache (1,37 → 1,4 → 1,45;
  `editor.step`). Kleine Schritte eines Touchpad-Pinch werden zu einer
  Raste gesammelt.
- **POIs:** vorerst ausgeblendet (`editor.pois = false`: kein Werkzeug, keine
  Marken); Code und Konzept bleiben, gespeicherte POIs wirken weiter. Mit
  dem Werkzeug: Klick setzt, ziehen verschiebt, die Leiste gewichtet und
  löscht; sie werden sofort gespeichert. Beim ersten POI fragt der Editor,
  ob die Hand-Crops gelöscht werden sollen.
- **Basis:** der oberste Knopf der linken Leiste, in beiden Modi; das
  Symbol zeigt die Basis des Rahmens (`maximize` = fill in, `maximize-off`
  = fit in). Umschalten setzt den Rahmen zurück auf mittig, Scale 1 — „fill
  in" bzw. „fit in", so zeigt sich gleich, was der Modus heißt. Ein Master
  ohne Crop ist `fill`; `fit` macht ihn zum Crop, zurück ohne weitere
  Änderung ist er wieder automatisch. Ersetzt die früheren Knöpfe Schloss
  zu/offen, fit-in und fill-in.
- **Zurücksetzen im Modus** (zweiter Knopf): Master — Automatik, der größte
  Rahmen im Bild beim aktuellen Winkel, Look bleibt (ersetzt den früheren
  Knopf „Ratio des Bildes"); Crops — der Crop der Ratio wieder berechnet.
  Nur sichtbar, wenn es etwas zurückzusetzen gibt; eine Änderung wie jede
  andere, übernommen mit ✓.

Endpunkte: beantwortet von `Media\CropEditorEndpoint` (system), aufgerufen
aus dem Panel der Anwendung, das vorher die Rechte prüft — in cms
`…/editor/file/<aktion>?target_id=<Media-Node>`. Jeder antwortet mit dem
Zustand danach (`Media\CropEdits::state()`):

| Aktion | Parameter |
|---|---|
| `cropState` | — |
| `saveMasterCrop` | `ratio ax ay scale basis` oder `auto=1`; `brightness contrast saturation angle flip` |
| `saveRatioCrop` | `ratio ax ay scale basis` |
| `saveRatioCrops` | `crops=<json>`: `[{ratio, ax, ay, scale, basis}` oder `{ratio, auto: true}, …]` — alle oder keiner; der Editor nutzt nur diesen |

`basis` ist `fill` oder `fit`, ohne Angabe `fill`. Was die Basis nicht
erlaubt (Ausrichtung außerhalb 0–1, in `fill` Scale unter 1), ist 422.
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

Ein neuer Master lässt die Hand-Crops stehen: Ausrichtung, Scale und Basis
bleiben, sie gelten jetzt im neuen Master (`CropEdits::master()`). Nur Drehen um
Vierteldrehungen und Spiegeln nehmen die Ausrichtung mit: Ein Crop links im
Bild sitzt nach 90° oben (`CropEdits::reoriented()`, ein schräger Winkel
zählt als nächste Vierteldrehung). `clipped` bleibt leer.

## Klassen

| Klasse | Aufgabe |
|---|---|
| `Media\Ratio`, `CropBox` | Werte: Ratio; Crop als Ratio + Ausrichtung + Scale + Basis |
| `Media\CropResolver` | Ausschnitt einer Ratio aus Hand-Crops und POIs |
| `Media\MasterGeometry` | Original ↔ Master, mit Spiegeln und beliebigem Winkel |
| `Media\Variants` | deklarierte Ratios und Sizes, Dateinamen, Aliasse, Schalter |
| `Media\VariantRenderer` | Master, Ausschnitt, Skalieren, Schreiben (Imagick) |
| `Media\VariantServer` | Anfrage einer fehlenden Datei, Platzgrenze, Vorschau, Umleitung |
| `Media\CropEdits` | was der Editor speichert und erfährt |
| `Media\CropEditorEndpoint` | die Anfragen des Editors, für jede Anwendung gleich |
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

## Fallen

- **Ende des Reglers erbt die Ausrichtung des nächsten Hand-Crops**
  (Symptom: 9:16 sitzt am Rand wie 4:3, alle Ratios dahinter auch; zweimal
  aufgetreten). Ursache: der Randanker bekam Ausrichtung des Nachbarn statt
  mittig. Lösung: die Enden sind automatisch (mittig, Scale 1) — Fälle in
  `media-crop-resolver.cases.json` („the outermost declared ratio itself").
  Nach einer Regeländerung im Resolver sind schon gemachte Varianten
  veraltet, bis das Medium neu gespeichert oder `var/formats/` geleert wird.

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
