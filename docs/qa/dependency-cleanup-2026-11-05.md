# External PHP dependency cleanup — 2026-11-05

User request: remove embedded external implementations from System/CMS source.

- Removed `lib/webauthn` and its fallback autoloader. Existing `lbuchs/webauthn:^2.2` Composer requirement remains the single source.
- Removed `src/Vendor/DiceBear`; `LocalAvatar` now uses unmodified `dicebear/core:10.6.0` from Composer. Exact pin preserves deterministic avatar output. Its schema/Opis dependencies are resolved by Composer.
- Resource builder now generates only two compressed style definitions and their manifest. No PHP vendor sources are generated. Notices, provenance and deployment instructions updated.
- Central package mirror manifest/lock updated. Local Spaceticker Composer install updated: five added dependencies; existing symlinked first-party packages and TCPDF had lock metadata refreshed, with source already present. No online deployment performed.

## Verification

| Requirement | Status | Actual verification |
|---|---|---|
| Remove embedded external PHP libraries | PASS | No System/CMS `src/Vendor` or `lib/webauthn` remains. A fresh Composer installation resolves successfully. Reflection locates both libraries exclusively under its `vendor/`. |
| Preserve passkey service behaviour | PASS | Existing `tests/passkeys.php`: 79 checks plus final success, against a disposable `fluxo_test_dependency_cleanup` MySQL database. Includes registration, real OpenSSL-signed assertions, invalid signatures/origin/verification, login, challenge expiry and credential revocation. Fixed missing `fs::toNative` in standalone fixture. |
| Preserve avatars | PASS | Existing local-avatar filesystem/rendering tests and portrait fallback tests pass. 48 byte-identical raw SVG comparisons between the removed adapted renderer and Composer core, both styles, 12 seeds and two palettes. Resource builder `--check` verifies three files. |
| Browser and shared-component checks | PASS with limits | Actual Composer-generated avatar preview inspected at 1440, 768 and 390 widths; all eight images loaded without horizontal overflow. Light/dark control exercised. Isolated preview has no browser errors. Running local Spaceticker frontend, CMS navigation and security/passkey listing loaded after dependency installation. No real credentials changed. |
| Notices and source checks | PASS | `third-party-notices.php`, `legal-notices.php`, `viewer-notices.php`, PHP syntax checks, Composer validation and `git diff --check`. Composer's exact-version recommendation is intentional for deterministic avatars. |
| Real authenticator / physical touch-device acceptance | BLOCKED | No interactive authenticator or physical iPad available. No real passkey enrollment/login or physical touch-device test claimed. Online server not accessed or deployed. |

The live browser logged one `focusPath: 22 candidates ...` error from unchanged `public/js/core/focus.js` during navigation. No corresponding error occurred in the isolated avatar preview. Existing PHP log warnings in `Settings/EnvironmentForm.php` predate this run; no new PHP application errors observed. Viewport screenshot capture occasionally produced stale/clipped images after resizing; light-layout checks were repeated after reload.

No real project content, photos, passkeys or security settings were changed for testing. Existing unrelated worktree changes were preserved. Full hardware acceptance remains open; do not describe this as a completed real-device end-to-end test.
