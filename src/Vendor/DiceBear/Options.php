<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear;



class Options
{
    public const COLOR_ORDER_RANDOM = 'random';
    public const COLOR_ORDER_FIXED = 'fixed';


    private array $data;


    private ?array $tags = null;


    public function __construct(array $data = [])
    {
        // Fluxo LocalAvatar validates the fixed, restricted option surface.

        $this->data = $data;
    }

    public function seed(): ?string
    {
        return $this->data['seed'] ?? null;
    }

    public function size(): ?int
    {
        return $this->data['size'] ?? null;
    }

    public function idRandomization(): ?bool
    {
        return $this->data['idRandomization'] ?? null;
    }

    public function title(): ?string
    {
        return $this->data['title'] ?? null;
    }


    public function flip(): array
    {
        return $this->asArray($this->data['flip'] ?? null);
    }


    public function fontFamily(): array
    {
        return $this->asArray($this->data['fontFamily'] ?? null);
    }


    public function fontWeight(): array
    {
        return $this->asArray($this->data['fontWeight'] ?? null);
    }


    public function scale(): ?array
    {
        return $this->toRange($this->data['scale'] ?? null);
    }


    public function borderRadius(): ?array
    {
        return $this->toRange($this->data['borderRadius'] ?? null);
    }


    public function rotate(): ?array
    {
        return $this->toRange($this->data['rotate'] ?? null);
    }


    public function translateX(): ?array
    {
        return $this->toRange($this->data['translateX'] ?? null);
    }


    public function translateY(): ?array
    {
        return $this->toRange($this->data['translateY'] ?? null);
    }


    public function tags(): array
    {
        return $this->tags ??= array_map(static function (string $token): array {
            $negated = str_starts_with($token, '!');
            $body = $negated ? substr($token, 1) : $token;
            $sep = strpos($body, ':');

            if ($sep === false) {
                return ['category' => $body, 'negated' => $negated];
            }

            return [
                'category' => substr($body, 0, $sep),
                'value' => substr($body, $sep + 1),
                'negated' => $negated,
            ];
        }, $this->asArray($this->data['tags'] ?? null));
    }


    public function componentVariant(string $name): ?array
    {
        $raw = $this->dynamic($name . 'Variant');

        if ($raw === null) {
            return null;
        }

        if (is_string($raw)) {
            return [$raw => 1];
        }

        if (is_array($raw) && array_is_list($raw)) {

            return array_fill_keys($raw, 1);
        }


        return $raw;
    }

    public function componentProbability(string $name): int|float|null
    {
        return $this->dynamic($name . 'Probability');
    }


    public function color(string $name): ?array
    {
        $raw = $this->dynamic($name . 'Color');

        return $raw === null ? null : $this->asArray($raw);
    }


    public function colorFill(string $name): array
    {
        return $this->asArray($this->dynamic($name . 'ColorFill'));
    }


    public function colorAngle(string $name): ?array
    {
        return $this->toRange($this->dynamic($name . 'ColorAngle'));
    }


    public function colorFillStops(string $name): ?array
    {
        return $this->toRange($this->dynamic($name . 'ColorFillStops'));
    }

    public function colorOrder(string $name): ?string
    {
        return $this->dynamic($name . 'ColorOrder');
    }


    private function dynamic(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }


    private function asArray(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }


    private function toRange(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return ['min' => $value, 'max' => $value];
        }

        if (is_array($value) && array_is_list($value) && count($value) > 0) {
            return ['min' => min($value), 'max' => max($value)];
        }

        return null;
    }
}
