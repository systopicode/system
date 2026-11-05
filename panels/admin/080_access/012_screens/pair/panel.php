<?php

declare(strict_types=1);

namespace Systopic\System\Panels\Root\Admin\Access\Screens\Pair;

use Systopic\System\Panels\Root\Admin\Panel as CmsPanel;
use Systopic\System\Queries\UserAdmin\UserAdmin;
use Systopic\System\Tables\Users\Model as User;

/**
 * Pairing one screen (`?user_id=`), apart from editing it: the right
 * `authScreens` is enough here, the lightbox of `edit` takes `cmsAdmin`. The
 * screen is settled by the row that was clicked, so nobody chooses an account.
 * The row has two actions: `&way=scan` takes the device code of a screen,
 * without it an auth code is made.
 */
class Panel extends CmsPanel
{
    public bool $inNavi = false;

    public ?User $screen {
        get {
            $user = UserAdmin::of($this->scope)->user((int) (\http::get('user_id') ?: \http::dataset('user_id') ?: \http::post('user_id')));
            return $user?->type === User::SCREEN ? $user : null;
        }
    }

    public bool $scanning {
        get {
            return \http::get('way') === 'scan' || $this->action === 'approveDeviceCode';
        }
    }

    /** The code made in this request: ['code' => …, 'expires' => ts]. */
    public ?array $issuedCode = null;
}
