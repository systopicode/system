# Pylon so breit wie sein Inhalt, gezogene Breite für jedes Panel merken
Von: Oliver · Für: Lars · Status: offen, in cms/controlmap eingebaut · 2026-10-30

## Anlass

`cms/controlmap` zeigt im Pylon den ganzen Panel-Baum (tief, lange Namen).
Mit der festen Breite (`--pylonWidth`, Default 250px) und der Kappung in
`30_layout.css` (`min(var(--pylonWidth), 30rem)`) werden die Namen
abgeschnitten. Gewünscht ist eine flexible Breite.

Dabei sind zwei Dinge aufgefallen:

1. **Gezogene Breite wird nur in `cms/pages` gespeichert.**
   `cms/panel.js` → `draginitPylonwidth` schickt beim Loslassen
   `updatePylonWidth` an das aktive Panel. Eine Action dafür hat nur
   `cms/pages` (`010_pages/actions.php`). In allen anderen Panels springt
   die Breite beim nächsten Laden zurück.
2. **`pylonWidth` hat im State einen Default (180/250px).** Eine Breite,
   die sich nach dem Inhalt richtet, lässt sich daran nicht erkennen.

## Vorschlag

- Ein Pylon ohne gezogene Breite ist so breit wie sein Inhalt (Flex-Item
  mit `width: auto`), höchstens z. B. `60vw`. Nach dem Ziehen gilt die
  gezogene Breite. So steht es in `cms/controlmap` (Panel-CSS
  `085_controlmap/panel.css`):

  ```css
  body > main > div.pylon.controlmapTree{
  	flex: 0 0 auto;
  	width: auto;
  	max-width: 60vw;
  }
  body > main > div.pylon.controlmapTree[style*="--pylonWidth"]{
  	width: var(--pylonWidth);
  	max-width: min(var(--pylonWidth), 80vw);
  }
  ```

  Das Template setzt `style="--pylonWidth: …"` dann nur, wenn eine Breite
  gezogen wurde, nicht mit dem Default.
- `updatePylonWidth` einmal zentral beantworten, z. B. in `PanelNode` oder
  im `cms`-Panel, für das Panel, dessen Pylon gezogen wurde. Dann braucht
  kein Panel eine eigene Action.
- Ob das als Modus pro Panel (`fit` / `fixed`) gehört oder global, ist
  deine Entscheidung.

## Betroffen

- `system/public/css/30_layout.css` (Pylon-Breite)
- `system/panels/cms/panel.js` (`draginitPylonwidth`)
- `system/panels/cms/child.pylon.tpl.php`, `085_controlmap/pylon.tpl.php`
- `cms/panels/cms/010_pages/actions.php` (`updatePylonWidth`)

## So ist es in `cms/controlmap` gelöst

Nur dort, als Vorlage für die Entscheidung. Die anderen Pylons sind
unverändert.

| Datei | was |
|---|---|
| `system/panels/cms/085_controlmap/panel.css` | die beiden Regeln oben, nur für `div.pylon.controlmapTree` |
| `system/panels/cms/085_controlmap/pylon.tpl.php` | `style="--pylonWidth: …"` nur, wenn `state->treeWidth` gesetzt ist. Sonst kein Style, die Breite folgt dem Baum. |
| `system/panels/cms/085_controlmap/010_discover/actions.php` | `updatePylonWidth` speichert die gezogene Breite als `treeWidth` im State des Controlmap-Panels (200 bis 1200px) |

- **Eigener Schlüssel `treeWidth`** statt `pylonWidth`: Der hat im State
  einen Default (`CmsPanel::getDefaultState()`). Damit wäre immer eine Breite
  gesetzt, und der Pylon würde sich nie dem Inhalt anpassen.
- **Die Action sitzt im Kind** (`discover`), weil `draginitPylonwidth` an das
  aktive, also tiefste Panel schickt. Eine weitere Kind-Ansicht bräuchte sie
  noch einmal. Eine zentrale Lösung (siehe oben) würde beides erübrigen.
- **Zurück auf „so breit wie der Inhalt“** geht nur, indem man
  `treeWidth` im State löscht. Eine Geste dafür gibt es nicht, z. B. einen
  Doppelklick auf den Rand.

Wird der Vorschlag abgelehnt oder anders gelöst, kommen die drei Stellen
wieder raus. Dann gilt die normale Pylon-Breite.
