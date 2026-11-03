<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear;


class Avatar
{
    private string $svg;

    private array $resolvedOptions;


    public function __construct(mixed $styleInput, array $optionsInput = [])
    {
        if (!$styleInput instanceof Style) {
            trigger_error(
                'Passing a style definition to ' . self::class . ' is deprecated and '
                    . 'will be removed in v11. Wrap it in a Style first: '
                    . 'new Avatar(new Style($definition), $options).',
                E_USER_DEPRECATED,
            );
        }

        $style = $styleInput instanceof Style ? $styleInput : new Style($styleInput);
        $options = new Options($optionsInput);
        $resolver = new Resolver($style, $options);

        $this->svg = (new Renderer($style, $resolver))->render();
        $this->resolvedOptions = $resolver->resolved();
    }

    public function __toString(): string
    {
        return $this->svg;
    }


    public function toString(): string
    {
        return $this->svg;
    }


    public function toJSON(): array
    {
        return [
            'svg' => $this->svg,
            'options' => $this->resolvedOptions,
        ];
    }


    public function toDataUri(): string
    {
        $encoded = strtr(rawurlencode($this->svg), [
            '%21' => '!',
            '%2A' => '*',
            '%27' => "'",
            '%28' => '(',
            '%29' => ')',
        ]);

        return 'data:image/svg+xml;charset=utf-8,' . $encoded;
    }
}
