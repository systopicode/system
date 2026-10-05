-- tagGroups.sql, aufgeteilt in drei Ergebnismengen statt einer Concat-Spalte.
--
-- Warum: tags_data packte Tags UND deren node-ids in eine einzige Spalte, mit
-- drei einander ausweichenden Trennzeichen (Komma von GROUP_CONCAT, ';' je
-- Feld, ':' zwischen Schluessel und Wert, ' ' fuer die innerste Liste).
-- Zwei Probleme daran:
--   1. group_concat_max_len ist per Vorgabe 1024 Byte. Was darueber hinausgeht,
--      faellt weg -- als Warnung 1260, die PDO nicht durchreicht.
--   2. getColumnMeta() liefert fuer tags_data table = "", weil die Spalte aus
--      einem abgeleiteten Alias kommt. Eine Zuordnung Spalte -> Tabelle -> Klasse
--      ist damit unmoeglich, jede solche Query braucht einen eigenen Parser.
--
-- Hier kommt jede Spalte aus einer echten Tabelle -- nachgemessen:
--   Set 1  cms_taggroups.taggroup_id  cms_taggroups.taggroup_name  ...
--   Set 2  cms_tags.tag_id  cms_tags.tag_taggroup_id  cms_tags.tag_name
--   Set 3  cms_tags_in_nodes.tin_tag_id  cms_tags_in_nodes.tin_node_id
-- Die Zuordnung Eltern -> Kinder laeuft ueber den Fremdschluessel, den die
-- Kindzeile ohnehin traegt (tag_taggroup_id bzw. tin_tag_id). Eine ID-Liste in
-- der Elternzeile braucht es dafuer nicht.
--
-- ---------------------------------------------------------------------------
-- VORAUSSETZUNG: emulierte Prepares.
--
-- Mehrere Anweisungen in einem query() haengen NICHT an
-- Pdo\Mysql::ATTR_MULTI_STATEMENTS, sondern an PDO::ATTR_EMULATE_PREPARES.
-- Nachgemessen (PHP 8.5.7, MySQL 8.4.3):
--
--   emulate = true,  Flag nicht gesetzt   -> 3 Sets
--   emulate = true,  Flag = true          -> 3 Sets
--   emulate = false, Flag = true          -> Fehler 1064
--   beliebig,        Flag = false         -> Fehler 1064
--
-- db.php:61 SCHREIBT ATTR_EMULATE_PREPARES => FALSE, es wirkt aber nicht:
-- loader.php:60 ruft connect(..., isset($driver_options) ? $driver_options : NULL)
-- und ein ausdrueckliches NULL setzt den Vorgabewert eines Parameters ausser
-- Kraft. Live nachgemessen ist die Emulation daher EIN — die Datei laeuft in
-- dieser Installation ohne jedes Zutun. Sollte der Aufruf einmal repariert
-- werden, laesst sich die Einstellung auf derselben Verbindung umschalten und
-- danach zuruecksetzen (Db\Connection::runMulti() macht genau das):
--
--   $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, TRUE);
--   $st = $pdo->query($sql);
--   $groups = $st->fetchAll(PDO::FETCH_ASSOC);  $st->nextRowset();
--   $tags   = $st->fetchAll(PDO::FETCH_ASSOC);  $st->nextRowset();
--   $links  = $st->fetchAll(PDO::FETCH_ASSOC);
--   $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);
--
-- Native Typen kommen dabei unveraendert an (INT -> int, DOUBLE -> float).
-- Pdo\Mysql::ATTR_MULTI_STATEMENTS wird nur gebraucht, um Mehrfach-Anweisungen
-- gezielt ABZUSCHALTEN; der alte Name PDO::MYSQL_ATTR_MULTI_STATEMENTS ist seit
-- PHP 8.5 deprecated.
--
-- Wichtig: die Anweisungen sehen einander nicht. Anweisung 2 kann die ids aus
-- Anweisung 1 nicht verwenden, deshalb wiederholt jede ihren Filter als
-- Subquery. Das haelt die Datei in sich geschlossen.
-- ---------------------------------------------------------------------------

-- ---------------------------------------------------------------- 1 Gruppen
SELECT
	taggroup_id,
	taggroup_name,
	taggroup_color,
	taggroup_is_persistent
FROM cms_taggroups
WHERE taggroup_id != 1
ORDER BY taggroup_name;

-- ------------------------------------------------- 2 Tags dieser Gruppen
-- Der frueher noetige LEFT JOIN entfaellt: Gruppen ohne Tags stehen in
-- Ergebnismenge 1 und bekommen hier einfach keine Zeilen.
SELECT
	tag_id,
	tag_taggroup_id,
	tag_name
FROM cms_tags
WHERE tag_taggroup_id IN (
	SELECT taggroup_id FROM cms_taggroups WHERE taggroup_id != 1
)
ORDER BY tag_name;

-- -------------------------------------- 3 Zuordnung Tag -> Node (Junction)
-- Der JOIN auf cms_nodes bleibt, er filtert verwaiste Junction-Zeilen weg --
-- genau das tat der innere Subselect der alten Fassung auch.
SELECT
	tin_tag_id,
	tin_node_id
FROM cms_tags_in_nodes
JOIN cms_nodes ON node_id = tin_node_id
WHERE tin_tag_id IN (
	SELECT tag_id FROM cms_tags
	WHERE tag_taggroup_id IN (
		SELECT taggroup_id FROM cms_taggroups WHERE taggroup_id != 1
	)
);
