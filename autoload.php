<?php

/**
 * Composer `autoload.files` of systopic/system: what the package announces
 * before anything runs. Projects pick it up with `composer dump-autoload`.
 */

// The tables of the system — scanned by the ORM on the first Scope.
\Systopic\Db\Schema\Registry::addTables(__DIR__ . '/src/Tables', 'Systopic\System\Tables');

// Right after the database, before the panel tree is built (which already
// sends an anonymous visitor to the login): a device cookie logs its user in
// again, a live session's device is checked. Nothing happens without one.
\Systopic\System\Sys\Hooks\Hooks::on('afterConnect', static fn() => \Systopic\System\Auth\Devices::restore());
