# Das ORM — wie man damit eine Anwendung baut

Vier Schichten, von unten nach oben. Jede kennt nur die unter ihr.

```
Db/            Verbindung, Zeilen, Abfragen, Schreiben, Schema — weiß nichts von der Anwendung
Tables/<T>/    eine Tabelle: Model (die Zeile), Operator (was die Tabelle kann), Facetten
Queries/<Q>/   ein benannter SELECT über mehrere Tabellen, mit seiner .sql-Datei daneben
panels/        Panels und Templates: fragen Queries, halten Objekte, rufen Operatoren
```

Die Regeln in einem Absatz: **Eine Zeile ist ein Objekt**, im ganzen Request
nur einmal, egal welche Abfrage sie brachte. **Abfragen sind SQL-Dateien**,
nicht zusammengesetzte Strings; ihr Ergebnis liegt im Request-Cache, bis eine
beteiligte Tabelle geschrieben wird. **Schreiben geht über eine Unit of
Work**, in einer Transaktion, nur die geänderten Spalten. **Logik liegt in
Operatoren** — zustandslos, sie geben zurück, was zu speichern ist. Das
Schema kommt aus den Klassen: fehlt eine Tabelle oder Spalte, legt der
Provisioner sie beim ersten Fehler an.

Das Beispiel unten ist eine Ahnendatenbank: Personen, Orte, Quellen.

---

## 1 · Eine Tabelle: `Tables/<Name>/Model.php`

Ein Ordner je Tabelle, benannt wie die Tabelle ohne Präfix, in PascalCase.
Die Zeilenklasse heißt immer `Model`; wer sie benutzt, gibt ihr beim Import
einen sprechenden Namen.

```php
namespace Systopic\System\Tables\Persons;

use DateTimeImmutable;
use Systopic\Db\Schema\Col;
use Systopic\Db\Schema\Index;
use Systopic\Db\TableRow;
use Systopic\Db\Type\Text;
use Systopic\System\Tables\Places\Model as Place;

/** Eine Person — `ahnen_persons`. */
final class Model extends TableRow
{
    public const TABLE  = 'persons';   // ohne Präfix; das kommt aus DEFAULT_TABLE_PREFIX
    public const PREFIX = 'person';    // Spaltenpräfix: person_id, person_name …

    #[Col(length: 120, index: Index::Plain)]
    public ?string $name { get => $this->__get__(__PROPERTY__); set => $this->__set__(__PROPERTY__, $value); }

    public ?DateTimeImmutable $born { get => $this->__get__(__PROPERTY__); set => $this->__set__(__PROPERTY__, $value); }
    public ?DateTimeImmutable $died { get => $this->__get__(__PROPERTY__); set => $this->__set__(__PROPERTY__, $value); }

    /** Eine Beziehung: die Spalte heißt person_father_id und trägt einen Fremdschlüssel. */
    public ?Model $father { get => $this->__get__(__PROPERTY__); set => $this->__set__(__PROPERTY__, $value); }
    public ?Model $mother { get => $this->__get__(__PROPERTY__); set => $this->__set__(__PROPERTY__, $value); }

    public ?Place $birthplace { get => $this->__get__(__PROPERTY__); set => $this->__set__(__PROPERTY__, $value); }

    public ?Text $notes { get => $this->__get__(__PROPERTY__); set => $this->__set__(__PROPERTY__, $value); }

    // ---- virtuell: berechnet, keine Spalte ----------------------------------

    public bool $isAlive { get => $this->died === null; }
}
```

Was hier steht, ist die ganze Definition der Tabelle:

- **Eine Spalte ist ein Property**, das public ist, Hooks hat und nicht
  virtuell ist. `get => $this->__get__()` liefert den Wert (oder holt ihn nach,
  wenn die Abfrage die Spalte nicht mitgebracht hat), `set =>
  $this->__set__()` prüft ihn gegen die Deklaration und merkt ihn als Änderung.
  Ein Property nur mit `get` ist berechnet und keine Spalte.
