# CLI Testing API — Compact Reference

The system supports invoking the full request lifecycle (auth, panel build,
template rendering, DOM-layer slicing) from the command line. This is the
primary tool used by Cursor agents to reproduce browser behaviour without
spinning up a webserver — request a panel/page, capture the rendered HTML
or JSON, inspect server logs, iterate.

---

## 0. Mandatory: Autologon (`"user"`)

**Never test protected panels without `"user": <id>` in the CLI JSON.**

Without an authenticated user, access-controlled modules (admin, cms, …)
are not built / not selected — you get a login redirect or an empty tree and
**false-negative “it works” / “nothing loads” results**.

| Context | Autologon |
|---------|-----------|
| Browser DEV (`index.php` UA switch) | Yes — sets `AUTOLOGON_ID` for Firefox/Chrome |
| **CLI** (`php index.php @…`) | **No** — the same block is skipped when `php_sapi_name() === 'cli'` |

CLI must therefore always set:

```jsonc
{ "path": "/…/admin/migrations/", "user": 35, "ajax": true, "output": "json" }
```

`"user"` defines `AUTOLOGON_ID` before the server switch runs. Project IDs
differ (e.g. PWK DEV: `35` / `36`; spaceticker often `2`). Prefer a
superuser when testing admin modules.

**Do not** use bare `curl` / `Invoke-WebRequest` without a session cookie
as a substitute for CLI testing of protected modules — same trap.

---

## 1. Entry point

`public/index.php` is both the HTTP entry point and the CLI entry point.
It calls `Systopic\System\Agent\AgentCli::boot(__FILE__)`: on the CLI that
parses the request payload from `argv`, defines the CLI constants
(`OUTPUT_FORMAT`, `AUTOLOGON_ID`, `CONFIG_INSTANCE`), synthesises a `$_SERVER`
environment via `Systopic\System\Http::getCliEnvironment()`, and the normal
`loader.php` → dispatch flow runs. All payload keys are listed in the
docblock of `src/Agent/AgentCli.php`.

```
php public/index.php @<request-file.json>     # request from JSON file
php public/index.php - < request.json          # request from STDIN
php public/index.php '{"path":"/cms/"}'        # request inline (avoid: shells mangle JSON)
```

Always prefer `@file` or `-` (STDIN). Inline JSON breaks on Windows
`cmd.exe` / PowerShell because the argv splitter eats braces and quotes.

---

## 2. Storage convention — `public/var/cursor/`

All ephemeral CLI artefacts (request payloads, captured stdout/stderr,
rendered HTML, cookies, intermediate dumps) live in the project's
`public/var/cursor/` directory:

```
<projectRoot>/public/var/cursor/
├── cli-test-<name>.json          – request payload
├── cli-test-<name>-result.txt    – captured stdout (any output mode)
├── cli-test-<name>-result.html   – rendered HTML response
├── cli-test-<name>-result.json   – JSON response
├── cli-stderr.txt                – last captured stderr
├── cli-cookies.txt               – session cookies (curl --cookie-jar style)
├── cli-step1.html, cli-step2.html – multi-step flows
└── …
```

Rationale:

- Under `public/var/` → same convention as all other runtime-generated data
  (`var/backups/`, `var/original/`, `var/ical/`, `var/cache/`).
- The `var/` folder lives inside `public/` for this project.
- Single, predictable folder so cleanup is one `Remove-Item public\var\cursor\cli-* -Force`.

`public/index.php` resolves `@<file>` lookups in this order:

1. literal path (relative to current cwd or absolute)
2. `<publicDir>/var/cursor/<file>` ← **always works regardless of cwd**
3. `<publicDir>/<file>` (legacy fallback)

So `@cli-test-cms.json` Just Works from anywhere as long as the file lives
in `public/var/cursor/`.

---

## 3. Request payload (JSON shape)

