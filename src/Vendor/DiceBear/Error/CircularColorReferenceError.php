<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear\Error;


class CircularColorReferenceError extends \RuntimeException
{

    public readonly array $chain;


    public function __construct(array $chain)
    {
        $path = implode(' → ', $chain);

        parent::__construct("Circular color reference: {$path}");
        $this->chain = $chain;
    }
}
