# Desktop feedback, 1 November 2026

Scope: original desktop feedback items 1–21. No project content or permissions were intentionally changed. Appearance was restored to System. Confirmation tests cancelled the operation. No mail was sent and no logs were cleared.

| Nr. | Status | Verification |
|---|---|---|
| 1 | PASS | Auth footer screenshot at desktop width; footer padding compensates for the shared button's horizontal padding. |
| 2 | PASS | IP-address passkey guidance now says system; PHP syntax checked. |
| 3 | PASS | Running profile: scrolled 229 px, changed appearance, remained at 229 px; restored System. Shared feedback/navigation regression suite passed. |
| 4 | PASS | Opened Add device, selected code entry, submitted empty code: invalid form, no code request, dialog stayed open. |
| 5 | PASS | Ran Check files; visually inspected the generated table. Unreadable originals remain reported as a partial result, not an all-clear. |
| 6 | PASS | Running user dialog: inserting local status left the dialog bounds unchanged. Status is positioned as an overlay rather than a flex row. |
| 7 | PASS | Superuser revoke confirmation opened and was dismissed without mutation. Device revocation retains its existing confirmation handler. |
| 8 | PASS | Opened user E-Mail Log, expanded an entry, opened nested preview and closed it; stayed in the user dialog. No JS errors. |
| 9 | PASS | Dashboard footer screenshot inspected; divider has more space above and card-padding below. |
| 10 | PASS | Disposable PHP render test for numeric video values including 0 and 1: text controls, not switches. Existing running video settings inspected. No real format created. |
| 11 | PASS | Running Media Settings: removed these two help controls; format and extension selectors retained. |
| 12 | PASS | Inserted a disposable DOM notice, navigated Tags to Backup through AJAX: notice removed. |
| 13 | PASS | Running Tags: edited title, dirty outline appeared, Escape restored original and released navigation. Enter/persistence fixture suite passed. |
| 14 | PASS | Strengthened shared presence notice surface and border with theme variables. Desktop Pages checked; real-device contrast remains for review. |
| 15 | BLOCKED | Production synthetic-touch media-move tests pass, including rejected/cancelled restoration. Native iPad gesture cannot be exercised on this host. |
| 16 | PASS | Running Pages help and meta-default dialogs opened and closed with simulated touch at iPad size. |
| 17 | PASS | Production drop-policy tests: allowed media/page targets highlighted, plain text rejected. Synthetic touch only. |
| 18 | PASS | Real-browser CSS fixture at iPad size: Share Image and MML both 38 px controls / 18 px glyphs, identical theme colours in both tested palettes. |
| 19 | PASS | Unified image editor regression suite at 1920, 1194, 1024 and 390 px; small previews recenter and larger previews retain reachable pan area. Native pinch still requires device review. |
| 20 | PASS | Sidebar output includes explicit spacing before both sitemap and llms icons; PHP syntax checked. |
| 21 | PASS | Backup direct load, AJAX navigation and browser Back/Forward: no visible pylon. Original unknown gesture could not be reproduced. |

Additional findings fixed in this pass:
- PUT responses restored scroll before a later descendant redraw; restore the preserved scroll after processing the commands too.
- Cancelling a tag/administrative header edit restored text but left feedback dirty. Explicitly cancel the shared feedback state.
- Touch-only MML size overrides had omitted Share Image. Both now use the same selectors.

Other verification: PHP/JS syntax checks, both repositories' diff whitespace checks, GUI feedback suite, pencil Enter suite, unified image editor suite and media drop/move suites. No new page JavaScript errors in the exercised Dev routes. No new PHP errors observed in the relevant project log during this pass.

Not a full native-device sign-off: item 15 remains blocked pending an actual iPad test. No commit was requested for this feedback block.
