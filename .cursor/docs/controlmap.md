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

## Offen (nächste Schritte)

1. **Baum + Map-GUI:** je Panel `build`, `actions` (Default) und je Action
   eine Rollenliste. Die Vererbung wird angezeigt („geerbt von cms/pages“).
2. **Configs:**
   - Pakete liefern `panel.config.json` neben `panel.php` als Default mit
     generischen Rollen (`editor`).
   - Das Projekt speichert Abweichungen in `config/controlmap.json`, das
     wandert per Sync.
   - Zur Laufzeit wird das zu einem PHP-Array in `var/` zusammengeführt.
3. **Rollen-Schlüssel:** `role_builtin` zu einem stabilen `role_key`
   verallgemeinern. Die Configs nennen den Key, umbenennen bricht nichts.
4. **Laufzeit:** `PanelBuilder` (`buildGranted`) und der Dispatch in
   `app.php` (`actionGranted`) fragen die Map. Danach entfallen die
   Overrides in den Panels und werden zu Defaults der Configs.
