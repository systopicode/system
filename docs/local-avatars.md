# Local avatars — implementation and verification

System-only change; no commits or pushes. No public avatar API is used.

## Behaviour

1. Uploaded profile photos retain priority and their existing naming, MIME restrictions and upload path.
2. People without a photo get a local Notionists SVG, selected by SHA-256 of the trimmed, lower-case email. With no email, the account ID is used. Screens use Bottts with an account-derived seed. The same person email, style version and options give the same SVG across installations.
3. Generated files live beside uploads under the installation's `public/var/avatars/` URL: `<id>.generated-<fingerprint>.svg`. They are never mistaken for uploaded photos by `hasFile()`.
4. Cache hits reuse the file. Email/theme/style changes produce a new fingerprint and clear stale generated variants only after successful replacement. Uploading/removing a photo clears automatic variants; user deletion also clears the avatar files.
5. Invalid cache SVGs regenerate. Atomic temporary-file/rename avoids partially written images. If generation or storage is unavailable, the existing Solar person/display Duotone fallback is returned.

The two style definitions stay gzip-compressed on disk. Only the required definition is decoded temporarily in PHP memory on a render cache miss. No unpacked second copy is installed and no target-host Composer, Node or service process is required.

## Distribution

- Adapted private DiceBear PHP renderer 10.6.0 under `src/Vendor/DiceBear`.
- Exactly Notionists and Bottts under `resources/avatars/dicebear`.
- No Opis/schema runtime packages. Original schema validation is used in the temporary reference tests; runtime accepts only fixed digest-checked definitions and a narrow option surface.
- 239,110 bytes for the runtime, restricted adapter, resources, resource README and complete licence/notice texts at the time of measurement (about 239 KB; filesystem block allocation can be larger). Tests, build tools and this report are not part of that runtime measurement. Generated avatars are additional per-installation data; the tested live person SVG is about 10 KB.
- Full sources, archive hashes, version and adaptations are documented in the manifest and resource README. `tools/build-local-avatars.py --check` verifies reproduction from the original pinned archives.

## Verification

| Requirement | Result | Verification |
|---|---|---|
| Local renderer with two styles | PASS | 48 byte-identical output comparisons against unmodified DiceBear PHP 10.6.0 with full original validators, across both styles, 12 seeds per style and two palettes. |
| E-mail mapping | PASS | Trimming/case normalisation and same email under distinct account IDs produce the same person SVG. Distinct seeds produce varied faces. Email changes invalidate the generated cache. |
| Store in project avatar folder | PASS | Actual `Portrait::src()` writes under the real Files::varDir()/avatars boundary, tested on disposable filesystem data and in the authenticated local profile. Live URL is `var/avatars/3.generated-….svg`; the browser decodes it at 128×128. |
| Cache and own photo priority | PASS | Cache reuse preserves mtime; damaged cache regenerates; real WebP upload through the existing staging boundary removes automatic SVGs and keeps photo priority after email changes. Native profile reload also reuses its existing generated SVG. |
| Duotone fallback | PASS | Both original person/screen fallback SVGs render. Invalid storage and a child PHP process with gzdecode disabled return the local fallback without PHP errors. |
| Cleanup | PASS | Explicit removal clears uploaded and generated files. Successful upload clears generated variants. The existing account-delete action also invokes cleanup after removal. No real account was deleted during testing. |
| Rendering | PASS | Real generated SVGs and both fallbacks decode in desktop/iPad/narrow and dark examples; touch context checked. Authenticated profile checked at desktop, 768 and 390 widths; avatar fits its frame. No foreign avatar, font or image requests or JS errors in the isolated renderer test. |
| Notices/privacy | PASS | Core MIT, original Notionists CC0 and Bottts artist permission are supplied and shown in third-party credits; SVG attribution is preserved. Privacy text describes local email-derived pseudonyms and cache storage. Legal-notice and notice-digest tests pass. |

The live browser initially reported a `systopic/relay` refresh error (`relay.js:321`, reading `fail`). The affected client returned no value from `done()`, while Relay assumed chainable callbacks. Relay now registers success and failure callbacks separately and retains the watchdog for legacy clients without `fail()`. Delivery, concurrent changes, HTTP failure/retry, missing legacy failure hooks and project stop/restart pass regression tests. An isolated real-browser test passes with non-chainable callbacks and an HTTP failure followed by successful retry; the refreshed live CMS browser reports no JavaScript errors. An older cached client is a plausible explanation for the original callback mismatch, but was not proven. No claim is made that the entire installation is free of unrelated errors.

No uploaded project photos were replaced for testing. Fixture data is disposable and cleaned up. The generated SVG of the signed-in account is deliberately retained as the requested working local cache.

## Commands

```sh
FLUXO_DICEBEAR_REFERENCE=/tmp/fluxo-local-avatars/reference php tests/local-avatars.php
php tests/portrait-fallback.php
php tests/legal-notices.php
php tests/third-party-notices.php
php tests/account-blocking.php
python3 tools/build-local-avatars.py --archives-dir /tmp/fluxo-local-avatars --check
FLUXO_PHP=/path/to/php FLUXO_PLAYWRIGHT_MODULE=/path/to/playwright/index.mjs node tests/local-avatars-ui.mjs
```

The reference installation is temporary and is not shipped. The optional UI-test output directory is controlled by `FLUXO_AVATAR_ARTIFACTS`.
