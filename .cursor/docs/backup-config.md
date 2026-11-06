# Backup und Config

Stand 2026-11-05. Environment-Dateien sind aus dem Backup entfernt. Ob und wie
Config-Werte wieder mitreisen, ist noch offen.

## Stand

Ein Backup enthält die Datenbank, auf Wunsch die Medien sowie Global Settings
und Media Settings. `config/*.config.json` und `config/config.local.json` gehören
nicht mehr dazu.

- **Anlegen:** Der Schalter „Environment“ und der Schritt `addEnvironment` sind weg.
- **Zurückspielen:** Ein älteres Archiv mit `config/` bleibt gültig (`Preflight`
  kennt `addEnvironment` weiter), der Schritt wird aber verworfen
  (`restoreTaskKept()`: `extractEnvironment` ist immer `false`). Die Dateien in
  `config/` bleiben unberührt, das Archiv zeigt „Environment“ nicht mehr an.
- **Alte Archive enthalten die Dateien weiterhin.** Die Server sind nicht geprüft.

## Warum

1. **Config gehört zur Maschine, nicht zum Inhalt.** Das Archiv enthielt alle
   Instanz-Dateien und die `config.local.json` der Quelle. Auf einer anderen
   Stage zurückgespielt, ersetzte das dort Zugangsdaten und `matches` durch die
   Dateien der Quelle. Im schlechtesten Fall ist die Site danach offline oder
   zeigt auf die falsche Datenbank.
2. **Secrets lagen im Archiv.** Der Schalter stand beim Anlegen auf an, und die
   Rechte passten nicht zusammen: Herunterladen dürfen Admins, das Zurückspielen
   des Environments durften nur Superuser.
3. **Es passt nicht zum Config-Konzept.** Secrets sollen nur noch in
   `config.local.json` liegen und weder Git noch Sync berühren. Ein Weg über das
   Backup wäre genau die Lücke, die das schließen soll.

## Was trotzdem zum Inhalt gehört

Manche Config-Werte hängen an dem, was in der Datenbank steht. Sie unterscheiden
sich darin, ob sie mitreisen dürfen:

| Element | Liegt in | Hängt an | Beim Zurückspielen |
|---|---|---|---|
| Medienformate, Mediengruppen | `media.settings.json` | Medien in der Datenbank, Dateien in `var/formats` | reist mit, heute schon abwählbar |
| Sprachen | `global.settings.json` (`languages`) | Inhalte je Sprache | reist mit, heute schon abwählbar |
| Sites | `cms_sites` (Datenbank) und `matches[].site` (Instanz) | Der Name hängt am Inhalt, die Adresse an der Stage | **nicht kopieren, abgleichen** |
| `db`, `debug`, `matches` ohne `site`, Secrets | Instanz, local | nur an der Maschine | nie |

Die Sites sind der schwierige Fall. Eine zurückgespielte Datenbank bringt ihre
`cms_sites` mit. Die Regeln dazu dürfen aber nicht von der Quelle kommen, denn
`staging.systopic.net` ist auf DEV falsch.

## Vorschlag: Definition statt Dateiliste

Ob ein Wert mitreist, steht in der Definition, nicht im Backup-Code. Ohne Angabe
reist nichts mit, ein neuer Schlüssel ist also von selbst sicher.

| Angabe | Bedeutung |
|---|---|
| `"x-backup": "content"` | Der Wert hängt am Inhalt und reist im Archiv mit. |
| `"x-backup": "stage"` | Der Wert verweist auf den Inhalt, gehört aber der Stage. Er reist nicht mit, nach dem Zurückspielen wird abgeglichen. |

Für die Sites sähe das so aus:

```json
"site": {
	"type": "string",
	"x-backup": "stage",
	"x-options": "sites"
}
```

Ablauf beim Zurückspielen der Datenbank:

1. `cms_sites` mit den `matches` der **eigenen** Instanz vergleichen.
2. Site ohne Regel: melden und eine Regel vorschlagen, wie beim Anlegen einer
   Site (`InstanceFiles`). Der Host kommt von dieser Stage.
3. Regel ohne Site: melden, nichts löschen.

Im Archiv lägen statt ganzer Dateien nur die Werte mit `content`. Das setzt die
Definitionsdateien aus dem Config-Umbau voraus. Bis dahin bleiben Global und
Media Settings als ganze Dateien im Backup.

## Offen

1. **Alte Archive:** löschen, oder den `config/`-Teil aus ihnen entfernen? Erst
   die Server prüfen.
2. **Totalverlust eines Servers:** `config.local.json` ist dann nirgends gesichert.
   Entschieden am 2026-11-06: Es gibt keine Ablage außerhalb. Erzeugbare Secrets
   entstehen neu, `relay` kommt aus der Hub-Config, `db.pass` und `mail.password`
   werden beim Hoster neu vergeben und am Server eingetragen.
3. **Hochgeladene Archive:** Das Backup-Panel nimmt auch fremde ZIPs an. Der Schutz
   oben gilt für sie genauso, die Settings darin bleiben aber übernehmbar.
4. **Global Settings:** Sollen sie als Ganzes mitreisen, oder nur `languages`?