- **Der Typ ist die Spaltendefinition.** `?string` wird `VARCHAR(255) NULL`,
  `int` wird `INT`, `bool` wird `TINYINT(1)`, `DateTimeImmutable` wird
  `DATETIME`. Was PHP nicht sagen kann, sagt `#[Col]`: Länge, Index,
  unsigned, Nachkommastellen. Typklassen für den Rest: `Type\Text` (TEXT),
  `Type\Decimal`, `Type\Date` (DATE ohne Zeit), `Type\Binary`, `Type\BigInt`.
- **Eine Beziehung ist ein Property vom Typ einer anderen `Model`-Klasse.**
  Die Spalte heißt `<präfix>_<property>_id` und bekommt einen Fremdschlüssel.
  `$person->father` liefert das Objekt: aus derselben Abfrage, wenn sie es
  mitbrachte, sonst aus der IdentityMap, sonst als lazy Ghost, der seine
  Zeile beim ersten Zugriff holt.
- **`$id` ist immer da** und nicht deklariert: `<präfix>_id`, AUTO_INCREMENT.
  NULL heißt „noch nicht in der Datenbank“.
- **Ein Wert, der nicht in die Deklaration passt, wird bei der Zuweisung
  abgelehnt** (`ValueRejected`): ein 300-Zeichen-Name für `length: 120`,
  NULL für ein nicht-nullbares Property. Das passiert im Setter, nicht erst
  beim Speichern.

### Eine Verbindungstabelle

Der Schlüssel ist ein Tupel, `KEY` sagt aus welchen Properties, in
Schlüsselreihenfolge. So eine Zeile hat kein `$id`.

```php
namespace Systopic\System\Tables\PersonsInSources;

final class Model extends TableRow
{
    public const TABLE  = 'persons_in_sources';
    public const PREFIX = 'pis';
    public const KEY    = ['person', 'source'];   // pis_person_id, pis_source_id

    public ?Person $person { get => $this->__get__(__PROPERTY__); set => $this->__set__(__PROPERTY__, $value); }
    public ?Source $source { get => $this->__get__(__PROPERTY__); set => $this->__set__(__PROPERTY__, $value); }

    /** Die Seite in der Quelle, auf der die Person vorkommt. */
    public ?int $page { get => $this->__get__(__PROPERTY__); set => $this->__set__(__PROPERTY__, $value); }
}
```

### Tabellen aus einem anderen Präfixraum

`TABLE_PREFIX` setzt den Präfix fest, wenn er nicht der der Installation ist:
so teilen sich mehrere Installationen eine Benutzertabelle.

```php
public const TABLE        = 'users';
public const TABLE_PREFIX = 'usm';   // usm_users, überall
```

### Wie die Tabellen entstehen

Aus den Klassen. `Registry::scanTables()` liest `src/Tables/*/Model.php` ein
(die Scope tut das beim ersten Gebrauch); `Schema::of(Model::class)` baut
daraus die Tabellendefinition. Anlegen tut sie der **Provisioner**: bei dem
Fehler, den eine fehlende Tabelle oder Spalte auslöst, nicht durch Vergleich
vorab. Eine Abfrage, die ihn bekommt, wird nach der Reparatur wiederholt:

```php
$scope->rows($file, $params, provisioner: new Provisioner($scope->connection));
```

Was er nicht tut, weil es keinen Fehler auslöst — Spalten verengen, löschen,
umbenennen —, zeigt `Audit` als Liste von Aktionen (`db status` und `db
audit` im CMS), und `Migrations` hängt Datenmigrationen an das Anlegen oder
Verbreitern einer Spalte.

**Falle MariaDB (2026-10-27, behoben in systopic/db):**
- *Symptom:* `db status` zeigt auf staging für jede nullable Spalte
  `DEFAULT 'NULL'` als Unterschied (spaceticker: 177). Lokal (MySQL) sind es 0
  bei gleicher Datenbank. `db audit` führt alle Aktionen fehlerfrei aus, und
  danach ist alles wie vorher.
