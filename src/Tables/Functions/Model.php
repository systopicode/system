<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Functions;

use Systopic\Db\TableRow;
use Systopic\Db\Type\Text;

/**
 * One thing a group is allowed to do — `usm_functions`.
 *
 * **The class cannot be called `Function`.** That is a reserved word in PHP,
 * so the name says whose functions these are. The table keeps its own name;
 * `TABLE` and the class are allowed to differ, and here they have to.
 *
 * The assignment to groups is `usm_functions_in_groups`, and it has a class:
 * `FunctionInGroup`, keyed on both its columns.
 */
final class Model extends TableRow
{
    public const TABLE        = 'functions';
    public const TABLE_PREFIX = 'usm';
    public const PREFIX       = 'function';

    public ?string $type {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $name = '' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Text $description {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
