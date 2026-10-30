# Local migration validation — 30 October 2026

Local code is implemented in both working trees; **full release acceptance is still open**. No commit, staging, push, tag, history rewrite or publication was performed.

Basis HEADs: System `e0ef7beef5f36c554e96d1057de18606b3403556`; CMS `1e79488b439f612f3361c62c9f2a981e26a6fb39`. The initial trees contained extensive existing changes. Every initial write checked the current file hash before applying its prepared change. Original versions and initial Git diffs are retained in `/tmp/fluxo-icon-migration/`. Two CSS files changed between the first snapshot and preparation; the newer edits were included and preserved. Snapshot delta reports can also contain independent concurrent edits and are not an attribution of all changes to this migration.

## Numbered briefing gate

| Original work item | Status | Actual evidence |
|---|---|---|
| 1. Instructions and working-tree baseline | PASS | Local CMS AGENTS read; HEADs, status, hashes and pre-existing diffs saved; Git index remains empty. |
| 2. Inventory and before images | PASS | Source inventory and original asset hashes; representative before/after local review of navigation, controls and artwork; dynamic media producers reviewed. |
| 3. Assets, licences and visual rules | PASS | Pinned Tabler 3.50.0 / Solar data 1.2.13; archive SHA-512 verification; selected local SVGs and per-file hashes; existing central ThirdParty mechanism extended. |
| 4. Shared icon rendering and representative slice | PASS | Registry-driven local masks, PHP/JS adapters, actual CMS navigation and Theme view, shadow-root test, deterministic passkey phases. |
| 5. Both repositories and dynamic consumers | PASS within inspected repositories | Static/stringbuilder/DOM-builder classes, old shorthand styles, media types/fallbacks, menus, status changes, search, edit bar and the owned viewer toolbar migrated. No known unregistered literal core key remains; partial `chevron-` expressions intentionally resolve to up/down at runtime. External project overrides are not claimed audited. |
| 6. Passkey artwork and animation | PASS for graphics/UI; hardware ceremony unverified | Tabler fingerprint/user-scan/lock-open, existing 9s sequence, 1s/4s/7s phase checks, actual success and Reduced Motion verified by the icon integration test. No authentication protocol or endpoint changed. |
| 7. Provider removal and legal/documentation updates | PASS for current source | Old CSS and two WOFF2 files removed; licences, provenance and anonymous/session legal views checked; Privacy updated in existing local-software section; no unexplained runtime FA residuals. Historical explanatory text and bounded adapter inputs are intentional. |
| 8. Install/upgrade/full acceptance | BLOCKED / pending release acceptance | Two pre-existing UI failures reproduced in isolated baseline copies; isolated MySQL access unavailable; real platform-authenticator, published-package fresh install, production cache cleanup and every third-party extension not verified. CMS now requires planned System ^1.3. No release was created. |
| 9. Inventories and handoff | PASS | Registry, mapping, source usage, asset/licence inventories, residual reasons, reproducible generator, precise dry-run cleanup and this report. |

This is one coordinated migration block. Item 8 remains open; do not call the full release gate complete or start deployment on the basis of source implementation alone.

## Passing checks

- Syntax check: 164 changed PHP/JS files in the initial implementation passed. Later PHP/JS refinements were exercised by their relevant tests and syntax checks.
- PHP suites: `icons.php`, `third-party-notices.php`, `contrast.php`, `sign-in-feedback.php`, `tools-navigation.php`, `dashboard-layout.php`, `messages.php`; sibling CMS `media-presentation.php`.
- Browser/JS suites: `icons-ui.mjs`, `theme-contrast-ui.mjs`, `gui-feedback-ui.mjs`, `pencil-enter-ui.mjs`, `pages-blur-save-ui.mjs`, `dashboard-responsive-ui.mjs`, `failed-actions-ui.mjs`, `log-filters-ui.mjs`, `auth-success-footer-ui.mjs`, `loader-ready.mjs`; sibling CMS `tree-live-ui.mjs`, `page-edit-conflict-ui.mjs`.
- New icon integration coverage: every registered displayed asset loads; all requests stay on the local test origin; 1440/768/390px slots; shadow root; bounded legacy adapter; accessibility state; passkey phase order, true-success presentation and reduced motion; 180px MML tiles do not enlarge the glyph; badges and hover-button glyphs have bounded dimensions; missing image/video/file and GIF/video keys resolve to appropriate artwork.
- Archive integrity and `tools/build-icons.py --check`: deterministic output verified. The generator handles safe regular-file-only extraction without requiring newer tarfile filter arguments.
- Cleanup tool tested only against disposable fixtures: dry run changes nothing; apply removes the three exact old provider files and preserves Inter.
- Anonymous License response (decoded from its client-render JSON): exactly one Tabler credit, one Solar credit and complete notices, without the obsolete Pro section. Logged-in legal Lightbox also checked, including Close.
- Actual running installation: `https://dev.local/projects/stefanpohlfilm.de/public/cms/`. Dashboard, navigation, Theme, Legal, Tools navigation and a known Pages/MML view inspected. Desktop, tablet and narrow Theme layouts inspected. No content, theme values, user permissions, media, backups or credentials were deliberately modified.
- New icon-related PHP log errors: none observed. Existing panel-parent wiring warnings (`duplicates`, `tags`, `php`) were observed and are not concealed as a clean-console result.

