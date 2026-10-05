<?php

/**
 * Composer `autoload.files` of systopic/system: what the package announces
 * before anything runs. Projects pick it up with `composer dump-autoload`.
 */

// The tables of the system — scanned by the ORM on the first Scope.
\Systopic\Db\Schema\Registry::addTables(__DIR__ . '/src/Tables', 'Systopic\System\Tables');
