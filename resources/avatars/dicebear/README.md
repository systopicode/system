# Local avatar subset

Pinned Composer renderer and Notionists/Bottts definitions: **10.6.0**. Upstream archive revisions, SHA-256 digests, retained style metadata and generated resource hashes are in `manifest.json`.

## Runtime

`Auth/Portrait.php` gives uploaded photos precedence. Otherwise `Auth/LocalAvatar.php` renders a local SVG and caches it beside uploaded photos in the installation's `var/avatars/` directory:

- Uploads retain the existing `<user-id>.<extension>` naming and upload rules.
- Automatic SVGs use `<user-id>.generated-<cache-fingerprint>.svg`. `hasFile()` continues to mean an uploaded photo, so automatic images do not acquire a misleading Remove photo control.
- People use SHA-256 of the trimmed, lower-case email. No email: account-derived seed. Screens use an account-derived Bottts seed. Style, version and options must match for matching images across installations. This is pseudonymous account data, not anonymisation.
- The fingerprint changes with the seed, theme, pinned renderer/style or adaptation revision. Old automatic variants are removed after a successful replacement; uploads are preserved. Uploading/removing an own photo and deleting an account remove its automatic variants too.
- Cached SVGs are reused without rendering or rewriting. Invalid cache files regenerate. Failed generation, missing optional runtime facilities or unavailable storage use the existing local Solar user/display Duotone fallback. No external avatar API is called.

The `.json.gz` files remain compressed **on disk**. `gzdecode()` expands only the selected style in PHP memory on a cache miss. Style objects are reused within the request. Generated SVGs are additional installation data and their existing style attribution metadata is retained.

The cache fingerprint is a derived digest, not an authentication token. It must not be reused for authentication or treated as anonymous merely because it is hashed. Neither a clear email address nor the raw seed is embedded in the generated SVG.

## Composer renderer

The unmodified `dicebear/core` 10.6.0 package and its schema validators are
installed by Composer under the project's `vendor/` directory. No renderer
source or private vendor namespace is copied into System. `Auth/LocalAvatar`
remains the application adapter: it accepts only a 64-character hexadecimal
seed and three six-digit colours and loads the two digest-checked definitions.
No uploaded styles or arbitrary HTTP options are accepted.

PHP 8.4 is required by System; the avatar path also uses mbstring, DOM/libxml
and zlib. The complete Composer dependency tree must be deployed with System.
No Node, Docker, external avatar API or background service is required.

## Rebuild and verify

Download the two original archives using the URLs in `manifest.json` as `core.zip` / `styles.zip` into a temporary directory. No runtime downloads are performed.

```sh
python3 tools/build-local-avatars.py --archives-dir /tmp/fluxo-local-avatars
python3 tools/build-local-avatars.py --archives-dir /tmp/fluxo-local-avatars --check
php tests/local-avatars.php
php tests/portrait-fallback.php
php tests/third-party-notices.php
```

`tests/local-avatars.php` now exercises the actual Composer renderer, including
its original schema validators. Set `FLUXO_DICEBEAR_AUTOLOAD` to another Composer
installation's `vendor/autoload.php` when running standalone checks outside an
installed System package. Before changing the pinned renderer/style version,
compare generated SVGs against the previous release and review cache identity.

Licences: `legal/third-party/DiceBear-MIT.txt`, `DiceBear-Styles-NOTICE.txt`, `CC0-1.0.txt`. No public API usage permission is needed for this implementation because it never calls that API.