- *Ursache:* MariaDB liefert `information_schema.COLUMNS.COLUMN_DEFAULT` als
  SQL-Text: `NULL` für keinen Default, Strings mit Quotes.
- *Lösung:* `Column::schemaDefault()` normalisiert das, wenn
  `Connection::isMariaDb()` zutrifft. Wer `COLUMN_DEFAULT` selbst liest, muss
  es auch durch diese Funktion schicken.

**Falle Spalte mit Fremdschlüssel löschen (2026-10-29, behoben in systopic/db):**
- *Symptom:* `db audit` → `drop column cms_nodes.node_group_id` scheitert mit
  `1828 Cannot drop column … needed in a foreign key constraint`, auch mit
  ausgeschalteten FK-Checks.
- *Ursache:* Die FK-Checks prüfen nur Daten, nicht die Struktur. Solange ein
  Constraint auf der Spalte liegt, lässt MySQL sie nicht löschen.
- *Lösung:* Die `DropColumn`-Aktion löscht zuerst jeden Fremdschlüssel auf
  der Spalte (eigene und eingehende), dann die Spalte.

---

## 2 · Lesen: die Scope

Alles, was ein Request liest und schreibt, geht durch **eine** `Db\Scope`:

```php
use Systopic\Db\Scope;

$scope = Scope::default();          // die Scope dieses Requests
$scope->connection;                 // die Verbindung
$scope->identity;                   // ein Objekt je Zeile
$scope->results;                    // Abfrageergebnisse dieses Requests
$scope->uow();                      // eine Unit of Work
$scope->save(...$rows);             // Zeilen rein, ein Commit
$scope->remove(...$rows);           // Zeilen raus, ein Commit
$scope->write($save, $remove);      // beides, ein Commit
```

Eine Abfrage läuft so:

```php
$rows = $scope->rows(__DIR__ . '/Ancestors.sql', ['personId' => 42, 'depth' => 6]);
$rows->all(Person::class);          // jede Person, ohne Wiederholung
$rows->first(Place::class);
$rows->records();                   // je Zeile: [Klasse => Objekt|null, …]
$rows->looseValues();               // je Zeile: Spalten, die zu keiner Tabelle gehören
```

Was dabei passiert: Die Datei wird ausgeführt, PDO sagt zu jeder Spalte, aus
welcher Tabelle sie kommt, die Registry sagt, welche Klasse zur Tabelle
gehört, und der RowReader baut je Zeile ein Objekt pro beteiligter Tabelle.
Zwei Zeilen mit derselben `person_id` sind dasselbe Objekt (IdentityMap).
Bringt eine spätere Abfrage mehr Spalten derselben Zeile, werden die Lücken
gefüllt. Wird eine Spalte gelesen, die keine Abfrage geliefert hat, holt die
Zeile sie sich einmal selbst (`SELECT * WHERE id = ?`); für eine ganze Gruppe
auf einmal geht das mit `$scope->need($row, 'notes')`.

**Der Ergebnis-Cache.** Dieselbe Datei mit denselben Platzhaltern kostet im
selben Request eine Abfrage. Welche Tabellen ein Ergebnis gelesen hat, weiß
es selbst (aus den PDO-Metadaten); ein Commit, der eine davon schreibt,
wirft es weg. Es gibt nichts zu invalidieren und nichts nachzuführen.

### Die SQL-Datei

Handgeschrieben, ein SELECT über so viele Tabellen wie nötig, Platzhalter
mit Doppelpunkt. Ein Array-Platzhalter (`IN (:ids)`) wird textuell
eingesetzt; ein Platzhalter, der im SQL nicht vorkommt, wird nicht gebunden.
Am Ende stehen die Platzhalter als Kommentar, damit man sie sieht:

