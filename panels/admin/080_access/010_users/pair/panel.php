<?php
declare(strict_types=1);
namespace Systopic\System\Panels\Root\Admin\Access\Users\Pair;
use Systopic\System\Panels\Root\Admin\Panel as AdminPanel;
use Systopic\System\Queries\UserAdmin\UserAdmin;
use Systopic\System\Tables\Users\Model as User;
class Panel extends AdminPanel {
    public bool $inNavi = false;
    public ?User $user {
        get {
            $user = UserAdmin::of($this->scope)->user((int) \http::get('user_id'));
            return $user?->type !== User::SCREEN ? $user : null;
        }
    }
}
