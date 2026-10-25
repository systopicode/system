# fsFileOperators

Externe Werkzeuge (Imagick, ImageMagick-CLI, DOMDocument, TCPDF …) hinter einer
einheitlichen API an `fsFile`. Ein Operator ist ein Adapter: er liest die
Quelldatei, macht daraus etwas Neues und legt das Ergebnis in einer gemeinsamen
Result-API ab, aus der es gespeichert, ausgegeben oder verworfen wird.

Dateien: `lib/class/fs/file/operator/`. Schwesterfamilie (lesend):
`lib/class/fs/file/mediainfo/`. Auflösung externer Binaries:
`lib/class/shell/binary/shellBinary.php`.

## Schichten

```
fsFile                    eine Datei
 +- fsFileOperators       Registry + Auswahl, eine Instanz je fsFile
     +- fsFileOperator_x  Adapter auf ein Werkzeug, eine Instanz je Name und Datei
         +- helper/       Unterordner: Klassen, die die Fremdbibliothek erweitern
```

Die Trennung Root-/Unterordner ist Absicht und im Klassenkommentar festgehalten:
im Root stehen die Operatoren, die das fsFile-System kennen, im Unterordner die
Klassen, die von der Fremdbibliothek erben (`tcpdf/fsFileOperatorTcpdf extends
TCPDF`) und deshalb nichts vom fsFile-System wissen dürfen.

| Operator | Werkzeug | read | write | Status |
|---|---|---|---|---|
| `ffmpeg` | CLI `ffmpeg` über shellBinary | mp4, mov, webm, m4v | jpg, jpeg, png, webp | benutzt (CMS-Formate für Videos, pwk-Renderings über `videoFrame`) |
| `imagick` | PHP-Extension Imagick | pdf, tif, tiff, jpg, png, gif, psd, eps, svg | jpg, png, gif, pdf, tif | benutzt (pwk renderings) |
| `magick` | CLI `magick` über shellBinary | pdf, ps, eps, ai | jpg, png, gif, tif | benutzt (pwk patchwork thumb) |
| `domdocsvg` | DOMDocument über `domdocsvg/fsFileOperatorDomdocsvg` | svg | svg | benutzt (patchworkItemBrand) |
| `tcpdf` | TCPDF über `tcpdf/fsFileOperatorTcpdf` | – | – | leerer Rumpf; die Helper-Klasse wird direkt instanziiert (`pwk lib/class/pdf/pdf.php`) |
| `meyfasvg` | meyfa/php-svg | – | – | Rumpf, nur `create()` |

## Zugriff

Drei Wege, zwei davon in Benutzung:

```php
$file->getOperator('imagick')      // explizit - der reale Weg in pwk
$file->imagick                     // dasselbe als Magic-Property (fsFile::delegateDynamic)
$file->convertTo('jpg')            // Operator wird über Capabilities gesucht - ohne Aufrufer
```

`getOperator()` merkt sich die Instanz je Name am fsFile, `destroy()` nimmt sie
wieder aus der Liste. Die Namen kommen aus `getExistingOperators()`: ein Glob
über den Ordner, der Dateiname hinter dem Unterstrich ist der Name.

## Ablauf und Result-API

Ein Operator arbeitet auf `$this->file` (Quelle) und legt sein Ergebnis in genau
einem von zwei Trägern ab:

| Träger | wer benutzt ihn | warum |
|---|---|---|
| `resultBlob` | In-Process-Werkzeuge (imagick, domdocsvg), gepipte CLI-Werkzeuge (ffmpeg) | Ergebnis liegt im Speicher, nichts geht über die Platte |
| `resultTempPath` | CLI-Werkzeuge, die in eine Datei schreiben (magick, ffmpeg mit `setStream(FALSE)`) | das Tool braucht ein Ziel mit Namen, `var/temp/<operator>/` |

Ein CLI-Werkzeug muss nicht über die Platte: schreibt es nach `pipe:1`, liest der
Operator die Bytes direkt in `resultBlob` (siehe `fsFileOperator_ffmpeg::run()`).
Zwei Pipes wären ein Deadlock — stderr geht deshalb in ein `tmpfile()`-Handle,
weil `stream_set_blocking()` und `stream_select()` unter Windows auf Pipes nicht
greifen. `proc_open()` bekommt die Argumente als Array: kein Shell, kein
`escapeshellarg`, keine cmd.exe-Quoting-Regeln.

Danach ist der Abschluss für beide gleich: `hasResult()`, `getContents()`,
`store($pfad)` (legt den Zielordner an, verschiebt bzw. schreibt, `chmod 0664`,
räumt auf), `output($mime)` oder `cleanup()`.

```php
$source->file
    ->getOperator('imagick')
    ->read()
    ->scale($scale)
    ->render('jpg')
    ->store($this->file->filepath);
```

`render()` ist der Konvertierungsschritt und gibt `static` zurück, damit die
Kette bis `store()` durchläuft. Die Basisklasse implementiert `render()` und
`export()` als "kann ich nicht" und meldet das über `d()`.

## Operator-Auswahl

