<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Builder;

/**
 * Result of PanelClassResolver::resolve().
 * Carries the PHP class name to instantiate and whether the panel folder
 * contains its own module.php (as opposed to falling back to a parent class
 * or PanelNode).
 */
readonly class ResolvedClass
{
    public function __construct(
        /** Fully-qualified (global) PHP class name to instantiate. */
        public string $className,
        /**
         * True when a module.php was present AND a matching class was found
         * inside it. False when falling back to PanelNode or inheriting from
         * the parent panel's class.
         */
        public bool $hasOwnClass,
    ) {}
}