```jsonc
{
  // Mandatory: the request URI to dispatch (becomes $_SERVER['REQUEST_URI']).
  // Use the full URL path including the public/-stripping prefix the dev
  // server would see, e.g. "/projects/spaceticker.app/public/cms/pages/".
  "path": "/cms/pages/",

  // Optional: HTTP method. Default GET.
  "method": "POST",

  // Optional: response shape. Default 'json'.
  //   "html"    → full page render via app.php / site.php
  //   "json"    → ajax-style JSON (DOM-layer slices)
  "output": "json",

  // Optional: pretend the request is XHR (sets HTTP_X_REQUESTED_WITH).
  // Triggers app.php's diff-render branch — needed to test panel-diff
  // rendering. Default false. Equivalent to "server":{"HTTP_X_REQUESTED_WITH":"XMLHttpRequest"}.
  "ajax": true,

  // REQUIRED for access-controlled panels: auto-logon as this user::id
  // (defines AUTOLOGON_ID). Browser DEV autologon does NOT apply in CLI.
  "user": 35,

  // Query / body params. A "path" with ?id=… does NOT populate $_GET in CLI,
  // so panels reading http::get('id') see nothing and $this->object stays null
  // (symptom: "Call to a member function … on null" in a view template).
  "get":  { "id": 30 },
  "post": { "name": "value" },
  "put":  { "name": "value" },     // also switches method to PUT

  // Optional: PHP session control.
  "sessionId":       "abc123…",          // resume an existing session
  "sessionSavePath": "public/var/cursor/sess",   // override session.save_path
  "session":         { "foo": "bar" },    // pre-populate $_SESSION (merged in loader)

  // Optional: extra $_SERVER overrides (merged last → wins).
  "server": {
    "HTTP_X_REQUESTED_WITH": "XMLHttpRequest",
    "QUERY_STRING":          "id=42"
  }
}
```

`getCliEnvironment()` (in `src/Http.php`) seeds sensible defaults:
`SERVER_NAME`/`HTTP_HOST` = local socket IP, `REMOTE_ADDR` = same,
`SCRIPT_NAME`/`SCRIPT_FILENAME` = the index.php path,
`HTTP_USER_AGENT` = `'Cursor-AI/1.0'`. Override anything via `server`.

---

## 4. Naming convention

| Suffix                             | Purpose                                            |
|------------------------------------|----------------------------------------------------|
| `cli-test-<name>.json`             | request payload                                    |
| `cli-test-<name>-result.txt`       | captured stdout (raw)                              |
| `cli-test-<name>-result.html`      | parsed/pretty HTML response                        |
| `cli-test-<name>-result.json`      | parsed JSON response                               |
| `cli-stdout.txt`, `cli-stderr.txt` | last raw streams (debug, frequently overwritten)   |
| `cli-step{N}.html`                 | intermediate stages of a multi-step flow           |
| `cli-cookies.txt`, `cli-raw.txt`   | adhoc debug captures                               |

Rule of thumb: every persistent test request gets its own `<name>` so
artefacts don't clobber each other (`cms`, `pages`, `events`, `contacts`,
`auth-login`, …).

---

## 5. Recipes

### Render a panel as JSON (ajax-style slices)

```jsonc
// public/var/cursor/cli-test-pages.json
{ "path": "/projects/spaceticker.app/public/cms/pages/", "user": 2, "output": "json" }
```

```powershell
cd v:\www\projects\spaceticker.app\public
php index.php @cli-test-pages.json `
  > var\cursor\cli-test-pages-result.txt `
  2> var\cursor\cli-stderr.txt
```

### Render a panel as full HTML

