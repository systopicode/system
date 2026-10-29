# Rechte: Content Groups und ACLs

Stand 2026-10-29.

## Das Modell

```
usm_users ── usm_users_at_roles ── usm_roles ── usm_roles_on_groups (read/write) ── usm_groups
                                                                                       │
                     cms_nodes.node_acl_id ── usm_acls ── usm_groups_in_acls ──────────┘
```

- **Gruppe** = Inhaltsbereich (HRDocuments, BoardDocuments).
- **Rolle × Gruppe** = Lese- und Schreibrecht (`usm_roles_on_groups`).
- **ACL** (Access Control List) = eine Menge von Gruppen (`usm_acls`,
  `usm_groups_in_acls`). Ein Element zeigt auf **eine** ACL und gehört damit
  zu allen ihren Gruppen. Es gilt ODER: Wer eine der Gruppen lesen darf, darf
  das Element lesen. Für Schreiben gilt dasselbe.
- **NULL** = keine Gruppe: nur der Besitzer (`*_user_id`) und Superuser.
  Öffentlich ist eine Seite nie von allein, sondern über eine Gruppe (z. B.
  „öffentlich“), die die eingebaute Rolle `everyone` lesen darf.

## Eingebaute Rollen

`usm_roles.role_builtin` (unique): `everyone` (jeder Aufruf, auch ohne
Login) und `authenticated` (jeder Eingeloggte), definiert in
`Roles\Operator::BUILTIN`. Erkannt werden sie nur am Schlüssel, der Name ist
änderbar.

- `UserRights::builtinRoles()` legt fehlende Zeilen beim ersten Gebrauch an,
  einmal pro Installation.
- `Identity::rightsRoleIds()` gibt jedem `everyone` dazu, Eingeloggten
  zusätzlich `authenticated`.
- In der Rechte-Matrix stehen sie wie andere Rollen. Einem User zuweisen,
  löschen oder als Fake-Rolle wählen kann man sie nicht
  (`UserAdmin::assignableRoles()`, `Identity::allRoleNames`).

## Seiten: Website und CMS

`Params::$readAcls` filtert `PageTree.sql`, `.nodes.sql`, `.ancestors.sql`
und `PageDetail.sql`: `node_acl_id IN (:readAcls)` oder der Viewer besitzt
den Node. Damit gilt es für Navigation, Route, Links, sitemap.xml, llms.txt
und den Seitenbaum im CMS. Gesetzt wird es von `PageAccess::readAcls()`
(cms, `src/Pages/PageAccess.php`), für Website und Seiten-Panel.

- **NULL** heißt ungefiltert: Superuser (ohne Fake-Rollen), Recovery, und
  jede interne Abfrage (`new Params(lang: '')` für Trash, Removal …).
- **Projekt-Schalter `PAGE_ACL`** (global settings bzw. `settings.php`):
  Ohne ihn ist der Filter aus. Jede Installation hat anfangs nur Nodes ohne
  Gruppe, die Website wäre beim Einschalten leer.
- **Einschalten:** erst eine Gruppe „öffentlich“ mit Leserecht für
  `everyone` anlegen, sie allen Wurzelseiten und eigenen ACLs hinzufügen,
  dann `PAGE_ACL` auf 1. So sieht jeder Besucher dasselbe wie vorher.
- Eine nicht lesbare Seite antwortet wie eine nicht vorhandene URL.

### Schreiben im CMS

`PageAccess::mayWrite($node)` / `mayEdit($page)` (Alias: auch das Original)
gelten mit denselben Ausnahmen wie das Lesen (ohne `PAGE_ACL`, Superuser,
Recovery: alles erlaubt). Geprüft wird in den Actions von `cms/pages`
(`refuses()` / `refusesEdit()` geben die Meldung aus):

| Was | braucht Schreibrecht auf |
|---|---|
| Inhalt, Label, Veröffentlichen, neue Sprache, Seitenleiste | die Seite (bei Alias auch das Original) |
| Neue Unterseite, Alias als Kind, Ablegen *in* | die Seite |
| Neu daneben, Duplizieren, Ablegen *vor/nach*, Alias danach | den Parent |
| Verschieben | die Seite **und** den neuen Parent |
| Löschen / Wiederherstellen | alle Nodes des Zweigs (sonst nichts) |
| Alles veröffentlichen | je Seite, der Rest bleibt und wird gemeldet |
| Medien (Bibliothek, Upload, Datei-Editor, Tags) | den Media-Node bzw. die Zielseite |

