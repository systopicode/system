<?php

declare(strict_types=1);

namespace Systopic\System\Controlmap;

/**
 * One action of a panel, as Discover read it off the code — every place
 * that names it.
 *
 * How it was found (`$kinds`):
 *   case     a `case 'x':` of the action switch (`switch ($this->action)`)
 *   compare  `$this->action === 'x'` and the like
 *   list     `in_array($this->action, ['x', …])`
 *   method   a public method of the panel class with `#[Action]`: app.php
 *            calls it before actions.php
 *
 * `$entry`: listed in the panel's `$entryActions` — may be opened from
 * outside, by GET (ActionGuard).
 */
final class DiscoveredAction
{
    /** @var list<string> */
    public array $kinds = [];

    public bool $entry = false;

    /** @var list<string> "file:line", relative to the panel folder */
    public array $places = [];

    public function __construct(public readonly string $name) {}

    public function add(string $kind, string $file, int $line): void
    {
        if (!in_array($kind, $this->kinds, true)) {
            $this->kinds[] = $kind;
        }
        $this->places[] = "$file:$line";
    }
}
