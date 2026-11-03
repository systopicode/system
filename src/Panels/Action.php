<?php

declare(strict_types=1);

namespace Systopic\System\Panels;

/**
 * A panel method that a request may call by its name, as an action.
 *
 *     #[Action]
 *     public function refresh(): void { … }
 *
 * app.php calls a method of the action's name only when it carries this
 * attribute. Without it, a public method is a helper for the templates —
 * before, every public method of the panel class was callable from the
 * address bar (`cms/backup/removeOldMediaDir`). Actions in `actions.php`
 * need nothing.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Action
{
}
