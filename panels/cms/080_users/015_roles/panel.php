<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Users\Roles;

use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;
use Systopic\System\Queries\UserAdmin\UserAdmin;
use Systopic\System\Tables\Roles\Model as Role;

/**
 * cms/users/roles — the role list. Editing one role is the lightbox.
 */
class Panel extends CmsPanel
{
    public $icon = 'fa-regular fa-user-tag';

    public function __construct($name, $parent = null)
    {
        parent::__construct($name, $parent);
        $this->label = 'Roles';
    }

    public UserAdmin $admin {
        get => UserAdmin::of($this->scope);
    }

    /** @var list<Role> */
    public array $roles {
        get => array_values($this->admin->roles);
    }

    public ?Role $role {
        get => $this->admin->role((int) (\http::dragdata('role_id') ?: \http::dataset('role_id')));
    }
}