Die oberste Ebene (kein Parent) hat keine Gruppen: dort legt nur der
Superuser an. Der Baum zeigt nicht beschreibbare Seiten blass mit Schloss,
ohne Griff und ohne Drop-Zonen; das Kontextmenü zeigt nur Erlaubtes, die
Felder einer solchen Seite rendern wie auf der Website. Der Superuser sieht
bei `PAGE_ACL` Marken: eigene Gruppen unterhalb der obersten Ebene
(`fa-users`, Titel = Gruppen) und „keine Gruppe“ (`fa-user-lock`).

`Session::mayWrite()` lässt einen Superuser (ohne Fake-Rollen) immer zu,
auch bei einer Gruppe, die keine Rolle schreiben darf (z. B. „öffentlich“).

## ACLs sind unveränderlich und dedupliziert

Jede Gruppenmenge gibt es genau einmal. `acl_group_ids` (`3,7`, sortiert)
ist der Unique-Key. Mehrere Elemente teilen sich eine ACL.

- **Gruppen eines Elements ändern** heißt: mit
  `Acls\Operator::forGroups($scope, [...])` die passende ACL holen bzw.
  anlegen, speichern und das Element darauf umhängen.
- **Die Zeilen einer bestehenden ACL nie ändern.** Sonst ändern sich alle
  Elemente mit, die sie teilen.
- Verwaiste ACLs schaden nicht.

Weil es nur wenige ACLs gibt, filtert eine Suche wie bisher mit
`x_acl_id IN (-1,:read_acl_ids)`. Die IDs kommen aus
`Session::current()->readAclIds` bzw. `->writeAclIds`
(`UserRights.acls.sql`). Ausgewertet wird pro Aufruf über den Scope-Cache,
daher ist eine im selben Request angelegte ACL sofort dabei.

## Bäume: effektiver Wert und Vererbungs-Flag

`cms_nodes` speichert immer die **effektive** ACL (`node_acl_id`) und
`node_acl_inherit`:

- `1`: übernommen vom Parent. Ein Root ohne Parent erbt keine Gruppe, also NULL.
- `0`: eigene ACL. Die Vererbung endet hier.

Setzen über `Nodes\Operator::withAcl($node, $acl)` (eigene ACL) und
`inheritingAcl($node)` (wieder erben), dann speichern.

In der Oberfläche: Seitenleiste von `cms/pages/` → Content → Permissions
(`packages/cms/panels/cms/010_pages/sidebar/045_permissions.tpl.php`,
`actions.permissions.php`). Wer nicht Superuser ist, schaltet nur Gruppen
um, auf die er Schreibrecht hat.

Das Weitergeben an die Nachkommen macht der Observer
`Nodes\AclInheritance` (registriert in `autoload.php`), und zwar nach
**jedem** Commit, in dem sich bei einem Node `parentNode`, `acl` oder
`aclInherit` geändert hat, egal welcher Code ihn geschrieben hat. Er
schreibt per SQL-`UPDATE` und `hydrate()`, nicht über eine Unit of Work:
Ein verschachtelter Commit würde den anderen Observern die Changes der Zeilen
wegnehmen.

**Daten, die am ORM vorbei geschrieben wurden** (Import, SQL), richtet
`AclInheritance::repair($scope)` von den Roots her wieder gerade.

## Andere Tabellen

Tabellen ohne Baum (z. B. `pwk_patchworks`) bekommen bei Bedarf eine
Beziehung `?Acl $acl` ohne Flag. `Session::mayRead`/`mayWrite` prüft eine
ORM-Zeile mit `acl`-Property über die ACL, sonst wie bisher über `group`
bzw. `group_id`. In pwk laufen die alten `*_group_id`-Felder vorerst weiter,
eine Migration steht noch aus.

## Prüfen

```bash
FLUXO_TEST_DSN="mysql:host=127.0.0.1;dbname=fluxo_test_acls;charset=utf8mb4" php tests/acls.php
```

Der Test löscht die Datenbank und legt sie neu an. Sie muss mit
`fluxo_test_` beginnen.

## Fallen

- **Zeile 1 der usm-Tabellen ist der leere Default-Datensatz.**
  `UserRights.roles.sql` überspringt `role_id = 1`.
  - *Symptom:* In einer frischen Test-DB hat die erste angelegte Rolle keine
    Rechte.
  - *Lösung:* Zuerst leere Group/Role/User anlegen.
- **Rows einer früheren Abfrage behalten ihre Werte.** Eine spätere Abfrage
  füllt nur Lücken. Was am ORM vorbei in die DB geschrieben wurde, sieht ein
  Row-Objekt im selben Request nicht. Deshalb liest `NodeAcls::all()` für die
  Reparatur Rohwerte.
- **Die alten Spalten** `node_group_id`, `node_permissions` und
  `node_permissions_inherit` stehen nicht mehr im Model. `db audit` schlägt
  vor, sie zu löschen. Sie sind nullable mit Default, Inserts gehen also auch
  vorher.
