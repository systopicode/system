<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear\Style;


class Canvas
{

    private ?array $elements = null;


    public function __construct(private readonly array $data) {}


    public function width(): int|float
    {
        return $this->data['width'];
    }


    public function height(): int|float
    {
        return $this->data['height'];
    }


    public function elements(): array
    {
        return $this->elements ??= array_map(
            fn(array $el) => new Element($el),
            $this->data['elements'],
        );
    }
}
