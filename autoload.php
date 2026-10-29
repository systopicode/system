<?php

/**
 * Composer `autoload.files` of systopic/system: what the package announces
 * before anything runs. Projects pick it up with `composer dump-autoload`.
 */

// The tables of the system — scanned by the ORM on the first Scope.
\Systopic\Db\Schema\Registry::addTables(__DIR__ . '/src/Tables', 'Systopic\System\Tables');

// Passkeys ship with the system. A project does not install the library
// itself: this loads the copy in lib/webauthn when Composer has none.
spl_autoload_register(static function (string $class): void {
    $prefix = 'lbuchs\\WebAuthn\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $root = __DIR__ . '/lib/webauthn/src/';
    $file = $root . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (!is_file($file)) {
        return;
    }
    // WebAuthn.php pulls its siblings with relative require_once.
    set_include_path($root . PATH_SEPARATOR . get_include_path());
    require $file;
});

// ffmpeg/ffprobe lookup, including a copy the project keeps in var/bin.
require_once __DIR__ . '/lib/ffmpeg.bin.php';

// Right after the database, before the panel tree is built (which already
// sends an anonymous visitor to the login): a device cookie logs its user in
// again, a live session's device is checked. Nothing happens without one.
\Systopic\System\Sys\Hooks\Hooks::on('afterConnect', static fn() => \Systopic\System\Auth\Devices::restore());
\Systopic\System\Sys\Hooks\Hooks::on('afterConnect', static fn() => \Systopic\System\Auth\LoginCodes::answerLive());

// The web app manifest, before the login redirect of the panel tree.
\Systopic\System\Sys\Hooks\Hooks::on('afterConnect', static fn() => \Systopic\System\Sys\WebApp::serve());

// Send account security notifications only after committed ORM changes.
\Systopic\Db\Scope::addObserver(new \Systopic\System\Auth\SecurityNotice());

// Inherited ACLs of the nodes follow a move or an ACL change, after the commit.
\Systopic\Db\Scope::addObserver(new \Systopic\System\Tables\Nodes\AclInheritance());
