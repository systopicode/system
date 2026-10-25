# Server-seitige Confirms (`confirmRequired`)

**Verwandt:** [`panel-node-api.md`](panel-node-api.md) · [`panel-diff-rendering.md`](panel-diff-rendering.md)

Zwei Wege, eine destruktive Action abzusichern:

| | `userConfirm` (Client) | `confirmRequired()` (Server) |
|---|---|---|
| Rückfrage | `window.confirm`, eine Zeile Text | gerendertes Lightbox-Markup |
| Round Trips | 1 | 2 |
| Kann Betroffene benennen | nein | ja — Anzahl, Liste, Mehrfachauswahl |
| Trigger | `data-on_click='userConfirm' data-action='…' data-confirm='…?'` | Action ruft sich selbst als Guard auf |

Für ein schlichtes Ja/Nein bleibt `userConfirm` (`public/js/panelNode.js`) die günstigere Wahl.

---

## Benutzung

```php
case 'removeAllItems':
	$items = (array) $patchwork->queue->items;
	if ($this->confirmRequired(count($items) . ' Items werden entfernt.', [
		'title' => 'Warteschlange leeren',
		'label' => 'Ja, alle entfernen',
		'items' => array_map(fn($i) => "$i->contentType #$i->id", $items),
	])) {
		break; // Rückfrage steht offen — nichts tun
	}
	db::query('DELETE FROM …');
	break;
```

`confirmRequired()` gibt `true` zurück, solange die Frage unbeantwortet ist. Beim zweiten
Durchlauf — der Bestätigungsbutton wiederholt dieselbe Action mit dem gespeicherten Token —
gibt sie `false` zurück und der Action-Body läuft. `$this->confirmAnswered->payload` trägt
optionale Daten über den Roundtrip.

Optionen: `title`, `message` (1. Argument), `label`, `dismiss`, `items` (werden escaped),
`payload`, `href` (Override, falls der Trigger kein `data-on_click` hatte).

---

## Sichtbarkeit wird abgeleitet, nicht gespeichert

Der Dialog ist der View `confirmbox`. `PanelNode::viewVisible('confirmbox')` liefert `true`
genau solange eine Frage offen ist — es gibt keinen `hiddenViews`-Eintrag und kein
`showView`/`hideView`.

Das ist der entscheidende Unterschied zu einer per State-Flag gesteuerten Lightbox: ein Flag
muss von irgendjemandem wieder zurückgesetzt werden, und jede Action, die das vergisst, lässt
den Dialog liegen. `PendingConfirm::sweep()` verwirft die Frage stattdessen bei **jedem**
Request, dessen Action nicht die gefragte ist. Lebensdauer = ein Roundtrip. Ein Dialog kann
damit keinen fremden Klick, keinen Reload und keinen Panel-Wechsel überleben.

Der Abbrechen-Button braucht deshalb keinen Handler: er schickt die Action `confirmCancel`,
und dass diese nicht die gefragte ist, *ist* das Verwerfen.

Beim Verwerfen ist kein `updateView()` nötig — `openRoot()` bricht für den unsichtbaren View
ab, der Layer-Diff sieht einen `panelName`-Mismatch und der Client ersetzt den Dialog durch
den Placeholder (siehe `PanelNavigator::cancel()`).

---

## Wo der Dialog hängt

Im Body per `withRoot`, **nicht** `withSelected`:

```php
$this->withRoot('confirmbox');
```

`withRoot` läuft über `findRenderRoot()`: das tiefste Panel auf der selektierten Kette, das
`confirmbox.tpl.php` mitbringt, gewinnt. Wichtig für Widgets — eine Widget-Action läuft unter
`…/edit/items/`, das Widget selbst braucht aber keine Datei: der Dialog liegt auf `edit` und
wird trotzdem gefunden. Ein Widget, das eigenes Markup will (Thumbnails o.ä.), legt einfach
sein eigenes `confirmbox.tpl.php` an und übersteuert damit.

Das Host-Template ist eine Zeile:

```php
<?php echo $this->renderConfirmbox(); ?>
```

Weil das fragende Panel den Host nicht kennt, markiert `confirmRequired()` den View auf der
gesamten Ancestor-Kette als zu aktualisieren. Ohne das bliebe ein Dialog, der einen bereits
offenen ersetzt, ein `keep` — der Client würde die alte Frage samt totem Token weiterzeigen.

---

## Dateien

| Datei | Rolle |
|---|---|
| `src/Panels/ConfirmsActions.php` | Trait auf `PanelNode`: `confirmRequired`, `confirmPending`, `renderConfirmbox` |
| `src/Panels/Confirm/PendingConfirm.php` | Session-Slot + Verfallsregel |
| `src/Panels/PanelNode.php` | `use ConfirmsActions`, Sonderfall in `viewVisible()` |
| `<projekt>/panels/…/confirmbox.tpl.php` | Host-Template |

Referenz-Umsetzung: `pwk.systopic.net`, `panels/020_patchworks/edit/` (Host) und
`edit/items/actions.php` (`removeAllItems`).