```sql
-- Die Vorfahren einer Person, bis zu :depth Generationen — eine Anweisung.

WITH RECURSIVE line (id, generation) AS (
    SELECT person_id, 0 FROM ahnen_persons WHERE person_id = :personId
    UNION ALL
    SELECT p.person_id, line.generation + 1
    FROM ahnen_persons p
    JOIN ahnen_persons child ON child.person_father_id = p.person_id OR child.person_mother_id = p.person_id
    JOIN line ON line.id = child.person_id
    WHERE line.generation < :depth
)
SELECT
    ahnen_persons.*,
    ahnen_places.*,
    line.generation
FROM line
JOIN ahnen_persons ON person_id = line.id
LEFT JOIN ahnen_places ON place_id = person_birthplace_id
ORDER BY line.generation, person_born

-- :personId
-- :depth
```

`line.generation` gehört zu keiner Tabelle und kommt als **loser Wert** zurück,
je Zeile neben den Objekten. Mehrere Ergebnismengen in einer Datei
(`SELECT …; SELECT …;`) sind erlaubt und kommen als `$rows->sets()`.

---

## 3 · Eine Query: `Queries/<Name>/<Name>.php`

Eine Query ist eine Klasse mit ihrer SQL-Datei daneben. Sie führt aus und
setzt das Ergebnis zu dem zusammen, was die Anwendung fragt. Sie hat keinen
Zustand; was sie zusammenbaut, legt sie in der Scope ab.

```php
namespace Systopic\System\Queries\Ancestors;

use Systopic\Db\Scope;
use Systopic\System\Tables\Persons\Model as Person;

/** Die Vorfahren einer Person, je Generation. */
final class Ancestors
{
    /** @return array<int, list<Person>> Generation => Personen; 0 ist die Person selbst */
    public static function of(Scope $scope, int $personId, int $depth = 6): array
    {
        $rows = $scope->rows(__DIR__ . '/Ancestors.sql', ['personId' => $personId, 'depth' => $depth]);

        $byGeneration = [];
        foreach ($rows->records() as $i => $record) {
            $person = $record[Person::class] ?? null;
            if ($person !== null) {
                $generation = (int) ($rows->looseValues()[$i]['generation'] ?? 0);
                $byGeneration[$generation][] = $person;
            }
        }
        return $byGeneration;
    }
}
```

Sind die Platzhalter ein fester Satz, den viele Queries teilen — Sprache,
Vorschau, ein Filter —, ist das ein **Params**-Wert: readonly, mit `params()`
für die Platzhalter und `key()` als Schlüssel. Eine Query nimmt dann
`(Scope $scope, Params $params, …)`.

Weitere Abfragen derselben Query hängen ihren Zweck an den Dateinamen:
`Ancestors.sql`, `Ancestors.places.sql`.

### Was aus Ergebnissen gebaut wird, lebt in der Scope

Ein Objekt, das mehrere Ergebnisse verbindet — ein Baum, eine Sammlung — darf
im Request nur einmal existieren und muss weg, wenn seine Ergebnisse weg
sind. Beides tut `objectFor()`:

```php
public static function tree(Scope $scope, Params $params): self
{
    return $scope->objectFor(self::class, $params->key(), static fn() => self::build($scope, $params));
}
```

Der Schlüssel sagt, wofür das Objekt gebaut wurde. Ein Commit räumt alle
diese Objekte mit den Ergebnissen ab.

---

## 4 · Schreiben: die Unit of Work

```php
$person = new Person();
$person->name = 'Anna Berger';
$person->born = new DateTimeImmutable('1871-03-04');
$person->birthplace = Place::withId(17);      // nur die id, ohne die Zeile zu holen

$scope->uow()->persist($person)->commit();    // INSERT, $person->id ist danach gesetzt
```

