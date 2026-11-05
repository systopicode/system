# Autorisierungs-Workflows: Use Cases, Wege, Verortung

Stand 2026-11-04, nach dem Umbau. Diese Doku sagt, **wer wen wo anmelden darf**:
alle Use Cases, je Use Case die Workflows, und wo sie im Admin und in der App
liegen. Die Technik dahinter (Cookies, Hashes, Laufzeiten) steht in
[auth-devices.md](auth-devices.md), Passkeys in
[auth-passkeys.md](auth-passkeys.md).

„App“ meint hier spaceticker, „Admin“ den Bereich `admin/`. Geprüft wird das mit
`.cursor/tools/auth-workflows-check.php` (echte Anfragen gegen DEV).

## Grundmodell

Eine Person A bringt ein Gerät dazu, als ein Konto B angemeldet zu sein. A
und B können dieselbe Person sein, B kann auch ein Screen sein.

| Begriff | Bedeutung |
|---|---|
| **Trusted-Gerät** | Ein Gerät, auf dem A angemeldet ist. |
| **Zielgerät** | Das Gerät, das angemeldet werden soll. Es ist noch niemand. |
| **Weg T** | Der Code kommt vom Trusted-Gerät. Sein Zweck steht fest: Er kann nur Konto B anmelden. Das Zielgerät löst ihn ein. |
| **Weg Z** | Der Code kommt vom Zielgerät. Sein Zweck ist offen. A liest ihn auf dem Trusted-Gerät und bestimmt dort, wer das Zielgerät wird. |
| **Gerätecode** | Der Code von Weg Z. |

### Wie ein Gerätecode arbeitet

Das Zielgerät hat noch keine Identität, es kann nur fragen.

1. Beim Aufruf von `auth/` entstehen zwei Dinge: der **Code** (8 Zeichen, zum
   Anzeigen) und ein **Poll-Geheimnis** (64 Zeichen, im HttpOnly-Cookie
   `sys_device_code`).
2. Solange die Seite sichtbar ist, fragt sie alle 3 Sekunden: „Hat mich jemand
   freigegeben?“ Das ist das Warten.
3. A liest den Code und bestimmt das Konto. Der Server trägt es beim Code ein.
4. Bei der nächsten Frage meldet der Server das Gerät als dieses Konto an. Ein
   Screen geht auf die Startseite, eine Person auf die gemerkte Seite.

Der Code gilt 2 Minuten (`Operator::DEVICE_CODE_LIFETIME`), die Seite zählt
sichtbar herunter. Bei null lädt sie neu und zeigt einen neuen. Ein Screen
erneuert seinen Code also von selbst. Eine verborgene Seite wartet damit, bis sie
wieder sichtbar ist.

Der angezeigte Code identifiziert das Gerät nicht, das tut das Poll-Geheimnis.
Wer den Code nur sieht, kann ihn freigeben, aber die Anmeldung landet immer auf
dem Gerät mit dem Cookie.

### Regeln

1. **Wer autorisiert, bestimmt die Identität.** Das geschieht immer auf dem
   Trusted-Gerät. Das Zielgerät wählt nie, wer es ist.
2. **Ein Code von Weg T meldet genau sein Konto an.** Einlösen heißt
   anmelden, ohne Rückfrage und ohne Auswahl.
3. **Vor jeder Autorisierung bestätigt A die Identität** und sieht den
   Hinweis, dass das protokolliert wird, auch wenn A sich selbst anmeldet.
4. **Das Log nennt beide:** wer autorisiert hat, welches Konto, welcher Weg.
5. **Ein Code entsteht nur durch eine Handlung von A.** Einzige Ausnahme ist
   die Installation der App (UC 7).
6. **Weg T und Weg Z sind zwei getrennte Funktionen** mit eigenen Namen,
   gleich im Admin und in der App.
7. **Ein Code hat keinen eigenen Zweck.** Was ein Konto darf, steht bei seinen
   Rollen und Rechten, nicht beim Code.
8. **Ein Passwort ist nicht nötig.** Ein Code und ein Gerät reichen zum
   Anmelden. Ob ein Passwort verlangt wird, kann später eine Policy festlegen.

## Übersicht

