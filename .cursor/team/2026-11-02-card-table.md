# Mehrere Karten, eine Tabelle: gleich breite Spalten über Karten hinweg
Von: Oliver · Für: Lars · Status: offen, in cms/controlmap/discover eingebaut · 2026-11-02

## Anlass

Discover zeigt zwei Karten untereinander, „This panel“ und „Below“, beide
mit denselben Spalten. Jede Karte war bisher eine eigene
`section.widget.is-list` mit eigener Tabelle. Jede Tabelle bemisst ihre
Spalten selbst, also stehen die Spalten der beiden Karten versetzt.

Das Problem hatten wir schon einmal, und es kommt wieder: Überall, wo
Zeilen derselben Art in Gruppen mit Zwischenüberschriften stehen
(Ergebnis + Liste, Gruppen einer Liste, Bereiche eines Logs).

## Lösung

**Eine Tabelle, jede Karte ein `tbody`.** Die Spalten gehören der ganzen
Tabelle und sind deshalb in allen Karten gleich breit.

```html
<table class="cardTable">
	<tbody class="card">
		<tr class="cardHead"><th colspan="4"><header><h2>This panel</h2>…</header></th></tr>
		<tr class="cardColumns"><th>Panel</th><th>From</th>…</tr>
		<tr>…</tr>
	</tbody>
	<tbody class="cardGap"><tr><td colspan="4"></td></tr></tbody>
	<tbody class="card">…</tbody>
</table>
```

```css
.cms table.cardTable.cardTable{
	background: transparent;
	filter: drop-shadow(var(--cmsShadow));        /* the card shadow */
}
.cms table.cardTable.cardTable > tbody.card{
	background: var(--cmsCanvas);                 /* the white card */
	clip-path: inset(0 round var(--cmsRadiusLg)); /* the round corners */
	box-shadow: inset 0 0 0 1px var(--cmsLine);   /* the card border */
}
.cms table.cardTable.cardTable > tbody > tr > :is(th, td){
	background: transparent;                      /* the tbody paints */
}
.cms table.cardTable.cardTable > tbody.cardGap > tr > td{
	height: 1.25rem; padding: 0; border: none;    /* the space between cards */
}
```

Was dabei zu beachten ist (geprüft in Edge/Chromium):

- **Die Zellen sind transparent, das `tbody` malt die Karte.** Haben die
  Zellen einen eigenen Hintergrund (24_table.css: `th` in `--cmsCanvas`),
  ist der Abstand zwischen den Karten nicht mehr leer.
- **`border-radius` rundet ein `tbody` nicht**, `clip-path` dagegen schon.
  Der Rand ist deshalb ein `inset`-Schatten, denn ein Rand außen würde
  mit abgeschnitten.
- **Der Schatten kommt von der Tabelle** (`filter: drop-shadow`). Er folgt
  den abgeschnittenen Karten. Ein `box-shadow` am `tbody` würde
  `clip-path` mit abschneiden.
- **`margin` gibt es für ein `tbody` nicht.** Der Abstand ist ein eigenes
  `tbody.cardGap`. Ein transparenter `border-top` reicht nicht, weil der
  Hintergrund des `tbody` auch unter dem Rand der Zellen liegt.
- **Kein `.tableScroll` darum.** Dessen `overflow-x: auto` schneidet den
  Schatten ab. Das Scrollen übernimmt `section.cms`.
- **Spezifität:** `.cms table:not(.octalSelector, .editor table)` in
  24_table.css hat (0,2,2) und schlägt `.cms table.cardTable` (0,2,1).
  Deshalb steht die Klasse doppelt da.
- **Nebenbefund:** `.cms table:not(…) input { background: … }`
  (24_table.css, Zeile 81) setzt die Kurzform und löscht damit die Lupe
  des Suchfelds (`50_forms.css`). In Discover setzt eine Regel die Lupe im
  Kartenkopf wieder. Besser wäre dort `background-color` statt
  `background`.

## Vorschlag

Die Regeln aus `085_controlmap/010_discover/discover.css` (Block
`cardTable`) wandern als Baustein nach `24_table.css` und kommen in
`gui.md` §5 zu den Bausteinen („`table.cardTable` mit `tbody.card`:
mehrere Karten, gleiche Spalten“). Dazu `background-color` in Zeile 81 von
24_table.css. Danach entfällt die Lupen-Regel in Discover.

Offen ist noch der Telefon-Fall. `tableFit.js` (`.stacked`) hängt an
`.tableScroll`, eine `cardTable` wird also nicht gestapelt.

## Dateien

- `panels/cms/085_controlmap/010_discover/main.tpl.php` (Markup)
- `panels/cms/085_controlmap/010_discover/discover.css` (Block `cardTable`)
- `public/css/24_table.css` (Ziel; Zeile 81)
- `.cursor/docs/gui.md` §5
