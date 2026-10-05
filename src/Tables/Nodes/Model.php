<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Nodes;

use Systopic\Db\Schema\Col;
use Systopic\Db\TableRow;
use Systopic\System\Tables\Groups\Model as Group;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Users\Model as User;

/**
 * A node in the tree — `cms_nodes`.
 *
 * Deliberately the first class: it is small, it is the root of nearly every
 * relation, and it has three self-references that show whether the derivation
 * holds.
 *
 * Columns only, no logic. What hangs off `page::__get()` today in terms of
 * tree questions — children, parent, level, root — goes to
 * `Tables\Pages\Views\Family` in phase 5, not here.
 *
 * As a reminder why that is an improvement: `node_ordering`, `node_type` and
 * the remaining columns of this table do appear in `$page->fields` today, but
 * are **not retrievable** through `$page->…` — the access terminates the
 * request. Here they are ordinary typed properties.
 */
final class Model extends TableRow
{
    public const TABLE  = 'nodes';
    public const PREFIX = 'node';

    /** The parent in the tree. NULL for a root. */
    public ?Node $parentNode {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** Does this node point at another one? */
    public ?Node $redirectNode {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** Alias: the node this one descends from. */
    public ?Node $originalNode {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** Position among the siblings — never negative. */
    #[Col(unsigned: true)]
    public ?int $ordering = 0 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?string $type = null {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?bool $permissionsInherit = true {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /** A bit mask, 508 by default. Sixteen bits are more than enough. */
    #[Col(unsigned: true)]
    public ?int $permissions = 508 {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?User $user {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Group $group {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public bool $isTrashed = false {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    // ---- virtual: computed, not a column -----------------------------------

    public bool $isRoot { get => $this->parentNode === null; }

    public bool $isAlias { get => $this->originalNode !== null; }
}
