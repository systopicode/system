<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Builder;

use Systopic\System\Panels\PanelNode;

/**
 * Value object returned by PanelBuilder::buildChildren().
 *
 * Carries the resolved logical/real path segments and the deepest selected
 * panel back to PanelTree so it can populate its own path/realPath state
 * without the builder reaching into tree internals.
 */
readonly class ChildBuildResult
{
    public function __construct(
        public array      $resolvedPath,
        public array      $resolvedRealPath,
        public ?PanelNode $selected,
    ) {}
}
