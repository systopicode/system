<?php

declare(strict_types=1);

use Systopic\System\Sys\AdminRoute;

require dirname(__DIR__) . '/src/Sys/AdminRoute.php';

$cases = [
    ['/cms', '/', true, '/admin/pages/'],
    ['/cms/', '/', false, '/admin/'],
    ['/project/public/cms/pages/?id=7&lang=de', '/project/public/', true, '/project/public/admin/pages/?id=7&lang=de'],
    ['/cms/users/permissions/edit/?id=3', '/', true, '/admin/access/content/edit/?id=3'],
    ['/cms/controlmap/discover/', '/', true, '/admin/access/panelmap/discover/'],
    ['/cms-tools/', '/', true, null],
    ['/assets/systopic/cms/public/editor.js', '/', true, null],
    ['/other/cms/pages/', '/project/public/', true, null],
    ['/admin/pages/', '/', true, null],
];

foreach ($cases as [$uri, $root, $hasPages, $expected]) {
    if (AdminRoute::legacyTarget($uri, $root, $hasPages) !== $expected) {
        throw new RuntimeException('Unexpected redirect for ' . $uri);
    }
}
echo "Admin legacy redirects passed.\n";
