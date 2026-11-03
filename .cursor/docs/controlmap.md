# Controlmap: wer welches Panel sieht und welche Action ausführt

Stand 2026-10-29. Ersetzt den Plan mit `FUNCTIONS`-Konstanten. Das
Inhaltsrecht (Content Groups, ACL) bleibt davon getrennt, siehe [acl.md](acl.md).

## Der Weg

```
discover (Code lesen) → Baum → Maps (GUI) → Configs (JSON) → Laufzeit liest nur die Configs
```

- **Keine Datenbank.** Panels und Actions stehen im Code. Discover liest
  sie bei jedem Aufruf neu, IDs gibt es nicht. Die Configs verweisen auf
  **logische Pfade** (`cms/pages/sidebar`, ohne `010_`-Präfix) und auf
  **Rollen-Namen**.
- **Vererbung im Panel-Baum.** Die Rechte einer Action ergeben sich so:
  eigene Liste, sonst Action-Default des Panels, sonst `build` des Panels,
  sonst Eltern-Panel. Es gibt nur Erlauben, kein Verbieten. Eine neue Action
  erbt also, ohne dass jemand etwas pflegt.
- **ACL bleibt im Code der Action** (`PageAccess::refuses($node)`). Die Map
  sagt nur, ob die Rolle die Action überhaupt darf.

## Schritt 1: Discover (`cms/controlmap/discover/`)

Nur für Superuser. Code: `src/Controlmap/` (system).

| Klasse | liest |
|---|---|
| `Discover` | die Panel-Ordner aller Roots, gemischt wie im Panel-Baum (`FolderResolver::discover()`, last wins) |
| `ActionScanner` | `actions.php` des gewinnenden Ordners und Includes mit Literal-Namen aus demselben Ordner |
| `ClassScanner` | `panel.php` / `module.php`: Klasse, `extends`, eigene `buildGranted()` / `actionGranted()`, öffentliche Methoden |
| `Tokens` | `PhpToken::tokenize()` ohne Whitespace/Kommentare. Es wird nichts eingebunden oder ausgeführt. |

Als Action zählt:

- `case 'x':` eines `switch ($this->action)` / `switch ($action)` /
  `switch ($module->action)`, aber nur dessen eigene Cases, nicht die eines
  verschachtelten Switch auf eine andere Variable
- Vergleiche `$this->action === 'x'` (auch `==`, `!==`, `!=`, umgedreht)
- `in_array($this->action, ['x', 'y'])`
- **Methoden:** öffentlich und mit `#[Action]` (seit 2026-10-30, siehe
  unten). Andere öffentliche Methoden sind Helfer der Templates und keine
  Actions.
- **Einstiege:** `$entryActions` der Klasse, im Chip markiert.

Zugriff im Code (`buildGranted` / `actionGranted`): Rollen aus
`hasRole()`/`hasOneRole()`, `superuser`, `signed in` (`known()`/`active()`),
`everyone`/`nobody` (`return true/false`), `parent`, `custom` (alles andere).

## ActionGuard: bevor eine Action läuft

`app.php` ruft `ActionGuard::check()` auf. Greift eine Regel, wirft sie
`ActionRefused`. `app.php` fängt die Exception ab und gibt sie per `p($e)`
aus; Debug zeigt sie als Fehler mit Trace (`tools/debug/value.php` behandelt
seit 2026-10-30 jedes `Throwable` so). Außerdem landet sie per `error_log`
im Log, der Benutzer bekommt eine Meldung und HTTP 403. Das Panel rendert
ohne die Action.

