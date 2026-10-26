# fsFileOperators

Externe Werkzeuge (Imagick, ImageMagick-CLI, ffmpeg, DOMDocument, TCPDF …) hinter
einer einheitlichen API an `fsFile`. Ein Operator ist ein Adapter: er liest die
Quelldatei, macht daraus etwas Neues und legt das Ergebnis in einer gemeinsamen
Result-API ab, aus der es gespeichert, ausgegeben oder verworfen wird.

Dateien: `lib/class/fs/file/operator/`. Schwesterfamilie (lesend):
`lib/class/fs/file/mediainfo/`. Auflösung externer Binaries:
`lib/class/shell/binary/shellBinary.php`.

Stand 2026-10-23.

## Grundsatz

**Welches Werkzeug, entscheidet der Aufrufer.** Mehrere Werkzeuge können
dasselbe, aber verschieden: das eine resampelt oder montiert besser, das andere
ist schneller oder läuft im eigenen Prozess. Das ist eine Einzelfallentscheidung
und steht dort, wo der Fall ist — mit der Begründung daneben. Es gibt keine
zentrale Rangliste und keine automatische Auswahl.

Was ein Werkzeug kann, fragt der Operator **das Werkzeug selbst** (eine neuere
Version kann mehr, ohne dass jemand eine Liste pflegt).

## Schichten

```
fsFile                    eine Datei
 +- fsFileOperators       Registry, firstOperator(), eine Instanz je fsFile
     +- fsFileOperator_x  Adapter auf ein Werkzeug, eine Instanz je Name und Datei
         +- helper/       Unterordner: Klassen, die die Fremdbibliothek erweitern
```

Im Root stehen die Operatoren, die das fsFile-System kennen, im Unterordner die
Klassen, die von der Fremdbibliothek erben (`tcpdf/fsFileOperatorTcpdf extends
TCPDF`) und deshalb nichts vom fsFile-System wissen dürfen.

| Operator | Werkzeug | Formate erfragt über | benutzt von |
|---|---|---|---|
| `imagick` | PHP-Extension Imagick | `Imagick::queryFormats()` | mediaImage (Alias aus Pixelbildern, Rückfall für Seiten), pwk-Renderings |
| `magick` | CLI `magick` über shellBinary | `magick -list format` (lesen/schreiben getrennt) | mediaImage (Alias aus Seiten), pwk patchwork thumb |
| `ffmpeg` | CLI `ffmpeg` über shellBinary, proc_open | `ffmpeg -demuxers` (lesen) | mediaImage (Alias aus Videos), pwk über `videoFrame` |
| `domdocsvg` | DOMDocument über `domdocsvg/fsFileOperatorDomdocsvg` | – (deklariert) | patchworkItemBrand, svg-Vorbereitung für imagick |
| `tcpdf` | TCPDF über `tcpdf/fsFileOperatorTcpdf` | – | leerer Rumpf; die Helper-Klasse wird direkt instanziiert (pwk `lib/class/pdf/pdf.php`) |
| `meyfasvg` | meyfa/php-svg | – | Rumpf, nur `create()` |

## Zugriff

```php
// ein Werkzeug, vom Aufrufer benannt
$file->getOperator('imagick')->read()->scale($scale)->render('jpg')->store($path);
$file->imagick                      // dasselbe als Magic-Property (fsFile::delegateDynamic)

// mehrere, in der Reihenfolge, in der sie versucht werden - der erste mit Ergebnis
// pdf-Seite: magick zuerst (eigener Prozess), imagick als Rückfall
$op = $file->firstOperator(['magick', 'imagick'],
    fn($op) => $op->setMegapixels(3)->setPage(0)->render('png'));
if ($op === null) {
    throw new RuntimeException($file->operators->lastError);   // ein Grund je Versuch
}
```

`firstOperator()` überspringt, was hier nicht verfügbar ist (`isAvailable()`) oder
die Datei nicht lesen kann (`canRead()`), führt den Auftrag mit einer frischen
Instanz aus und gibt den ersten Operator mit Ergebnis zurück. Der Aufrufer liest
das Ergebnis und gibt den Operator frei (`cleanup()`, `destroy()`).

`getOperator()` merkt sich die Instanz je Name am fsFile, `destroy()` nimmt sie
wieder aus der Liste. Die Namen kommen aus `getExistingOperators()`: ein Glob
über den Ordner, der Dateiname hinter dem Unterstrich ist der Name.

## Was ein Operator bietet

| Teil | Zweck |
|---|---|
| `static isAvailable()` | läuft das Werkzeug hier (Binary gefunden, Extension geladen)? |
| `static formats()`, `canRead($typ)`, `canWrite($typ)` | was das Werkzeug kann: `queryFormats()` des Operators plus deklarierte `$capabilities`, klein geschrieben, `jpeg`/`jpg` und `tiff`/`tif` beide; je Request einmal gefragt |
| `setMegapixels()`, `setPage()` | was herauskommen soll (Rasterformate) |
| `render($format)` | der Umwandlungsschritt, gibt `static` zurück |
| `hasResult()`, `getContents()`, `store()`, `output()`, `cleanup()` | ein Abschluss für alle Werkzeuge |
| `lastError` | warum nichts herauskam — für den Aufrufer; `p()` darf zusätzlich |
| `sourceSize` | `[breite, höhe]` der Quelle, wenn das Ergebnis skaliert ist (Original eines Fotos, Bildgröße eines Videos) |

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

## Seitenformate (pdf, ps, eps, ai)

* Immer **eine Seite** (`[n]`, `setPage()`), nie das ganze Dokument.
* Die Dichte wird **vor** dem Rastern aus der Seitengröße berechnet
  (`fsFileOperator::densityFor()`), so dass die Megapixel herauskommen — egal wie
  groß das Druckformat ist. Die Seitengröße holt jeder Operator selbst: magick
  über `magick identify -format "%w %h %x %y"`, imagick über `pingImage()`.
