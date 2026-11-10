# Local screening of new passwords

New passwords require at least 15 Unicode characters by default. A project can lower or raise this with `packages.system.auth.passwordLength` in `config/config.json` (at least 6, `AuthConfig::passwordLength()`). Below 15 the blocklist does not help, because it only holds passwords of 15 or more characters. No digit, uppercase or symbol composition rule is imposed. Existing sign-in verification is unchanged.

`Auth/PasswordBlocklist.php` rejects whole passwords found in the shipped SHA-256 list and values consisting entirely of a repeated pattern of one to six characters. It does not reject a passphrase merely because it contains a common word. Comparison for screening trims surrounding whitespace and lowercases ASCII; the actual password is not rewritten before hashing.

The browser explains length, matching and simple repetitions. The authoritative common-password check happens on submission through `Users/Operator::passwordProblems()`, shared by profile changes, activation/reset, setup and initial-account creation. A rejection returns the existing error-message flow. No password or derived hash is sent to an external service. Special characters remain valid data.

## Dataset and limits

Sources retrieved 2026-11-01:
- SecLists `Passwords/Common-Credentials/xato-net-10-million-passwords.txt`: 5,189,454 lines. Source SHA-256 `c95989fecfae9c91aa9f5c730344d5cabd6335bb094c9c60aba16bf1742a72fe`.
- The earlier Django subset is retained as a union (BSD notice).

The combined list contains 140,926 unique SHA-256 digests of UTF-8 passwords of at least 15 characters. It lives in `src/Auth/data/common-passwords.bin`, outside public assets. Sorted fixed-width 32-byte digests permit binary search with bounded memory (about 18 reads per lookup), suitable for shared hosting. Missing or malformed data fails closed rather than silently disabling screening. Keep `SecLists-MIT.txt` and `Django-BSD.txt`; both are included in the licence view.

Build: decode UTF-8 lines, strip surrounding whitespace, retain 15+ Unicode characters, lowercase ASCII, SHA-256 each complete value, union with the previous Django digests, sort unique raw digests and concatenate. Do not include real user passwords in this build process.

This is a limited common-password list, not a comprehensive or automatically updated breach database. A password passing screening is not guaranteed secure. Update the local dataset deliberately when updating the package. No full NIST compliance claim is made.

Reference: NIST SP 800-63B-4 requires screening new passwords against commonly used, expected or compromised values: https://pages.nist.gov/800-63-4/sp800-63b.html

## Verification

- `tests/password-policy.php`: common long password, repetitions, length, Unicode, mismatches, permitted special characters and a common word inside a passphrase.
- `tests/current-seven-ui.mjs`: pre-submit repetition feedback/disabled state, desktop 1440, tablet-touch 1194 and narrow-touch 390.
- PHP/JS syntax and repository whitespace checks passed; project PHP error log unchanged.
- Live account submission and physical iPad verification were not performed. The live browser tool was unavailable (`browser.user is unavailable`); no real credentials were changed.

## Login and recovery review

Password login now uses the same per-IP failure records as codes and passkeys: ten failures within 15 minutes block attempts for 15 minutes. Existing account and blocked-account checks remain. Recovery keys use 16 random bytes, expire and are consumed on successful password setting. Session IDs rotate on login. These checks are not a complete BSI/ISO certification or a substitute for host/TLS and operational controls.

`tests/password-login-throttle.php` exercises the actual Session login method against in-memory query/persistence fixtures, including blocking before lookup and acceptance after expiry; no real account or database is changed.
