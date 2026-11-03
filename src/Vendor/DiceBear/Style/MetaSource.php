<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear\Style;


class MetaSource
{

    public function __construct(private readonly array $data) {}


    public function name(): ?string
    {
        return $this->data['name'] ?? null;
    }


    public function url(): ?string
    {
        return $this->data['url'] ?? null;
    }
}
