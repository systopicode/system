<?php

declare(strict_types=1);

namespace Systopic\System\Controlmap;

/**
 * One panel as Discover found it in the folders: where it comes from, what
 * its class asks for access, which actions its code names, and the panels
 * below it.
 *
 * The path is the logical one (`cms/pages/sidebar`, folder prefixes like
 * `010_` dropped): that is what a config refers to, since a folder can be
 * renumbered and ids change between installations.
 */
final class DiscoveredPanel
{
    /**
     * @param list<string>                    $origins every package (or 'project') that has the folder, in registration order
     * @param array{build?: list<string>, action?: list<string>} $grants what the class's own buildGranted() / actionGranted() ask for
     * @param array<string, DiscoveredAction> $actions
     * @param list<self>                      $children
     */
    public function __construct(
        public readonly string $path,
        public readonly string $name,
        public readonly string $folder,
        public readonly string $origin,
        public readonly array $origins,
        public readonly ?string $classFile,
        public readonly ?string $className,
        public readonly ?string $extends,
        public readonly array $grants,
        public readonly array $actions,
        public readonly bool $hasTemplates,
        public readonly array $children,
    ) {}

    /** A folder without class, templates or actions — an asset folder the tree builds as a panel anyway. */
    public bool $isPlain {
        get => $this->classFile === null && !$this->hasTemplates && $this->actions === [];
    }

    public int $depth {
        get => substr_count($this->path, '/');
    }

    /** @return \Generator<self> this panel and every one below, depth first */
    public function all(): \Generator
    {
        yield $this;
        foreach ($this->children as $child) {
            yield from $child->all();
        }
    }
}
