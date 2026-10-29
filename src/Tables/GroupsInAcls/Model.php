<?php

declare(strict_types=1);

namespace Systopic\System\Tables\GroupsInAcls;

use Systopic\Db\TableRow;
use Systopic\System\Tables\Acls\Model as Acl;
use Systopic\System\Tables\Groups\Model as Group;

/**
 * A group in an ACL — `usm_groups_in_acls`.
 *
 * What the rights query joins against `usm_roles_on_groups`. Written once,
 * together with its ACL, and never changed afterwards: see `Acls\Model`.
 */
final class Model extends TableRow
{
    public const TABLE        = 'groups_in_acls';
    public const TABLE_PREFIX = 'usm';
    public const PREFIX       = 'gia';
    public const KEY          = ['acl', 'group'];

    public ?Acl $acl {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Group $group {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