Zwei Mechanismen nebeneinander:

1. **Capabilities** — jeder Operator deklariert `static $capabilities` mit
   `read`/`write`. `fsFileOperators::findOperatorFor($von, $nach)` fragt zuerst
   eine zentrale Tabelle `$conversions` (`'pdf=>jpg' => ['operator' => 'magick',
   'priority' => 1]`) und geht sonst die Operatoren durch, bis einer beides kann.
2. **Aufrufer wählt** — `getOperator('magick')` benennt das Werkzeug direkt.
   Das ist der Weg, den der gesamte produktive Code geht.

## Externe Binaries

`shellBinary::find('name')` liefert den Pfad oder `NULL`. Reihenfolge: Konstante
`NAME_BIN` (z. B. `FFMPEG_BIN`) aus der Projekt-`index.php`, sonst `where`
(Windows) bzw. `command -v`. Das Ergebnis wird je Request gecacht.

Windows-Fallstricke, die in dieser Codebase real vorkommen:

* Ein blankes `convert` ist unter Windows **nicht** ImageMagick, sondern
  `System32/convert.exe` (FAT nach NTFS). `mediaImage.php` ruft
  `shell_exec("convert …")` und `shell_exec("identify …")` ohne shellBinary auf.
* `magick` ist auf dieser Maschine nicht installiert — `shellBinary::find('magick')`
  gibt `NULL`. Betroffen: `fsFileOperator_magick` und `fsFileMediainfo_identify`
  (beide melden es sauber), sowie die Legacy-Aufrufe oben (melden es nicht).
* Pfade immer durch `fs::toNative()`, Argumente durch `escapeshellarg()`.
  So gebaut funktionieren die Aufrufe unter Windows — nachgemessen mit ffmpeg.

## Schwesterfamilie: fsFileMediainfo

Gleiches Namensschema, andere Mechanik — lesend statt schreibend:

| | Operators | Mediainfo |
|---|---|---|
| Auswahl | Name vom Aufrufer, oder Capabilities | `fsFileMediainfo::create()`, `switch` über mimeext |
| Instanz | je Name, lazy über `getOperator()` | genau eine je fsFile |
| Ergebnis | Blob oder Tempfile | Array mit festen Schlüsseln (`$defaultMediainfo`) |
| Fehler | `p()` / `d()` | Array plus `error`-Schlüssel |

Beide adressieren dieselben Werkzeuge (imagick, magick/identify, ffprobe,
DOMDocument), aber über getrennte Registries.

## Vorteile dieses Aufbaus

* **Ein Abschluss für alle Werkzeuge.** Ob Imagick einen Blob im Speicher hält
  oder `magick` in eine Tempdatei schreibt, sieht der Aufrufer nicht:
  `store()`/`output()` sind identisch. Das ist der stärkste Teil des Entwurfs.
* **Lazy und je Datei.** Ein Operator entsteht erst beim Zugriff; ein
  Imagick-Objekt wird nie gebaut, wenn niemand rendert.
* **Fluent.** `read()->scale()->render()->store()` liest sich wie die Pipeline,
  die es beschreibt.
* **Erweiterbar ohne Registrierung.** Datei anlegen, fertig — der Glob findet
  sie, und `$file->neuername` funktioniert sofort.
* **Fremdbibliotheken bleiben draußen.** Vererbung von TCPDF und Co. passiert im
  Unterordner, der Root-Operator bleibt an der fsFile-Welt.

## Nachteile und Inkonsistenzen

1. **Zwei Auswahlmechanismen, einer tot.** `convertTo()`, `findOperatorFor()` und
   die Tabelle `$conversions` haben im gesamten Repo keinen Aufrufer. Produktiv
   benennt jeder Aufrufer sein Werkzeug selbst — genau das, was die Capabilities
   ablösen sollten.
2. **Capabilities und `$conversions` sind Redundanz.** Der Plan
   (`.cursor/plans/fsFileOperator_conversion.plan.md`) sah die Prioritäten
   *innerhalb* der Capabilities vor (`'pdf=>jpg' => 5`). Implementiert wurde eine
   zweite Tabelle daneben. Beide Quellen können auseinanderlaufen.
3. **Verfügbarkeit ist kein Kriterium.** `findOperatorFor('pdf', 'jpg')` liefert
   `magick`, auch wenn das Binary fehlt — `imagick` könnte es hier auch.
   `isAvailable()` gibt es bisher nur an `fsFileOperator_ffmpeg`, nicht in der
   Basisklasse und nicht in der Auswahl.
4. **Drei Begriffe von "Typ".** `extension` (roh, wie auf der Platte),
   `standardizedExtension` (nur `strtolower`) und `mimeext` (gesnifft, `jpeg`
   wird zu `jpg`). `convertTo()` fragt mit `extension`, die Capabilities sind in
   `mimeext`-Schreibweise notiert: eine Datei `Foto.JPEG` findet nichts.
5. **Discovery per Regex.** `filepath2name()` nimmt das Stück hinter dem
   *letzten* Unterstrich. `fsFileOperator_video_frame.php` hieße `frame`.
   Unterstriche im Operatornamen sind verboten, ohne dass es irgendwo steht.
