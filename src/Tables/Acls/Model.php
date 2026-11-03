<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Acls;

use Systopic\Db\Schema\Col;
use Systopic\Db\Schema\Index;
use Systopic\Db\TableRow;

/**
 * An access control list — `usm_acls`: a set of content groups.
 *
 * A row that wants rights (`cms_nodes`, later `pwk_patchworks` …) points at
 * one ACL instead of one group, so a document can belong to HR and to the
 * board at once. What a role may do on each group stays where it was, in
 * `usm_roles_on_groups`; the ACL only says *which* groups.
 *
 * **One row per set, and a set never changes.** Two documents in {HR, Board}
 * share the same ACL; giving one of them another group means pointing it at
 * another ACL (`Operator::forGroups()`), never editing the rows of this one —
 * the other document would change with it. That is also why there are few
 * ACLs: as many as combinations are in use, so the ids a user may read fit
 * into an `IN (…)` like the group ids before.
 *
 * The groups are in `usm_groups_in_acls` for the joins, and once more here as
 * `$groupIds`: the sorted ids, the unique key that finds a set again.
 */
final class Model extends TableRow
{
    public const TABLE        = 'acls';
    public const TABLE_PREFIX = 'usm';
    public const PREFIX       = 'acl';

    /** `3,7` — the group ids, sorted, comma-separated. Never empty: no group is NULL on the row, not an ACL. */
    #[Col(index: Index::Unique)]
    public ?string $groupIds = null {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }
}