* Imagick nie mit `readImageBlob()` für eine Seite: es rastert dann jede Seite mit
  72 dpi, und eine danach gesetzte Auflösung wirkt nicht (gemessen: 596×842
  statt 1190×1680). Das war die Erfahrung „bei Imagick kann man die dpi nicht
  setzen“ — es liegt am Lesen. `fsFileOperator_imagick::readPage()` liest über
  Pfad + Seite mit vorher gesetzter Auflösung, sobald `setMegapixels()` gesetzt
  ist; ohne Megapixel liest `read()` wie bisher (pwk).

## Einrichtungsfehler

`fsFileOperator::explainMagickError()` macht aus einer ImageMagick-Meldung, was zu
tun ist — für die beiden Fälle, die an der Installation liegen:

* **Sicherheitsrichtlinie** (IM7 CLI „not allowed by the security policy“, IM6
  „not authorized“, Extension „NotAuthorized“): welche Zeile in policy.xml wie
  zu ändern ist; der magick-Operator nennt die policy.xml-Dateien, die gerade
  gelten (`magick -list policy`).
* **Ghostscript fehlt**: installieren, PHP / Webserver neu starten.

## mediaImage (CMS-Bildpfad)

`mediaImage::renderAlias()` lässt das Alias — das Raster in Alias-Megapixeln, aus
dem jedes Format geschnitten wird — von einem Operator machen; die Reihenfolge
steht dort mit Begründung:

| Quelle | Operatoren | warum |
|---|---|---|
| Video | `ffmpeg` | Standbild, von ffmpeg auf die Megapixel skaliert (Lanczos) |
| pdf (ps, eps, ai) | `magick`, `imagick` | magick im eigenen Prozess; imagick als Rückfall, gleiche Dichte |
| Pixelbild | `imagick` | liest und resampelt (`resample()`, Sinc — wie zuvor in mediaImage) |

Zwischen Operator und mediaImage geht PNG (verlustfrei, mit Transparenz). Zuschnitt,
Fokus und das Schreiben der Formate bleiben in mediaImage.

## Externe Binaries

`shellBinary::find('name')` liefert den Pfad oder `NULL`. Reihenfolge: Konstante
`NAME_BIN` (z. B. `FFMPEG_BIN`, über config `constants`), sonst `where`
(Windows) bzw. `command -v`, sonst die PATH-Ordner von PHP direkt durchsucht.
Das Ergebnis wird je Request gecacht.

Windows-Fallstricke, die in dieser Codebase real vorkommen:

* Ein blankes `convert` ist unter Windows **nicht** ImageMagick, sondern
  `System32/convert.exe` (FAT nach NTFS). Deshalb immer `magick` über
  `shellBinary`.
* Unter FastCGI erben Kindprozesse die Umgebung des Webservers, nicht den PATH,
  den PHP mit `getenv()` sieht: ein nach dem Serverstart installiertes
  ImageMagick fand `where` nicht, der volle Pfad lief. `shellBinary` sucht
  deshalb zuletzt selbst in den PATH-Ordnern und liefert den vollen Pfad.
* Pfade durch `fs::toNative()`, Argumente durch `escapeshellarg()` — außer
  Formatstrings mit `%`: `escapeshellarg()` macht unter Windows aus `%` ein
  Leerzeichen.

LIVE: Shell ist erlaubt; benötigt werden ImageMagick 7 (`magick`), Ghostscript,
ffmpeg/ffprobe, die imagick-Extension als Rückfall.

## Schwesterfamilie: fsFileMediainfo

Gleiches Namensschema, andere Mechanik — lesend statt schreibend:

| | Operators | Mediainfo |
|---|---|---|
| Auswahl | Name(n) vom Aufrufer | `fsFileMediainfo::create()`, `switch` über mimeext |
| Instanz | je Name, lazy über `getOperator()` | genau eine je fsFile |
| Ergebnis | Blob oder Tempfile | Array mit festen Schlüsseln (`$defaultMediainfo`) |
| Fehler | `lastError` | Array plus `error`-Schlüssel |

Beide adressieren dieselben Werkzeuge (imagick, magick/identify, ffprobe,
DOMDocument). Überschneidungen — ein Operator misst selbst, was mediainfo auch
wüsste — sind in Kauf genommen: der Operator bekommt so den Grund eines
Fehlschlags (policy, Ghostscript) mit, mediainfo liefert dann nur Leerwerte.

## Offene Punkte

1. **Discovery per Regex.** `filepath2name()` nimmt das Stück hinter dem
   *letzten* Unterstrich; Unterstriche im Operatornamen sind damit verboten.
2. **Operatornamen sind fsFile-Properties.** Jeder Operator belegt `$file-><name>`;
   ein Operator namens `size` oder `contents` kollidierte still.
3. **Basisklasse ist kein strenger Vertrag.** Nicht abstrakt; `read()` und
   `create()` haben je Operator andere Signaturen.
4. **Leere Rümpfe zählen als Operatoren.** `tcpdf`, `meyfasvg`.
5. **Tempdateien ohne Lebenszyklus.** `var/temp/<operator>/` räumen nur
   `store()`/`cleanup()` auf; nach einem Fatal bleibt die Datei liegen.
6. **ps/eps/ai im CMS.** Die Operatoren können es, `mediaImage` lässt in
   `$typeSupported` nur pdf durch.
7. **Zuschnitt.** Ob Fokus, Zuschnitt und Formate aus mediaImage in
   `fsFileOperator_imagick` wandern — offen.
