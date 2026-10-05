<?php

declare(strict_types=1);

namespace Systopic\System\Dom\Layer;

/**
 * Common interface for the two types of items stored inside a DomLayer:
 *  - DomLayer  (a nested layer, isLayer() = true)
 *  - DomLayerSlice (a captured HTML chunk, isLayer() = false)
 */
interface DomLayerItem
{
    public function isLayer(): bool;

    public function __toString(): string;
}
