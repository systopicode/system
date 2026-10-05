<?php

declare(strict_types=1);

namespace Systopic\System\Tables\FunctionsInGroups;

use Systopic\Db\TableRow;
use Systopic\System\Tables\Groups\Model as Group;
use Systopic\System\Tables\Functions\Model as UserFunction;

/**
 * Something a group is allowed to do — `usm_functions_in_groups`.
 *
 * `$function` is a property name, not a class name, so the reserved word is no
 * problem here — which is why the column can keep being `fig_function_id`
 * while the class on the other side has to be called `UserFunction`.
 */
final class Model extends TableRow
{
    public const TABLE        = 'functions_in_groups';
    public const TABLE_PREFIX = 'usm';
    public const PREFIX       = 'fig';
    public const KEY          = ['function', 'group'];

    public ?UserFunction $function {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Group $group {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