- **Nur Änderungen werden geschrieben.** Eine geladene Zeile, an der ein
  Feld geändert wurde, wird zu einem UPDATE dieses einen Feldes. Was die
  Abfrage nicht geladen hat, bleibt unberührt.
- **Reihenfolge nach Abhängigkeit.** `$child->father = $newFather` mit einem
  neuen Vater: der Vater wird zuerst eingefügt, seine id landet in der
  Spalte. Neue Zeilen, die über eine Beziehung erreichbar sind, werden
  mitgenommen; geladene nicht — die schreibt, wer sie persistiert.
- **Eine Transaktion.** `persist()` und `remove()` beliebig oft, dann
  `commit()`. Danach werfen die Ergebnisse, die eine geschriebene Tabelle
  gelesen haben, sich weg.
- **Ein Schlüssel wird nicht geändert.** Ein anderer Wert im Schlüssel einer
  gespeicherten Zeile wäre eine andere Zeile: die alte entfernen, die neue
  anlegen.

`$scope->save(...$rows)` ist die Kurzform für „diese Zeilen, ein Commit“,
`$scope->remove(...$rows)` dasselbe fürs Löschen. Gibt ein Operator beides
zurück — `[$zuSpeichern, $zuLöschen]` —, nimmt `$scope->write(...$result)`
es in einem Commit: erst die Inserts und Updates, dann die Deletes in der
gegebenen Reihenfolge (eine Verbindungszeile vor der Zeile, auf die sie
zeigt).

### Ereignisse

Wer wissen will, dass sich eine Spalte irgendeiner Zeile einer Tabelle
geändert hat, sagt es der Scope. Gemeldet wird **nach dem Commit**, mit
altem und neuem Wert; bei Beziehungen sind das ids, beim Löschen ist der
neue Wert NULL.

```php
$stop = $scope->onChange(Person::class, 'father', function (Person $p, ?int $old, ?int $new) {
    // …
});
$stop();   // abmelden
```

---

## 5 · Logik: `Tables/<Name>/Operator.php`

Was eine Tabelle kann, unabhängig davon, welche Abfrage die Zeilen brachte.
Zustandslos: der Operator arbeitet auf dem, was er bekommt, und gibt zurück,
was zu speichern ist. Speichern entscheidet der Aufrufer.

```php
namespace Systopic\System\Tables\Persons;

use Systopic\Db\Scope;
use Systopic\System\Queries\Ancestors\Ancestors;

final class Operator
{
    /** Alter in Jahren an einem Tag — heute, wenn keiner gegeben ist. */
    public static function age(Model $person, ?\DateTimeImmutable $on = null): ?int
    {
        if ($person->born === null) {
            return null;
        }
        $until = $person->died ?? $on ?? new \DateTimeImmutable();
        return $person->born->diff($until)->y;
    }

    /**
     * Einen Elternteil setzen — und dabei den Kreis ausschließen: niemand ist
     * sein eigener Vorfahre.
     *
     * @return list<Model> was zu speichern ist
     */
    public static function setParent(Scope $scope, Model $child, Model $parent, string $side): array
    {
        foreach (Ancestors::of($scope, (int) $parent->id, 20) as $generation) {
            if (in_array($child, $generation, true)) {
                throw new \DomainException("{$parent->name} stammt von {$child->name} ab");
            }
        }
        $child->$side = $parent;   // 'father' oder 'mother'
        return [$child];
    }
}
```

Ein Operator, der die Datenbank braucht, nimmt die Scope als Argument; er
schreibt trotzdem nicht selbst. Formularwerte in typisierte Werte zu
verwandeln, Zeilen zu kopieren, eine Sortierung neu zu nummerieren — das
alles ist Operator-Arbeit.

### Facetten

Beantwortet eine Klasse Fragen über **eine** Zeile und braucht dafür
Abfragen — „die Eltern dieser Person“, „ihre Geschwister“ —, liegt sie
neben Model und Operator: `Tables/Persons/Person.php`. Sie nimmt Scope (und
Params), hält die Zeile als `$row` und holt sich Ergebnisse über Queries.
Auch sie existiert je Request nur einmal (`objectFor`).

