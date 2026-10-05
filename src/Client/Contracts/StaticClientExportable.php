<?php

declare(strict_types=1);

namespace Systopic\System\Client\Contracts;

use Systopic\System\Client\ClientExport;

/**
 * Marks a PHP class that exports static (non-instance) configuration data
 * to the JS sys.* namespace tree.
 *
 * Replaces the legacy `clientData` interface. The static method must return
 * either a single ClientExport or an array of ClientExports (for classes
 * that export multiple routes).
 *
 * Example implementing class:
 *
 *   class http implements StaticClientExportable
 *   {
 *       public static function exportStaticClientData(): ClientExport
 *       {
 *           return new ClientExport(
 *               route: 'singletons.http',
 *               data: (object) ['root' => http::$root, 'sysRoot' => http::$sysRoot],
 *           );
 *       }
 *   }
 */
interface StaticClientExportable
{
    /** @return ClientExport|ClientExport[] */
    public static function exportStaticClientData(): ClientExport|array;
}
