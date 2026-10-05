<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Tags;

use Systopic\Db\TableRow;
use Systopic\System\Tables\TagGroups\Model as TagGroup;

/**
 * A tag — `cms_tags`.
 *
 * `$taggroup` is called that and not `$group`: the column is
 * `tag_taggroup_id`, and `Group` already means the user group in this
 * namespace. Two different things must not carry the same name just because
 * both are called "group".
 */
final class Model extends TableRow
{
    public const TABLE  = 'tags';
    public const PREFIX = 'tag';

    public ?TagGroup $taggroup {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * Nullable, because the column is — `varchar(255) NULL`, and the legacy
     * reference row with the id 1 has NULL in it.
     *
     * Declared `string` until now, and it went unnoticed because nothing ever
     * loaded that row: `TagGroups.sql` filters `tag_id != 1`. As soon as
     * `TagInNode` became a row of its own, a join from the junction to its tag
     * reached row 1 and the assignment failed with a TypeError before anything
     * could report it.
     */
    public ?string $name = '' {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * The nodes this tag hangs on.
     *
     * Not a column — the assignment lives in cms_tags_in_nodes. The loader
     * fills it from the third result set of TagGroups.sql.
     *
     * The legacy code solves it via node_ids_spl: a space-separated list
     * inside a GROUP_CONCAT column that itself sits inside a GROUP_CONCAT
     * column. Here they are simply numbers.
     *
     * @var list<int>
     */
    public array $nodeIds = [];

    // ---- virtual: computed, not a column -----------------------------------

    public int $nodeCount { get => count($this->nodeIds); }

    public string $label { get => $this->name ?? ''; }

    /** The colour comes from the group — a tag has none of its own. */
    public string $colorStyle { get => $this->taggroup?->colorStyle ?? ''; }
}
