<?php

declare(strict_types=1);

namespace Systopic\System\Client;

/**
 * Immutable value object representing a single PHP→JS data export.
 *
 * Maps to the object shape that sys.lib.updateClientData() expects on the
 * client side:
 *
 *   { route: string, data: object, onLibReady?: string[] }
 *
 * `route` is a dot-separated path into the sys.* namespace tree, e.g.
 * 'panels.cms.pages' or 'singletons.http'.
 *
 * `onLibReady` is an optional list of sys.* function paths to call after
 * the lib layer has been built (e.g. 'sys.siteRenderer.updateDocument').
 */
readonly class ClientExport implements \JsonSerializable
{
    public function __construct(
        public string $route,
        public object $data,
        public array  $onLibReady = [],
    ) {}

    public function jsonSerialize(): array
    {
        $result = [
            'route' => $this->route,
            'data'  => $this->data,
        ];
        if ($this->onLibReady !== []) {
            $result['onLibReady'] = $this->onLibReady;
        }
        return $result;
    }
}
