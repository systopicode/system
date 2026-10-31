<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear;

use Systopic\System\Vendor\DiceBear\Error\CircularColorReferenceError;
use Systopic\System\Vendor\DiceBear\Utils\Color as ColorUtil;


class Resolver
{
    private Style $style;
    private Options $options;
    private Prng $prng;

    private array $colorResolving = [];

    private array $result = [];

    private ?array $tagFilter = null;

    public function __construct(Style $style, Options $options)
    {
        $this->style = $style;
        $this->options = $options;
        $this->prng = new Prng($this->seed());
    }

    public function seed(): string
    {
        // Deliberately not memoized — the seed is the only input we keep out
        // of the {@see resolved} snapshot, so a serialized avatar never leaks it.
        return $this->options->seed() ?? '';
    }

    public function size(): ?int
    {
        return $this->memo('size', fn() => $this->options->size());
    }

    public function idRandomization(): bool
    {
        return $this->memo('idRandomization', fn() => $this->options->idRandomization() ?? false);
    }

    public function title(): ?string
    {
        return $this->memo('title', fn() => $this->options->title());
    }

    public function flip(): string
    {
        return $this->memo(
            'flip',
            fn() => $this->prng->pick('flip', $this->options->flip()) ?? 'none',
        );
    }

    public function fontFamily(): string
    {
        return $this->memo(
            'fontFamily',
            fn() => $this->prng->pick('fontFamily', $this->options->fontFamily()) ?? 'system-ui',
        );
    }

    public function fontWeight(): int|float
    {
        return $this->memo(
            'fontWeight',
            fn() => $this->prng->pick('fontWeight', $this->options->fontWeight()) ?? 400,
        );
    }

    public function scale(): float
    {
        return $this->memoFloat('scale', $this->options->scale(), 1.0);
    }

    public function borderRadius(): float
    {
        return $this->memoFloat('borderRadius', $this->options->borderRadius(), 0.0);
    }

    public function rotate(): float
    {
        return $this->memoFloat('rotate', $this->options->rotate(), 0.0);
    }

    public function translateX(): float
    {
        return $this->memoFloat('translateX', $this->options->translateX(), 0.0);
    }

    public function translateY(): float
    {
        return $this->memoFloat('translateY', $this->options->translateY(), 0.0);
    }


    public function variant(string $name): ?string
    {
        return $this->memo("{$name}Variant", function () use ($name) {
            $components = $this->style->components();
            $component = $components[$name] ?? null;

            if ($component === null || !$this->isVisible($name, $component)) {
                return null;
            }

            return $this->prng->weightedPick(
                "{$name}Variant",
                $this->variantWeights($component),
            );
        });
    }


    public function color(string $name): array
    {
        return $this->memo("{$name}Color", fn() => $this->resolveColor($name));
    }

    public function colorFill(string $name): string
    {
        return $this->memo(
            "{$name}ColorFill",
            fn() => $this->prng->pick("{$name}ColorFill", $this->options->colorFill($name)) ?? 'solid',
        );
    }

    public function colorAngle(string $name): float
    {
        return $this->memoFloat("{$name}ColorAngle", $this->options->colorAngle($name), 0.0);
    }

    public function colorOrder(string $name): string
    {
        // Deliberately not memoized: unlike colorFill this is no PRNG pick, so
        // it stays out of the {@see resolved} snapshot.
        return $this->options->colorOrder($name) ?? Options::COLOR_ORDER_RANDOM;
    }


    public function componentTransform(string $name): array
    {
        $components = $this->style->components();
        $component = $components[$name] ?? null;

        return [
            'rotate' => $this->memoFloat("{$name}Rotate", $component?->rotate(), 0.0),
            'translateX' => $this->memoFloat("{$name}TranslateX", $component?->translate()->x(), 0.0),
            'translateY' => $this->memoFloat("{$name}TranslateY", $component?->translate()->y(), 0.0),
            'scale' => $this->memoFloat("{$name}Scale", $component?->scale(), 1.0),
        ];
    }


    public function resolved(): array
    {
        return array_filter($this->result, static fn($v) => $v !== null);
    }


    private function probability(Style\Component $component): int|float
    {
        $raw = $this->options->componentProbability($component->sourceName());

        return $raw ?? $component->probability();
    }

    private function isVisible(string $name, Style\Component $component): bool
    {
        return $this->prng->bool("{$name}Probability", $this->probability($component));
    }


    private function variantWeights(Style\Component $component): array
    {
        $variants = $component->variants();
        $named = $this->options->componentVariant($component->sourceName());
        $weights = [];

        if ($named !== null) {
            $names = array_keys($named);
        } elseif (count($this->options->tags()) > 0) {
            $names = $this->tagFilteredNames($variants);
        } else {
            $names = array_keys($variants);
        }

        foreach ($names as $name) {
            if (isset($variants[$name])) {
                $weights[$name] = $named !== null ? $named[$name] : $variants[$name]->weight();
            }
        }

        return $weights;
    }


