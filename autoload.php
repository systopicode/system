<?php

/**
 * Composer `autoload.files` of systopic/system: what the package announces
 * before anything runs. Projects pick it up with `composer dump-autoload`.
 */

// The tables of the system — scanned by the ORM on the first Scope.
\Systopic\Db\Schema\Registry::addTables(__DIR__ . '/src/Tables', 'Systopic\System\Tables');

// ffmpeg/ffprobe lookup, including a copy the project keeps in var/bin.
require_once __DIR__ . '/lib/ffmpeg.bin.php';

\Systopic\System\Sys\Hooks\Hooks::on('afterConnect', static fn() => \Systopic\System\Sys\AdminRoute::redirectLegacy());

// Right after the database, before the panel tree is built (which already
// sends an anonymous visitor to the login): a device cookie logs its user in
// again, a live session's device is checked. Nothing happens without one.
\Systopic\System\Sys\Hooks\Hooks::on('afterConnect', static fn() => \Systopic\System\Auth\Devices::restore());
\Systopic\System\Sys\Hooks\Hooks::on('afterConnect', static fn() => \Systopic\System\Auth\SignInCodes::answerLive());

// The web app manifest, before the login redirect of the panel tree.
\Systopic\System\Sys\Hooks\Hooks::on('afterConnect', static fn() => \Systopic\System\Sys\WebApp::serve());

// Send account security notifications only after committed ORM changes.
\Systopic\Db\Scope::addObserver(new \Systopic\System\Auth\SecurityNotice());

// Shared-hosting housekeeping: existing expiry rules, bounded and project-local.
\Systopic\System\Maintenance\Tasks::register();
\Systopic\System\Sys\Hooks\Hooks::on('afterConnect', static fn() => \Systopic\System\Maintenance\Runner::boot(), 1000);
// Inherited ACLs of the nodes follow a move or an ACL change, after the commit.
\Systopic\Db\Scope::addObserver(new \Systopic\System\Tables\Nodes\AclInheritance());
