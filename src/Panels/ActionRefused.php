<?php

declare(strict_types=1);

namespace Systopic\System\Panels;

/**
 * An action the request named was not run — thrown by `ActionGuard`, caught
 * in app.php: the debug output shows it (`p()`), the log has it, the user
 * gets a message and a 403, and the panel renders as without the action.
 */
final class ActionRefused extends \RuntimeException
{
    public function __construct(
        public readonly string $panelPath,
        public readonly string $action,
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }

    /** From another site — a link, a form or a script there (CSRF). */
    public static function foreign(PanelNode $panel, string $action, string $evidence): self
    {
        return new self(
            $panel->getPath(), $action, 'origin',
            "Action '$action' of {$panel->getPath()} refused: the request did not come from this site ($evidence).",
        );
    }

    /** The panel's actionGranted() said no. */
    public static function notGranted(PanelNode $panel, string $action): self
    {
        return new self(
            $panel->getPath(), $action, 'granted',
            "Action '$action' of {$panel->getPath()} refused: actionGranted() says no.",
        );
    }

    /**
     * A public method of the action's name, but not marked as an action —
     * and nothing else runs it. `$inherited`: a method of the panel
     * framework (updateView …), never an action.
     */
    public static function notAnAction(PanelNode $panel, string $action, \ReflectionMethod $method, bool $inherited): self
    {
        $where = $method->getDeclaringClass()->getName() . '::' . $method->getName() . '()';
        return new self(
            $panel->getPath(), $action, 'attribute',
            $inherited
                ? "Action '$action' of {$panel->getPath()} refused: $where belongs to the panel framework, it is not an action."
                : "Action '$action' of {$panel->getPath()} refused: $where has no #[Action] attribute (Systopic\System\Panels\Action), and actions.php does not name '$action'. Add #[Action] to the method to call it as an action.",
        );
    }

    /** What the user is told. */
    public function userMessage(): string
    {
        return match ($this->reason) {
            'origin' => 'This action was not run: the request did not come from this site.',
            'attribute' => 'This action does not exist.',
            default => 'You may not run this action.',
        };
    }
}
