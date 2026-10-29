# Git — Repos, Sync und Arbeitsplätze

Stand 2026-10-16. Gilt für alle Repos unter `packages/` und `projects/` und
für jeden Arbeitsplatz, denn alle sind gleich aufgebaut (Laragon,
`C:\laragon\www\packages\…`, `…\projects\…`).

## Die Repos

| Repo | Sichtbarkeit | GitHub | Releases |
|---|---|---|---|
| `packages/system` | öffentlich | `systopicode/system` | Tags `v1.x`, Packagist `systopic/system` |
| `packages/db` | öffentlich | `systopicode/db` | Tags, Packagist `systopic/db` |
| `packages/cms` | öffentlich | `systopicode/cms` | Tags, Packagist `systopic/cms` |
| `packages/relay` | öffentlich | `systopicode/relay` | Tags, Packagist `systopic/relay` |
| `projects/<projekt>` | **privat** | `systopicode/<projekt>` (bisher nur `spaceticker.app`) | keine |

Pakete sind öffentlich, Projekte privat. Kein Repo haben `packages/tools`
(Entwicklerwerkzeug, `sync.php` kommt per Rollout), `packages/system-legacy`
und die übrigen Projekte (noch nicht).

## Pull und Push: immer alle Repos

**Regel des Benutzers (2026-10-27):** Wer pullt oder pusht, nimmt alle Repos
**des aktuellen Workspace** mit, nicht nur das, in dem gerade gearbeitet
wird. Ein Projekt hängt an seinen Paketen, und ein halb aktualisierter Stand
passt nicht zusammen.

- **Welche Repos:** die Ordner des Workspace, die ein `.git` haben, und nicht
  alles unter `packages/`. Für Claude sind das das Arbeitsverzeichnis und die
  „Additional working directories“. Die Quelle ist
  `<Wurzel>\ide\cursor.files\<name>.code-workspace` (`"path"`-Einträge). So
  gehört `cms` zu `spaceticker.app.src`, aber nicht zu `spaceticker.app`.
  Ordner ohne `.git` (z. B. `tools`) überspringen.