1. **Herkunft (gegen CSRF).**
   - **Das Loch:** Die Action ist das letzte Pfadsegment, auch bei GET. Das
     Session-Cookie ist `SameSite=Lax` und wird bei einem Link-Klick von einer
     fremden Seite mitgeschickt. `cms/pages/publish?id=5` in einer Mail
     hätte also veröffentlicht.
   - **Die Regel:** Es läuft nur `Sec-Fetch-Site: same-origin`.
   - Ohne diesen Header muss `Origin`, wenn er da ist, unseren Host nennen.
     Ohne beide ist es kein Browser, also auch kein CSRF. Das gilt für CLI,
     curl und Webhooks.
   - **Ausnahme:** `PanelNode::$entryActions`, nur per GET/HEAD, von überall.
     Bisher nur `cms/auth`: `activate`, `reset` (der Schlüssel im Mail-Link
     autorisiert), die Formulare `login`, `register`, `resetPassword` und die
     Rechtstexte.
2. **`actionGranted()`.** Früher hat es mit `die('no access …')`
   abgebrochen, jetzt wirft es dieselbe Exception.
3. **Methode ohne `#[Action]`.** Nennt ein Request eine öffentliche Methode
   ohne das Attribut und kennt `actions.php` die Action auch nicht (kein
   `case`, Vergleich oder `in_array`, gelesen mit dem `ActionScanner` von
   Discover), wirft `ActionGuard::method()` mit dem Hinweis „has no
   #[Action] attribute … Add #[Action] to the method“. Bei einer Methode des
   Frameworks (`updateView` …) lautet er stattdessen „belongs to the panel
   framework“. Kennt `actions.php` die Action (`cms/auth` → `loginMethod`),
   wird die Methode still übergangen. Das ist der Hinweis für migrierte
   Panels, deren Methoden früher Actions waren.

Methoden: `app.php` ruft eine Panel-Methode mit dem Namen der Action nur
noch auf, wenn sie `#[Action]` trägt (`Systopic\System\Panels\Action`).
Vorher war jede öffentliche Methode der Klasse aufrufbar. In smp waren das
28 Stück, keine davon als Action gemeint, z. B. `cms/backup/removeOldMediaDir`
für jeden Editor. `loginMethod` (`cms/auth`) lief dadurch doppelt. pwk habe
ich nur statisch geprüft: Die Upload-Methoden werden intern aufgerufen,
`cancel` ist ein `case`. Closure-Properties (`$this->sendData = function …`
in pwk) laufen weiter wie bisher.

**Testen:** `php tests/actionguard.php` (ohne DB). Per CLI-Payload lassen
sich die Header setzen:
`"server": {"HTTP_SEC_FETCH_SITE": "cross-site"}`. Über Apache:
`curl -sk -X PUT -H 'Sec-Fetch-Site: cross-site' …/cms/auth/loginMethod`
liefert 403.

**Falle:** Eine neue Action, die von außen erreichbar sein muss (ein
Mail-Link, ein QR-Code direkt auf eine Action), läuft in 403 „did not come
from this site“. Die Lösung ist, sie in `$entryActions` aufzunehmen, aber nur
wenn sie nichts allein aufgrund der Session ändert. QR-Codes und Mails
sollten besser auf eine Seite zeigen (`…/?id=`), deren Button dann
same-origin ist.

## Configs (`Controlmap\Map`)

Stand 2026-11-02: gelesen, in cms/controlmap angezeigt und bearbeitet, und
die Laufzeit fragt danach (unten „Laufzeit“).

**Ebenen** je Panel, die spätere ersetzt die frühere Schlüssel für
Schlüssel:

1. `panel.config.json` im Panel-Ordner jeder Herkunft, in der Reihenfolge
   der Ordner (`system`, `cms` …, `project`). Das ist der Standard dessen,
   der den Code liefert.
2. `config/controlmap.json` des Projekts, nach logischem Pfad. Das
   entscheidet die Installation.

```json
// cms: panels/cms/010_pages/panel.config.json
{ "build":   ["cmsAdmin", "cmsEditor", "cmsPageEditor"],
  "actions": ["cmsEditor"],
  "action":  { "deletePage": ["cmsAdmin"] },
  "rights":  { "move": ["cmsAdmin", "cmsEditor"] } }

// Projekt: config/controlmap.json
{ "cms/pages": { "action": { "publish": ["cmsAdmin"] } } }
```

