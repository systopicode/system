# Pfade und Dateizugriff: immer über `fs`

Stand 2026-10-26.

## Interne und native Pfade

`fs` führt alle Pfade intern mit `/` und, unter Windows, mit dem Laufwerk als
erstem Segment: `/C/laragon/www/projects/x/public/`. So sehen `fs::$root`,
`fs::$projectRoot`, `fs::$siteRoot` und `fs::$sysRoot` aus, und alles, was
daraus zusammengesetzt wird.

PHPs eigene Dateifunktionen verstehen das unter Windows nicht: `/C/...` ist
dort ein Ordner `C` im Wurzelverzeichnis des aktuellen Laufwerks. Unter Linux
und macOS sind interner und nativer Pfad gleich, deshalb fällt der Fehler
dort nie auf. Er zeigt sich erst auf einem Windows-Arbeitsplatz, meist als
`Failed to open stream` oder als stilles Fehlen (kein Logo, keine Farben,
leeres Log).

## Die Regel

Jede Dateioperation geht über `fs`:

```php
// falsch: prüft über fs, liest nativ mit dem internen Pfad
if (fs::isFile($file)) {
    $css = file_get_contents($file);
}

// richtig
if (fs::isFile($file)) {
    $css = fs::file_get_contents($file);
}
```

Die Wrapper (`lib/class/fs/fs.php`, `lib/class/fs/helper/fsHelper.php`, über
`fs::__callStatic` erreichbar) nehmen interne und native Pfade:

| statt | nimm |
|---|---|
| `is_file`, `is_dir`, `file_exists` | `fs::isFile`, `fs::isDir`, `fs::fileExists` |
| `file_get_contents`, `file_put_contents` | `fs::file_get_contents`, `fs::file_put_contents`, `fs::getFileContents` |
| `filesize`, `filemtime`, `touch`, `chmod` | `fs::filesize`, `fs::filemtime`, `fs::touch`, `fs::chmod` |
| `glob`, `scandir`, `opendir` | `fs::glob`, `fs::scandir`, `fs::fsOpendir` |
| `mkdir`, `rmdir`, `unlink`, `rename`, `copy` | `fs::fsMkdir`, `fs::fsRmdir`, `fs::unlinkFile`, `fs::fsRename`, `fs::fsCopy` |
| `fopen`, `realpath` | `fs::fopen`, `fs::realpath` (gibt intern zurück) |
| `include`, `require` | `fs::includeFile`, `fs::requireFile`, `fs::requireOnceFile` |

`fs::glob()` liefert native Pfade. Die Wrapper nehmen sie wieder an, eine
Liste daraus also auch nur über `fs` weiterverarbeiten.

## Ausnahme: einmal an der Grenze umwandeln

Arbeitet eine Klasse durchgehend mit PHPs Funktionen (Locks, Streams,
`tempnam`, fremde Bibliotheken), wandelt sie den Pfad einmal mit
`fs::toNative()` um und behält ihn nativ. Beispiele:
`Settings\EnvironmentFile::directory()`, `Settings\ProjectFile::directory()`,
`Http\Assets::rootDir()`.

Nie interne und native Pfade in einer Variablen mischen.

## Behoben am 2026-10-26

Dieselbe Mischung (über `fs` prüfen, nativ lesen oder schreiben) stand an
diesen Stellen und schlug unter Windows fehl:

- `panels/cms/head.tpl.php`: `x_responsive.css` fehlte in der CMS-Vorschau.
- `panels/cms/090_tools/logs/php/`: das PHP-Log blieb leer, Download und Leeren gingen nicht.
- `panels/cms/auth/license.tpl.php`: die Lizenz fehlte.
- `src/Sys/Theme.php`: Theme-Farben aus `cms_overrides.css` und `overrides/css/` wurden nie gelesen.
- `src/Settings/ProjectFile.php`: `page.settings.php` und `media.settings.php` wurden nicht gefunden.
- `lib/class/template/template.php`: der Template-Ordner des Projekts wurde übersehen.
- cms `panels/cms/010_pages/panel.php`: Video-Poster ohne Version.
