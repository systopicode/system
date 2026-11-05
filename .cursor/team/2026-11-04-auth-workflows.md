# Anmeldung umgebaut: was an der GUI hängt
Von: Oliver · Für: Lars · Status: offen · 2026-11-04

## Anlass

Die Anmeldung folgt jetzt [auth-workflows.md](../docs/auth-workflows.md). Der Umbau
berührt deinen Bereich, das war abgesprochen. Bitte ansehen, ob du es so willst.

## Was sich geändert hat

- **Profil (`admin/user`):** Drei Widgets mit je zwei Knöpfen, „Scan code“
  (`.scanCodeBox`) und „Create code“ (`.addDeviceBox`): My devices, neu
  Paired screens und Authorize other user. Bei den beiden neuen steht ein
  Dropdown über den Knöpfen (`.deviceWays`, in `profile.css`). Die Boxen gibt es je Widget, dazu
  `.kindSelf`, `.kindScreen`, `.kindPerson`. Neues Icon `object-scan` für jedes
  Scannen, `camera` bleibt nur für die Kamera-Freigabe. Alle Boxen
  tragen die Klasse `.deviceBox` statt `.remoteLoginBox`, ihr `main` heißt
  `.deviceBoxMain` statt `.remoteLogin`. Neu ist „Sign out other devices“.
- **`ui.js`:** Die Kamera startet auf dem Handy jetzt beim Knopf für das Scannen.
- **`qr-scan.js`:** `data-remote-offer` und „Show QR“ sind weg, der Parameter heißt
  `deviceCode` statt `screen`.
- **Benutzerverwaltung:** Die Screen-Liste hat in der Aktionsspalte zwei neue
  Aktionen, „Scan device code from screen“ und „Create new auth code“. Beide
  öffnen eine Lightbox des Panels `pair/` mit `.deviceBox` und Info-Hinweis.
  Die Scan-Bausteine liegen jetzt in `panels/admin/user/scanCode.tpl.php`,
  `pair/pair.css` bindet `profile.css` ein.
- **`auth/`:** „Who is signing in?“ und die CSS-Regeln `.authPick` sind weg.
- **Texte:** „Sign in from another device“ statt „Remote login“, „Sign out“ statt
  „Log out“, „Sign-in log“ statt „Login log“.

## Zur Entscheidung

1. **Aussehen von `pair/` und vom Fuß der Geräte-Karte.** Dort stehen jetzt bis zu
   drei Knöpfe. Bitte prüfen, ob das am Handy so passt.
2. **`gui.md` ist nicht nachgezogen,** die Doku gehört dir:
   - Zeile 457, 464: `LoginLog`, `var/log/login.log` → `SignInLog`,
     `var/log/signin.log`.
   - Dazu die Punkte aus [2026-11-03-auth-top-level.md](2026-11-03-auth-top-level.md).
3. **Tests.** Die Browser-Tests (`*.mjs`) brauchen Playwright und liefen hier nicht.
   Ihre Pfade und Namen sind angepasst, geprüft ist nur die Syntax.

Betroffene Dateien: `panels/admin/user/main.tpl.php`, `profile.css`,
`public/js/gui/ui.js`, `qr-scan.js`, `code-until.js`, `public/css/x_responsive.css`,
`panels/admin/080_access/012_screens/pair/`, `panels/auth/auth.css`.
