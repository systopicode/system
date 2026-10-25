# Panel-Assets: CSS und JS aus Panel-Ordnern

Wie die Stylesheets und Skripte, die neben einem Panel liegen, in den Browser
kommen — und warum ein Panel-Stylesheet nur seinen eigenen Bereich anfassen
darf. Anlass: eine Regel aus `040_backup/backup.css` hat den Pylon im
Seiten-Panel ausgeblendet, zwei Klicks später.

## Die zwei Ladewege

**Vollreload.** [head.tpl.php:65-74](../../panels/cms/head.tpl.php#L65-L74) läuft
den realPath des aktiven Panels nach oben ab und gibt pro Ordner — aus
sys-, project- und site-Root — die gefundenen `*.css` aus:

```php
echo fsLoader::includeCSSFilesInLayer(fs::$sysRoot . "panels/$modulePathString/", 'cms');
```

Das landet als `@import url('…') layer(cms)` im `<style>` des Kopfes. Die
Cascade Layer sind wichtig: sie legen die Reihenfolge fest, **unabhängig davon,
wann eine Datei geladen wurde**.

**Ajax.** [client::collectActiveModuleCssFiles()](../../lib/class/client/client.php#L119-L168)
macht denselben Lauf und hängt das Ergebnis an **jede** Antwort als
`loadCSS`-Kommando. Der Client
([server.command.js:238-256](../../public/js/core/server/server.command.js#L238-L256))
legt ein `<link>` an, wenn noch keins mit diesem href existiert.

**Entladen gibt es nicht.** Kein Kommando, kein Aufräumen beim Panelwechsel.
Was einmal geladen wurde, bleibt bis zum nächsten Vollreload im Dokument.

Beim JS ist genau das richtig: `sys.loader.addScripts` dedupliziert über die
Skript-id, und eine einmal ausgewertete Klasse lässt sich ohnehin nicht
zurücknehmen. Panel-JS bindet sich über die Layer- und Panel-Hooks
(`onUpdate<View>`), nicht über globale Nebenwirkungen — deshalb schadet es
nicht, dass das Skript liegen bleibt.

CSS ist der Unterschied: ein Stylesheet ist globaler Zustand, der weiterwirkt,
auch wenn das Panel, zu dem es gehört, längst nicht mehr auf dem Schirm ist.

## Der Vorfall

`040_backup/backup.css` enthielt:

```css
main > div.pylon { display: none; }
```

Kein Scope, keine Eingrenzung. Ab dem ersten Besuch von `/cms/backup/` ist der
Pylon in **jedem** Panel weg — auch im Seiten-Panel, wo er den Seitenbaum
enthält. Im Diff sah alles richtig aus (Server und Client zeigten den Layer),
nur sichtbar war er nicht.

Die anderen Panel-Stylesheets sind zufällig harmlos, weil sie alle mit `.cms …`
beginnen. `backup.css` war das einzige mit einem freilaufenden Selektor.

## Warum `@scope` hier nicht reicht

Naheliegender Gedanke: Panel-CSS in `@scope (…)` einsperren, wie es
[fsLoader::includeCSSFilesScoped()](../../lib/class/fs/loader/fsLoader.php#L99-L120)
für die Seitenvorschau tut. Für **eigene** Markup-Regeln ist das richtig.

Für diesen Fall funktioniert es nicht: der Pylon liegt **außerhalb** des
Backup-Panels, eine Ebene höher im Shell-Layout
([body.tpl.php:41](../../panels/cms/body.tpl.php#L41)). Ein Scope um das
Panel herum kann ihn per Definition nicht erreichen. Das gilt für jede Regel,
die nach oben oder zur Seite greift: Pylon, Footer, Sidebar, Body-Hintergrund.

Damit zerfällt Panel-CSS in zwei Sorten, die verschieden behandelt werden
müssen:

| Sorte | Beispiel | gehört wohin |
|---|---|---|
| **Eigenes Aussehen** | wie die Backup-Tabelle aussieht | Panel-Stylesheet, auf den eigenen Teilbaum begrenzt |
| **Zustand der Shell** | „hier gibt es keinen Pylon" | keine CSS-Angelegenheit des Panels — deklarierter Zustand, den die Shell umsetzt |

## Ist `body.backup .pylon` gutes Design?

Es funktioniert, ist billig und war die richtige Antwort auf die Frage „wie
komme ich an etwas heran, das nicht mir gehört". Als **einziger** Mechanismus
hat es drei Schwächen:

1. **Nichts erzwingt das Präfix.** Eine vergessene Klasse, und die Regel gilt
   global — genau das ist hier passiert. Die Disziplin ist freiwillig und nicht
   prüfbar, solange niemand prüft.
2. **Das Wissen liegt am falschen Ort.** Dass das Backup-Panel keinen Pylon
   hat, steht dann in einem Blatt-Stylesheet. Die Shell weiß es nicht, kann es
   nicht abfragen, nicht testen, nicht wiederverwenden — und rendert die Spalte
   trotzdem, sie wird nur unsichtbar gemacht. Tatsächlich weiß der Server es
   längst: `withChildSelected('pylon')` scheitert mit `template file not found`,
   sichtbar im Layer-Baum als `result: FALSE`.
3. **Die Kopplung geht an den Panel-Pfad.** `body.backup` bindet Aussehen an
   einen Ordnernamen. Panel umbenennen oder verschieben, und das Styling geht
   still kaputt.

Dazu kommt Spezifitäts-Inflation: jede Regel braucht das Präfix, und wer sie
überschreiben will, muss darüber. Die `@layer cms` federt das ab, aber die
Regeln werden dadurch nicht kürzer.

**Bewertung:** als Kanal für *deklarierten Shell-Zustand* sind Body-Klassen
genau richtig — sie sind schon da, der Server setzt sie
(`$panelTree->getBodyClasses()`), beide Renderwege tragen sie. Als
*Namespace-Ersatz für beliebiges Panel-CSS* sind sie die schwache Variante,
weil sie jedem Blatt die ganze Seite öffnen.

## Vorschlag

**1. Eigenes Aussehen wird eingegrenzt.** Ein Panel-Stylesheet trifft nur
innerhalb des Panels. Träger dafür: ein Wrapper (`.cms`, wie es die anderen
Panels schon machen), eine Panel-Klasse, oder `@scope ([data-panel="cms.backup"])`
mit einem Attribut am Layer-Wurzelknoten. Das lässt sich prüfen: ein Scanner in
der Art von [strayoutput.php](../tools/strayoutput.php) meldet jede
Panel-CSS-Regel, deren Selektor nicht mit einem erlaubten Scope beginnt.

**2. Was nach außen wirkt, wird deklariert statt gestylt.** Nicht das Panel
greift auf die Shell zu, sondern es sagt, was es braucht, und die Shell setzt
es um:

```php
// Panel
public bool $hasPylon = false;     // oder: die Shell leitet es daraus ab,
                                   // dass die View nicht auflöst
```

```php
// Shell (body.tpl.php)
if ($this->childSelected?->hasPylon) {
    $this->withChildSelected('pylon');
}
```

Dann braucht `backup.css` die Regel gar nicht, die Spalte existiert nicht, und
das Layout muss nichts verstecken. Wo ein Zustand doch per CSS wirken soll,
setzt die **Shell** die Body-Klasse (`body.pylon-empty`) und die **Shell**
bringt die Regel mit — kein Blatt greift nach oben.

**3. Body-Klassen bleiben, mit klarer Rolle.** Sie sagen, welches Panel aktiv
ist und in welchem Zustand die Shell ist. Sie sind kein Freifahrtschein für
Blatt-Stylesheets.

## Live / Bake

Mit (1) und (2) wird der Bake unkompliziert: **alles in ein Bundle, einmal
laden, nie entladen.** Genau so arbeiten Sprockets, Vite und webpack im
Normalfall. Die Bausteine liegen schon hier:

* ~~`clientCompress::compressCSS()`~~ — am 2026-10-16 entfernt: es baute
  auf Nicht-DEV-Stages für Besucher ein `compressed.css` je Ordner. Mit HTTP/2
  bringt Bündeln nichts mehr, siehe
  [asset-delivery.plan.md](../plans/asset-delivery.plan.md). Ein Bundle käme,
  wenn überhaupt, als `*.bundle.css` aus einem Build.
* `PUBLIC_VERSIONAPPENDIX` ist der Cache-Buster.
* `@layer cms` gibt die Reihenfolge vor, unabhängig von der Ladereihenfolge.
  Ohne Layer hinge die Kaskade davon ab, welche Panels der Benutzer vorher
  besucht hat — ein zweiter Fehler derselben Familie, der heute nur deshalb
  nicht auffällt.

Praktisch: DEV lädt weiter pro Panel (kurze Wege beim Entwickeln), LIVE liefert
ein `cms.bundle.css` mit allen Panel-Stylesheets in Layer-Reihenfolge. Weil
alles eingegrenzt ist, macht es keinen Unterschied, ob eine Datei gerade
„gebraucht" wird.

## Was andere machen

| Stack | Mechanismus |
|---|---|
| Vue SFC `scoped` | Attribut-Hash pro Komponente (`data-v-abc123`) |
| Angular | `ViewEncapsulation.Emulated`, `_ngcontent-xyz` |
| React, Svelte | CSS Modules bzw. gehashte Klassennamen |
| Web Components | Shadow DOM — echte Isolation, das Debug-Panel macht es so |
| Rails, Laravel, Django | ein Bundle plus BEM- oder Namespace-Konvention |

Zwei Beobachtungen daraus:

* **Niemand entlädt CSS.** Route-Splitting in Vite/webpack lädt nach, nimmt
  aber nie etwas heraus. Alle verlassen sich auf Eingrenzung.
* **Keiner dieser Mechanismen löst den Pylon-Fall.** Sie grenzen nach innen
  ein. Wenn eine Komponente das Layout ringsum beeinflussen soll, läuft das
  überall über Zustand, der nach oben gemeldet wird (Props, Slots, Layout-Route,
  Store) — nicht über CSS, das nach oben greift. Das ist genau Punkt (2).

## Entladen — falls doch

Mechanisch klein: der Server schickt ohnehin die vollständige Soll-Liste, der
Client hängt `data-panel` an jedes `<link>` und entfernt beim Update, was nicht
mehr drin steht. Rund 15 Zeilen.

Ich würde es trotzdem nicht als Absicherung bauen. Es erzeugt genau die
Illusion, die zu diesem Fehler geführt hat — „meine Regeln gelten nur bei mir" —
und hält der Live-Variante mit einem Bundle nicht stand. Als Dev-Komfort
(weniger Stylesheets im Dokument) ist es in Ordnung, als Sicherheitsnetz nicht.

## Offene Entscheidungen

* Wird der Pylon künftig gar nicht erst gerendert (Panel deklariert `hasPylon`),
  oder setzt die Shell eine Zustandsklasse?
* Bekommen Layer-Wurzelknoten ein `data-panel`, damit `@scope` für
  panel-eigenes CSS benutzbar wird?
* Soll der Scope-Prüfer in CI/Pre-Commit laufen, wie `strayoutput.php`?
