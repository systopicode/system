<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear\Style;


class Component
{
    private ?ComponentTranslate $translate = null;

    private ?array $variants = null;


    public function __construct(
        private readonly string $name,
        private readonly array $data,
        private readonly ?Component $source = null,
    ) {}


    public function name(): string
    {
        return $this->name;
    }


    public function extendsName(): ?string
    {
        return $this->data['extends'] ?? null;
    }


    public function sourceName(): string
    {
        return $this->extendsName() ?? $this->name;
    }


    public function width(): int|float
    {
        return $this->source !== null ? $this->source->width() : $this->data['width'];
    }


    public function height(): int|float
    {
        return $this->source !== null ? $this->source->height() : $this->data['height'];
    }


    public function probability(): int|float
    {
        if ($this->source !== null) {
            return $this->source->probability();
        }

        return $this->data['probability'] ?? 100;
    }


    public function rotate(): ?array
    {
        if ($this->source !== null) {
            return $this->source->rotate();
        }

        return $this->data['rotate'] ?? null;
    }


    public function scale(): ?array
    {
        if ($this->source !== null) {
            return $this->source->scale();
        }

        return $this->data['scale'] ?? null;
    }


    public function translate(): ComponentTranslate
    {
        if ($this->source !== null) {
            return $this->source->translate();
        }

        return $this->translate ??= new ComponentTranslate($this->data['translate'] ?? []);
    }


    public function variants(): array
    {
        if ($this->source !== null) {
            return $this->source->variants();
        }

        if ($this->variants === null) {
            $this->variants = [];

            foreach ($this->data['variants'] as $name => $data) {
                $this->variants[$name] = new ComponentVariant($data);
            }
        }

        return $this->variants;
    }
}
