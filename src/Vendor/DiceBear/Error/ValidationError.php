<?php
// DiceBear PHP 10.6.0, MIT. Fluxo private namespace; see resources/avatars/dicebear/README.md.

declare(strict_types=1);

namespace Systopic\System\Vendor\DiceBear\Error;


class ValidationError extends \RuntimeException
{

    public readonly array $details;


    public function __construct(string $prefix, array $details)
    {
        $parts = [];

        foreach ($details as $detail) {
            $segments = [];

            if (isset($detail['instancePath']) && $detail['instancePath'] !== '') {
                $segments[] = $detail['instancePath'];
            }

            if (isset($detail['message']) && $detail['message'] !== '') {
                $segments[] = $detail['message'];
            }

            $parts[] = implode(' ', $segments);
        }

        parent::__construct($prefix . ': ' . implode(', ', $parts));
        $this->details = $details;
    }
}
