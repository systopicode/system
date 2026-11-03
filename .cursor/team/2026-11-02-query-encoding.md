# Query-Werte mit `%xx` werden beim Ajax-GET doppelt kodiert
Von: Oliver · Für: Lars · Status: offen, in cms/controlmap umgangen · 2026-11-02

## Anlass

Im Baum von `cms/controlmap` zeigte ein Klick auf einen Knoten weder die
Markierung noch den Inhalt des Knotens. Die Links lauteten
`./?panel=cms%2Ftools`. Beim Server kam `panel=cms%252Ftools` an, also
`cms%2Ftools` nach dem Dekodieren. Den Knoten gibt es so nicht.

Die Ursache liegt im Client, nicht im Panel:

1. `sys.http.parseQuery()` (`lib/class/http/http.js`) zerlegt den Query,
   **dekodiert die Werte aber nicht**: `{panel: 'cms%2Ftools'}`.
2. `server.sendGet()` (`public/js/core/server/server.js`) baut den Query
   mit `obj.getQueryString()` (`public/js/core/lib/obj.js`) neu. Das
   **kodiert jeden Wert**, aus `%2F` wird `%252F`.

Ein unkodierter `/` im Wert ist auch keine Lösung. `parseURL()` liest ihn
als Teil des Pfads, und `historyPush` schreibt dann
`…/discover/?panel=cms/?panel=cms/` in die Adresszeile.

Betroffen ist jeder Link, dessen Query ein `%` enthält, also Leerzeichen,
Umlaute, `&` oder `/` in einem Wert. Bisher fiel das nicht auf, weil die
Query-Werte fast überall Zahlen sind (`?id=19`).

## Vorschlag

`parseQuery()` dekodiert die Werte, und `query2qeryStr()` (für
`url2string`/`historyPush`) kodiert sie wieder. Dann gilt auf beiden Wegen
dasselbe Paar:

```js
http.parseQuery = query => {
	if (!query) {
		return false;
	}
	var data = {};
	query.split('&').forEach(pair => {
		var i = pair.indexOf('=');
		var key = i === -1 ? pair : pair.slice(0, i);
		var value = i === -1 ? '' : pair.slice(i + 1);
		try {
			data[decodeURIComponent(key)] = decodeURIComponent(value.replace(/\+/g, ' '));
		} catch (e) {
			data[key] = value;   // a malformed escape stays as it was
		}
	});
	return data;
};

http.query2qeryStr = query => {
	var values = [];
	for (var key in query) {
		values.push(encodeURIComponent(key) + '=' + encodeURIComponent(query[key]));
	}
	return values.length ? '?' + values.join('&') : '';
};
```

Vorher prüfen: Wer liest `url.query` sonst noch und erwartet die
kodierte Form? Was liefert `queriesEqual()` beim Vergleich?

## Umgehung in cms/controlmap

`Controlmap\Panel::nodeHref()` schreibt den Pfad mit Punkten
(`?panel=cms.tools`, wie `panelPath` im Client), `onLoad()` macht daraus
wieder `cms/tools`. Kein Panel-Ordner enthält einen Punkt. Ist der
Vorschlag übernommen, kann die Umgehung bleiben oder auf `rawurlencode`
zurückgehen.

## Dateien

- `lib/class/http/http.js`: `parseQuery`, `query2qeryStr`
- `public/js/core/server/server.js`: `sendGet`
- `public/js/core/lib/obj.js`: `getQueryString`
- Umgehung: `panels/cms/085_controlmap/panel.php` (`nodeHref`)
