<?php

declare(strict_types=1);

namespace Systopic\System\Tables\RolesOnGroups;

use Systopic\Db\TableRow;
use Systopic\System\Tables\Groups\Model as Group;
use Systopic\System\Tables\Roles\Model as Role;

/**
 * What a role may do on a group — `usm_roles_on_groups`.
 *
 * **The first junction with a payload**, and the one that settles the question
 * of whether a junction needs a class at all. `$read` and `$write` are not
 * decoration: in `pwk_dev` the table holds 40 rows, 13 of them with read and
 * 19 with write. A model that could only express pairs — Doctrine's
 * `ManyToMany`, and the shape this design nearly took — would have to leave
 * these two columns out and let somebody read them past the model.
 *
 * `$read` and `$write` are the column names, and they are not renamed to
 * `$canRead`/`$mayWrite`: the row says what it says.
 */
final class Model extends TableRow
{
    public const TABLE        = 'roles_on_groups';
    public const TABLE_PREFIX = 'usm';
    public const PREFIX       = 'rog';
    public const KEY          = ['role', 'group'];

    public ?Role $role {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Group $group {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    // ---- the payload -------------------------------------------------------

    public ?bool $read {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?bool $write {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
