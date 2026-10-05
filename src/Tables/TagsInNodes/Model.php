<?php

declare(strict_types=1);

namespace Systopic\System\Tables\TagsInNodes;

use Systopic\Db\TableRow;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Tags\Model as Tag;

/**
 * A tag on a node — `cms_tags_in_nodes`.
 *
 * The first junction in the new model, and the reason the key became a tuple.
 * Until now this table was read as **loose values** in `Queries\TagGroups\TagGroups`: the
 * third result set of `TagGroups.sql` has no class, so the row reader hands
 * it over as raw columns and the assignment is put together by hand.
 *
 * That worked, and it was the one place in the new model where a table was
 * handled by a mechanism of its own. Now it is a row like any other — it can be
 * read, written, removed, and it turns up in `db audit` with everything else.
 *
 * **Both sides are relations, and together they are the key.** There is no
 * `tin_id` and there never was; `PRIMARY KEY (tin_tag_id, tin_node_id)` is
 * what the table has had all along, and it is also the guarantee that a tag
 * cannot be hung on the same node twice.
 */
final class Model extends TableRow
{
    public const TABLE  = 'tags_in_nodes';
    public const PREFIX = 'tin';
    public const KEY    = ['tag', 'node'];

    public ?Tag $tag {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?Node $node {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
