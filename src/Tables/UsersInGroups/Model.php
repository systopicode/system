<?php

declare(strict_types=1);

namespace Systopic\System\Tables\UsersInGroups;

use Systopic\Db\TableRow;
use Systopic\System\Tables\Groups\Model as Group;
use Systopic\System\Tables\Users\Model as User;

/**
 * A group a user is in — `usm_users_in_groups`.
 *
 * Group and role are two questions, and the table layout has always said so: a
 * group is *who*, a role is *what may be done*, and `usm_roles_on_groups`
 * carries the read/write pair between them. This is the first of the three.
 */
final class Model extends TableRow
{
    public const TABLE        = 'users_in_groups';
    public const TABLE_PREFIX = 'usm';
    public const PREFIX       = 'uig';
    public const KEY          = ['user', 'group'];

    public ?User $user {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Group $group {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