6. **Operatornamen sind fsFile-Properties.** Jeder neue Operator belegt
   automatisch `$file-><name>`. Ein Operator namens `size` oder `contents` würde
   still mit einer echten Property kollidieren.
7. **Basisklasse ist kein Vertrag.** `fsFileOperator` ist nicht abstrakt,
   `render()` meldet den Fehlschlag über `d()` (Debug-Ausgabe) statt über
   Rückgabewert oder Exception. Signaturen driften: `read()` gibt in der Basis
   nichts zurück, in `imagick` `static`; `create()` hat je Operator eine andere
   Signatur.
8. **Fehler landen im Debug-Panel, nicht beim Aufrufer.** `p()`/`d()` in
   `magick::render()` und `imagick::read()`. Der Aufrufer sieht nur
   `hasResult() === false` und weiß nicht, warum. `videoFrame::$lastError` macht
   es besser — außerhalb der Familie.
9. **Leere Rümpfe zählen als Operatoren.** `tcpdf` und `meyfasvg` haben keine
   Capabilities und kein `render()`, tauchen aber in `getExistingOperators()` und
   damit als Magic-Property auf.
10. **Tempdateien ohne Lebenszyklus.** `var/temp/<operator>/` wird nur von
    `store()`/`cleanup()` aufgeräumt. Ein Fatal dazwischen lässt die Datei liegen;
    niemand sammelt sie ein.
11. **Der Legacy-Bildpfad benutzt nichts davon.** `mediaImage::createImageFormat()`
    macht Imagick, `identify` und `convert` selbst, inklusive eigener
    Tempdatei-Logik und eigener Megapixel-Rechnung. Dieselbe Aufgabe, zwei
    Implementierungen — und nur eine davon ist Windows-fest.
12. **Flache Sonderfaelle neben der Familie.** `videoFrame` war eine eigene
    Klasse mit eigenem Fehlerkanal, obwohl sie dasselbe tut wie ein Operator;
    inzwischen delegiert sie an `fsFileOperator_ffmpeg` und bleibt nur als
    flacher Einstiegspunkt der pwk-Renderings bestehen. Vergleichbare Reste
    stecken noch in `mediaImage` (siehe 11).

## Strukturvorschläge

In der Reihenfolge, in der sie sich lohnen:

1. ~~**`fsFileOperator_ffmpeg`**~~ — **umgesetzt.** Der Operator rendert
   `jpg/jpeg/png/webp` aus `mp4/mov/webm/m4v` durch die Pipe in einen
   `resultBlob` (`setStream(FALSE)` für den Weg über eine Tempdatei),
   `videoFrame::extract()` delegiert dorthin (Signatur und `$lastError`
   unverändert), und `mediaImage::createImageFormat()` zieht für eine
   Video-Quelle erst ein Standbild, bevor die Bildpipeline läuft — erst beim
   Bauen des Alias, ein vorhandener Alias kostet also keinen ffmpeg-Lauf. Die
   Position kommt von `setSeconds()` oder, ohne Angabe, aus der ffprobe-Dauer
   (Frame 0 ist zu oft schwarz).
2. **Eine Quelle für die Auswahl.** Prioritäten in die Capabilities (wie im Plan),
   `$conversions` wird daraus abgeleiteter Cache oder fällt weg.
3. **`isAvailable()` je Operator**, in `findOperatorFor()` berücksichtigt:
   `magick` fehlt, `imagick` übernimmt pdf=>jpg. Das macht die Auswahl erst
   wirklich automatisch.
4. **Typ-Schlüssel vereinheitlichen.** Überall `mimeext`, mit
   `standardizedExtension` als Fallback für nicht existierende Dateien.
5. **Vertrag schärfen.** `fsFileOperator` abstrakt, `render()`/`export()` mit
   definiertem Rückgabewert, Fehler über `lastError` oder Exception statt `d()`.
6. **Discovery explizit.** Entweder eine Liste in `fsFileOperators` oder der Glob
   mit strengerem Muster, plus eine Sperrliste gegen Kollisionen mit
   fsFile-Properties.
7. **Legacy zusammenführen.** `mediaImage` auf die Operator-API heben — dann erbt
   der alte CMS-Pfad Windows-Festigkeit, Tempfile-Handling und die
   Werkzeugauflösung, statt sie ein zweites Mal zu haben.
8. **Tempfiles aufräumen.** `getTempPath()` registriert den Pfad, ein
   Shutdown-Handler räumt auf, was liegen geblieben ist.

## Status gegenüber dem Plan

`.cursor/plans/fsFileOperator_conversion.plan.md` ist teilweise umgesetzt:
Capabilities und `findOperatorFor()` existieren (an `fsFileOperators`, nicht an
`fsFileOperator`), `convertTo()` existiert und gibt den Operator zurück statt
eines temporären `fsFile`. Offen: Prioritäten in den Capabilities, der
Capabilities-Cache und Aufrufer für die automatische Auswahl.
