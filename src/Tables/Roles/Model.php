<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Roles;

use Systopic\Db\Schema\Col;
use Systopic\Db\Schema\Index;
use Systopic\Db\TableRow;
use Systopic\Db\Type\Text;

/**
 * A role — `usm_roles`.
 *
 * Belongs to user management, like `User` and `Group`, and carries
 * `TABLE_PREFIX = 'usm'` for it.
 *
 * Role and group are two different questions and the table layout says so:
 * a group is *who*, a role is *what may be done*, and `usm_roles_on_groups`
 * holds the read/write pair that connects them — `RoleOnGroup`, a row like any
 * other since the key may be a tuple.
 */
final class Model extends TableRow
{
    public const TABLE        = 'roles';
    public const TABLE_PREFIX = 'usm';
    public const PREFIX       = 'role';

    /** Looked up by name, and indexed for it in the database already. */
    #[Col(index: Index::Plain)]
    public ?string $name {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $icon {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?string $color {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Text $description {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * What code and configs call the role (`Session::hasRole('cmsEditor')`,
     * the controlmap): it says which role it is, the name is only its label
     * and may change. The system's own roles have theirs from
     * `Operator::BUILTIN` and `Operator::SYSTEM`; a role somebody made gets
     * one from its name (`Operator::keyFrom()`).
     */
    #[Col(length: 32, index: Index::Unique)]
    public ?string $key = null {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    // ---- virtual: computed, not a column -----------------------------------

    /** Held by every request on its own (`Operator::BUILTIN`): not assigned to a user. */
    public bool $isBuiltin { get => isset(Operator::BUILTIN[(string) $this->key]); }

    /** One of the system's own roles: its key is fixed, and it is not to be deleted. */
    public bool $isSystem { get => $this->isBuiltin || isset(Operator::SYSTEM[(string) $this->key]); }
}
