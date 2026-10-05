<?php

declare(strict_types=1);

namespace Systopic\System\Tables\UsersAtRoles;

use Systopic\Db\TableRow;
use Systopic\System\Tables\Roles\Model as Role;
use Systopic\System\Tables\Users\Model as User;

/**
 * A role a user holds — `usm_users_at_roles`.
 *
 * Belongs to user management, so `TABLE_PREFIX = 'usm'` like `User` and
 * `Role`: the same table in every installation, whoever uses it.
 *
 * The `uar_` column prefix stays, because renaming columns of a table that
 * carries 77 rows in `pwk_dev` is a migration for nothing. What does not
 * survive is deriving the column names *from* it — the design settled that in
 * section 7, and `pwk_colors_in_colors` is the proof: two sides of the same
 * table would both be called `color_id`. Here the property name decides, so
 * `$user` is `uar_user_id` and `$role` is `uar_role_id`.
 */
final class Model extends TableRow
{
    public const TABLE        = 'users_at_roles';
    public const TABLE_PREFIX = 'usm';
    public const PREFIX       = 'uar';
    public const KEY          = ['user', 'role'];

    public ?User $user {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Role $role {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
