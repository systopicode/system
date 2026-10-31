<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear\Style;


class Meta
{
    private ?MetaLicense $license = null;
    private ?MetaCreator $creator = null;
    private ?MetaSource $source = null;


    public function __construct(private readonly array $data) {}


    public function license(): MetaLicense
    {
        return $this->license ??= new MetaLicense($this->data['license'] ?? []);
    }


    public function creator(): MetaCreator
    {
        return $this->creator ??= new MetaCreator($this->data['creator'] ?? []);
    }


    public function source(): MetaSource
    {
        return $this->source ??= new MetaSource($this->data['source'] ?? []);
    }
}
