# Anmeldung umgebaut: was an der GUI hängt
Von: Oliver · Für: Lars · Status: offen · 2026-11-04

## Anlass

Die Anmeldung folgt jetzt [auth-workflows.md](../docs/auth-workflows.md). Der Umbau
berührt deinen Bereich, das war abgesprochen. Bitte ansehen, ob du es so willst.

## Was sich geändert hat

- **Profil (`admin/user`):** Aus „Add device“ wurden zwei Knöpfe, „Add device“
  (`.addDeviceBox`) und „Scan code from another screen“ (`.scanCodeBox`). Beide
  tragen die Klasse `.deviceBox` statt `.remoteLoginBox`, ihr `main` heißt
  `.deviceBoxMain` statt `.remoteLogin`. Neu ist „Sign out other devices“.
- **`ui.js`:** Die Kamera startet auf dem Handy jetzt beim Knopf für das Scannen.
- **`qr-scan.js`:** `data-remote-offer` und „Show QR“ sind weg, der Parameter heißt
  `deviceCode` statt `screen`.
- **Benutzerverwaltung:** Die Screen-Liste hat in der Aktionsspalte ein neues
  Panel `pair/`. Die Lightbox nutzt die vorhandenen Bausteine, das Layout ist
  bewusst schlicht.
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
