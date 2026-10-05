<?php

declare(strict_types=1);

namespace Systopic\System\Client\Contracts;

use Systopic\System\Client\ClientExport;

/**
 * Marks a PHP object that has a corresponding representation in the JS
 * sys.* namespace tree.
 *
 * Replaces the legacy `clientInstance` interface. Implementing classes
 * return a typed ClientExport DTO instead of an untyped plain object.
 *
 * The returned route must be a dot-separated path matching the position of
 * the object in the JS tree, e.g. 'panels.cms.pages'.
 */
interface ClientExportable
{
    public function exportClientData(): ClientExport;
}
