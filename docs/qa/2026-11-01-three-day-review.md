# Three-day pre-merge review — 1 November 2026

Scope: all current changes in the separate system and cms repositories since their
30/31 October commits. Existing user changes are included; no project environment
was rewritten and no external mail was sent during this review.

| Requirement | Status | Verification |
| --- | --- | --- |
| PHP / JavaScript structure | PASS | Reviewed new service namespaces and panel classes against the custom panel loader and GUI conventions. Existing editor globals remain the documented shared architecture. 185 changed/new PHP/JS files passed syntax checks. 75 executable PHP suites passed under PHP 8.4 with Imagick. |
| CSS / theming | PASS, bounded | Reviewed shared tokens and component placement; no new layout library or parallel state framework introduced. Technical QR contrast and shadow colours intentionally remain fixed. Browser suites cover dashboard layout, status return, field/action feedback, media policies/ratios, editor/crop and mail themes. This is not a claim that every historical selector was exhaustively verified. |
| Auth metadata / favicons | PASS | Actual Spaceticker code-auth page loaded at 1440, 834 and 390px with no page errors or failed HTTP responses. Project favicon discovery accepts singular/plural folders and 96px PNG; missing optional files no longer restore framework icons. Theme colour emitted. Narrow code fields corrected after screenshot inspection. Branding unit tests cover frontend versus system return destinations. |
| Documentation / releases | PASS | Button-variant and Auth metadata documentation updated. Duplicate 0.5.7 release keys merged so earlier notes are retained. Review fixes added without a version bump. |
| Full device / integration gate | BLOCKED | Physical iPad Safari, native passkey/QR camera workflows and real inbox delivery were not exercised. Three database suites require an isolated MySQL test DSN; none was configured, and project data was not used as a substitute. |

## Browser verification limits

The current targeted browser suites pass for responsive dashboard, feedback,
mobile-to-desktop status restoration, environment autofill, action loading,
current shared UI, mail logo light/dark, unified editor, media drop policy,
media ratio settings, on-page crop, GUI follow-ups, touch editor, camera loader
and media toolbar. The older `system/tests/mobile-shell-ui.mjs` fixture stops at
its obsolete mobile content-visibility expectation; it does not construct the
current mobile sheet contract. This fixture still needs migration and is not
reported as a passing device test. The final UI fixture was updated to load its
actual page-relations CSS/markup and test header/footer right padding rather than
obsolete symmetric mobile padding.

## Before merging / deployment

1. On a real iPad: keyboard open/close in global search, body scroll lock, crop
   pinch/handles, toolbar/dropdowns and dashboard long press with auto-scroll.
2. Real browser Auth: password, invitation/reset, passkey success/cancel and QR
   expiry/foreign-instance code; verify destination, branding and two-second skip.
3. Run `cms/tests/quality.php`, `system/tests/passkeys.php` and
   `system/tests/passkeys-upgrade.php` against disposable databases only.
4. Refresh the legacy mobile-shell fixture, then rerun it in Chrome and Safari.
5. On staging: verify SMTP delivery and logo switching in actual mail clients;
   SPF/DKIM/DMARC and inbox placement remain installation checks.
6. Exercise concurrent configuration saves in staging, then reload: invalid input
   must preserve the previous valid file. Atomic per-file writes and locking are
   tested; changes spanning multiple files are not a database-style transaction.

No push or deployment is included. Historical PHP database-connection errors in
server logs predate this review; successful Auth requests do not prove every
installation/database environment is healthy.
