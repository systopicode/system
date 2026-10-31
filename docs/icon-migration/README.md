# Local SVG icons — current state 31 October 2026

Tabler Outline 3.50.0 supplies the normal controls and content icons; Phosphor Duotone 2.1.1 supplies the twelve registered navigation/custom-panel variants and the passkey artwork. The explicitly retained Solar 1.2.13 info-circle is the only monochrome exception. Cleanup uses Tabler eraser. No icon runtime package, external API or icon font is required. Inter remains the GUI typeface. Icon licences remain separate from the CMS software licence.

## Implementation and extension contract

`src/Ui/icon-map.json` is the shared registry. Existing semantic names (save/check, file types, status, navigation etc.) use `cmsIcon cmsIcon-<key>`. Main-navigation entries additionally use `cmsIcon-duotone` when a registered Phosphor variant exists. `public/css/11_icons.css` paints local SVG masks into existing `<i>` slots, keeping selectors, event targets and layout stable. CSS is applied before JS; no hydration or external service is required to see icons. `:host` variables support the website edit bar's shadow root. Pseudoelements use semantic mask variables instead of private Unicode glyphs.

Use `Systopic\System\Ui\Icons::classes($key, $duotone)` or `::html($key, $duotone)` in PHP; use `cmsIcons.classes()` / `cmsIcons.html()` in JS. Label the owning icon-only button. Decorative symbols are hidden from accessibility APIs. Unknown names display the help-circle fallback and are reported. No user-supplied SVG/URL/path is rendered.

Font Awesome class compatibility has been removed. Extensions must emit registered semantic keys or native `cmsIcon` classes; old `fa-*` markup is no longer upgraded. No stored icon configuration was identified in the inspected core. Project-specific extensions and old generated asset copies must be updated before deployment.

Changes in meaning: `user-clock` uses a history symbol for “Signed in …”; `images` uses the photo-library symbol. Tools navigation uses Phosphor Wrench; CMS Pages uses Phosphor Files. See `icon-map.csv` for exact mappings. All mapped names exist in pinned upstream archives.

## Reproducible assets

`tools/build-icons.py` reads the registry and verifies SHA-512 npm archive integrity pinned in `assets.json`. It does not download or execute package scripts. Download the exact `tabler.tgz`, `solar.tgz` and `phosphor.tgz` URLs in that manifest to a temporary directory, then run:

```sh
python3 tools/build-icons.py --archives-dir /tmp/fluxo-icon-archives
python3 tools/build-icons.py --archives-dir /tmp/fluxo-icon-archives --check
```

The build emits the registered Tabler subset, the retained monochrome Solar info-circle, independent primary/secondary Phosphor masks, CSS and JS registry keys. Tabler retains upstream stroke 2. Phosphor geometry is retained; the source Duotone opacity is removed only from the split masks so CSS controls each layer independently. Navigation secondary opacity is 40% normally and 50% when selected; primary contours remain opaque. Icon glyphs are 1.2em independently of their thumbnail/button containers.

Passkey artwork uses original Phosphor fingerprint/user-focus/lock-open Duotone geometry with theme ink roles. The existing nine-second phase sequence, success state and reduced-motion handling are retained. Its source and SHA-256 hash are recorded in `assets.json`; it is a reviewed template, not overwritten by the generator. Full licences and attribution are exposed through ThirdParty and the License view. Theme includes side-by-side Tabler Outline and Phosphor Duotone buttons with external-link icons on the right. Privacy states that all icon assets are local.

The `sitemap` Duotone key is available for custom panels but has no active System/CMS consumer. Tools → Website → Paths → Reassign sites uses the normal Tabler sitemap icon. `usage-inventory.csv` records original pre-migration source locations; its line numbers are historical. `changes.csv` is also a historical migration snapshot, not the current asset list: `assets.json` and `asset-license-inventory.csv` are authoritative for shipped files.

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
All registered keys were rechecked against their original FA meaning and consumers, including finite dynamic producers. See `semantic-audit.csv` (one row per key) and `dynamic-producers.csv`. Only existing upstream icons are permitted; the show/hide-children UI combination is the sole exception. Former custom composites now use upstream history, user-cancel, user-cog, circle-play and photo icons. Tools uses the original Phosphor Wrench Duotone icon.


## Confirmed normal-icon selection

The user explicitly retained the Solar info-circle for small-size readability. The shared `broom` key now maps to the original Tabler `eraser` asset, covering cleanup and log clearing, including dynamically supplied keys. The old Solar broom SVG has been removed. Existing semantic keys and action behaviour are unchanged. No project-specific panel files are modified.