---

## 6 · Im Panel

Das Panel besitzt die Objekte, mit denen Templates und Actions arbeiten, als
**Getter-Properties**. Templates lesen sie, Actions rufen Operatoren und
speichern über die Scope. Nichts wird in einer Action oder in einem
Template gebaut.

```php
namespace Systopic\System\Panels\Root\Ahnen;

use Systopic\Db\Scope;
use Systopic\System\Queries\Ancestors\Ancestors;
use Systopic\System\Tables\Persons\Person;

class Panel extends PanelNode
{
    public Scope $scope { get => Scope::default(); }

    /** Die gezeigte Person — NULL, wenn keine gewählt ist. */
    public ?Person $person {
        get => ($id = (int) ($this->state->id ?? 0)) > 0 ? Person::load($this->scope, $id) : NULL;
    }

    /** @var array<int, list<\Systopic\System\Tables\Persons\Model>> */
    public array $ancestors {
        get => $this->person === NULL ? [] : Ancestors::of($this->scope, $this->person->id);
    }
}
```

Die Getter dürfen bei jedem Zugriff rechnen: die Abfragen dahinter sind
gecacht, die Objekte in der Scope dedupliziert. Was das Panel **nicht** tut:
sich Objekte merken und nach einem Schreibzugriff selbst aufräumen. Das tut
der Commit.

Eine Action:

```php
case 'setFather':
    $father = Person::load($this->scope, (int) http::dataset('father_id'));
    if ($this->person === NULL || $father === NULL) {
        break;
    }
    $this->scope->save(...PersonOperator::setParent($this->scope, $this->person->row, $father->row, 'father'));
    $this->updateView('main');
    break;
```

Ein Template:

```php
<?php
/** @var \Systopic\System\Panels\Root\Ahnen\Panel $this */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<h1><?= $e($this->person->row->name) ?></h1>
<?php foreach ($this->ancestors as $generation => $persons) { ?>
    <h2>Generation <?= $generation ?></h2>
    <?php foreach ($persons as $p) { ?>
        <p><?= $e($p->name) ?>, <?= PersonOperator::age($p) ?> Jahre<?= $p->birthplace ? ', ' . $e($p->birthplace->name) : '' ?></p>
    <?php } ?>
<?php } ?>
```

---

## 7 · Prüfen

Ein Prüfskript je Panel, das jede Action über `index.php` fährt wie ein
Browser, eigene Zeilen anlegt, in der Datenbank nachsieht und hinterher
aufräumt (Vorlage: `.cursor/tools/pages-panel-check.php`). Ein zweites ohne
Datenbank für Operatoren und Ereignisse (`events-check.php`). Ein Rauchtest
über alle Panels mit Query-Zahlen (`smoke-galerie.php`). Alle drei laufen
nach jeder Änderung; eine Query-Zahl, die steigt, ist ein Befund.

---

## 8 · Spickzettel

```php
// Zeile
Person::withId(17)                       Stand-in, nur die id
new Person()                             neu, nullbare Spalten sind NULL
$p->modified()  $p->changes()            was geändert wurde / alt und neu
$p->relationId('father')                 die id, auf die die Beziehung jetzt zeigt

// Scope
Scope::default()   ->rows($file, $params, Completion::Group, provisioner: …)
->uow()->persist()->remove()->commit()   ->save(...$rows)  ->remove(...$rows)  ->write($save, $remove)
->need($row, 'col', …)  ->value($row, 'col')
->objectFor($class, $key, fn)            ->onChange($class, $prop, fn)
->queryCount()  ->results->hits()

// Schema
Registry::scanTables()  Schema::of(Model::class)  Schema::tableNameOf(Model::class)
new Provisioner($conn)  new Audit($conn)->run()  new Status($conn)->tables()
```
