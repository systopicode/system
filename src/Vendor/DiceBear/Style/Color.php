<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear\Style;


class Color
{

    public function __construct(private readonly array $data) {}


    public function values(): array
    {
        return $this->data['values'];
    }


    public function notEqualTo(): array
    {
        return $this->data['notEqualTo'] ?? [];
    }


    public function contrastTo(): ?string
    {
        return $this->data['contrastTo'] ?? null;
    }
}
