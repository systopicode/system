# Shared hosting and maintenance — 29 October 2026

## Compatibility review

| Area | Current position | Limit / follow-up |
| --- | --- | --- |
| Normal CMS CRUD, settings and WYSIWYG | PHP + database; no persistent worker needed | Standard PHP/database prerequisites still apply. |
| Live updates / online status | Optional Relay/Centrifugo | No hub means no live status/cross-device refresh. A shared host does not need to operate a hub for ordinary editing. |
| Installation | Browser setup and Composer scaffold exist | PHP dependencies must already be installed/deployed; a fully browser-managed package updater is not implemented. |
| Images | Existing Imagick-based processing | Imagick is a real feature dependency; there is no new GD replacement. PDF processing can additionally depend on host policy/tools. |
| Video | FFmpeg/FFprobe, process functions; project-local binaries supported | Restricted hosts cannot transcode or generate frames. Binary lookup no longer fatals when shell_exec is disabled. Do not pretend those capabilities exist. |
| Backups | Existing ZipArchive-based workflow | PHP ZipArchive, permissions, disk space and request limits apply. Large backup redesign is a separate change. |
| Scheduled cleanup | Request fallback by default; manual, CLI and optional keyed Webcron use the same runner | Request fallback cannot run on a dormant site. Real scheduling needs an external trigger. |

## Implemented cleanup rules

- Login/security log: the fixed system-wide 365-day eligibility threshold, up to 100 entries per batch under a file lock. Preview is read-only; malformed/oversized logs require review. This is built into the system with no project override; it is not a legal minimum. Earlier manual deletion remains possible when the purpose ends.
- Used/expired one-time codes: existing expiry rule, up to 100 rows per batch. Existing on-demand purge remains.
- Session attribution markers: existing 90-day threshold; only hash-named regular files, no symlinks, no actual PHP sessions or login log. A bounded scan records progress.
- Relay remote diagnostic registry: existing 24-hour inactivity and session-expiry rules; at most 100 removals per batch. No live hub required. Invalid registry JSON is not silently replaced.
- Email/PHP/backup logs, active devices, accounts, media and backup archives remain excluded. No 90-day login-log policy has been activated; the operator subsequently chose 365 days.

## Execution and observation

System registers request scheduling after database bootstrap. Ordinary GET requests can run one due task at shutdown, no more frequently than every five minutes. Per-job success intervals default to one hour. Callbacks cooperate with a small execution budget; a slow filesystem or database call itself cannot be interrupted by PHP. Work is batched, with a non-blocking project lock. It neither calls fastcgi_finish_request nor launches a shell/daemon. Requests with a fatal error are skipped.

CLI/manual/Webcron execute one batch per registered task (or one selected task), not an unbounded queue. Repeat when `more` is true. Status is separate per project and instance, persisted atomically under project `var/maintenance`. Task failures retain eligibility for a retry. A manual run does not overwrite the request/CLI/Webcron heartbeat. Preview leaves task data and status unchanged (it may create the lock directory/file).

Only superusers see the cleanup panel and can mutate its settings or manually run tasks. Authorised UI preview and cleanup accept POST/PUT, matching the framework AJAX dispatcher; GET is refused. The systemcheck reports storage, observed execution and observed CLI PHP version; it does not infer that a cron job is installed merely from PHP_BINARY. No OS cron entries are installed or replaced automatically.

### CLI

Use the existing project front controller and an explicit instance, not the web PHP binary inferred from a browser request:

```json
{"maintenance":true,"instance":"YOUR_INSTANCE","preview":true}
```

Save outside the public directory as e.g. `maintenance-preview.json`, then:

```sh
cd /project/public
/path/to/php index.php @/project/maintenance-preview.json
```

The public working directory also supports older project front controllers that still use a relative chdir. The current scaffold uses absolute paths. Remove `preview` for real cleanup. Optional `job` selects one registered ID. Configure the scheduler for every five minutes; each call remains batched. Exit 1 means a busy runner or a failed task. Run as the project account with the same database/filesystem permissions and compatible PHP/extensions. Check the recorded CLI run after setup.

### Webcron

