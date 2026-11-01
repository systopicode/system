# Dashboard and profile logout

## Responsive layouts

The account's existing `sessiondata.dashboard.order` remains the legacy three-column order. `|` separates columns. Existing flat orders keep their historical column-major interpretation; removed or duplicate widget IDs are ignored, new widgets are appended.

The optional `dashboard.layouts` object stores independent orders for `1`, `2` and `3` columns. An order is saved only when the user sorts in that view. Sorting three columns also updates the legacy `order` field. Other views are retained. Hidden widgets are deliberately shared across views; hiding/showing does not alter any order or other account session data.

The server renders each widget once. The dashboard's existing CSS breakpoints select 1/2/3 real vertical stacks; the third desktop column is no longer placed under a two-column grid. An unsorted smaller view derives a stable plan from the existing desktop order, assigning widgets to the shortest measured stack. That derived plan stays fixed during card expansion and customization in the current document. Explicitly saved per-view layouts survive reload and take precedence. There is no new layout library or persistent layout cache.

`Dashboard\Layout` contains the storage/migration rules. The dashboard initializer in `panels/cms/panel.js` runs on the first document and through the standard `initEvents` callback for AJAX fragments. Fragments can be bound before insertion; their ResizeObserver performs layout after mounting. The dashboard reuses the shared drag engine for long press, insertion feedback and drop behavior. Its empty-column targets use full-area feedback; other lists retain their existing line or area feedback. Hide uses the existing transparent icon button and does not start a drag.

## Logout

The Log out control in the header of the user's own profile is native navigation (`target=_self`) to the existing user logout action. That action logs out once and immediately redirects to the existing auth/logout screen without rendering the emptied profile. Legacy AJAX callers retain their previous full-document transition. No second session action or new auth route is introduced.

## Verification

- `php tests/dashboard-layout.php`: legacy layout migration, independent saved views, JSON round trip, complete/deduplicated widget membership.
- `php tests/dashboard-actions.php`: actual move/hide/show handlers, target columns and unchanged other views.
- `tests/dashboard-responsive-ui.mjs`: first load and detached AJAX fragments, 1/2/3 columns, stable membership, no grid-row gaps, existing buttons, shared touch/drag handling.
- `tests/logout-navigation.mjs`: actual profile control + action through an isolated HTTP server; one logout, one 302, correct final screen, no empty profile document.
- Real Stefan Pohl Dev dashboard: 3→2→1→3 resizing and Customize→Done verified without changing the user's saved sort order. The real user's session was not logged out; logout is verified using an isolated session fixture.

Manual acceptance: reorder one view, reload, and switch to another device/column count; only the edited view should change. On iPad, scrolling before the long-press threshold must scroll rather than start dragging.

## Auth and customization follow-up

Continue actions align right, Back left. Skipping passkey setup now renders the shared successful-sign-in state for 2 seconds and navigates to the saved return destination using the existing full-document transition. Passkey creation during onboarding uses the same success state and delay. This also lets the CMS head apply the account appearance on first arrival, rather than retaining the auth document's light appearance.

Dashboard drop zones use insertion lines at half the stack gap. Populated column ends remain invisible line targets. During dragging, an empty column exposes a highlight-tinted drop surface with a dashed border; entering it changes the border to solid and uses full-area feedback instead of an insertion line. These surfaces disappear when dragging ends. Dropping onto the customization instruction hides a widget using the same hidden state as the cross. Holding a dashboard title outside customization opens the existing customization mode through the shared long-press action. The held item transfers to the shared drag engine only after the replacement dashboard mounts, and only while the pointer remains held. Within customization, dragging starts immediately on movement.

Additional regression checks: `tests/auth-continue.php` covers active/inactive skip and return target; `tests/auth-continue-ui.mjs` checks footer alignment and initial system dark mode/OS changes in an isolated browser.

## Shared desktop long press

The existing drag engine also starts a mouse drag after holding still for 400 ms. Movement still starts dragging at the existing 5 px threshold; the hold timer cannot start it twice. The same engine covers tree, tags, media, table grips and dashboard widgets without individual implementations. Quick release and window blur cancel the timer. Holding and releasing without moving never commits a drop. Resize hooks and interactive controls are excluded. Touch/pen keep their existing scroll-safe recogniser.

`data-on_longpress` reuses that recogniser for explicit mode-entry actions, including dashboard titles. Moving before the threshold cancels mode entry; AJAX-inserted headers work through delegation. No long-press delay is imposed on ordinary mouse dragging.

`tests/desktop-longpress-ui.mjs` exercises shared drag types, immediate movement, release/blur cancellation, resize exclusion, duplicate prevention and title actions in isolated Chrome.

## Auth methods and hidden widgets

