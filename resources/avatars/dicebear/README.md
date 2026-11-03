# Local avatar subset

Pinned DiceBear PHP core and Notionists/Bottts definitions: **10.6.0**. Archive revisions, SHA-256 digests, retained style metadata and generated-file hashes are in `manifest.json`.

## Runtime

`Auth/Portrait.php` gives uploaded photos precedence. Otherwise `Auth/LocalAvatar.php` renders a local SVG and caches it beside uploaded photos in the installation's `var/avatars/` directory:

- Uploads retain the existing `<user-id>.<extension>` naming and upload rules.
- Automatic SVGs use `<user-id>.generated-<cache-fingerprint>.svg`. `hasFile()` continues to mean an uploaded photo, so automatic images do not acquire a misleading Remove photo control.
- People use SHA-256 of the trimmed, lower-case email. No email: account-derived seed. Screens use an account-derived Bottts seed. Style, version and options must match for matching images across installations. This is pseudonymous account data, not anonymisation.
- The fingerprint changes with the seed, theme, pinned renderer/style or adaptation revision. Old automatic variants are removed after a successful replacement; uploads are preserved. Uploading/removing an own photo and deleting an account remove its automatic variants too.
- Cached SVGs are reused without rendering or rewriting. Invalid cache files regenerate. Failed generation, missing optional runtime facilities or unavailable storage use the existing local Solar user/display Duotone fallback. No external avatar API is called.

The `.json.gz` files remain compressed **on disk**. `gzdecode()` expands only the selected style in PHP memory on a cache miss. Style objects are reused within the request. Generated SVGs are additional installation data and their existing style attribution metadata is retained.

The cache fingerprint is a derived digest, not an authentication token. It must not be reused for authentication or treated as anonymous merely because it is hashed. Neither a clear email address nor the raw seed is embedded in the generated SVG.

## Restricted upstream adaptation

This is deliberately **not** a generic DiceBear installation or public rendering endpoint:

1. The core uses the private `Systopic\System\Vendor\DiceBear` namespace, so a project's normal DiceBear package cannot collide with it.
2. The schema-validator entry calls and their imports are removed from Style/Options. Validator classes and the Opis/schema packages are not shipped. Style alias checks remain.
3. Only two fixed, digest-checked definitions are accepted. They are checked with the unmodified schema-validating upstream library during reference testing. `LocalAvatar` accepts only a 64-character hexadecimal seed and three six-digit colours; it constructs the fixed option list itself. Do not expose the private vendor classes to uploaded styles or arbitrary HTTP options.
4. Documentation blocks are omitted to reduce source size. Runtime code comments, attribution generation and the full MIT notice are retained.
5. Definitions are JSON-compacted without discarding artwork or licence metadata, then gzip-compressed at build time.

PHP 8.4 is already required by the System package. This path also uses mbstring, DOM/libxml and zlib. It needs no Composer execution, Node, Docker or service on the target hosting package. If generation cannot run there, the neutral fallback remains available.

## Rebuild and verify

Download the two original archives using the URLs in `manifest.json` as `core.zip` / `styles.zip` into a temporary directory. No runtime downloads are performed.

```sh
python3 tools/build-local-avatars.py --archives-dir /tmp/fluxo-local-avatars
python3 tools/build-local-avatars.py --archives-dir /tmp/fluxo-local-avatars --check
php tests/local-avatars.php
php tests/portrait-fallback.php
php tests/third-party-notices.php
```

For reference comparison, temporarily install unmodified `dicebear/core:10.6.0` including its dependencies outside the project with Composer scripts/plugins disabled. Put the original Notionists/Bottts JSON files beside that reference directory. Then run:

```sh
FLUXO_DICEBEAR_REFERENCE=/tmp/fluxo-local-avatars/reference php tests/local-avatars.php
```

This validates the definitions/options through upstream and compares **48 SVGs byte for byte** across both styles, 12 seeds per style and two light/dark palettes. Run the full comparison before updating versions; increase the adaptation revision when output-affecting wrapper/vendor changes are made. Without a reference directory the local functional checks still run and clearly report the skipped upstream comparison.

Licences: `legal/third-party/DiceBear-MIT.txt`, `DiceBear-Styles-NOTICE.txt`, `CC0-1.0.txt`. No public API usage permission is needed for this implementation because it never calls that API.
