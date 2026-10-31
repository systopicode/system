
# Account suspension and administration additions

- Account suspension uses `user_is_blocked`, separate from activation. The existing ORM
  provisioning path adds the column when first needed. No rights structure is redesigned.
  Only superusers may block/unblock another account; self-suspension is rejected.
- Suspension rotates the existing user session key, revokes trusted devices and outstanding
  login codes, and clears activation/reset links. Session checks reject old generations even
  after unblocking. Roles, content and passkeys are retained; a fresh sign-in is required.
  With Relay, revoked devices receive logout; otherwise enforcement occurs on the next request.
- Environment → Mail has a manually requested SMTP probe for the active environment only.
  It uses effective configuration (including local overrides), the shared delivery policy and
  the configured test recipient. SMTP acceptance is not confirmation of inbox delivery.
- Restore preflight reads archive parts, task metadata and member paths, checks required groups,
  format version and working-directory prerequisites, and warns about space. It does not execute
  SQL or perform a trial restore. A successful restore must still be tested separately.
- Project setting-map key renames request confirmation and explain that references are not migrated automatically.
  Media-group pickers show existing usage and that restrictions do not delete existing files.
- Login, email, PHP and backup logs share client-side period/result/user/search filters.
  Logs without user attribution disable that filter. Known actors link to user administration.
- Theme shows base RGB contrast plus effective text/surface contrast in the current appearance.
  The normal-text threshold is 4.5:1. Custom CSS and individual components still need visual review.

Checks: account-blocking.php, backup-preflight.php, contrast.php, mail-test-delivery.php,
log-filters-ui.mjs, theme-contrast-ui.mjs. All fixtures are isolated; they do not send real mail,
block real accounts or restore project data.

Manual review: suspend a test account with two open sessions, test each login method, unblock
and confirm old sessions stay invalid; send a deliberate test email; inspect valid/incomplete
backup preflight; verify key-rename confirmation and group usage; combine log filters; inspect
contrast in light/dark appearance. Restore only into a separate test installation.

### Follow-up GUI checks (30 October 2026)

Account blocking lives under Access. A blocked account does not offer access grants, invitations or sign-in codes; attempts to invoke those actions directly are rejected too. Unblocking uses the standard user-unlock icon.

Colour contrast is a collapsible section of Colours. Settings/Tools widget titles use text consistently; dashboard widgets retain their icons. Restore preflight still runs on display and before execution, but the interface only shows actionable warnings/errors rather than a routine success card.

The mail section shows the effective SMTP destination and test recipient, including local overrides. SMTP acceptance is handover, not proof of inbox delivery. When accepted messages are absent, inspect the mail provider's queue/bounce/spam results. Do not bypass DEV recipient routing to diagnose that issue.

### Feedback and blocked-account follow-up

Only action controls (explicit feedback, submit, or data-action) receive save outlines. Navigation tabs never do. Current-request failure is carried explicitly in the shared AJAX response, so a previous visible error cannot make a later successful request look failed. Network/client failures use the same message styling as server validation. Messages retire after twenty seconds without hover/focus, using their measured height for a smooth collapse.

Blocked accounts keep read-only device/passkey lists, including revoked device records where available. The block/unblock card is last under Access. Normal accounts continue to list non-revoked devices by default; these presentation changes do not restore credentials or sessions.

## GUI review, 30 October 2026

- Messages have two placements: contextual inside an item/widget/dialog and one floating, content-aligned page host. Field saves retain the short footer status. Actions say Working/Done/Action failed rather than claiming to save a field. Success outlines expire after 2.5 seconds; failure outlines after 6 seconds. The detailed error message remains available for its normal reading interval.
- The first passkey requires an active owner session on that user's device, but no password or ten-minute grant. This intentionally supports QR-only accounts. Additional keys still require an existing credential's proof. Removing an own key after the fresh grant expires still requires proof. Origin, RP, CSRF, user verification, single-use challenge, account and project checks remain enforced.
- Passkey attempt blocking is an application rule shared with sign-in failures: ten failures within fifteen minutes block the IP for fifteen minutes. Cancellation and preparing a request do not increment the failure counter. Failed cryptographic verification does. The response shows the remaining minutes.
- SMTP errors expose a bounded, escaped server reply and redact the configured login/password. SMTP acceptance is not proof of delivery to the inbox. All subjects are formatted at final delivery: optional `Test mail // `, subject, configured title separator, installation URL. Formatting is idempotent.
- Tools use `/tools/website/{links,misses,paths}/`, `/tools/cleanup/{trash,unused,duplicates,automatic}/` and `/tools/media/`. Old maintenance bookmarks redirect to the canonical pages. Internal links and panel JavaScript routes use the canonical paths.
- Media-tool probing updates the separate `media` view; Recheck refreshes the whole check. The downloadable checker also searches project `var/bin/`, `bin/` and platform subfolders and labels optional missing capabilities as warnings.
- Screen portraits retain their stable seed and theme colours but omit Bottts' decorative texture. These are DiceBear SVGs. Screen pairing uses its own success text and the account portrait.