- **Pull:** in jedem Repo erst `fetch`, dann `status`. Ohne lokale Änderungen
  geht `pull --ff-only`. Mit lokalen Änderungen siehe
  [Nach einem Sync](#nach-einem-sync-git-nachziehen).
- **Push:** alle Repos mit ungepushten Commits zeigen und dann auf einmal
  pushen, sobald der Benutzer es sagt (siehe unten, „Pushen nur nach
  Rücksprache“).
- Am Ende immer eine Tabelle: Repo, Stand vorher, Stand nachher.

`.cursor/docs/` liegt im Repo. Pläne, Regeln und Prüfskripte unter `.cursor/`
bleiben lokal und wandern nur per Sync zwischen den Arbeitsplätzen.

## Sync für Dateien, Git für die Geschichte

- **Sync** bringt Dateien schnell von einem Arbeitsplatz zum anderen oder
  auf einen Server, zum Testen oder Weiterarbeiten. Er überträgt die
  `.gitignore` (seit 2026-10-16), aber **nie `.git`**.
- **Git** gibt es auf jedem Arbeitsplatz einzeln, jeder hat sein eigenes
  `.git`. Der gemeinsame Stand liegt auf GitHub.
- **Committet wird auf dem Arbeitsplatz, an dem gearbeitet wurde.** Kommt
  ein Stand per Sync an, wird dort nichts neu committet. Git wird nur
  nachgezogen (siehe unten).
- **Committet wird je Feature, erst wenn es abgeschlossen ist** (Regel des
  Benutzers, 2026-10-29). Abgeschlossen ist es, wenn der Benutzer es sagt
  oder wenn das Thema wechselt. Im Zweifel fragt Claude nach („Ist X fertig,
  soll ich committen?“). **Nicht** nach jedem Prompt und nicht nach jeder
  Korrektur: Nachbesserungen (Abstand, Farbe, Spalte) gehören in den Commit
  des Features, nicht in je einen eigenen. Zwischenstände bleiben
  uncommittet.
- **Pushen nur nach Rücksprache.** Claude committet lokal und pusht, wenn
  der Benutzer es sagt („bitte pushen“), auch das erst nach Abschluss des
  Features. Liegen beim Ende einer Sitzung
  ungepushte Commits vor, fragt Claude nach. Gepusht wird als `ohjay666`
  bzw. `sabietzki` (Lars) über den Git Credential Manager, mit
  `GIT_TERMINAL_PROMPT=0`, damit nichts auf eine Eingabe wartet. Der
  Benutzer selbst pusht mit GitHub Desktop oder `git push`.
- **Server haben kein Git.** Sie bekommen Pakete über Composer (Releases von
  Packagist) bzw. über den gesyncten `packages/`-Spiegel, siehe
  [composer.md](composer.md) und [deployment.md](deployment.md).

## Nach einem Sync: Git nachziehen

Nach dem Sync zeigt `git status` alles als geändert oder untracked, obwohl
die Dateien genau dem Stand auf GitHub entsprechen. Es fehlt nur der Zeiger.

```bash
git fetch
git status -sb                    # "behind N"?
git diff --stat origin/main       # leer = Arbeitskopie == GitHub
git reset origin/main             # OHNE --hard: setzt HEAD + Index, die Dateien bleiben
git status -sb                    # sauber
```

- `git diff` übersieht untracked Dateien. Die erscheinen dort als
  „gelöscht“, obwohl sie da sind. Im Zweifel einzeln vergleichen:
  `git show origin/main:<pfad> | diff -q --strip-trailing-cr - <pfad>`.
- **Ist der Diff nicht leer**, hat jemand gearbeitet und noch nicht gepusht,
  ob hier oder auf dem Platz, von dem der Sync kam. Dann nicht zurücksetzen.
  Entweder dort pushen, `fetch` und nochmal prüfen, oder hier committen, wenn
  die Arbeit von hier stammt.
- **Ist der lokale Stand ein älterer Zwischenstand** derselben Arbeit, hat
  GitHub also alles davon und mehr, dann ist das kein Grund zum Committen.
  So war es am 2026-10-27 in `spaceticker.app` und `relay`. Prüfen lässt sich
  das so: `git diff --ignore-cr-at-eol origin/main`. Sind die `+`-Zeilen nur
  Vorfassungen dessen, was oben steht, ist der lokale Stand veraltet. Dann
  `git stash push -u -m "…" -- . ':!<eigene Testdateien>'` und
  `git pull --ff-only`. Der Stash hebt den Stand auf, bis der Benutzer ihn
  verwirft.
- `reset` ohne `--hard` fasst keine Datei an. Geht etwas schief, steht der
  alte HEAD im Reflog (`git reflog`).
- Wurde die Historie auf GitHub neu angelegt (so bei `relay` am 2026-10-15),
  gibt es keine gemeinsame Basis mehr. Der `reset` ist dann trotzdem richtig.
  Der alte lokale Commit bleibt nur im Reflog.

Alle Repos eines Workspace auf einmal (Git-Bash, Wurzel des Arbeitsplatzes):

```bash
cd /v/www                          # DEV.ojoffice; sonst /c/laragon/www
ws=ide/cursor.files/spaceticker.app.code-workspace
for d in $(grep -o '"path": *"[^"]*"' $ws | sed 's/.*"\.\.\/\.\.\/\(.*\)"/\1/'); do
  [ -d "$d/.git" ] || continue
  git -C $d fetch -q
  echo "== $d $(git -C $d status -sb | head -1)  lokal geändert: $(git -C $d status --porcelain | wc -l)"
  git -C $d log --oneline origin/main..HEAD   # ungepushte Commits
done
# ohne lokale Änderungen:   git -C $d pull --ff-only -q
# nur der Zeiger fehlt:     git -C $d reset -q origin/main
```

## Repo fehlt auf einem Arbeitsplatz

Der Ordner kam per Sync, `.git` nicht:

```bash
git init -b main
git remote add origin https://github.com/systopicode/<name>.git
git fetch
git reset origin/main             # Dateien bleiben, wie sie sind
git branch -u origin/main
git config core.autocrlf input
git status                        # nur Unterschiede, die wirklich da sind
```

Fehlt die `.gitignore` (Sync-Builds vor 2026-10-16 ließen sie aus), holt
`git checkout origin/main -- .gitignore` sie.

## Einstellungen je Repo

Diese Einstellungen liegen in `.git/config`, also pro Arbeitsplatz. Sie
reisen nicht mit dem Sync.

- **`core.autocrlf input`**: Das Repo speichert LF, der Arbeitsordner wird
  nie umgeschrieben. Git for Windows stellt systemweit `true` ein. Dann
  schreibt jeder Checkout CRLF, die gesyncten Dateien wechseln das
  Zeilenende, und `status` meldet bei gemischten Zeilenenden hunderte
  Dateien.
- **Autor:** `ohjay666 <oliver@systopic.net>` bzw. Lars als `sabietzki`.
- **Git-Version ≥ 2.28** (`git init -b`). Das Git von Laragon
  (`C:\laragon\bin\git\bin\git.exe`, 2.47) passt. Die Git-Bash mancher
  Plätze ist älter (2.27), dann das Laragon-Git nehmen.

## Was nie ins Repo gehört

| Was | Warum |
|---|---|
| `.sync`, `.htsync*`, `public/sync.php` | Schlüssel und vertraute Instanzen des Sync |
| `config/STAGING.config.json`, `config/LIVE.config.json`, `*.local.json` | DB-Passwörter, Relay-Schlüssel; kommen per Sync auf die Server |
| `vendor/`, `composer.lock` (Projekt), `var/`, `public/var/` | erzeugt bzw. Daten |
| `public/assets/`, `*.bundle.*` | erzeugte Asset-Kopien |
| `.cursor/` (Pakete) | Doku und Werkzeug, wandert per Sync |

Die Sync-Ignores und die `.gitignore` sind **zwei Listen**. Die
Server-Configs zum Beispiel synct man, committet sie aber nie. Vor jedem
ersten Commit eines Repos die gestagte Dateiliste ansehen.

## Ein Release (Pakete)

Ändern, lokal testen (Junction), committen, Tag `vX.Y.Z`, Push mit Tags.
Packagist zieht sich das automatisch, danach auf dem Server
`composer update systopic/<paket>`. Braucht ein Paket eine neuere Version
eines anderen, hebt es die Anforderung an (z. B. `relay` → `systopic/system`
`^1.2`). Mehr dazu in [composer.md](composer.md) und
[package-publishing.plan.md](../plans/package-publishing.plan.md).

## Stand 2026-10-27 (DEV.ojoffice, `V:\www`)

Alle Repos stehen auf `origin/main`, `core.autocrlf input`. `system`, `db`,
`cms`, `relay` und `spaceticker.app` sind gepullt, `tools` hat kein Repo.
Die Git-Bash hier ist 2.27, für `git init -b` also das Laragon-Git nehmen.

## Stand 2026-10-16 (DEV.ojhome)

Alle Repos stehen auf `origin/main`, `core.autocrlf input`:
`system` v1.2.0, `db` v1.1.0, `cms` v1.1.1, `relay` v1.0.1,
`spaceticker.app` (seit diesem Tag mit eigenem `.git`).