Disabled until a superuser creates a separate key under Maintenance → Cleanup. Only a hash is stored, per environment. A scheduler sends HTTPS POST to the installation's `/_maintenance/` endpoint, with `X-Maintenance-Key` containing that secret. Never put it in a query string. Requests are rate-limited after successful execution; a project lock prevents concurrent mutation. Invalid/missing keys and non-POST requests are refused. Rotation revokes old access; disabling deletes the digest. No CMS user session is used. Endpoint responses suppress optional debug output. A scheduler that supports only unauthenticated GET cannot use this endpoint; request fallback/manual execution remain available.

## Not done silently

No project cron, external scheduler or Webcron key was enabled. The operator subsequently chose 365 days for login/security logs. Technical email, PHP and backup execution logs use a fixed 90-day retention rule. Media deletion and backup archive expiration remain separate. Undated legacy backup records require administrator review. The scheduler is not a GDPR compliance certification. Actual operator retention decisions and backup/recovery rules remain necessary. Large-image/video processing, package updates and very large backups need separate feature-level work if the host lacks their prerequisites.

## Validation

Isolated tests cover non-mutating preview, request throttling, separate manual/automatic evidence, project isolation, concurrent-run exclusion, failure handling, Webcron key rotation/revocation, bounded marker progress, preservation of active diagnostic/session data, and invalid-registry protection. Rendered cleanup controls were checked at 390/1024/1440 px. Existing media policy, media type, editor-reference/navigation and legal-notice checks passed. CLI previews also ran against Stefan Pohl and Spaceticker Dev with no task-data deletion; Stefan Pohl preview additionally passed with exec/shell_exec/proc_open disabled. No production shared-host deployment or external scheduler was available for an end-to-end scheduled-run test.

## Follow-up review

- Request jobs are ordered by oldest attempt; a repeatedly failing job cannot monopolise subsequent request batches. Manual/CLI batches also prioritise tasks deferred by an earlier time budget.
- Results explicitly name tasks deferred by the overall budget. Individual `more` flags mean further data remains in that task. A successful batch does not mean the entire queue is empty.
- Last attempt and last successful batch are displayed independently. Preview never advances either timestamp. Missing CLI is not a core error; unwritable maintenance storage/lock and task failures are reported.
- Nested status corruption and invalid task return values fail closed rather than being reported as success.
- Webcron requires a server-confirmed HTTPS request, POST and a separate key. Raw forwarded headers are not trusted. A TLS-terminating proxy must configure HTTPS/REQUEST_SCHEME for PHP correctly. The isolated HTTP test emulates that server indication; it does not test a production TLS proxy/certificate.
- Newly rotated secrets appear in a selectable, transient field instead of a fading message. The stored digest remains per environment. Configuration instructions are collapsed by default.

### Manual acceptance checklist

Use Dev first; do not deliberately expire an active user's code in production.

1. As superuser, open Maintenance → Cleanup and Systemcheck. Confirm readable desktop/tablet/mobile layout and consistent task/status labels. A regular administrator/editor must not receive cleanup controls or be able to invoke their actions directly.
2. Preview one task and all batches. No deletion or heartbeat should result. Counts describe a batch, not a guaranteed total.
3. Run a task with known expired Dev data; active records remain. For a large backlog, repeat batches and confirm progress/remaining-work messages. Recent login-log entries and backup archives must remain unchanged. Login entries older than 365 days and dated technical email/PHP/backup-log entries older than 90 days are eligible.
4. With no CLI/Webcron configured, ordinary GET requests should advance the request row after the five-minute throttle. One request runs at most one task. Because the run is at shutdown, its status becomes visible on a subsequent view. Successful tasks normally have a one-hour interval.
5. Run manual cleanup and check that CLI/Webcron timestamps do not move. Test CLI preview first, then a real batch; only the real batch should establish CLI execution evidence.
6. On the actual host, configure one scheduler with the correct PHP/instance/working directory or HTTPS POST/header. Confirm automatic timestamps advance. A key rotation must revoke the old scheduler key; disabling Webcron must deny access. Requests without a key, via plain HTTP or GET must not execute cleanup.
7. Test the actual proxy/TLS setup and hosting permissions. An isolated test cannot establish that the deployed PHP process receives the correct HTTPS signal or that the host's cron service actually runs.
8. Normal sign-in, code redemption, page editing/sorting, media upload/replace and backups should behave as before. Simulated scheduler/task failures should appear in status without preventing ordinary editing; use a disposable test fixture for corrupt/unwritable state, not the production registry.