## User-requested visual correction

The first pass was too light and used box-relative masks, which made MML icons huge in full-size thumbnail slots or in controls whose icon element had no positioning context. Final Tabler masks retain regular upstream stroke **2**. Solar is adapted to matching stroke **2**, with inactive main-navigation secondary opacity **.75**. Normal glyphs use **1.2em** dimensions centred independently of their container; mark thumbnails and fallback slots remain bounded. Actual hover controls were visually checked in the running MML; the automated test checks their glyph/button dimensions without relying on an overlapping artificial hover fixture.

Tools now uses Solar **toolbox**, not a sledgehammer. Image collections use **library-photo**. Dynamic `image-slash`, `video-slash`, `file-slash`, `gif` and `video` are explicitly registered. The old question-mark fallback for an unreadable thumbnail was a missing mapping and has been corrected. Asset/licence inventories record the Solar stroke adjustment. The Theme view links to Tabler Outline and Solar's actual `suffixes=Line+Duotone` search filter.

## Confirmed existing failures / unexecuted checks

1. `system/tests/auth-continue-ui.mjs` fails because `.authFoot` resolves to both a visible footer and a hidden success footer. The identical failure was reproduced against the saved pre-migration source in a separate temporary tree. A trial selector adjustment exposed an additional existing `loggedOut` centring assertion; that trial was reverted. The original test is preserved unchanged.
2. `cms/tests/editor-presence-ui.mjs` expects “Also open” but receives an empty string. This also fails in the isolated pre-migration baseline. The test was not rewritten to hide it.
3. The full DB-backed `tests/passkeys.php` and its service-generated happy-dom fixture were not executed: a local MySQL probe without credentials was denied. No production/project database was used to get around this, and no credentials were guessed. Graphic/UI passkey checks are passing; real registration/login with platform hardware still needs a suitable test account/device.
4. No fresh install from a not-yet-published System 1.3 release, production deployment, actual-installation cache purge, Firefox/Safari-specific complete suite, or all customer extensions was performed. These remain explicit release/integration checks.
5. The full SketchUp viewer build source is not co-located in CMS. The three owned toolbar literals were migrated surgically, leaving the dependency bundle untouched. Rebuild provenance is a known packaging limitation, documented in the upgrade notes.

Test outputs and the isolated baseline copies are retained locally under `/tmp/fluxo-icon-migration/test-results/` and `/tmp/fluxo-icon-migration/baseline-test/`. They are not published or committed.

## Semantic follow-up requested by the user
The entire current registry and dynamic producer graph were rechecked; the per-key and producer inventories are `semantic-audit.csv` and `dynamic-producers.csv`. Corrections include blocked-user vs superuser, roles vs editing, user+time, generic image file vs JPEG, circled external-video play, redirects, stacked layers, table columns, colour swatches, visitor preview and missing dynamic waiting clock. Tools now uses the existing Solar tuning-2 Line Duotone icon. Custom compositions were removed; only show/hide-children remains combined. The cleanup broom retains the existing Solar geometry in one opaque colour. The browser test decodes every registered SVG as an actual image in addition to checking CSS-mask slots; the generator rejects malformed SVG XML. PHP tests exercise the real media-extension, broken-media and device icon producers. Full live authenticated reinspection was unavailable in this follow-up because the IAB control backend reports `browser.user is unavailable`; the native Chrome session was unauthenticated. The replacement motifs were visually inspected in a local component review, and 1440/768/390px icon checks ran in isolated browser fixtures. No credentials, project content or permissions were changed. Previously documented release blockers remain unchanged.

Semantic follow-up gate: 1 PASS (per-key meaning review), 2 PASS (finite dynamic domains and real PHP producer tests, including sibling CMS `tests/icon-producers.php`), 3 PASS (generator/XML/image decoding and desktop/tablet/mobile fixture checks), 4 BLOCKED for repeated authenticated live visual inspection (IAB control backend unavailable; local component review inspected instead), 5 PASS (preserved working trees, no staging/commit/push). The earlier numbered release gate remains open as documented above.

## Commit isolation review (30 October 2026)
Icon commits are built from each repository HEAD plus icon-only edits. Unrelated pre-existing changes are excluded. New feature panels that do not yet exist in HEAD remain local rather than including their functionality in this migration. No push is performed. Authenticated live acceptance and hardware passkey ceremonies remain unverified for the reasons above.