Die Werte sind Rollen-Schlüssel. Eine Liste ersetzt die darunter, `[]`
heißt: niemand außer dem Superuser.

**Vererbung**, wenn die eigenen Ebenen nichts sagen (`Map::effective()`):

| Schlüssel | eigener Wert, sonst … |
|---|---|
| `build` | `build` des Eltern-Panels |
| `actions` | `build` des Panels, sonst `actions` des Eltern-Panels |
| `action.x` | `actions` des Panels (wie oben weiter) |
| `right.x` | `right.x` der Vorfahren bis zur Wurzel, sonst `actions` des Panels |

Sagt keine Ebene irgendwo etwas: `null`, dann entscheidet der Code
(`buildGranted()`, `actionGranted()`) wie bisher.

**Anzeige:** In Discover öffnet ein Klick auf einen Chip (Herkunft, Access
in code, Action) die Lightbox `discover/config/?path=cms.pages&focus=…`.
Sie zeigt je Schlüssel die Spalten „In code“, jede Ebene und „Effective“ mit
Herkunft, darunter die Dateien. Das `</>` hinter einem Chip öffnet die
Stelle in syscoder. Rollen-Schlüssel, die keine Rolle hat, sind markiert.

**Bearbeiten** (`Map::write()`, nur die Spalte `config`, also
`config/controlmap.json`; die `panel.config.json` der Pakete bleiben, wie
sie sind):

- Der Stift übernimmt einen Schlüssel in die Projekt-Ebene. Er startet mit
  dem, was gerade wirksam ist. Entscheidet noch der Code, startet er mit den
  Rollen, die der Code abfragt.
- Danach wird jede Rolle einzeln an- und ausgeschaltet. Alle aus heißt
  `[]`, also niemand außer dem Superuser.
- Das Kreuz nimmt den Schlüssel wieder heraus, das Panel erbt dann wieder.
  Bleibt nichts übrig, wird die Datei gelöscht.
- Geschrieben wird über eine temporäre Datei und `rename`.

## Laufzeit (`Controlmap\Access`)

`PanelBuilder` fragt `Access::build($child)`, `ActionGuard`
`Access::action($panel, $action)`, der Code eines Panels
`Access::right($this, 'move')`. Gelesen werden die `panel.config.json` der
Ordner des Panels und seiner Vorfahren (`PanelNode::$altPaths`) und
`config/controlmap.json`, ohne Discover.

Was entscheidet, in dieser Reihenfolge:

1. die eigene Config des Panels (irgendeine seiner Ebenen) für den Schlüssel
2. der eigene Code: Der Ordner des Panels hat seine Klasse, und die Klasse
   deklariert `buildGranted()` bzw. `actionGranted()` selbst. Eine Klasse,
   die das Eltern-Panel vererbt (`$bequests`), zählt als Code des
   Eltern-Panels, so wie dessen Config.
3. die geerbte Config (vom Eltern-Panel, wie `Map::effective()`)
4. der geerbte Code (`PanelNode`: jeder Eingeloggte)

Eine geerbte Config lockert also nie, was ein Panel in seinem Code selbst
entscheidet. Die Lightbox in cms/controlmap zeigt „Effective“ nach derselben
Regel.

Eine Rollenliste lässt den Superuser immer durch, außer wenn er als Rolle
schaut (Fake-Rollen). `everyone` lässt jeden Aufruf durch, `authenticated`
jeden Eingeloggten. Alle anderen Rollen prüft `Session::hasOneRole()`. Ein
Schlüssel, den keine Rolle hat, wird übersprungen, es wird nicht geworfen.
Der Recovery-User hat keine Datenbank, bei ihm entscheidet der Code.

Geprüft 2026-11-02 in smp.de per CLI, als Benutzer nur mit
`cmsPageEditor`: Umhängen wird abgelehnt (Meldung, DB unverändert),
Umsortieren unter demselben Elternknoten geht.

## Offen (nächste Schritte)

