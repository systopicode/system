<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Cms\Users\Roles\Edit;

use Systopic\System\Panels\Root\Cms\Panel as CmsPanel;
use Systopic\System\Queries\UserAdmin\UserAdmin;
use Systopic\System\Tables\Roles\Model as Role;

/**
 * Lightbox for one role (`?role_id=`).
 */
class Panel extends CmsPanel
{
    public bool $inNavi = false;

    public UserAdmin $admin {
        get => UserAdmin::of($this->scope);
    }

    public ?Role $role {
        get => $this->admin->role((int) (\http::get('role_id') ?: \http::dataset('role_id')));
    }
}
