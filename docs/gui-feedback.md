# Common editing feedback

The existing footer contains a compact save status with icons and message-system colours. At mobile breakpoints the same status element moves out of the hidden footer into a fixed indicator above the safe area. Editing fields receive yellow unsaved/saving, green saved and red failed outlines; saved outlines retire after a short delay. No new notification library or persistent draft store is used.

- Input changes are tracked in memory for CMS editing fields and forms. Searches and authentication forms are excluded.
- Field writes show Saving, Saved or Not saved. Explicit operations show Working, Done or Action failed; they do not claim that a field was saved. Explicit operations retain their useful confirmation messages; routine colour, login-method and media-rule autosaves no longer emit Saved banners.
- A response only confirms the values sent with that request. Editing another field, or typing again while saving, keeps those changes pending. Escape on a normal input restores its last confirmed value. Rich text and header renaming retain their own undo/Escape handlers.
- Navigation/closing waits for outstanding writes. Unsaved edits require a discard confirmation; closing or reloading the tab uses the browser's native unload warning. Failed requests are not automatically replayed: commands can have side effects. This is protection within the open tab, not crash recovery.
- Slow reads show Loading in the footer. The existing loader reports preparation and missing scripts separately, with a Reload action on failure. Readiness follows the DOM and required scripts, not images finishing loading.
- Existing lightbox close routes/history rules are retained. Shared handling adds dialog semantics, focus containment, Tab cycling, Escape through the existing close action, and returning focus to the opener. Transient help sheets retain their history-free behavior.
- Empty tables distinguish empty lists from filtered lists without guessing permission problems. Existing headers and Add actions remain available. Search and backup hints explain the next action.

The sign-in success view lasts two seconds. Continue now in the existing footer follows the same saved destination immediately. The success pane fills available height so the footer stays at the bottom.

## Manual review

1. Login with password, passkey and remote pairing; verify name/photo, two seconds, footer placement and Continue now returning to the correct page.
2. Settings: change two fields quickly, edit again during a save, inspect the footer. Reload after Saved and confirm persistence.
3. Disconnect the network while editing: edits must not be labelled Saved. Reconnect, save explicitly/by the field's normal autosave action, and confirm persistence.
4. Leave an edited form, close its lightbox, or reload. Cancel must preserve input. Escape on a plain input must restore the last confirmed value. Verify the existing editor menus/undo remain usable.
5. Hard reload with throttled network: preparation disappears when tools are ready. A blocked required script shows a useful failure message and Reload.
6. Open nested/local/route-based lightboxes by keyboard. Check focus, Tab/Shift-Tab, Escape, return focus, Back/Forward, and history-free link editing/help sheets.
7. Review empty roles/backups and filters with no matches. Add actions and permissions must remain unchanged.

Automated checks: `node tests/loader-ready.mjs`; `FLUXO_PLAYWRIGHT_MODULE=… node tests/gui-feedback-ui.mjs`; `… node tests/auth-success-footer-ui.mjs`; `php tests/auth-continue.php`; `php tests/sign-in-feedback.php`.

Field feedback resolves an unambiguous replacement after a response rebuilds a form, so the success/error contour is applied to the visible field. Success retires after 2.5 seconds. Feedback uses one contour and the existing CSS spinner. Each lightbox has a local status footer; the page status is visually hidden while a dialog is open. Nested dialogs show their own context rather than lifting the global footer above every backdrop. Dirty-state tracking is retained when a footer is hidden. The mobile Safari bottom colour surface is removed when returning to desktop, so it cannot cover the restored global footer.

Pages text editing now commits on blur: rendered plain text fields, WYSIWYG, meta description, rewrite paths, sidebar text/group values, publishing dates, media labels and SVG source. Search inputs remain live; toggles, selectors and explicit structural actions remain immediate. Explicit link insertion saves before closing its dialog. Completed toolbar edits made while focus is already outside the WYSIWYG field also commit, so they cannot be left unsaved without a future blur.

AJAX navigation now queues the last requested destination while blur-autosaves complete, rather than consuming the click. Render references and XHR setup are generated only when navigation actually resumes. A failed write cancels automatic continuation and retains the form. Native unload protection remains active.

Enter in page-title/tree pencil edits dispatches the existing blur-save with a value snapshot before edit mode ends. Native follow-up blur is suppressed to avoid duplicate writes. Composition input is not committed early; Escape cancels. Regression: `tests/pencil-enter-ui.mjs` exercises the real event/payload collector against an isolated endpoint and reloads the saved result.

## Upload results and explicit site actions

The CMS multi-upload uses the shared lightbox and jobs status line. Each batch retains per-file success, failure, rejection and pending state in the current document. Closing the overview keeps uploads running; Upload results and the running job's Details button reopen it. An explicit retry skips successful and rejected entries and restarts only failed/pending files. A failure leaves later files pending. Results are cleared by reload; no persistent draft/file store is added.

Queued uploads carry a stable random identity. Session-scoped completion receipts bind it to the file and destination. Repeated chunks write at their fixed offsets, and repeated completed requests return success without creating another medium. This does not add cross-session/server-crash exactly-once semantics.

Site renaming is an explicit reviewed action. Typing a new domain no longer triggers a rename. Meta defaults explain each value's origin and link to the corresponding Site, Page Settings or Theme editor. Accessibility findings and verification scope are documented in `accessibility-review-2026-10-30.md`.

## Save errors and display dates (1 November 2026)

