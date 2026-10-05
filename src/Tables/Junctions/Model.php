<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Junctions;

use Systopic\Db\Schema\Col;
use Systopic\Db\TableRow;
use Systopic\Db\Type\Decimal;
use Systopic\System\Tables\Nodes\Model as Node;

/**
 * A link between two nodes, with an optional value — `cms_junctions`.
 *
 * The table is easy to mistake for the settings table, because that is what
 * reads it most: `pageSettings` builds its inherited tree out of
 * `cms_settings` and this. But both columns point at `cms_nodes`, and the
 * numbers say the general case is the real one. Measured in `bad_endbach_de`,
 * 1232 rows:
 *
 *     left  (junction_node_id)           page 1082 · medias 8 · setting 5
 *     right (junction_setting_node_id)   setting 1146 · page 6
 *     no value at all                    753 of 1232   (61 %)
 *
 * So: mostly page-to-setting, but not only; and in three cases out of five
 * there is no value, which makes the row a **selection** rather than a
 * setting — "these 99 nodes are linked to the node `image`". Page settings are
 * one use of this table, not what it is.
 *
 * Hence the name. `Junction` alone would say nothing (every junction is one),
 * and `SettingValue` would name the use instead of the thing.
 *
 * **Two foreign keys onto the same table**, like `pwk_colors_in_colors` and
 * like `Node` itself with its three self-references. This is exactly the case
 * where deriving column names from the table name breaks down and deriving
 * them from the property name does not: `$node` and `$settingNode` give
 * `junction_node_id` and `junction_setting_node_id`.
 *
 * The three value columns keep their names, and the properties are named after
 * them rather than after what somebody stores in them. `$varchar` is an ugly
 * name for a value — and it is the honest one: which of the three is meant is
 * decided by `Setting::$datatype` on the other side, so this row genuinely
 * does not know what its value means.
 */
final class Model extends TableRow
{
    public const TABLE  = 'junctions';
    public const PREFIX = 'junction';
    public const KEY    = ['node', 'settingNode'];

    /** The node the link starts at — a page, as a rule. */
    public ?Node $node {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * The node it points at — the setting's node, as a rule.
     *
     * Not a `Setting`: the column holds a `node_id`, and `cms_settings` is
     * keyed on `setting_id` with `setting_node_id` beside it. The link goes
     * through the tree, which is also how the inheritance along
     * `node_parent_node_id` can work at all.
     */
    public ?Node $settingNode {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    // ---- the value, in one of three columns --------------------------------

    public ?string $varchar = null {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    public ?int $int = null {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    /**
     * `decimal(18,2)` — money, and therefore not a `float`.
     *
     * The declared precision is the column's, not a preference: 18 digits with
     * 2 after the point is what the table has.
     */
    #[Col(length: 18, scale: 2)]
    public ?Decimal $decimal {
        get => $this->__get__(__PROPERTY__);
        set => $this->__set__(__PROPERTY__, $value);
    }

    // ---- virtual: computed, not a column -----------------------------------

    /** Does this link carry anything, or is it a plain assignment? */
    public bool $hasValue {
        get => $this->varchar !== null || $this->int !== null || $this->decimal !== null;
    }
}
