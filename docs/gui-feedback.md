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

Field feedback resolves an unambiguous replacement after a response rebuilds a form, so the success/error contour is applied to the visible field. Success retires after 2.5 seconds. Feedback uses one contour and the existing CSS spinner; the entire desktop statusbar keeps its position above lightbox backdrops; its message has no separate stacking override.

Pages text editing now commits on blur: rendered plain text fields, WYSIWYG, meta description, rewrite paths, sidebar text/group values, publishing dates, media labels and SVG source. Search inputs remain live; toggles, selectors and explicit structural actions remain immediate. Explicit link insertion saves before closing its dialog. Completed toolbar edits made while focus is already outside the WYSIWYG field also commit, so they cannot be left unsaved without a future blur.

AJAX navigation now queues the last requested destination while blur-autosaves complete, rather than consuming the click. Render references and XHR setup are generated only when navigation actually resumes. A failed write cancels automatic continuation and retains the form. Native unload protection remains active.

Enter in page-title/tree pencil edits dispatches the existing blur-save with a value snapshot before edit mode ends. Native follow-up blur is suppressed to avoid duplicate writes. Composition input is not committed early; Escape cancels. Regression: `tests/pencil-enter-ui.mjs` exercises the real event/payload collector against an isolated endpoint and reloads the saved result.

## Upload results and explicit site actions

The CMS multi-upload uses the shared lightbox and jobs status line. Each batch retains per-file success, failure, rejection and pending state in the current document. Closing the overview keeps uploads running; Upload results and the running job's Details button reopen it. An explicit retry skips successful and rejected entries and restarts only failed/pending files. A failure leaves later files pending. Results are cleared by reload; no persistent draft/file store is added.

Queued uploads carry a stable random identity. Session-scoped completion receipts bind it to the file and destination. Repeated chunks write at their fixed offsets, and repeated completed requests return success without creating another medium. This does not add cross-session/server-crash exactly-once semantics.

Site renaming is an explicit reviewed action. Typing a new domain no longer triggers a rename. Meta defaults explain each value's origin and link to the corresponding Site, Page Settings or Theme editor. Accessibility findings and verification scope are documented in `accessibility-review-2026-10-30.md`.
