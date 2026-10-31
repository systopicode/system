<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear\Style;


class Element
{

    private ?array $children = null;


    public function __construct(private readonly array $data) {}


    public function type(): string
    {
        return $this->data['type'];
    }


    public function name(): ?string
    {
        return $this->data['name'] ?? null;
    }


    public function value(): string|array|null
    {
        return $this->data['value'] ?? null;
    }


    public function attributes(): ?array
    {
        return $this->data['attributes'] ?? null;
    }


    public function children(): array
    {
        return $this->children ??= array_map(
            fn(array $child) => new Element($child),
            $this->data['children'] ?? [],
        );
    }
}
