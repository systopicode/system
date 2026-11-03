# auth liegt jetzt oben: `auth/` statt `admin/auth/`
Von: Oliver · Für: Lars · Status: offen · 2026-11-03

## Anlass

Ein abgelehntes `admin` nahm den Login-Ast mit, deshalb gab es in
`admin::buildGranted()` eine Sonderregel. Für den Plan „Alles in die
Config“ (controlmap.md, Schritt 6) muss auth dort raus. Verschoben mit
`git mv`: `panels/admin/auth` → `panels/auth`, Namespace
`Root\Admin\Auth` → `Root\Auth`, alle Links `admin/auth/` → `auth/`.

## Was an der GUI hängt (dein Bereich)

So klein wie möglich gehalten, damit die Anmeldung aussieht wie vorher
(Screenshots spaceticker, systopic.net, smp.de verglichen):

- `auth/head.tpl.php` bindet `admin/head.tpl.php` jetzt über
  `dirname(__DIR__) . '/admin/head.tpl.php'` ein statt `../head.tpl.php`.
- `PanelTree::getBodyClasses()` gibt auch `auth/` die Klasse `cms`
  (vorher nur Pfade unter `admin`). Ohne sie fehlte das ganze Layout der
  Karte (`body.cms:has(.authCard)`).
- `admin/legal/lightbox.tpl.php` holt die Rechtstexte aus `panels/auth/`
  (`dirname(__DIR__, 2)`).

## Zur Entscheidung

1. **Modul-CSS/JS von admin fehlen auf `auth/`.** Der Kopf lädt CSS und JS
   je Pfadsegment. Unter `admin/auth` kamen `admin/panel.css`,
   `dashboard.css`, `texts.css`, `panel.js`, `mobile.js` mit, unter `auth/`
   nicht mehr. Ich habe keinen sichtbaren Unterschied gefunden (Login,
   Passwort-Formular, Registrieren, Impressum). Falls auth etwas davon
   braucht (Tastatur auf dem Handy, `mobile.js`?): in auth einbinden oder
   nach `public/css` bzw. `public/js` ziehen.
2. **gui.md** nennt noch `admin/auth` (Zeilen ~465, 556, 566) — bitte
   anpassen: `auth/`, `system/panels/auth/releases.php`.

Betroffene Dateien: `panels/auth/*`, `panels/admin/head.tpl.php` (unverändert,
`$headHost->name === 'auth'` passt weiter), `src/Panels/Tree/PanelTree.php`,
`panels/admin/legal/lightbox.tpl.php`.