Password, invitation/recovery code, identity confirmation, password activation/reset, passkey and remote pairing finish through the shared two-second success state. The optional passkey offer remains before that final state. Return destinations are preserved. Reset password has an inline return to password entry and a footer leading to method selection. The read-only `?view=resetCode` and `?view=activationCode` forms can be inspected without sending mail; validation is unchanged.

Auth autofill uses the card canvas/text palette rather than browser fill colours. Hidden widgets retain their own widget icon, support click-to-restore and shared drag-to-position. A successful insertion clears the hidden flag and saves only the current column view. The hidden-widget rail reserves its first row even while empty to avoid the first item's height jump.

## Auth polish and extension hints

The system shell and auth body signal `data-1p-ignore` and `data-bwignore`; newly bound fields receive the same hints, including login fields as explicitly requested. These are extension hints, not enforcement; supported extensions may also stop offering automatic filling. Native autocomplete semantics (including one-time codes and passkeys) remain intact. Autofill colours use the common forms palette. Autofill shadows do not animate through browser highlight colours.

Auth buttons use the existing global button font size. Inline return links share their margin, successful-sign-in text is centered, and camera denial keeps manual code entry plus one camera retry without duplicate mode buttons. Real tree drag labels are allowed by desktop-hold filtering; nested interactive controls stay excluded.

## Final interaction checks

Tree title anchors inside drag labels also support desktop hold; Safari Force Click previews are cancelled on tree drag surfaces and tree text selection/callouts are suppressed. Editable labels keep their own input behavior. Local page-ID navigation schedules the existing tree reveal after layout and uses `revealScroll`, preserving its scroll-restoration coordination. Unchanged remote selections do not trigger this path.

WYSIWYG link clicks open the existing anchor bar in the editor's capture handler, including links configured for a new tab. The shared AJAX handler also refuses to navigate editable links. Tag drag feedback no longer copies a label; only shared line feedback remains.

The auth UI checks basic WebAuthn/browser/secure-context support before offering passkeys, without treating absent platform biometrics as absence of external authenticators. Forgot-password keeps an already-entered email in the existing auth controller only until the reset form mounts; GET navigation does not carry the event dataset. The value is assigned to the rendered input without a URL parameter or persistent browser storage. Actual button-to-inline-link gaps are regression-tested with the surrounding form markup. Camera denial renders its retry as a secondary button.

Password-manager ignore hints are best effort. Safari's native credentials badge is hidden separately; extensions control their own overlays and may ignore page hints.

## Follow-up: native tree previews and scroll ownership

Tree titles are selection buttons using the existing `data-href` navigation convention; they keep the label styling, keyboard behavior and context menu. They no longer expose native link previews on long/force presses. Original/redirect references remain anchors. Selection and a stationary hold were also checked in the real Dev UI without changing the ordering.

Scroll snapshots now record their reveal version. Both DOM pins and delayed restores yield to a newer explicit selection reveal, including selection at offset zero. `tests/scroll-reveal-ui.mjs` covers these competing restore paths and ordinary unchanged-view restoration.

The password form submits a transient `passkey_supported` capability field. The setup offer is attempted only when that request reports support; no account preference or credential is created by detection. Capability checks run again on each browser visit. Cameras are not persistently hidden based on an unreliable pre-permission device list.

Password and reset inline return links now both live inside their forms, preventing the sign-in pane's flex gap from adding extra space. The logout action is below its countdown. Reset-code verification is labelled “Verify code”: it opens password entry, and sign-in follows only after successfully setting the password.

## Unified auth form layout

`src/Auth/form.card.tpl.php` supplies the same `authSignin` / `authPane` / `authFoot` layout as password login for reset email, reset/activation code and password choice. The footer is rendered once inside that layout. Large passkey sign-in artwork and success screens center headings and copy; Signed out keeps its title/subtitle left aligned; add-passkey onboarding is intentionally unchanged. Password sign-in uses the existing password-input icon in the method picker. Countdown-to-label spacing shares the existing `authProgress + authWait` rule.

Tree editing keeps its icon in the same padded flex area as the normal title and does not change row height. Explicit tree reveals use native smooth scrolling through `revealScroll`; reduced-motion users retain instant positioning. Scroll ownership still protects against stale restores.

The read-only `?view=setPassword` route previews password choice; saving still requires a valid activation/reset key. No credentials are generated by viewing it.

`tests/auth-screen-fixture.php` renders real templates without a project, messages or credentials. The auth browser regression now checks actual rendered form geometry, centered illustrated states and email transfer after mounting, rather than comparing incomplete HTML fragments. `tests/tree-edit-layout-ui.mjs` checks unchanged row/icon geometry.

The success card shows the authenticated account name and its existing Portrait image. Passkey and pairing responses supply this profile only after successful authentication; hidden pre-login success cards contain no account profile.
