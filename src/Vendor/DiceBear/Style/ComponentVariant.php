<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear\Style;


class ComponentVariant
{

    private ?array $elements = null;


    public function __construct(private readonly array $data) {}


    public function elements(): array
    {
        return $this->elements ??= array_map(
            fn(array $el) => new Element($el),
            $this->data['elements'],
        );
    }


    public function weight(): int|float
    {
        return $this->data['weight'] ?? 1;
    }


    public function tags(): array
    {
        return $this->data['tags'] ?? [];
    }


    public function hasTag(string $category, ?string $value = null): bool
    {
        if ($value === null) {
            foreach ($this->tags() as $tag) {
                if ($tag === $category || str_starts_with($tag, "{$category}:")) {
                    return true;
                }
            }

            return false;
        }

        return in_array("{$category}:{$value}", $this->tags(), true);
    }
}
