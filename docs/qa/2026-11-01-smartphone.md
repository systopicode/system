# Smartphone review — 2026-11-01

Phone-specific presentation uses the existing 767px breakpoint. Tablet/desktop tables and dialog layouts remain unchanged. Shared search dismissal also releases its keyboard proxy.

Verified in Chrome against the running Dev installation at 390, 1194 and 1440px: tags, migration, trash, profile and database status load without JavaScript errors, HTTP 5xx responses or document overflow. Database rows open/close, audit headings identify their subject, Theme cards retain rounded corners and aligned colour fields. Search closes via X and page selection. Main-menu Back/Forward and a synthetic left swipe work.

`tests/smartphone-sheets-ui.mjs` exercises nested dialog bounds, header overflow actions, the visible close control, QR/code separation, themed footers and phone-to-tablet/desktop table reset using disposable markup. `tests/mobile-refinements-ui.mjs` covers existing mobile behaviour. Set `FLUXO_PLAYWRIGHT_MODULE` to the installed Playwright module when it is not locally available.

Remaining device acceptance: real iOS safe-area colour, keyboard/background scroll with the keyboard open, physical swipe/drag and VoiceOver. Synthetic touch and Chrome viewport emulation do not prove those behaviours. The migration installation has no pending conversion fixture; no conversion or deletion was performed. Real QR generation was not invoked; QR sizing was verified with disposable dialog markup. Profile-to-login-log deep-link navigation needs a real link-flow acceptance test.

Interpretations without the missing screenshots: items 10/11 refer to Theme cards/colour fields; item 9 refers to the lightbox footer; nested dialogs (23) use compact modals over the first phone sheet. These interpretations still need user confirmation.
