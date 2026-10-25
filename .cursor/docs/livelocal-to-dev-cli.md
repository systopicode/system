# LIVE.local→DEV Migrations-CLI

Autonomes Kommandozeilen-Tool, das den Datenstand aus LIVE.local (Dateiabbild + SQL-Dump auf der Netzfreigabe) nach DEV überträgt und Schema-/Format-Migrationen ausführt.

Quelle ist immer LIVE.local, **nie** der LIVE-Server. Das Befüllen von LIVE.local übernimmt `bin/live-to-livelocal.php`.

## Voraussetzungen

- DEV-DB `pwk_dev` erreichbar (`systop`/`systop` @ `127.0.0.1`)
- `mysql` / `mysqldump` (Laragon-Pfad wird automatisch erkannt)
- LIVE.local aktuell: `W:\pwk.burda.com\var` (dieses Tool synct **nicht**, dafür `live-to-livelocal`)
- Neuester Dump: `W:\pwk.burda.com\var\backups\*LIVE*.sql` (Timestamp im Dateinamen)
- Genug Disk für `public/var` (~15 GB) plus den archivierten Vorgänger unter `V:\www\backup`

## Aufruf

```powershell
cd v:\www\projects\pwk.systopic.net

# Full (Default): Brands + alle Files
php bin/livelocal-to-dev.php --phase=all --yes

# Smoke: Brands voll, Files nur erste 100
php bin/livelocal-to-dev.php --phase=all --file-limit=100 --yes

# Dry-Run (keine Schreibaktionen)
php bin/livelocal-to-dev.php --phase=all --dry-run

# Einzelne Phasen
php bin/livelocal-to-dev.php --phase=backup,import,sql --yes
php bin/livelocal-to-dev.php --phase=media --yes
php bin/livelocal-to-dev.php --phase=files --file-limit=0 --yes
php bin/livelocal-to-dev.php --phase=scripts --yes
php bin/livelocal-to-dev.php --phase=scripts --script=003 --yes
```

Doppelklick-Variante: `bin\livelocal-to-dev.bat` (reicht Argumente durch).

## Phasen

| Phase | Aktion |
|-------|--------|
| `backup` | `mysqldump` → `public/var/backups/<ts>-pwk_dev-pre-live-import.sql` |
| `import` | Tabellen in `pwk_dev` droppen, neuesten `*LIVE*.sql` importieren |
| `sql` | `migrations.sql` chronologisch (älteste zuerst), inkl. Settings-Actions; danach Rebuild von `pwk_publication_deps` |
| `media` | `public/var` → `V:\www\backup\var.<projekt>-<ts>` verschieben; Copy von `W:\…\var` (Brands immer voll; Files per `--file-limit`, Default 0=alle) |
| `files` | nur `files/` syncen, **ohne** var-Rotation (nach Smoke sinnvoll) |
| `scripts` | PHP-Scripts: 004 → 003 → 002 → 006; Extra-Brand-Uploads → Subfolder `bucket` |
| `verify` | Pflicht-Tabellen (inkl. Publishing: instances/files/deps/logs) + Spalten-Check + grobe Counts |
| `all` | alle Phasen |

Fehlende Library-Formate unter `var/{brands\|files\|…}/{idpath}/{subfolder}/{filebase}.{ext}` werden on-demand gebaut (`system/media.php`).

## Archivierung von public/var

Die Phase `media` **verschiebt** das bisherige `public/var` aus dem Projekt heraus nach `V:\www\backup\var.<projektname>-<timestamp>` (Zeitstempel in Ortszeit). Beide Pfade liegen auf `V:`, der Umzug ist daher ein `rename` und unabhängig von der Größe sofort fertig.

Zielverzeichnis überschreibbar mit `--var-archive=DIR`. Das alte `var/cursor` wird in das frische `var` zurückkopiert.

## Script-Reihenfolge

1. **004** PERSONS `people` → `persons` (oft No-Op)
2. **003** BRANDS name-Ordner → Format-Slots + JSON (**immer vollständig**)
3. **002** FILES flat → `source` + JSON (`--file-limit`)
4. **006** FILES `sourcefiles` → `source` + JSON (`--file-limit`)

## Settings-Actions

In `migrations.sql`:

```text
action: addGeneralSettingsTab;   # chronologisch zuerst
action: addPluginSettingsTab;    # danach (Root-Geschwister von general)
```

CLI führt sie idempotent aus (nur wenn `setting_name` fehlt). Reihenfolge absichtlich **General vor Plugin**.

## Logs

`public/var/cursor/livelocal-to-dev-<timestamp>.log`

PHP-Warnings, Notices und Deprecations werden als `[WARN]` in dieselbe Logdatei geschrieben (`Logger::installPhpHandlers`).

Nach dem Import normalisiert die CLI automatisch:
- `sys_migration` → `sys_migrations`
- `usm_user_in_group` → `usm_users_in_groups`
- `usm_function_in_group` → `usm_functions_in_groups`

(ohne Rename bricht die Homepage an `unmatched table 'usm_function_in_group'`.)

Die Phase `sql` liest `migrations.sql` im Projekt-Root (neueste Statements oben; Apply älteste zuerst). Dazu gehören auch die Publishing-Migrationen (`pwk_publication_instances`, `_files`, `_deps`, `_logs`, Umbau von `pwk_publications`). Anschließend wird `publicationIndex::rebuildAll()` aufgerufen.

Nur Schema nachziehen (ohne Import):

```powershell
php bin/livelocal-to-dev.php --phase=sql,verify --yes
```

## Manuell

- Archivierte `V:\www\backup\var.*` nach Erfolg selbst löschen
- LIVE-Dump im Adminbereich auf LIVE erzeugen, danach `php bin/live-to-livelocal.php` — der Dump kommt mit dem Dateiabgleich nach LIVE.local

## Scripts erneut (wenn Brands/Files noch flach)

```powershell
php bin/livelocal-to-dev.php --phase=scripts --file-limit=100 --yes
```

## Code

- Entry: `projects/pwk.systopic.net/bin/livelocal-to-dev.php`
- Module: `projects/pwk.systopic.net/bin/livelocal-to-dev/*`
