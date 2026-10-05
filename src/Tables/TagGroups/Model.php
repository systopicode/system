<?php

declare(strict_types=1);

namespace Systopic\System\Tables\TagGroups;

use Systopic\Db\Schema\Col;
use Systopic\Db\TableRow;
use Systopic\System\Tables\Tags\Model as Tag;

/**
 * A tag group — `cms_taggroups`.
 *
 * The first class that does not merely rebuild but improves something:
 * `tagGroups.sql` today packs all tags of a group together with their node ids
 * into **one** column, using three separators that dodge each other. The
 * replacement file `TagGroups.sql` returns three result sets instead —
 * every column out of a real table, the assignment over the foreign key the
 * child row carries anyway.
 */
final class Model extends TableRow
{
    public const TABLE  = 'taggroups';
    public const PREFIX = 'taggroup';

    public string $name = '' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * A hex colour, `26b556` or `#26b556` — never longer.
     *
     * The length is declared because `string` cannot say it: without `#[Col]`
     * this would claim `varchar(255)` while the real column is `varchar(8)`,
     * and a 40 character value would pass the check here only to make the
     * provisioner widen the column on the way out.
     */
    #[Col(length: 8)]
    public ?string $color = null {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
    public ?bool $isPersistent = false {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * The tags of this group.
     *
     * No hooks, so by the rule in TableRow **not a column** — just like $id.
     * It gets filled by the loader (Queries\TagGroups\TagGroups), which assigns the
     * second result set of TagGroups.sql over the foreign key.
     *
     * Later a `$this->hasMany(...)` in a get hook will take that over; until
     * then the assignment sits in one place instead of in the class, and that
     * is more honest than a hook that secretly fetches.
     *
     * @var list<Tag>
     */
    public array $tags = [];

    // ---- virtual: computed, not a column -----------------------------------

    /** The name as a human is meant to read it. */
    public string $label { get => \str::name2label($this->name); }
    public string $entitiesName { get => htmlentities($this->name); }
    public string $colorStyle { get => $this->color ? '#' . ltrim($this->color, '#') : ''; }

    /** @return list<int> */
    public array $tagIds { get => array_map(static fn(Tag $t): int => (int) $t->id, $this->tags); }
}
