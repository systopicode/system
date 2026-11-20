<?php

declare(strict_types=1);
namespace Systopic\System\Panels\Root\Admin\Tools\Logs\Email\View;

/**
 * The lightbox of one email log entry (`?index=`). Not a menu entry: without
 * this the email log counted it as a submenu and showed itself as a branch.
 */
class Panel extends \Systopic\System\Panels\PanelNode
{
    public bool $inNavi = false;
}
