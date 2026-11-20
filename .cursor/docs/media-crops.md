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
- `basis`: `fill` oder `fit`, ohne Angabe `fill`. Automatische Crops haben
  die Basis des Masters (`CropSet::basis()`): ein Master „fit in" hat seine
  Crops „fit in" (siehe Resolver).

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

In `config.json`, im Admin unter Settings › System › Config, Block „Media"
(Schema `system/config/config.schema.json`, `x-project`):

```json
"packages": { "system": { "media": {
  "ratios":  ["2:1", "191:100", "16:9", "3:2", "4:3", "1:1", "3:4", "2:3", "9:16"],
  "sizes":   [0.5, 1, 2, 5, 10],
  "aliases": { "fullWidth": { "megapixels": 5 }, "square": { "width": 800, "height": 800 } },
  "videoFormats": { … },          // VIDEO_FORMATS
  "external": ["YouTube", "Vimeo"] // EXTERNAL_MEDIAS
} } }
```

Die Vorgaben stehen im Schema (gleich `Media\Variants::RATIOS`, `SIZES`, die
ohne Config gelten, etwa in Tests). Ein Projekt mit `settings.php` kann
weiter `Variants::configure([...], [...])` und `mediaFormats::add()` rufen.
Die **Media-Gruppen** stehen in cms: `packages.cms.media.groups` (Settings ›
CMS › Config, Block „Media"); ohne sie gilt `$media_groups` einer settings.php.

Die früheren **Media Settings** (`config/media.settings.json`, eigenes Panel)
sind entfallen (2026-11-19). `bin/config.php --import-settings` zieht eine
noch vorhandene Datei nach config.json und löscht sie; `images`
(JPEG_QUALITY …) und `legacyFormats` liest nichts mehr. Ein altes
Backup-Archiv mit der Datei wird ohne sie zurückgespielt. Erledigt in
`spaceticker.app` und `rebekka-degott.com`.

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
   Basis. Verschieden: beide erst als `fit` gelesen (`CropBox::asFit()`,
   derselbe Rahmen), das Ergebnis ist `fit`. Als `fill` gelesen würde ein
   Rahmen mit Hintergrund bis kurz vor das `fit`-Ende ins Bild gezogen und
   spränge dort (auch beim Gleiten des Reglers).
3. POIs vorhanden und Master in `fill`: größter Ausschnitt, so verschoben,
   dass sie darin liegen; passen nicht alle, gewinnt das Fenster mit dem
   meisten Gewicht. Master in `fit`: entfällt, der ganze Master ist zu sehen.
4. Hand-Crop nur auf einer Seite: der ganze Master zählt als Anker bei
   seiner eigenen Ratio, in der Basis des nächsten Hand-Crops (mittig bei
   Scale 1 ist er in beiden derselbe Rahmen). Außerhalb aller Anker ist die
   äußerste deklarierte Ratio dieser Seite **automatisch**: mittig, Scale 1,
   in der Basis des Masters („fill in" bzw. „fit in"); sie erbt vom nächsten
   Hand-Crop weder Ausrichtung noch Basis. Dazwischen wird vom nächsten
   Hand-Crop zu ihr hin interpoliert. Ohne Liste der deklarierten Ratios:
   gleich automatisch.
5. Sonst zentriert, Scale 1, in der Basis des Masters.

Berechnete Ausschnitte in `fill` liegen immer im Master; `fit` entsteht aus
`fit`-Hand-Crops oder einem Master in `fit`. Die Fälle stehen als Daten in
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
exakte Pixelmaße. Die Namen: `packages.system.media.aliases` (beim Laden
in `mediaFormats`, `Variants::addAliases()`), die des Systems (`portrait`,
`shareImage` …) und was eine settings.php noch hinzufügt. Ein unbekannter
Name ist der Master in 1 MP.

| Format | wird zu |
|---|---|
| `megapixels: 4` | Master, `5mp` |
| `megapixels: 3, aspect_ratio: 16:9` | `5mp16by9` |
| `width: 1920, height: 1920` | `5mp1by1` |
| `width: 1788, height: 2160` | nächste deklarierte Ratio: `5mp3by4` |
| `width: 1200, height: 630` (Share-Bild) | `1mp191by100` |

Seit 2026-11-19 gibt es **keinen Schalter zurück** zum alten Renderer mehr
(`Variants::live()`, `legacyFormats`, `MEDIA_LEGACY_FORMATS` entfallen), den
alten Editor im Datei-Editor nicht mehr, und alte Adressen
(`var/formats/<format>/…`) werden nicht mehr auf die neuen umgeleitet: eine
vorhandene alte Datei liefert der Webserver, eine fehlende macht noch der
alte Teil von `media.php`. Das Aufräumen des alten Renderers steht unter
„Offen".

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
- **Der Ort sagt, worauf etwas wirkt** (2026-11-20):
  - **Links der Regler:** was bearbeitet wird (im Crops-Modus Navigation).
  - **Mitte das Bild; seine Ecke oben rechts** (nur Crops-Modus) wirkt nur
    auf den gezeigten Crop: ↺ zurück auf automatisch (wieder berechnet, bei
    einem Hand-Crop also Löschen) — oder 📌 (`pin`) bei einem berechneten,
    unveränderten Crop: ihn so behalten, wie er zu sehen ist, als Hand-Crop;
    Änderungen der Nachbarn bewegen ihn dann nicht mehr (`pinnable()`,
    `pin()`). Immer nur einer der beiden.
  - **Rechts oben die Werkzeuge**, zwei je Zeile: Basis fill/fit (doppelt
    breit), Pan (Tabler `hand-stop`) und Zoom (`resize`) als Auswahl —
    einer ist immer gewählt, Pan zu Beginn; dann Spiegeln, Drehen, 90°,
    darunter doppelt breit **Reset Geometrie** — Trennlinie — Helligkeit, Kontrast,
    Sättigung, darunter **Reset Farbe**. Die beiden Resets gibt es nur im
    Master; sie bleiben sichtbar und sind ausgegraut, solange die Gruppe
    unverändert ist (`geometryUnchanged()`, `lookUnchanged()`). Reset
    Geometrie: automatisch (das ganze Bild), nicht gedreht, nicht gespiegelt,
    `fill`, der Look bleibt (`resetGeometry()`); Reset Farbe: Look 100/100/100,
    die Geometrie bleibt (`resetLook()`). Der Regler eines Farbwerkzeugs
    öffnet links daneben. Im Crops-Modus unter Pan/Zoom doppelt breit
    **Reset all**: alle Hand-Crops zurück auf automatisch, als ausstehende
    Änderung wie jede andere (`resetCrops()`); ausgegraut, solange kein Crop
    von Hand gesetzt, ausstehend oder auf dem Bild verändert ist (`anyCrop()`).
  - **Basis-Symbol** (eigenes SVG, `BASIS` in cropEditor.js): gestrichelt
    das Bild (16:9, halbe Deckkraft), durchgezogen der Crop (1:1), dünne
    Linien, kleine Radien. fill: der Crop im Bild, fit: das Bild im Crop.
    Hover zeigt animiert den anderen Zustand — das Bild schrumpft auf 9/16,
    der Crop bleibt; nach dem Umschalten erst wieder, wenn die Maus den Knopf
    verlassen hat (`.is-switched`; ohne Animation bei `prefers-reduced-motion`).
  - **Rechts unten „Discard" und „Apply"** (Text, kein Symbol; „Apply (n)",
    wenn mehrere Crops warten) für alles, was im Modus wartet; nur sichtbar,
    wenn etwas wartet.
  - Telefon: Regler unten quer, Werkzeuge im Menü, Discard/Apply als Zeile
    ganz unten.
- **Was nicht geht, wird nicht gezeigt** (statt ausgegraut): Look, Drehen,
  90°, Spiegeln nur im Master-Modus.
- **Speichern:** Im Crops-Modus warten Änderungen beim Wechsel der Ratio
  (`pending`); die berechneten Crops daneben rechnet der Editor sofort neu,
  nach denselben Regeln wie der Server (`cropMath.resolve`, POIs wie
  `poisToMaster()`). Apply schickt alle in einer Anfrage (`saveRatioCrops`).
  Zurücksetzen auf Automatik und 📌 sind ebenfalls solche Änderungen. Enter
  = Apply. Rückfrage nur
  beim Verlassen: Moduswechsel, Schließen, ein Link oder Schließknopf
  außerhalb des Editors (Klick-Wächter in der Capture-Phase), Verlassen der
  Seite (`beforeunload`). Escape mit Werkzeug legt es weg, sonst schließt es
  wie gewohnt (mit Rückfrage).
- **Master-Modus:** zeigt das Original; Ratio per Klick oder frei am Regler,
  Drehen, Spiegeln, Helligkeit / Kontrast / Sättigung. Der Dreh-Knopf
  nimmt nur das Werkzeug auf (ziehen = freier Winkel um den Fixpunkt; der
  Winkel steht in der Statuszeile); der 90°-Knopf
  (Tabler `file-orientation`) dreht auf den nächsten rechten Winkel im
  Uhrzeigersinn (30,4° → 90°, nicht 120,4°) — die verlustfreien Drehungen.
  Gedreht wird immer um die Mitte des Rahmens; dieses Fadenkreuz lässt sich
  nicht verschieben, ein Klick legt das Werkzeug weg. Spiegeln ist ein
  Schalter (gedrückt = gespiegelt). Jeder Farbregler hat zusätzlich ↺
  „unverändert". Alles aufs Original = Reset Geometrie + Reset Farbe.
- **Der Regler im Master** stellt die Ratio des Masters ein: ein Druck
  allein ändert nichts, erst Ziehen; eine Ratio, die er schon hat, ist keine
  Änderung, der Master bleibt automatisch (`unchangedMaster()`).
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
- **CSS-Falle:** `50_forms.css` kommt nach `14_cropEditor.css` und setzt für
  `.cms .button` / `.cms button` `display: inline-flex`, Ränder und
  `box-shadow: none !important`. Eigene Regeln für Knöpfe brauchen mehr
  Spezifität (`.ceStage > .ceMore`), Schatten gehen nur über `filter`.
- Der Rahmen steht, das Bild bewegt sich: ziehen, Rad oder zwei Finger.
- **Zoom-Werkzeug** und **Dreh-Werkzeug** arbeiten um einen Fixpunkt
  (Fadenkreuz; beim Zoom verschiebbar, rastet an den neun Punkten des
  Rahmens — beim Ziehen des Kreuzes als Kreise gezeigt, Durchmesser =
  Fangweite `editor.snap`); Klick auf das Fadenkreuz oder Escape legt sie
  weg (zurück auf Pan). Beim Zoom ist der Zeiger ein Pfeil vom Fixpunkt
  weg, in 45°-Schritten (`ARROWS`, `arrow()`). Mit
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
- **Basis:** der erste Knopf der Werkzeuge, in beiden Modi; das
  Symbol zeigt die Basis des Rahmens (eigenes SVG, s. o.). Umschalten setzt den Rahmen zurück auf mittig, Scale 1 — „fill
  in" bzw. „fit in", so zeigt sich gleich, was der Modus heißt. Ein Master
  ohne Crop ist `fill`; `fit` macht ihn zum Crop, zurück ohne weitere
  Änderung ist er wieder automatisch. Ersetzt die früheren Knöpfe Schloss
  zu/offen, fit-in und fill-in.

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
| `Media\Variants` | deklarierte Ratios und Sizes (aus config.json), Dateinamen, Aliasse |
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

- **Look im Crops-Modus anders als im Master-Modus** (Kontrast vergraut,
  Sättigung 0 andere Grautöne). Ursache: der Master-Modus zeigt das Original
  mit CSS-Filtern, der Crops-Modus den vom Server gerechneten Master — und
  der rechnete Kontrast in zwei Schritten (mal, dann plus: die Lichter wurden
  bei Weiß abgeschnitten, bevor sie wieder herunterkamen) und Sättigung über
  HSL (`modulateImage`). Lösung (2026-11-20): `VariantRenderer::levels()`
  rechnet genau wie `brightness() contrast() saturate()` — Kontrast in einem
  Schritt (`FUNCTION_POLYNOMIAL`), Sättigung mit der Matrix von CSS
  `saturate()`; Test gegen die CSS-Formel in `media-variant-render.php`, im
  Browser (Canvas-Filter) nachgemessen. Schon gemachte Master mit Look sind
  veraltet: ihre Varianten löschen (`Files::removeVariants()`) oder das
  Medium neu speichern — lokal erledigt für 95 und 325, auf Staging/Live
  noch zu tun.

- **Übergang zwischen einem `fill`- und einem `fit`-Crop springt** (beim
  Gleiten des Reglers und bei den berechneten Ratios dazwischen). Ursache:
  beide wurden als `fill` interpoliert und auf Scale ≥ 1 geklemmt — ein
  Rahmen mit Hintergrund blieb bis kurz vor dem `fit`-Ende im Bild.
  Lösung (2026-11-20): gemischt wird in `fit` gerechnet (`asFit()`), `fit`
  kann jeden `fill`-Rahmen ausdrücken.

## Offen

- **Drehen:** Modell „größte Fläche" seit 2026-11-17. Offen, ob das Format
  zusätzlich zwischen dem bei 0° und 90° begrenzt werden soll (dann geht das
  Format nicht erst breiter, kostet bei kleinen Winkeln 3–7 % Fläche).
- **Aufräumen:** alter Renderer (`mediaImage`, `mediaEditor`,
  `imageEditor.js`), Alias-Ordner und Formatordner, `var/adjust/look.json`,
  die alten Crop-Zeilen und Spalten, Live-Area, der alte Teil von
  `media.php` — der Schalter zurück ist seit 2026-11-19 weg, das Aufräumen
  steht an. Ebenso totes CSS/JS der früheren Media Settings und des alten
  Datei-Editors (GUI, Lars: `.cursor/team/2026-11-19-media-settings-gui.md`).
- **Formatnamen aus settings.php** (systopic.net, beautiful-technology.de,
  expo, smp, galerie …: `mediaFormats::add()`, `$media_groups`) gelten weiter;
  `--import-settings` liest nur die JSON-Datei. Umzug nach config.json von
  Hand oder mit dem Umstellen der Templates auf `getCrop()`.
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
