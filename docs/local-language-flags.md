# Local language flags

Eight bundled flags: DE, GB, US, JP, IT, ES, FR, SE. Only these SVGs are shipped,
not the complete flag-icons collection or CSS/JS. Provenance and hashes are in
local-language-flags.json; the MIT notice is included in the central License view.

Language flag codes resolve locally. Aliases en -> gb, ja -> jp and sv -> se
preserve existing language-style codes. A custom file under the original code
wins before an alias. Unknown codes have a neutral placeholder; no remote fallback.

Settings / Global Settings / Languages shows previews, an explicit external
SVG-download link and SVG upload per flag code. Project files live in
public/overrides/img/flags/ relative to the project public root and are served
through the existing panel-asset override mechanism. Uploads affect every
language using that same flag code. Reset deletes only the project override.
Language definitions, their keys, activation and country selection are preserved.

Only authenticated administrators can upload/reset, using POST and a session
form token. Static SVG uploads are bounded to 128 KiB and validated by a DOM
allowlist: no scripts, event handlers, embedded images, external references,
DTD/entity declarations or processing instructions. Rejected uploads preserve
the old flag. Files are replaced atomically; URLs use normal asset versioning.

## Verification (30 October 2026)

- PASS: eight shipped SVGs pass the static-vector validator; aliases and custom
  precedence resolve locally, unknown codes never request a remote fallback.
- PASS: real multipart upload/replace/reset through the settings action file,
  using disposable project fixtures. Existing flags survive rejected uploads;
  source paths, scripts, external references, oversized and malformed files are
  rejected. POST/admin/session-token guards exercised with negative requests.
- PASS: country-code changes use the existing settings writer and preserve
  language keys, labels, activation and unrelated configuration.
- PASS: rendered ProjectForm language section inspected at 1440/768/390 pixels
  and 768-pixel touch; upload targets are at least 44 pixels on touch. No external
  requests or browser errors. Test-server log reviewed: no PHP errors/warnings.
- PASS: complete local license notices and provenance hashes, privacy tests,
  PHP syntax and git diff whitespace checks.
- BLOCKED: authenticated inspection in the actual local CMS. Its IAB control
  backend returns browser.user is unavailable. The fixtures exercise the actual
  renderer, SVG storage and action file, with auth/asset-environment stubs;
  they are not claimed as a full installed-app acceptance test.

No actual project language configuration or flag override was changed by tests.
No commit or push was made for this change.

The language table shows Language, Flag, Name and On. The old separate code
column is hidden; existing country-code mappings are retained internally.
New rows need only their language identifier; an absent/blank legacy flag code
falls back to that identifier. Uploaded SVGs choose the desired flag visually.

Language row actions are icon-only in the rightmost column. The preview column
is fixed and narrow; Name receives the remaining width. The SVG download link
uses the normal grey button style, with the external marker on the right.
Layout and upload/reset were checked with global table CSS plus settings
project.css at desktop, tablet and narrow widths; all three simultaneous
actions fit with 44-pixel touch targets on the tablet fixture.

Flag is the first visible column before Language. Its preview button opens the
same uniquely identified SVG file input as the right-hand upload control.
The file input itself carries the upload tooltip, as does the preview.