Failed editable fields retain their red contour until edited, retried or discarded. The
short-lived outline of an explicit action is separate. A failed response that rebuilds a
field must preserve the latest local value, not replace it with an older server value.
`tests/gui-feedback-ui.mjs` covers both cases, edits in flight and guarded navigation.

Display dates use `LocalTime::DATE` (`d.m.Y`), `DATETIME` (`d.m.Y H:i`) and
`DATETIME_SECONDS` (`d.m.Y H:i:s`). Use these constants for new CMS display code.
`LocalTime::format()` converts to the existing user display zone; formatting a
`DateTimeInterface` directly preserves its zone. Publishing, backup archive dates and
raw dated mail/backup log entries keep their existing server/source-time semantics.
Do not change SQL timestamps, serialized metadata, file names, filter attributes or
native date/datetime input values. Native date controls follow the browser locale.

Checks: `php tests/display-dates.php` on PHP 8.4 and 8.5, plus
`FLUXO_PLAYWRIGHT_MODULE=… node tests/log-filters-ui.mjs`.

## Search and editor follow-up (1 November 2026)

- Reopening global search focuses the end of its retained query; result updates preserve the current caret.
- WYSIWYG CSS-class tools exclude the temporary `cmsMediaOn` hover marker. Class names remain space-separated authored content.
- Image-editor split menus anchor 4 px below their trigger, like other split menus; transparent Close uses frame text colour.
- Upload/backup progress reuses the existing fill/counter state with one centered, contrast-backed counter.
- Account, Tags and Backup actions share a single root toolbar, preserving view replacement and widget headers.
- Dashboard edit-mode handoff does not infer a drop from layout moving under a stationary finger. A subsequent movement beyond 5 px restores normal hit-testing. Physical iPad verification remains outstanding.

## Smartphone shared components (2 November 2026)

- Main-menu visibility is UI state and does not push browser history. Actual leaf navigation still reaches the router on Back/Forward, then restores its sheet.
- Backup and account pages are regular content at every width; only pylon detail pages use phone sheets.
- Table folding uses the shared transparent button and shared row spacing, including schema tables.
- Active dialog tabs remeasure their visible bounds after layout changes and scroll smoothly into view (respecting reduced motion).
- Search retains the main theme colour; the bottom chrome edge no longer paints over content. Native iOS safe-area/keyboard behaviour still needs physical-device verification.
- Phone nested dialogs have no entrance animation. Signet previews retain the main-colour surface.

Verification: running Dev app at 390 px (menu history, Backup/Profile, Settings table folding, search open/close, Back/Forward), 1194/1440 px layout regression; shared mobile refinement and statusbar-return browser fixtures. No page errors in those runs; no new entries in the inspected project PHP log. Physical Safari gesture, keyboard and safe-area testing is not covered by desktop touch emulation.

Follow-up: phone search uses the full available height and its existing sheet entrance. Search focus remains owned by the shared lightbox/openSearch lifecycle rather than mobile render synchronisation; keyboard-height changes do not animate the search surface. Closing a detail sheet routes its parent so the pylon selection clears. Shared phone shells use the same upper radius, shadow and centred header spacing. Theme asset overrides omit the redundant ownership label and reuse the colour-reset icon.

Checked again in the running Dev application: Backup/Profile at 390, 1194 and 1440 px; Settings close/back/forward; Media/Global Settings and Database accordion open states; full-height search and close; unsaved indicator above an Environment sheet (input restored before blur). No JavaScript errors; physical iOS Safari address-bar transparency and keyboard reopening remain unverified.

### Dark-mode shadows (2 November 2026)

- Dark appearance uses black standard and popup shadows; existing shadow geometry is retained. Sticky-table fades and sidebar tab-edge shadows no longer use light text/surface colours. Focus and state outlines remain unchanged.
- Light appearance and the light page-preview scope retain their existing shadow values. Browser comparison of shared components found identical light-mode computed values and screenshots before/after. Dev table/search-dialog checks passed in light/dark appearance without JavaScript errors.

### Module stylesheet refresh (2 November 2026)

AJAX navigation now updates an existing module stylesheet when its versioned URL changes, retaining its node and cascade position. Previously the path-only lookup discarded the new modification timestamp. Document responses already use no-store; CSS and JS URLs use individual file modification times. An open tab still needs a normal reload to load changed core JavaScript; runtime class replacement is intentionally avoided.

Verified with the running Environment panel: changed version updates the existing stylesheet, repeated identical versions retain it, and no duplicate module links are created. Native iPhone cache behaviour remains to be verified on-device.

## Mobile layout consolidation (2026-11-05)

Phone layouts reuse the shared buttons, widget footers and tables. Nested static dialogs (including email previews) have no sheet handle. Email previews in user details and the global email log share the same metadata and sandboxed message body. On phones without a pylon, an open dialog hides the shell header until it closes. Desktop geometry is retained.

Theme colours are loaded at entry independently of opening Theme Settings, with legacy configuration supported. Theme raster uploads accept transparent WebP through the existing conversion pipeline. Backup logs now retain actor information; older records without it remain readable without inventing an actor.

Validation: changed PHP files pass syntax checks; `php tests/backup-log-users.php`, `php tests/config-writes.php` and JavaScript syntax checks pass. The user mail preview was visually checked at phone and desktop widths. Final cross-preview and native iPhone checks remain pending: dev.local became unavailable (DNS resolution failed). These limitations are not a visual acceptance pass.
