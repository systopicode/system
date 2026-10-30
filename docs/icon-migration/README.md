# Local SVG icon migration — 30 October 2026

Tabler Outline 3.50.0 is the primary set; seven selected Solar Line Duotone icons come from `@iconify-json/solar` 1.2.13. No icon runtime package, external icon API or icon font is required. Inter remains the GUI typeface. The systopic Software License is unchanged; the icon licences remain separate.

## Implementation and extension contract

`src/Ui/icon-map.json` is the shared registry. Existing semantic names (save/check, file types, status, navigation etc.) use `cmsIcon cmsIcon-<key>`. Main-navigation entries additionally use `cmsIcon-duotone` when a registered Solar variant exists. `public/css/11_icons.css` paints local SVG masks into existing `<i>` slots, keeping selectors, event targets and layout stable. CSS is applied before JS; no hydration or external service is required to see icons. `:host` variables support the website edit bar's shadow root. Pseudoelements use semantic mask variables instead of private Unicode glyphs.

Use `Systopic\System\Ui\Icons::classes($key, $duotone)` or `::html($key, $duotone)` in PHP; use `cmsIcons.classes()` / `cmsIcons.html()` in JS. Label the owning icon-only button. Decorative symbols are hidden from accessibility APIs. Unknown names display the help-circle fallback and are reported. No user-supplied SVG/URL/path is rendered.

Font Awesome class compatibility has been removed. Extensions must emit registered semantic keys or native `cmsIcon` classes; old `fa-*` markup is no longer upgraded. No stored icon configuration was identified in the inspected core. Project-specific extensions and old generated asset copies must be updated before deployment.

Changes in meaning: `user-clock` uses a history symbol for “Signed in …”; `images` uses the photo-library symbol. Tools navigation uses the existing Solar Line Duotone tuning controls; CMS Pages uses Solar documents. See `icon-map.csv` for exact mappings. All mapped names exist in pinned upstream archives.

## Reproducible assets

`tools/build-icons.py` reads the registry and verifies SHA-512 npm archive integrity pinned in `assets.json`. It does not download or execute package scripts. Download the exact `tabler.tgz` and `solar.tgz` URLs in that manifest to a temporary directory, then run:

```sh
python3 tools/build-icons.py --archives-dir /tmp/fluxo-icon-archives
python3 tools/build-icons.py --archives-dir /tmp/fluxo-icon-archives --check
```

The build emits only the registered Tabler subset, independent primary/secondary Solar masks, CSS and the JS registry keys. Tabler icon masks retain the regular upstream stroke 2. Solar uses matching stroke 2; inactive navigation secondary opacity is .75. Icon glyphs are 1.2em, independent of the host thumbnail/button dimensions. Solar opacity is applied by theme tokens; luminance-mask geometry in the search icon is flattened before splitting. Passkey artwork uses licensed fingerprint/user-scan/lock-open geometry at stroke 0.9 with explicit ink/tone-fill roles. Its source and hash are recorded in `assets.json`; it is maintained as a reviewed template, not silently overwritten by the asset generator. All geometry remains local. Full licences and modification attribution are exposed through the existing ThirdParty registry, legal text directory and public License view. `usage-inventory.csv` records the original source locations before migration; line numbers will move as development continues.

## Viewer bundle

The CMS currently distributes `public/modules/skp-viewer.js` without a co-located reproducible source build. Only the owned toolbar's three exact class literals (Minus, Reset, Plus) were changed; Three.js and parser code were not transformed. On an upstream rebuild, ensure those three literals emit `cmsIcon` classes, then run the residual scan. Do not mass-rewrite the dependency bundle. The source candidate outside these two repos was not modified.

## Deployment, releases and history

The next coordinated System release is planned as **1.3.0** because CMS now calls the new registry. CMS requires `systopic/system: ^1.3`; no tags or package versions have been published. Publish the tested System release before the matching CMS release. Local package symlinks can test the working trees; a fresh resolver install cannot be claimed before that release exists. No lockfile was present in either package; the enclosing project lock was not edited or resolved.

The source tree removes exactly the old provider stylesheet and two WOFF2 files. Existing published asset copies are installation-specific: run `php tools/icon-cleanup.php <asset-public-directory>` as a dry run. Only after checking the directory use `--apply`. It removes those three exact relative paths, refuses escape/symlink targets and does not touch project fonts or user media. It was not run on the real installation's published cache. Current CSS/JS URLs already use Assets::href/mtime; relative SVG URLs go through Assets::rewriteCss and receive their own versions.

Old open tabs need a full reload after the coordinated deployment; do not mix older producer JS with removed fonts. Revert a local migration using the exact prior code/data format as a coordinated pair, not one package alone. A deployed rollback must account for the rights to any former Pro files. This change does not remove Pro assets from Git history, tags, past releases or external caches. No history rewrite, force push, release deletion, commit or push was performed. Historical licence/redistribution questions need a separate decision.

## Validation

See `validation.md` for the actual results, known pre-existing issues and release-only checks. Implemented code is not equivalent to a tested fresh release, a real platform-authenticator ceremony or every third-party project integration.

Sign-in/out artwork uses Tabler `login-2` / `logout`: sign-in points right into the door, sign-out points right out of the door. All login/logout aliases use this pair.

## Complete semantic follow-up
All registered keys were rechecked against their original FA meaning and consumers, including finite dynamic producers. See `semantic-audit.csv` (one row per key) and `dynamic-producers.csv`. Only existing upstream icons are permitted; the show/hide-children UI combination is the sole exception. Former custom composites now use upstream history, user-cancel, user-cog, circle-play and photo icons. Tools uses the existing Solar tuning controls.


Cleanup uses the existing Solar `broom-line-duotone` icon. Solar is used exclusively for Line Duotone. The central broom key is used for cleanup, technical cache clearing and log clearing.

Tools now uses the existing Solar `tuning-2-line-duotone` icon. No custom artwork or duotone split is generated.

Spaceticker custom panels (events and contacts) emit native cmsIcon classes, including dynamic absence, calendar and company/person fallbacks. Main navigation uses existing Solar calendar-date and notebook Line Duotone icons. Technical Tools/System uses a CPU; Settings/Environment retains the server.
