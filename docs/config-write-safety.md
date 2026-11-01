# CMS configuration writes (1 November 2026)

The CMS writes project settings (`global.settings.json`, `media.settings.json` and
legacy PHP settings), environment files, site-matching rules, theme CSS and theme
assets. Setup and backup restoration can also publish configuration files.
Language SVG overrides, Centrifugo origins/ownership and the Webcron key have
separate validated writers.

## Publication and concurrent edits

`Config/FileWriter` provides a per-path advisory lock and same-directory atomic
replacement. Project settings, environment changes, site rules and theme colours
lock the complete read/modify/write operation, so edits to different fields do
not silently overwrite each other. Existing file permissions are retained; new
environment/setup/restore files and the Webcron digest use 0600. Symlink targets
are refused. A failed publication removes its temporary file. Config contents and
credentials must never appear in diagnostic messages.

Whole-file CSS edits and explicit backup restores intentionally replace the
selected file; they do not merge divergent edits. Site changes involving several
files are individually atomic, not a transaction spanning all files. External
editors must cooperate with the lock to obtain the same concurrency guarantees.

Malformed JSON is not replaced by a field update. Legacy PHP changes are safely
quoted and parsed before publication. Existing field/key, legal URL, image sizing
and SVG validations remain in place. Environment saves additionally reject
invalid booleans, ports, TLS modes and test recipients. Environment text fields
require an intentional input event before blur can save; this reduces accidental
password-manager/autofill writes. It is not a security boundary against browser
extensions or deliberately entered incorrect connection details.

## Verification

Run from the System package:

- `php tests/config-writes.php`: 16 simultaneous JSON edits, four simultaneous
  theme-colour edits, malformed JSON, legacy PHP quoting, site rename preservation,
  file permissions, symlink rejection and failed-publication cleanup. Temporary
  fixtures only.
- `php tests/environment-settings.php`: environment field validation and secret
  preservation, plus shared Centrifugo-origin ownership rules.
- `php tests/language-flags.php`, `php tests/backup-preflight.php`,
  `php tests/maintenance-actions.php`, `php tests/maintenance.php`:
  flag validation, read-only archive preflight, cleanup authorization and Webcron.
- `tests/environment-autofill-ui.mjs` (Playwright, `FLUXO_PLAYWRIGHT_MODULE`):
  synthetic autofill is ignored; intentional DB and mail edits dispatch a save.

The dev entry point was loaded read-only in Chrome after the change: auth screen,
loader ready, no reported page errors or failed requests. No real configuration
was changed in these tests, no backup restored, no mail sent. Authenticated
end-to-end saves on desktop and a real iPad remain to be verified. A PHP/browser
fixture test is not evidence of real Safari autofill behaviour.
