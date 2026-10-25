# Schema-Migrationen nachziehen

`migrations.sql` im Projekt-Root (neueste Statements oben). Angewendet
werden sie älteste-zuerst; der Stand steht in `sys_migrations`.

## Automatisch beim Seitenaufruf

Nach dem DB-Connect zieht `dbMigrations::ensurePendingSchema()` nach:

- fehlende **Tabellen** (`CREATE TABLE IF NOT EXISTS`)
- fehlende **Spalten** (`ALTER TABLE … ADD COLUMN`)

`INSERT` / `DROP` / `CHANGE` laufen **nicht** von allein.

Fehlt eine Spalte trotzdem noch in einem schon geladenen Result
(`dbStatementResult:prop not found`), wird dasselbe ADD COLUMN nachgeholt
und die Property als `null` gelesen — die Seite bleibt erreichbar.

## Alle offenen Statements

Im Admin unter `/admin/migrations/sql/` gibt es
**„offene Migrationen jetzt ausführen“**. Das ist der Lauf für
Daten-Statements (z.B. Tag-Zuweisungen auf `pwk_tags_on_patchworks`).

CLI gegen die lokale DEV-DB:

```powershell
php bin/apply-migrations.php --run
```

Auf dem Staging-Host (nach Code-Sync):

```bash
php bin/apply-migrations.php --staging --run
```

Ohne `--run` wird nur das Migrations-Panel gerendert (Zählung / Sicht).

Die Instanz (und damit die Datenbank) kommt aus `config/`: erkannt über die
Socket-IP bzw. `rootDir`, festlegbar mit `"instance": "STAGING"` im
CLI-JSON. Siehe `config.md`.
