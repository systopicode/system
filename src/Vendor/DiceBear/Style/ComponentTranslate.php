<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear\Style;


class ComponentTranslate
{

    public function __construct(private readonly array $data) {}


    public function x(): ?array
    {
        return $this->data['x'] ?? null;
    }


    public function y(): ?array
    {
        return $this->data['y'] ?? null;
    }
}