Set `"output": "html"` and pipe to `cli-test-<name>-result.html`. Open the
file in the browser (or grep for the panel's marker comments) to verify
the render.

### Capture stderr separately (always do this)

Without `2> file`, stderr lands in stdout and corrupts JSON parsing. The
canonical pattern:

```powershell
php index.php @cli-test-cms.json `
  > var\cursor\cli-test-cms-result.txt `
  2> var\cursor\cli-stderr.txt
```

Then check `cli-stderr.txt` first — any non-empty content here means a PHP
warning/notice/error occurred during the request.

### PowerShell writes UTF-16 — wrap redirects in `cmd`

`>` in PowerShell 5 encodes the file as UTF-16LE. The JSON is then unreadable
for every parser (`json_decode` returns null, the dump starts with `��{`).
Redirect through `cmd` when the result is meant to be parsed:

```powershell
cmd /c "php index.php @cli-test-cms.json > var\cursor\cli-test-cms-result.json 2> var\cursor\cli-stderr.txt"
```

### Multi-step flow with shared session

```jsonc
// step 1: log in
// public/var/cursor/cli-test-login.json
{ "path": "/login", "method": "POST", "sessionId": "agent-session-1",
  "server": { "QUERY_STRING": "user=oj&pw=…" } }

// step 2: visit a protected page on the same session
// public/var/cursor/cli-test-after-login.json
{ "path": "/cms/pages/", "sessionId": "agent-session-1", "output": "html" }
```

Both runs share `$_SESSION` because they pin the same `sessionId`.

### Pipe a request through STDIN (no file)

```powershell
'{"path":"/cms/","user":2}' | php index.php -
```

Use this for one-shot probes that aren't worth saving as a `.json` file.

---

## 6. Output modes

`OUTPUT_FORMAT` is set from `$cliInput['output']` (default `'json'`).

- **`json`** — `app.php` runs the closure-based diff/layer path,
  emitting JSON with DOM-layer slices. Best for inspecting which views
  re-render (`new` / `update`) and the data that powers them.
  Combine with `"ajax": true` to trigger diff-render mode (alias
  detection against the previous session's reference tree).
- **`html`** — full page render via `app.php` (when a panel matches) or
  `site.php` (for CMS pages). Returns a complete `<html>` document.
- Other modes (`raw`, `text`, …) follow whatever the matched dispatcher
  understands — they're route-specific.

---

## 7. Project-specific defaults (spaceticker.app)

The `SERVER_NAME` switch in `public/index.php` controls which DB constants and
`STAGE` are set. For the local dev machine use:

```jsonc
"server": { "SERVER_NAME": "192.168.10.157" }
```

This hits the `192.168.10.7 / 192.168.10.157 / 192.168.22.7 / 192.168.22.50`
fall-through case → `STAGE=DEV`, `DB_USER=systop`, `DB_PASS=systop`,
`AUTOLOGON_ID=2`.

The DB connection from PHP works fine with these credentials. Note that any
action dispatching a **shell subprocess** (e.g. `passthru('mysqldump ...')`)
runs independently of PHP's PDO connection — if the OS user or PATH differs,
the subprocess may fail even when PHP queries succeed. On Windows, always
use double quotes (not single quotes) around shell arguments.

**`"ajax": true` is required for actions that call `http::refresh()`.**
Without it, `http::refresh()` emits a `Refresh:` HTTP header instead of a
`client::addCommand('refresh')` entry, and the next step never appears in
`app.commands`. Always set `"ajax": true` when testing multi-step flows.

---

## 8. Things that bite (general)

- **Working directory matters.** `index.php` does `chdir('../')` early to
  reach the project root. If you launch from somewhere other than
  `public/` (or the project root, with `php public/index.php`), CWD math
  in `loader.php` (sys/path detection) may diverge. Prefer `cd public`
  before invoking, or pass an absolute `php` argv path.
- **Always pin output mode.** Without `"output"`, OUTPUT_FORMAT defaults
  to `'json'` in CLI — `html` panels then render as JSON-encoded slices,
  which is rarely what you want.
- **Stderr ≠ stdout.** Always redirect stderr separately. PHP warnings
  emit to stderr by default and silently corrupt stdout JSON otherwise.
- **`AUTOLOGON_ID` / `"user"` is mandatory for protected modules.** Setting
  `"user"` in the payload defines `AUTOLOGON_ID`. Browser-only DEV
  autologon in `index.php` is explicitly skipped for CLI — omitting
  `"user"` means you are testing as anonymous. See §0.
- **Session merge timing.** `session` payload data is stashed in
  `$GLOBALS['__cli_session_merge']` and merged into `$_SESSION` later by
  the loader once the session is started. Don't expect the keys to be
  visible before that.
- **`@file` resolution order.** Literal cwd-path → `public/var/cursor/` →
  `public/`. If your file is found in an earlier slot than expected, you
  may be picking up a stale copy. Delete or rename to disambiguate.

---

## 9. File / path map

```
<projectRoot>/
├── public/
│   ├── index.php                   ← CLI entry; @file resolver here
│   └── var/cursor/                 ← all CLI test artefacts (this folder)
│       ├── cli-test-<name>.json
│       ├── cli-test-<name>-result.{txt,html,json}
│       ├── cli-stderr.txt
│       └── …
└── vendor/systopic/system/
    ├── src/Http.php                ← getCliEnvironment(), $_SERVER seeding
    └── .cursor/docs/cli-testing.md ← this document
```

---

## 10. Cleanup

Wipe all CLI artefacts:

```powershell
Remove-Item v:\www\projects\spaceticker.app\public\var\cursor\cli-* -Force
```

The directory itself stays so the @file resolver always finds it.

---

## Instanz und Site im CLI-Request (config/)

Seit `config/` den `SERVER_NAME`-Switch ersetzt (siehe `config.md`):

- `"instance": "STAGING"` pinnt die Instanz (Konstanten, DB). Ohne Pin wird
  sie wie im Web erkannt — `SERVER_NAME` ist im CLI die Socket-IP,
  `rootDir` der Projektordner.
- Eine zweite Site wird über ihren Pfad-Root angesprochen: `SCRIPT_NAME` im
  `server`-Block setzt `http::$root`, der Host-Eintrag der Site wählt sie.

```jsonc
{ "path": "/projects/beautiful-technology.de/public/", "output": "json",
  "server": { "SCRIPT_NAME": "/projects/beautiful-technology.de/public/index.php", "HTTP_HOST": "192.168.22.50", "SERVER_NAME": "192.168.22.50" } }
```

Ein 301 (Pfad gehört einer anderen Site) ist im CLI nicht als Header
sichtbar; das Debug-JSON enthält `UrlResolver: 301 to owning site` mit dem
`target`. Details: `multi-site.md`.
