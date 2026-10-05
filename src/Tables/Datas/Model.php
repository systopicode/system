<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Datas;

use Systopic\Db\TableRow;
use Systopic\Db\Type\BigInt;
use Systopic\Db\Type\Text;

/**
 * A short-lived scrap of data — `tmp_datas`.
 *
 * `TABLE_PREFIX = 'tmp'` and it means it: nothing in here has to survive, and
 * anything reading it should cope with it being gone. The row is a key/value
 * pair with a type and a timestamp, which is what a cache looks like when it
 * lives in a table.
 *
 * `$timestamp` is a `BigInt` rather than `?int`, and that is not pedantry:
 * `int` maps to a 32-bit column, and a millisecond timestamp passes that
 * ceiling in 1970. PHP's own `int` cannot make the distinction — which is
 * exactly when a type class is the answer.
 */
final class Model extends TableRow
{
    public const TABLE        = 'datas';
    public const TABLE_PREFIX = 'tmp';
    public const PREFIX       = 'data';

    public ?string $type {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Text $string {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?BigInt $timestamp {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $key {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
