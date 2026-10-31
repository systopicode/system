<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear;

use Systopic\System\Vendor\DiceBear\Error\StyleValidationError;
use Systopic\System\Vendor\DiceBear\Style\Canvas;
use Systopic\System\Vendor\DiceBear\Style\Color;
use Systopic\System\Vendor\DiceBear\Style\Component;
use Systopic\System\Vendor\DiceBear\Style\Meta;


class Style
{

    private array $data;
    private ?Meta $meta = null;
    private ?Canvas $canvas = null;

    private ?array $components = null;

    private ?array $colors = null;

    public function __construct(mixed $data)
    {
        // Fluxo supplies only build-validated, digest-checked definitions.
        if (!is_array($data)) throw new \InvalidArgumentException('Expected a pinned style definition.');

        $this->data = is_array($data) ? $data : json_decode(json_encode($data), true);

        $this->validateAliases();
    }


    public static function fromJson(string $json): self
    {
        return new self(json_decode($json, true, flags: JSON_THROW_ON_ERROR));
    }


    public function id(): ?string
    {
        return $this->data['$id'] ?? null;
    }


    public function schema(): ?string
    {
        return $this->data['$schema'] ?? null;
    }


    public function comment(): ?string
    {
        return $this->data['$comment'] ?? null;
    }


    public function meta(): Meta
    {
        return $this->meta ??= new Meta($this->data['meta'] ?? []);
    }


    public function attributes(): array
    {
        return $this->data['attributes'] ?? [];
    }


    public function canvas(): Canvas
    {
        return $this->canvas ??= new Canvas($this->data['canvas']);
    }


    public function components(): array
    {
        if ($this->components !== null) {
            return $this->components;
        }

        $entries = $this->data['components'] ?? [];
        $this->components = [];

        foreach ($entries as $name => $data) {
            if (!self::isAlias($data)) {
                $this->components[$name] = new Component($name, $data);
            }
        }

        foreach ($entries as $name => $data) {
            if (self::isAlias($data)) {
                $this->components[$name] = new Component(
                    $name,
                    $data,
                    $this->components[$data['extends']] ?? null,
                );
            }
        }

        return $this->components;
    }


    private function validateAliases(): void
    {
        $components = $this->data['components'] ?? null;

        if ($components === null) {
            return;
        }

        $errors = [];

        foreach ($components as $name => $data) {
            if (!self::isAlias($data)) {
                continue;
            }

            $target = $data['extends'];
            $targetData = $components[$target] ?? null;

            if ($targetData === null) {
                $errors[] = [
                    'instancePath' => "/components/{$name}/extends",
                    'message' => "references unknown component \"{$target}\"",
                ];

                continue;
            }

            if (self::isAlias($targetData)) {
                $errors[] = [
                    'instancePath' => "/components/{$name}/extends",
                    'message' => "references alias \"{$target}\" — alias chains are not allowed",
                ];
            }
        }

        if (count($errors) > 0) {
            throw new StyleValidationError($errors);
        }
    }


    private static function isAlias(array $data): bool
    {
        return array_key_exists('extends', $data);
    }


    public function colors(): array
    {
        if ($this->colors === null) {
            $this->colors = [];

            foreach ($this->data['colors'] ?? [] as $name => $data) {
                $this->colors[$name] = new Color($data);
            }
        }

        return $this->colors;
    }
}
