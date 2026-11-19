# Media Settings und alter Datei-Editor entfernt: was in der GUI übrig ist
Von: Oliver · Für: Lars · Status: offen · 2026-11-19

## Anlass

Die Media Settings sind aufgelöst: Ratios, Sizes, Formatnamen, Video-Formate und
Anbieter stehen in `packages.system.media`, die Media-Gruppen in
`packages.cms.media` (config.json, Formular Settings › System/CMS › Config).
Das Panel `030_settings/030_mediasettings` (mit der Lightbox für Dateitypen und
Templates) ist gelöscht, ebenso der alte Editor der benannten Formate im
Datei-Editor (cms `editor/file/image.tpl.php`: Format-Auswahl, „Edit",
„Master & crops", Vorschau-Zoom). Siehe `docs/config-struktur.md` und
`docs/media-crops.md`.

Code und Doku deines Bereichs habe ich nicht angefasst. Dort ist jetzt totes Zeug.

## Vorschlag: entfernen

| Datei | Was |
|---|---|
| `public/css/60_lightbox.css` | `.fileImageTools`, `.fileFrame.imageCropAlignContainer`, `.frameView`, `.frameZoom`, `img.srcToChange` (alter Datei-Editor); `.fileAdjust*` prüfen (der Inline-Editor `imageEditor.js` nutzt Teile noch) |
| `panels/admin/030_settings/project.css`, `public/css/x_responsive.css` | `.mediaTypes`, `.mediaTypeChoice(s)`, `.mediaChoiceText`, `.mediaTemplateMode`, `.imageFormatRow`, `.formatSizing`, `.formatField`, `.projectTemplates`, `.projectTemplatesMenu` |
| `panels/admin/panel.js` | der Template-Picker (`details.projectTemplates`, Z. ~200–290): `ProjectForm` schreibt ihn nicht mehr |
| `docs/gui.md` | Z. 34 „media settings" in der Liste der Settings; Z. 260 `$media_groups` (Media Settings, `ProjectForm::TEMPLATE_FIELDS`): heute `packages.cms.media.groups`, Templates als Text (`a;b`) |

## Zur Info

- Die Reiter des Bild-Editors (Edit master / Edit crops) stehen im Kopf des
  Fensters hinter dem Titel (`14_cropEditor.css`, `header > .ceTabs`), so mit
  dem Benutzer abgestimmt.
- Die Media-Gruppen haben im Config-Formular keine Checkbox-Auswahl mehr;
  Dateitypen und Templates sind Text. Wenn das zu roh ist: ein `x-`-Feld für
  eine Auswahl aus `GroupPolicy::TYPES` bzw. den Templates wäre der Weg.
