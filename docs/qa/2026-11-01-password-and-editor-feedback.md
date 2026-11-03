# Password and editor feedback: seven-item verification

1. Password forms explain the minimum length and matching requirement before submission. Browser fixtures cover empty, short, mismatching and valid values; the disabled state and hint were also inspected in the running profile.
2. New passwords require at least 15 Unicode characters, without composition requirements, in every environment. Existing password verification is unchanged. `tests/password-policy.php` verifies the server rule. This alone is not a claim of full NIST compliance.
3. Automatic action spinners require `data-loading`; tabs and expand/collapse controls are excluded. The shared request-lifecycle test covers success/error cleanup. The seven-item fixture checks visible labels, stable button dimensions and main-colour spinner styling.
4. The final email entry has no bottom border even when footer controls follow it. Inspected in the running user dialog in desktop and narrow layouts, including expand/collapse.
5. The existing crop glyph is enlarged within its unchanged button. Computed styling checked in desktop and emulated touch fixtures; native iPad visual confirmation remains outstanding.
6. Starting another inline crop uses the existing close/discard guard. The on-page crop fixture verifies switching unchanged crops and retaining a changed crop when discard is declined, at desktop, tablet-touch and phone-touch sizes.
7. New/unset appearance defaults to System. Explicit Light/Dark choices remain supported. Browser tests cover System with dark/light media preferences and an explicit Light override.

Checks: PHP syntax checks, `password-policy.php`, `current-ten-ui.mjs`, `action-loading-ui.mjs`, `current-seven-ui.mjs`, `cms/tests/on-page-crop-ui.mjs`, and both repository diff whitespace checks passed. The inspected live browser reported no JavaScript errors. The project PHP error log remained unchanged.

Limits: no physical iPad was available; no real account password was changed and no real email was sent. Password submissions and changed crop saves were exercised only in isolated fixtures.
