# systopic/system

The systopic framework: panels, sessions and rights, nodes, media, tags -
the base of every systopic project.

```bash
composer require systopic/system
```

Requires PHP 8.4 and [systopic/db](https://github.com/systopicode/db).
External PHP libraries are installed exclusively by Composer: `lbuchs/webauthn`
for passkeys, `dicebear/core` 10.6.0 for local avatars and `tecnickcom/tcpdf`
for PDF output. No embedded PHP-library fallback is shipped.

Before deploying this change to an existing project, run `composer update
systopic/system --with-all-dependencies` in that project to refresh its lock
file, then deploy the resulting `vendor/` tree together with the application.
Production builds can use `composer install --no-dev --prefer-dist
--optimize-autoloader`. Composer need not run on the hosting server when the
complete build is uploaded. Do not upload `system/` alone with stale dependencies.

Websites add [systopic/cms](https://github.com/systopicode/cms).

## Documentation

- [Editing feedback and display dates](docs/gui-feedback.md)
- [Administration and restore safety](docs/admin-safety.md)

© 2026 systopic GmbH. Publicly available source code under the
[systopic Software License](LICENSE). Use, modification and redistribution
are permitted only as set out in that license or a separate agreement.