The follow-up automated suite now also starts an isolated PHP HTTP server and verifies actual shutdown execution, GET throttling, no POST/fatal-request execution, and separate request-vs-CLI evidence. Preview smoke tests were repeated successfully for both Dev projects after the fixes. The main remaining acceptance work is the actual host's cron/proxy configuration and normal end-user workflows listed above.

## Media capability basics

The passive `shellBinary` lookup now accepts both historical `_BIN` and `_BINARY` constants/environment names and recognises project-local platform subfolders in both site/project roots. The legacy `cmsFindBinary` helper also consults that resolver for the current root while retaining its existing host-format validation and explicit-root isolation. Existing processing APIs remain compatible; no binary installer runs as part of a check.

Systemcheck separates passive discovery from an explicitly requested version test. The latter is superuser/POST-only, starts a fixed `-version` argument array without a shell, caps output, times out stalled processes, and verifies the expected tool banner. Disabled process functions yield an untested/restricted status rather than an exception. Results describe startup only; codec support, PDF policies and large conversions still require real hosting tests. Check results are not persisted as a permanent claim about host capabilities.

Read-only check calculations now live in `checks.inc.php`, separately from HTTP actions, so an AJAX test re-render recalculates the display in the correct variable scope. Missing optional tools are feature limitations, not a claim that all CMS editing is broken. Imagick and ZipArchive requirements are explained beside the controls. No new external service, stored account data or privacy purpose is introduced by these version checks; no additional Privacy entry is needed.

Validated with isolated executable fixtures: nested-folder discovery, legacy aliases, timeout termination, wrong-program rejection, missing tools and disabled process functions. Next acceptance check on Stefan's actual shared host: run “Test media tools” once, confirm intended local paths, then try one short MP4 and one normal image/backup. Larger compatibility work remains deferred until a concrete host limitation appears.

The preview was additionally verified through the real Dev GUI after correcting its AJAX PUT handling. It now has a persistent result block instead of relying on transient messages.


## Abschlussprüfung vom 29. Oktober 2026

Getestet in isolierten Daten-/Browserfixtures: 90-/365-Tage-Grenzen, vollständige mehrzeilige Logeinträge, ungültige/relative Datumsangaben, Vorschau ohne Löschung, begrenzte Durchläufe, Berechtigungen, Webcron-Schlüsselwechsel und Request-Fallback. Die gemeinsame Video-Komponente lädt vor Klick keine Player; öffentliche Freigaben schalten Editor-Ansichten nicht frei. HTML-Mailvorschauen führen keine Skripte aus und laden keine externen Tracking-Bilder.

Noch auf Stefan-Pohl-Shared-Hosting prüfen:

1. Systemcheck öffnen und die optionalen Medienwerkzeuge testen; ein kleines Bild, kurzes MP4 und einen Backup-Durchlauf prüfen.
2. Cleanup-Vorschau ausführen, dann nur mit entbehrlichen Testdaten einen Durchlauf. Bei unbekannten Altdaten stoppt die Regel; kein pauschales Löschen echter Altlogs zum Testen.
3. Nach normalen Besuchen Fortschritt prüfen (fünf Minuten Drosselung, abgeschlossene Regeln normalerweise stündlich). Ohne Besuche gibt es keinen Hintergrundprozess. Einen externen Scheduler nur testen, wenn tatsächlich eingerichtet; Key allein reicht nicht.
4. Schreibrechte, parallele Requests und echte PHP-Logformate prüfen. Dateisperren sind advisory; der native PHP-Fehlerlogger muss ihre Beachtung nicht garantieren. Die zusätzliche Größenprüfung erkennt zwischenzeitliches Anhängen, ist aber kein Beweis gegen jedes Parallelitätsfenster. Sehr aktive Fehlerlogs benötigen eine vom Hoster abgestimmte Logrotation; nicht als vollständig verifiziert ausgeben.
5. SMTP einmal mit einem Testempfänger prüfen; Code-Mail-Inhalt bleibt verborgen. Keine Produktivmails durch automatisierte Tests versenden.
6. Betreiber-Ergänzungslinks prüfen. Backup-Archive und aufbewahrungspflichtige Geschäftskorrespondenz brauchen einen eigenen Aufbewahrungsprozess.