| UC | Use Case | Weg T | Weg Z | Admin | App |
|---|---|---|---|---|---|
| 1 | Selbst anmelden | – | – | `auth/` | `auth/` |
| 2 | Neues Konto zum ersten Mal anmelden | ja | – | `admin/access/users/edit` | – |
| 3 | Zugang wiederherstellen | ja | – | `admin/access/users/edit` | – |
| 4 | Eigenes weiteres Gerät anmelden | ja | ja | `admin/user` | `devices/` |
| 5 | Gerät für eine andere Person anmelden | ja | ja | `admin/user`, `admin/access/users/edit` | `devices/` |
| 6 | Screen anmelden | ja | ja | `admin/user`, `admin/access/screens/pair` | `devices/` |
| 7 | Installierte App anmelden | ja | – | – | `install/` |
| 8 | Als andere Person arbeiten | – | – | `admin/access/users` | – |
| 9 | Zugang entziehen | – | – | `admin/user`, `admin/access` | `devices/` |

`auth/` ist für Admin und App dieselbe Karte. Die App hat keine eigene
Anmeldung und leitet dorthin weiter.

## Use Cases und Workflows

### UC 1 · Selbst anmelden

Niemand autorisiert jemanden, die Person weist sich selbst aus.

| Nr. | Workflow | Ort |
|---|---|---|
| 1.1 | Passwort | `auth/` (`doSignIn`) |
| 1.2 | Passkey | `auth/` |
| 1.3 | Gerät bleibt angemeldet | Cookie, `Devices::restore()` |

### UC 2 · Neues Konto zum ersten Mal anmelden

| Nr. | Workflow | Start | Einlösen | Code |
|---|---|---|---|---|
| 2.1 | Einladung per Mail | Admin: Konto → „Send invitation code“ | `auth/` | 24 h, an B gebunden |
| 2.2 | Code vorzeigen | Admin: Konto → „Create code“ | `auth/` | 10 min, an B gebunden |
| 2.3 | Selbst registrieren | `auth/register` (`createAccount`, nur mit `ACCOUNT_WHITELIST`) | Mail mit Schlüssel | Aktivierungsschlüssel |

Beide Knöpfe im Admin fragen vorher nach und nennen A, B und das Log. Das Konto
wird aktiv, sobald der Code eingelöst ist.

