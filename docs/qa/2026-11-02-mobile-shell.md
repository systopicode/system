# Mobile shell follow-up — 2 November 2026

Original request items 1–9; first block covers 1–4.

- 1 PASS for the shell geometry: shared phone radius reduced to 24px and pylon top padding adjusted; user confirmed delivery on the real iPhone. Navigation weight reservation confirmed fixed for Settings. Users had a separate 2.625px shift caused by the margin of an empty submenu; removed that margin and verified actual open/close in WebKit. Real-iPhone confirmation of this Users fix remains pending.
- 2 BLOCKED: petrol top safe-area colour accepted by the user, but the input-focus/reopening flash remains unresolved on the real iPhone.
- 3 BLOCKED: content behind the native Safari bottom address bar is still not accepted; no verified fix.
- 4 PASS in mobile WebKit: stronger, inward-spread dark shadow on partially lowered sheets; hidden when fully open. Dragged the actual sheet and checked screenshots. Real-iPhone confirmation of the latest shadow behaviour remains pending.

Additional navigation feedback: module changes fade the pylon contents in over 200ms with a 5px upward motion. The rounded shell stays stationary; reduced-motion disables this animation. Actual Users → Settings navigation exercised in WebKit without JS errors.

- 5–9 BLOCKED / not started: next implementation blocks intentionally await the first block's acceptance gate.

Checks: mobile-final-check.mjs (Media Settings, Global Settings, Database accordions), mobile-search-reopen.mjs, mobile-shoulders.mjs; no page errors; git diff --check clean. These browser checks do not substitute for native Safari safe-area testing.

### Follow-up: search behind Safari controls (item 2)

- Removed `touch-action:none` from the keyboard search ancestor; it prevented descendant native vertical panning even where the results declared `pan-y`.
- Removed the search section's bottom safe-area padding while the keyboard is open. The full-height results scroller now owns the occlusion padding.
- Measure the covered portion from the sheet bottom and `visualViewport` instead of assuming the document keyboard estimate matches the large viewport.
- WebKit geometry test with simulated touch and keyboard viewport: sheet bottom 844px, visual bottom 494px, measured occlusion 350px; last of 24 fixture results reaches 476.5px. Short list also tested. No JavaScript errors. Fixtures were DOM-only; project data unchanged.
- Desktop/mobile header regression passed. Native Device Hub Safari opens, but web-coordinate input fails with `noWindowsAvailable`; user also reports that typing is unavailable. Actual iOS keyboard/chrome rendering remains BLOCKED, not a claimed visual pass.