1. **Baum + Map-GUI:** je Panel `build`, `actions` (Default) und je Action
   eine Rollenliste. Die Vererbung wird angezeigt („geerbt von cms/pages“).
2. **Configs:** lesen, anzeigen und die Projekt-Ebene bearbeiten erledigt
   2026-11-02, siehe oben „Configs“. Offen: zur Laufzeit ein
   zusammengeführtes PHP-Array in `var/`; ein neues benanntes Recht in der
   GUI anlegen (heute nur über die JSON).
3. **Rollen-Schlüssel:** erledigt 2026-11-02, siehe [acl.md](acl.md)
   „Rollen-Schlüssel und Rollen des Systems“. `role_key` statt
   `role_builtin`, `hasRole()` nur nach Schlüssel (ein unbekannter wirft),
   das System legt `cmsAdmin`, `cmsEditor` und `cmsPageEditor` an. Noch offen
   dazu:
   - `cmsPageEditor` bearbeitet Seiten, darf im Seitenbaum aber nur die
     Reihenfolge ändern, nie `parent_id` (in der Vergangenheit eine häufige
     Fehlerquelle). `insertBefore`/`insertAfter` können heute beides, die
     Unterscheidung steckt deshalb im Code. Umgesetzt 2026-11-02: Ein
     benanntes Recht `move` steht in der Map (`rights`).
     `insertBefore`/`insertAfter`/`insertAsFirstChild` fragen
     `Access::right($this, 'move')`, sobald sich der Elternknoten ändert.
     Ohne das Recht zeigen sie eine Meldung und zeichnen den Baum neu, der
     Knoten springt zurück. Im Kontextmenü gibt es kein Umhängen. Offen: wie
     das Drag-Feedback im Client (Lars) erfährt, wohin nicht abgelegt
     werden darf.
   - Ebenen der Configs: `panel.config.json` je Herkunft (Reihenfolge wie
     die Ordner, die letzte gewinnt), darüber `config/controlmap.json`.
     Zusammengeführt wird je Schlüssel, eine Liste ersetzt die darunter
     (so kann ein Projekt auch einschränken). Die Paket-Ebene ist in der
     GUI nur lesbar (`vendor/` sind Symlinks auf `packages/`).
4. **Laufzeit:** erledigt 2026-11-02, siehe „Laufzeit“. Offen: ein Cache
   der zusammengeführten Configs in `var/`, falls das Lesen je Request
   spürbar wird.
5. **Prüfungen aus dem Code in Configs überführen**, Panel für Panel. Was
   im Code bleibt, gilt nur noch, wo keine Config etwas sagt: für den
   Recovery-User, der keine Datenbank hat, und als Rückfall, wenn jemand
   die Config löscht (dann meist: nur der Superuser).
   - Erledigt 2026-11-02 (system): `cms/settings` und `cms/users`
     (`cmsAdmin`), `cms/settings/theme` (`[]`, nur Superuser),
     `cms/backup` (Editoren sehen es; löschen, hochladen, herunterladen und
     aufräumen dürfen Admins; `createDatabase` nur der Recovery-User im
     Code), `backup/create` (Editoren), `backup/restore` (Admins, im Code
     der Recovery-User mit `restore`), `backup/log` (`clear` für Admins).
     Die Knöpfe in Backup fragen `Access::action()`.
   - Erledigt 2026-11-02 (spaceticker): `cms/events`, `cms/contacts`
     (Admin + Editor; die Ausnahme `|| STAGE === 'DEV'` ist weg, auf DEV
     braucht man jetzt auch die Rolle), `devices` mit dem Recht
     `pairScreens` (Admin). Der Link „Screen koppeln“ im Header prüft noch
     `hasRole('cmsAdmin')`, weil das devices-Panel dort nicht gebaut ist.
   - Bleibt im Code: `cms` selbst, wegen der Weiterleitung zum Login in
     `buildGranted()`, und das Root-Panel von spaceticker.
   - Offen: cms-Paket, EditBar und Vorschau (`appRequest`) fragen noch
     `hasOneRole()`.