Die Registrierung ist nicht verlinkt, die Seite ist aber erreichbar. Siehe
[Offen](#offen).

### UC 3 · Zugang wiederherstellen

| Nr. | Workflow | Start | Einlösen |
|---|---|---|---|
| 3.1 | Passwort vergessen | `auth/?view=resetPassword`, Mail mit Schlüssel | `auth/` |
| 3.2 | Admin hilft | wie 2.1 oder 2.2 | `auth/` |

Die Antwort von 3.1 ist für bekannte und unbekannte Adressen gleich, und jede
Anfrage zählt gegen die IP.

### UC 4 · Eigenes weiteres Gerät anmelden (A → A)

| Nr. | Weg | Start | Abschluss |
|---|---|---|---|
| 4.1 | T, Admin | `admin/user`, Widget „My devices“ → „Create code“ | Zielgerät: `auth/?code=` |
| 4.2 | T, App | Benutzermenü am Desktop → „Auf Mobilgerät anmelden“, „Verstanden“ (oder `devices/` → „Code für ein neues Gerät“) | Zielgerät: `auth/?code=` |
| 4.3 | Z, Admin | Zielgerät: `auth/` → „Sign in from another device“ | `admin/user`, Widget „My devices“ → „Scan code“, dann „Allow“ |
| 4.4 | Z, App | wie 4.3 | Benutzermenü am Mobilgerät → „Am Arbeitsplatz anmelden“, „Verstanden“, Code scannen (oder `devices/` → „Code von anderem Bildschirm scannen“, dann „Freigeben“) |

Im Profil trennen drei Widgets, wen A anmeldet. Jedes hat beide Richtungen,
„Scan code“ (Icon `object-scan`) und „Create code“ (Icon `qrcode`):

| Widget | Wen | Sichtbar mit |
|---|---|---|
| My devices | immer das eigene Konto, keine Auswahl | alle |
| Paired screens | einen Screen, zeigt alle Geräte der Screens, jedes mit Abmelden | `authScreens` |
| Authorize other user | eine andere Person | `authPersons` |

Bei „Paired screens“ und „Authorize other user“ steht die Auswahl im Widget über
den Knöpfen. Danach kommt nur eine Lightbox mit dem Hinweis: „Create code“ zeigt
den Code sofort, bei „Scan code“ gilt der eingegebene oder gescannte Code gleich
als Freigabe, ohne „Allow“. Die Box zeigt Kamera und Eingabe zusammen, ein
vollständiger Code geht von selbst los, bei Erfolg schließt sie sich. Das gilt
auch für die Aktion in der Screen-Tabelle. „My devices“ behält beide Schritte.

Das Formular trägt `kind` (`self`, `screen`, `person`). Es begrenzt nur die Auswahl,
das Recht prüft `SignInGrant::account()`. Kommt ein Gerätecode ohne `kind` über
den QR-Link, stehen alle erlaubten Konten zur Wahl.

### UC 5 · Gerät für eine andere Person anmelden (A → B)

| Nr. | Weg | Start | Abschluss | Recht |
|---|---|---|---|---|
| 5.1 | T, Admin | `admin/access/users/edit` → „Create code“ | Zielgerät: `auth/?code=` | `cmsAdmin` |
| 5.2 | T, Profil oder App | Widget „Authorize other user“: Konto wählen, dann „Create code“ | Zielgerät: `auth/?code=` | `authPersons` |
| 5.3 | Z, Admin | Zielgerät zeigt den Gerätecode | `admin/user`, Widget „Authorize other user“: Konto wählen, dann „Scan code“ | `authPersons` |
| 5.4 | Z, App | Zielgerät zeigt den Gerätecode | `devices/`: Konto wählen, Hinweis, „Freigeben“ | `authPersons` |

### UC 6 · Screen anmelden

| Nr. | Weg | Start | Abschluss | Recht |
|---|---|---|---|---|
| 6.1 | Z, Benutzerverwaltung | `admin/access/screens`, Aktionsspalte → „Scan device code from screen“ | Gerätecode scannen oder eingeben | `authScreens` |
| 6.1a | T, Benutzerverwaltung | `admin/access/screens`, Aktionsspalte → „Create new auth code“ | Code erzeugen, auf dem Gerät eingeben | `authScreens` |
| 6.2 | T oder Z, Admin | Screen zeigt den Gerätecode auf `auth/`, oder A erzeugt einen Code | `admin/user`, Widget „Paired screens“: Screen wählen, dann „Scan code“ oder „Create code“ | `authScreens` |
| 6.3 | Z, App | wie 6.2 | Benutzermenü → „Bildschirm koppeln“, Code scannen oder eingeben, solange es genau einen Screen gibt. Bei mehreren `devices/`: Screen wählen, Hinweis, „Freigeben“ | `authScreens` |

Wer nur `authScreens` hat (Rolle `screenOperator`), sieht in der
Benutzerverwaltung nur die Screens. Beide Aktionen liegen im Panel `pair`
(`&way=scan` für das Scannen), getrennt von „edit“. Der Screen steht dort schon
fest, A wählt nichts aus. Der Hinweis nennt das Konto und das Log.

Damit ein Screen ohne Tastatur seinen Code zeigt, steht in seiner Instanz
`defaultSignInMethod: device_code`.

### UC 7 · Installierte App anmelden

iOS gibt der App vom Home-Bildschirm eigene Cookies. Die Anmeldung aus Safari
zählt dort nicht.

| Nr. | Weg | Start | Abschluss |
|---|---|---|---|
| 7.1 | T, App | `install/` legt beim Aufruf einen Code an, das Manifest nimmt ihn in die `start_url` | Erster Start der App: Das Feld ist gefüllt, ein Tipp auf Anmelden |

Das ist die Ausnahme von Regel 5. Der Tipp bleibt absichtlich: Ein Link allein
soll niemanden anmelden, sonst ließe sich jemand unbemerkt in ein fremdes Konto
schicken.

### UC 8 · Als andere Person arbeiten

| Nr. | Workflow | Ort | Recht |
|---|---|---|---|
| 8.1 | Hijack: Superuser wechselt in ein Konto, kein neues Gerät | `admin/access/users` (`hijackUser`) | Superuser, nicht in andere Superuser |
| 8.2 | Zurück zum eigenen Konto | `admin/user` (`ransomUser`) | wer gewechselt hat |

Das Log nennt beide: „A switched to B“.

### UC 9 · Zugang entziehen

| Nr. | Workflow | Admin | App |
|---|---|---|---|
| 9.1 | Eigenes Gerät abmelden | `admin/user` | `devices/` |
| 9.2 | Alle anderen eigenen Geräte abmelden | `admin/user` | `devices/` |
| 9.3 | Gerät einer anderen Person abmelden | `admin/access/users/edit` | – |
| 9.4 | Screen abmelden | `admin/access/screens/edit` | `devices/` |
| 9.4a | Alle Screens abmelden | – | Benutzermenü → „Bildschirme abmelden“ |
| 9.5 | Konto sperren | `admin/access/users/edit` | – |

## Verortung

Dieselben Funktionen tragen im Admin und in der App denselben Sinn. Der
Server-Ablauf ist einer (`Auth\SignInCodes`, `Auth\SignInGrant`), die Oberfläche
unterscheidet sich: Die App geht noch in zwei Schritten vor wie „My devices“, die
Widgets „Paired screens“ und „Authorize other user“ gibt es dort nicht.

| Funktion | Weg | Admin | App |
|---|---|---|---|
| **Create code / Gerät hinzufügen:** Konto bestätigen, Hinweis, dann QR und Code. Im Admin nur bei „My devices“ so, die beiden anderen Widgets wählen vorher | T | `admin/user`, je Widget | `devices/#add`, Menü |
| **Scan code / Code von anderem Bildschirm scannen:** scannen oder tippen, Konto wählen, Hinweis, „Allow“. Im Admin nur bei „My devices“ und über den QR-Link so | Z | `admin/user`, je Widget | `devices/#scan`, Menü |
| **Code für eine Person:** Hinweis, dann Code oder Mail | T | `admin/access/users/edit` | – |
| **Scan device code from screen:** Hinweis, Gerätecode scannen oder eingeben | Z | `admin/access/screens`, Aktionsspalte | – |
| **Create new auth code:** Hinweis, dann Code und QR | T | `admin/access/screens`, Aktionsspalte | – |
| **Gerätecode zeigen:** Code und QR, fragt nach, erneuert sich | Z | `auth/` | `auth/` |
| **Code einlösen:** ohne Auswahl | T | `auth/` | `auth/` |

Im Schritt „Konto wählen“ steht zuerst A selbst. Personen und Screens kommen
nur dazu, wenn A das Recht dafür hat.

Der Hinweis lautet in den zweistufigen Boxen: „You, A, are signing this device
in. You identify the user on that device as B. This choice is written to the
sign-in log.“ In den einstufigen Boxen und in der Screen-Tabelle steht er als
Info-Kasten (`panels/admin/user/authInfo.tpl.php`): „By entering or scanning the
code, you authorize the device to be signed in as 'B'.“ oder „By creating the
auth code, you allow any person who gets to know it to sign in as 'B' on one
device.“, danach jeweils „Your choice is documented in the system's sign-in log.“

Die Freigabe von Weg Z zeigt in den zweistufigen Boxen, welches Gerät fragt:
Browser, System, IP und Uhrzeit. So merkt A, wenn ein fremder Code untergeschoben
wird. Browser und System meldet das Gerät selbst, sie sind ein Hinweis und kein
Beweis. Die einstufigen Boxen zeigen das nicht: Dort gilt der Code sofort, das
war so gewünscht.

Das Erzeugen eines Gerätecodes steht nicht im Log, sonst schriebe ein wartender
Screen rund 720 Zeilen am Tag. Die Freigabe und die Anmeldung stehen drin.

## Rechte

`authPersons` und `authScreens` sind Rechte der Controlmap. Sie stehen in der
Wurzel (`system/panels/panel.config.json`), damit Admin und App dasselbe fragen.
`SignInGrant` fragt sie mit `Access::rightNamed()` ab: Steht ein Recht nirgends,
hat es niemand.

| Rolle | `authPersons` | `authScreens` | Sieht in `admin/access` |
|---|---|---|---|
| `cmsAdmin` | ja | ja | alles |
| `screenOperator` | nein | ja | nur die Screens, ohne „edit“ |
| Superuser | ja | ja | alles |

Ein Superuser wird nur von einem Superuser angemeldet. Weitere Rollen trägt ein
Projekt in `config/controlmap.json` ein.

## Konfiguration

Gebaut ist `defaultSignInMethod`, der Rest ist Konzept.

```json
"auth": {
    "secret": "<64 hex>",
    "deviceDays": 180,
    "screenDays": null,
    "defaultSignInMethod": "device_code",
    "signInMethods": [],
    "registrationMethods": [],
    "recoverChallenges": []
}
```

Leer heißt überall: alles, was das System kennt.

| Schlüssel | Bedeutung | Einträge | Stand |
|---|---|---|---|
| `defaultSignInMethod` | Womit die Karte öffnet. Leer: Die Person wählt. | `password`, `passkey`, `code`, `device_code` | gebaut |
| `signInMethods` | Welche Anmeldungen angeboten werden. | wie oben | Konzept |
| `registrationMethods` | Wie man sich registrieren kann. **Jeder Eintrag reicht allein.** | `whitelist`, `email`, `approval` | Konzept |
| `recoverChallenges` | Womit jemand ohne Hilfe wieder hineinkommt. | heute nur `email` | Konzept |

**Achtung bei `registrationMethods`:** Da jeder Eintrag allein reicht, heißt eine
leere Liste: `email` genügt, und jede bestätigte Adresse kann sich registrieren,
auch außerhalb der Whitelist-Domains. Solange die App keine Rollen prüft, sieht
so ein Konto alles. Das muss beim Bau zusammen mit E13 entschieden werden. Heute
gilt weiter nur die Whitelist.

- `AuthConfig::block()` liest nur die Instanz-Datei. Die Policy gilt damit je
  Server, nicht je Projekt.
- `EnvironmentFile` kennt noch keine Listen als Wert.
- **Das Secret entsteht von selbst**, sobald es gebraucht wird
  (`AuthConfig::secret()`), in der Datei der laufenden Instanz. Überschreibt ein
  Sync diese Datei, entsteht ein neues. Das trifft nur Codes, die gerade unterwegs
  sind.
- Der alte Schlüssel `default_login_method` und der Wert `device` werden in der
  Übergangszeit weiter gelesen.

## Begriffe

| Begriff | Wofür | Verwendung |
|---|---|---|
| **auth** | Der Bereich | Panel `auth/`, Namespace `Auth\`, Config `auth` |
| **sign in / sign out** | Was eine Person oder ein Gerät tut | Einziges Verb in Oberfläche, Log und Config. `signIn` in PHP und JS, `signin` in Datenbank und Routen |
| **authorize / allow** | Was A für ein Zielgerät tut | Freigabe, Knopf „Allow“ |
| **authenticate** | Nur das technische Prüfen | WebAuthn und die Pseudo-Rolle `authenticated`, keine Oberfläche |
| **pair** | Nur für Screens | Panel `pair`, Meldung „Screen paired“. Kein Name für Codes |

Auf Deutsch heißt es **anmelden, abmelden, freigeben, koppeln**. Alle Schlüssel
stehen in camelCase.

### Anmelde-Methoden

| Bezeichner | Oberfläche | Weg | Im Log |
|---|---|---|---|
| `password` | Password | – | `password` |
| `passkey` | Passkey | – | `passkey` |
| `code` | Sign-in code | T | `code` |
| `device_code` | Sign in from another device | Z | `device_code` |
| – | Gerät bleibt angemeldet | – | `trusted_device` |

Der Anlass eines Codes (Einladung, Installation) steht in der Zeile, die ihn
erzeugt: „A ; created invitation for B“.

### Noch nicht umbenannt

Diese Namen tragen weiter `login` oder `logout`. Sie sind nur intern, ein eigenes
Thema: `Session::login()`, `Session::logout()`, die Action `logout`, JS-Namen wie
`appOnLogin` und `passkeyLogin`, CSS-Klassen wie `appLoginForm`.

Die alten Spalten `user_first_login` und `user_last_login` und die Tabelle
`usm_logincodes` bleiben stehen, bis `db audit` sie löscht.

## Entscheidungen

| Nr. | Frage | Entscheidung (Benutzer, 2026-11-04) |
|---|---|---|
| E1 | Ein Code für Weg Z? | Ja. `?pair=screen` ist weg. Das Trusted-Gerät entscheidet, was passiert. |
| E2 | Woher kommt das Recht, andere anzumelden? | Aus der Controlmap: `authPersons` und `authScreens`. |
| E3 | Hinweis und Log, wenn A sich selbst anmeldet? | Ja. |
| E4 | Pairing ohne Admin-Rolle? | `authScreens` öffnet die Benutzerverwaltung, gezeigt werden nur die Screens. Das Pairing ist ein eigenes Panel in der Aktionsspalte. |
| E5 | Bleiben die Zwecke getrennt? | Nein. Rollen und Rechte stehen bei den Usern. |
| E6 | Bleibt die Registrierung? | Ja, per Policy einstellbar. |
| E7 | Bleibt „Email a password link“? | Nein. Ein Passwort ist nicht nötig, eine spätere Policy kann das ändern. |
| E8 | Darf jemand ohne `cmsAdmin` andere anmelden? | Das sagt die Rolle. `screenOperator` hat `authScreens`, aber nicht `authPersons`. |
| E9 | Wo steht die Policy? | In der Config unter `auth`. Erst Konzept. |
| E10 | Bekommt jedes Projekt ein `auth.secret`? | Ja, es entsteht im Hintergrund, wenn es gebraucht wird. |
| E11 | Zeigt die Freigabe, welches Gerät fragt? | Ja: IP, Browser, Betriebssystem. |
| E12 | Was heißt eine leere Liste? | Alles, was das System kennt. |
| E13 | Verlangen die Panels der App eine Rolle? | Später, das wird mit dem Kunden besprochen. Bis dahin sieht jeder Angemeldete alles. |
| E14 | Gelten die Einträge der Registrierung zusammen? | Nein, jeder Eintrag reicht allein. |
| E15 | Heißt das einheitliche Verb „sign in“? | Ja. |
| E16 | Schreibweise der Schlüssel? | camelCase. |

## Offen

1. **Registrierung (E13, E14):** `auth/register` ist erreichbar, sieben Projekte
   haben eine `ACCOUNT_WHITELIST`. Ein neues Konto hat keine Rolle, ist aber
   angemeldet, und die App prüft keine Rollen. Wer ein Postfach auf einer
   Whitelist-Domain hat, sieht nach der Registrierung die Inhalte der App. Die
   Drossel und die neutralen Antworten sind gebaut. Der Zugang bleibt bis zum
   Gespräch mit dem Kunden so.
2. **Policy:** `signInMethods`, `registrationMethods`, `recoverChallenges` bauen.
3. **`db audit`:** alte Spalten und die Tabelle `usm_logincodes` löschen, sobald
   kein älterer Stand mehr auf derselben Datenbank läuft.
4. **Am Handy prüfen:** Kamera in „Scan code“ im Admin und in der App, Erster
   Start der installierten App. Die Boxen im Admin liefen am 2026-11-04 in Chrome
   mit Fake-Kamera, Firefox, Safari und echte Geräte fehlen noch.
5. **Zu entscheiden:**
   - Bei „Create code“ steht der Hinweis neben dem fertigen Code, nicht davor.
     Das ist die Folge von „nicht mehr zweistufig“. Bleibt das so?
   - Wird „My devices“ auch einstufig, mit Kamera sofort und ohne „Allow“?
   - Bekommt die App die drei Widgets wie `admin/user`?
   - Heißt es bei Personen auch „Auth code“? Dort steht noch „Sign-in code“.
6. **Kleinigkeiten:** Nach einem falschen Code im Profil steht der Cursor nicht
   mehr im Feld. Wer in den letzten Sekunden eines Gerätecodes freigibt, kann ins
   Leere laufen, dann ist eine zweite Freigabe nötig. Die 2 Minuten sind vorläufig.

## Profil-Dialoge (Stand 2026-11-05)

Die drei Geräte-Widgets verwenden `admin/user/authDialogs.tpl.php`, dieselbe manuelle PUT-Freigabe und dieselbe Kamera-Komponente. My devices benötigt keinen zusätzlichen Allow-Schritt. Das gewählte Konto wird explizit übertragen und serverseitig geprüft. Manuelle Eingabe sendet erst über den Haken. Desktop startet mit Eingabe und Kamera-Button; mobile Geräte mit Kamera und Rückweg zur Eingabe.

Scan und Create code zeigen zuerst den Sicherheitshinweis, einen ruhigen Platzhalter und I understand. Vor dieser Bestätigung sind Scan-Eingaben inert, die Kamera bleibt aus und es wird kein Code erzeugt. Die bestehenden Controls ersetzen den Platzhalter im selben reservierten Bereich. Create code tauscht nur das Ergebnisfragment der offenen Lightbox aus; ein Profil-Neuaufbau und eine erneute Slide-Animation entfallen. Nach Bestätigung nennt der Hinweis nur noch das Zielkonto. Backend-Codes zeigen denselben Countdown wie die Auth-Seite und erneuern sich nach Ablauf über den bestehenden stillen PUT-Kommandoaufruf; versteckte Dialoge und erfolgreich verwendete Codes werden nicht erneuert. Nach Rückkehr in einen versteckten Tab wird der ursprüngliche Ablaufzeitpunkt geprüft. Kamera-Abbruch, Timeout oder fehlender Zugriff zeigen direkt die manuelle Eingabe mit kurzem Status; Try again bleibt an der gemeinsamen unteren Aktionskante. Die Code-Lightboxen haben eine natürliche, durch die Fensterhöhe begrenzte Höhe; Größenwechsel animiert die gemeinsame Lightbox-Komponente. Mobil dürfen Platzhalter, Kamera und QR kleiner werden, damit die Aktionen sichtbar bleiben; ein manuell hochgezogenes Blatt bleibt hochgezogen. Jede erneute Öffnung beginnt wieder mit der Bestätigung. Create code zeigt danach QR und ein kompaktes Kopierfeld in QR-Breite. Der weiße QR-Rahmen gilt für beide Erscheinungsbilder. Mobile Code-Dialoge verwenden die normale Slide-Animation und passen ihre Höhe an den Inhalt an. Nach Verbrauch werden Code und Eingabe ausgeblendet; die Erfolgsmeldung steht im Dialog, ohne doppelte Widget-Meldung. Create another code bleibt verfügbar. Ablauf und Fehler bleiben im jeweiligen aktiven Dialog und dürfen beim Wiederöffnen nicht erneut auftauchen.

Jede tatsächliche Öffnung erhält eine neue clientseitige Darstellungskennung. Server-Renderings desselben Dialogs behalten ihre Kennung. Verspätete Antworten dürfen keine neue Öffnung treffen. Die Abschlussprüfung über HTTP bleibt bei einem Profil-Renderwechsel aktiv. Die vorhandene Leseaktion `refreshScreenDevices` aktualisiert eigene und gekoppelte Screen-Geräte sofort sowie nach 2 und 5 Sekunden; dafür ist kein Centrifugo-Kommando erforderlich. Freigabe und Code-Erzeugung werden nicht wiederholt.

Die Auth-Seite erneuert ausschließlich den QR-Block über `X-Device-Code-Renew`, ohne Seitenreload oder Ablauf-Zwischenmeldung. `X-Device-Code-Poll` liefert auch bei bereits angemeldeter Session JSON-Erfolg, ohne den Code erneut einzulösen. Die Weiterleitung wird vor optionaler Erfolgsdarstellung geplant. Auth- und Logout-Adressen werden nicht als Rücksprungziel gespeichert; Screens landen auf der öffentlichen Startseite.

Verifikation: DEV-Zwei-Geräte-Test mit Screen 30 bestätigte Anmeldung, HTTP-Abschluss und gezielte Listenaktualisierung. Desktop-, Tablet- und schmale Layouts wurden geprüft; der Nutzer bestätigte den angeglichenen My-devices-Ablauf. Regressionen prüfen echte Vorlagen, explizite Kontoübergabe für alle drei Arten, kein Autosenden, verspätete Antworten, QR-Teilersatz und bereits angemeldete Polls. Echte iPhone-/iPad-Kamera, sporadische Centrifugo-Ausfälle und SMTP-Zustellung sind damit nicht vollständig abgedeckt. Die verbleibende Safari-Safe-Area-Einfärbung beim direkten Vollhöhen-Start ist als visuelles Detail offen; Dokumenthintergrund und theme-color verwenden mobil die Projektfarbe.

## Gemeinsame Access-Einstiege (2026-11-05)

Access Users und Screens nutzen aus Tabellen und Edit-Lightboxen dieselbe `admin/user/authDialogs.tpl.php`. `fixedDialogs.tpl.php` ist nur der Adapter für das bereits bestimmte Konto, den Rückweg und das Aktualisierungsziel; es enthält kein eigenes Dialog-Markup. Scan, Bestätigung, Code-Ergebnis, Countdown, Kopieren, Kamera-Fallback und mobile Darstellung bleiben gemeinsam. `data-mobile-dialog="sheet"` hält dieselbe Blattdarstellung auch oberhalb einer User-Edit-Lightbox. Die alten Access-QR-Ergebnisvorlagen und Create-Code-Aktionen entfallen.

Die Formulare verwenden die zentralen `admin/user/createDeviceCode`- und `approveDeviceCode`-Aktionen, beide als reine Kommandoantworten. `SignInGrant` prüft das explizite Konto unverändert serverseitig; das feste Konto ist keine Berechtigungsabkürzung. Gesperrte Konten erhalten keine Kopplungsaktionen. Die 24-Stunden-Einladung per Mail bleibt ein eigener bestehender Ablauf. Erfolg aktualisiert über `refreshDevices` nur die Access-Liste beziehungsweise den Inhalt des offenen User-Editors, niemals die QR-Lightbox.


### Account-Bereiche (2026-11-05)

Die eigene Account-Seite verwendet ausführliche Auswahlzeilen (Icon, Titel, Erklärung), gemeinsam mit der Methodenwahl im Auth-Screen. Desktop: Navigation links, gewählter Inhalt rechts, feste Widget-Spalten ohne Masonry. Mobil: aufklappbare Bereichsauswahl oberhalb des Inhalts. Bereiche: Profile, Sign-in & Security, My devices, Pairing & Authorization und Preferences. Pairing ist beim normalen Einstieg vorausgewählt; ohne entsprechende Rechte ist My devices der Startbereich. Abmelden bleibt im haftenden Account-Kopf erreichbar. Bestehende Geräte-, Passkey- und Pairing-Komponenten werden weiterverwendet.

Die Passkey-Methode startet die vorhandene Browser-Abfrage auch nach AJAX-Ansichtswechseln direkt. Abbruch oder Fehler stellt den Button als „Try again“ wieder her. Keine Änderung an der eigentlichen WebAuthn-Prüfung.


### Account-Nutzerführung und Desktop-Oberfläche

Navigation und Inhalt bilden eine gemeinsame Fläche mit klarer linker Seitenleiste. Die eigene Fotobearbeitung befindet sich im Profil; der kompakte Account-Kopf hält Abmelden erreichbar. Alle Oberflächen verwenden die bestehenden Canvas-, Panel-, Border-, Radius-, Shadow- und Selected-Variablen des Themes sowie Standard-Widgets und Buttons.

Der Standard-Einstieg heißt „Connect a device“ und steht bei verfügbaren Gerätecodes auch ohne Rechte für andere Konten offen. Er unterscheidet „Myself“, „A screen“ und „Another person“; fremde Konten bleiben durch SignInGrant geschützt. „My devices“ verwaltet bestehende Geräte und verlinkt zum gemeinsamen Verbindungs-Einstieg. Die bestehenden Scan-/Create-Lightboxen werden unverändert verwendet, für Myself ohne Kontowahl. Die mobile Gestaltung wird nach der Desktop-Abstimmung weiter ausgearbeitet.


### Desktop-Korrektur: zwei Widget-Spalten

Der Desktop verwendet links ein Standard-Navigationswidget mit Header und rechts ausschließlich Inhaltswidgets in einer Spalte. Die zusätzliche äußere Canvas-Fläche und die wiederholten Bereichs-Titelblöcke entfallen. Der große Profilkopf enthält wieder Avatar, Name, E-Mail, Last sign-in und die Rollen einschließlich Superuser; Abmelden bleibt erreichbar. Navigations-Chevrons verwenden die gemeinsame summaryToggle-Geometrie und denselben Hoverzustand. Mobile bleibt für die separate Konzept-Runde vorgesehen.