    private function tagFilter(): array
    {
        if ($this->tagFilter !== null) {
            return $this->tagFilter;
        }

        $allows = [];
        $bares = [];
        $disallows = [];
        $bareDisallows = [];

        foreach ($this->options->tags() as $token) {
            if ($token['negated']) {
                $value = $token['value'] ?? null;
                $disallows[] = ['category' => $token['category'], 'value' => $value];

                if ($value === null) {
                    $bareDisallows[$token['category']] = true;
                }
            } elseif (isset($token['value'])) {
                $allows[$token['category']][] = $token['value'];
            } else {
                $bares[] = $token['category'];
            }
        }

        $this->tagFilter = [
            'allows' => $allows,
            'bares' => array_values(array_unique($bares)),
            'disallows' => $disallows,
            'bareDisallows' => $bareDisallows,
        ];

        return $this->tagFilter;
    }


    private function tagFilteredNames(array $variants): array
    {
        ['allows' => $allows, 'bares' => $bares, 'disallows' => $disallows, 'bareDisallows' => $bareDisallows] = $this->tagFilter();

        // A bare token only binds where its category is in use, so this
        // narrowing — unlike the classification — is genuinely per-component.
        $required = [];

        foreach ($bares as $category) {
            if (isset($bareDisallows[$category])) {
                continue;
            }

            foreach ($variants as $variant) {
                if ($variant->hasTag($category)) {
                    $required[] = $category;
                    break;
                }
            }
        }

        $names = [];

        foreach ($variants as $name => $variant) {
            $allowed = true;

            foreach ($required as $category) {
                if (!$variant->hasTag($category)) {
                    $allowed = false;
                    break;
                }
            }

            if (!$allowed) {
                continue;
            }

            foreach ($allows as $category => $values) {
                if (!$variant->hasTag($category)) {
                    continue;
                }

                $matches = false;

                foreach ($values as $value) {
                    if ($variant->hasTag($category, $value)) {
                        $matches = true;
                        break;
                    }
                }

                if (!$matches) {
                    $allowed = false;
                    break;
                }
            }

            $disallowed = false;

            foreach ($disallows as $disallow) {
                if ($variant->hasTag($disallow['category'], $disallow['value'])) {
                    $disallowed = true;
                    break;
                }
            }

            if ($allowed && !$disallowed) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }


    private function resolveColor(string $name): array
    {
        $userColors = $this->options->color($name);
        $styleColors = $this->style->colors();
        $styleColor = $styleColors[$name] ?? null;
        $source = $userColors ?? $styleColor?->values() ?? [];

        $candidates = array_map(fn($c) => ColorUtil::toHex($c), $source);
        $fixed = $this->colorOrder($name) === Options::COLOR_ORDER_FIXED;
        $verbatim = $userColors !== null && $fixed;
        $fill = $this->colorFill($name);
        $stops = $fill === 'solid' ? 1 : $this->colorFillStops($name, $verbatim ? count($candidates) : 2);

        if ($styleColor === null) {
            return array_slice($this->order($name, $candidates, $fixed, $verbatim), 0, $stops);
        }

        // Detect circular references (e.g. a.contrastTo = b, b.contrastTo = a)
        if (in_array($name, $this->colorResolving, true)) {
            throw new CircularColorReferenceError(array_merge($this->colorResolving, [$name]));
        }

        $this->colorResolving[] = $name;
        $contrastTo = $styleColor->contrastTo();
        $notEqualTo = $styleColor->notEqualTo();

        try {
            if ($contrastTo !== null && !$verbatim) {
                $refColor = $this->color($contrastTo)[0] ?? null;

                if ($refColor !== null) {
                    $candidates = ColorUtil::sortByContrast($candidates, $refColor);
                }
            }

            if (count($notEqualTo) > 0) {
                $excluded = [];

                foreach ($notEqualTo as $ref) {
                    foreach ($this->color($ref) as $color) {
                        $excluded[] = $color;
                    }
                }

                $candidates = ColorUtil::filterNotEqualTo($candidates, $excluded);
            }
        } finally {
            array_pop($this->colorResolving);
        }

        // Skip shuffle when sorted by contrast to preserve the ordering
        $ordered = $contrastTo !== null
            ? $candidates
            : $this->order($name, $candidates, $fixed, $verbatim);

        return array_slice($ordered, 0, $stops);
    }


    private function order(string $name, array $candidates, bool $fixed, bool $verbatim): array
    {
        if (!$fixed) {
            return $this->prng->shuffle("{$name}Color", $candidates);
        }

        if ($verbatim) {
            return $candidates;
        }

        // Hex colors are ASCII, so a byte sort equals the UTF-16 code unit
        // sort the JS reference applies.
        // Deprecated: DiceBear 11 will take the palette in its definition
        // order here, the same verbatim rule as user-supplied colors, and
        // drop this sort (see CHANGELOG.md, "Deprecated").
        $unique = array_values(array_unique($candidates));
        sort($unique, SORT_STRING);

        return $unique;
    }

    private function colorFillStops(string $name, int $fallback): int
    {
        $range = $this->options->colorFillStops($name);

        return $range === null ? $fallback : $this->prng->integer("{$name}ColorFillStops", $range);
    }


    private function memoFloat(string $key, ?array $range, float $fallback): float
    {
        return $this->memo($key, fn() => $range === null ? $fallback : $this->prng->float($key, $range));
    }

    private function memo(string $key, callable $compute): mixed
    {
        if (array_key_exists($key, $this->result)) {
            return $this->result[$key];
        }

        $value = $compute();
        $this->result[$key] = $value;

        return $value;
    }
}
