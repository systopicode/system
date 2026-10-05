<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Nodes;

use Systopic\Db\Scope;
use Systopic\System\Queries\NodeChildren\NodeChildren;

/**
 * What `cms_nodes` knows how to do, whichever query brought the rows.
 *
 * The row (`Model`) holds values; the views decide which rows belong
 * together. What is left over is table knowledge that does not depend on
 * either: how siblings are ordered, how they are renumbered, what "one place
 * up" means, how a node is hung under another one. The settings tree, the
 * page tree and the media tree all do that with nodes — they should not each
 * carry their own idea of how.
 *
 * Stateless on purpose. An operator works on the rows it is handed and
 * returns the ones it changed; saving them is the caller's decision, the same
 * rule the queries follow. Two kinds of method below: the first work on a
 * list the caller already has, the second read the siblings themselves
 * (`NodeChildren`, through the scope) — and still hand back what to save
 * rather than saving it.
 */
final class Operator
{
    /** Distance between two siblings — room for a later insert without renumbering. */
    public const STEP = 2;

    /**
     * Sibling order, as the queries sort: `node_ordering`, then `node_id`.
     *
     * A node without an id yet — built in this request, not saved — sorts
     * after every saved node with the same ordering, which is where the
     * AUTO_INCREMENT will put it.
     */
    public static function compare(Model $a, Model $b): int
    {
        return [(int) ($a->ordering ?? 0), $a->id ?? PHP_INT_MAX]
           <=> [(int) ($b->ordering ?? 0), $b->id ?? PHP_INT_MAX];
    }

    /**
     * Put a node into an ordered list of siblings where a fresh query would
     * put it.
     *
     * @template T
     * @param list<T> $list
     * @param T $item
     * @param \Closure(T): Model $nodeOf how to get from an entry to its node
     * @return list<T>
     */
    public static function insertSorted(array $list, mixed $item, \Closure $nodeOf): array
    {
        $mine = $nodeOf($item);
        $result = [];
        $placed = false;
        foreach ($list as $existing) {
            if (!$placed && self::compare($mine, $nodeOf($existing)) < 0) {
                $result[] = $item;
                $placed = true;
            }
            $result[] = $existing;
        }
        if (!$placed) {
            $result[] = $item;
        }
        return $result;
    }

    /**
     * One place up (-1) or down (+1) among its siblings.
     *
     * The successor of `node::reorderBranch()`. The legacy pushed the ordering
     * by 3 so the row would sort past its neighbour, read the branch back out
     * of the database and renumbered it — three steps because the list only
     * existed as a query. Here the siblings are an array, so the move is a
     * swap and the renumbering keeps it 2, 4, 6 as before.
     *
     * The push by 3 had a second problem worth not repeating: on the topmost
     * row it wrote -1 into `node_ordering`, which is `int unsigned`.
     *
     * @template T
     * @param list<T> $siblings in their current order
     * @param T $item the one to move
     * @param \Closure(T): Model $nodeOf how to get from an entry to its node
     * @return array{0: list<T>, 1: list<Model>} the new order, and the nodes
     *         whose ordering changed — empty when there is nowhere to move
     */
    public static function move(array $siblings, mixed $item, int $direction, \Closure $nodeOf): array
    {
        $from = array_search($item, $siblings, true);
        $to   = $from === false ? -1 : $from + $direction;
        if ($from === false || $to < 0 || $to >= count($siblings)) {
            return [$siblings, []];   // already at the end of the line
        }
        [$siblings[$from], $siblings[$to]] = [$siblings[$to], $siblings[$from]];

        return [$siblings, self::renumber(array_map($nodeOf, $siblings))];
    }

    /**
     * Put a node among siblings at a position — 0 is first — and renumber.
     *
     * The node may come from anywhere: another parent, a new row, or this
     * very list (then it moves). Setting its parent is the caller's business;
     * this is about the order only.
     *
     * @param list<Model> $siblings the children of the target parent, in order
     * @return list<Model> the nodes whose ordering changed
     */
    public static function place(array $siblings, Model $node, int $index): array
    {
        $others = array_values(array_filter($siblings, static fn(Model $s): bool => $s !== $node));
        $index = max(0, min($index, count($others)));
        array_splice($others, $index, 0, [$node]);
        return self::renumber($others);
    }

    /**
     * Into the trash, or out of it.
     *
     * @param list<Model> $nodes
     * @return list<Model> the ones that changed
     */
    public static function trash(array $nodes, bool $trashed = true): array
    {
        $changed = [];
        foreach ($nodes as $node) {
            if ($node->isTrashed !== $trashed) {
                $node->isTrashed = $trashed;
                $changed[] = $node;
            }
        }
        return $changed;
    }

    // ------------------------------------------------- on the tree itself
    //
    // The siblings come out of `NodeChildren`: every child of a parent with
    // the node's type, unfiltered — a hidden sibling keeps its ordering, and
    // renumbering around it would put two rows on one number.

    /**
     * Hang a node under a parent at a position, and say what that changed.
     *
     * The node may be new, may come from another parent, or may already be
     * a child here (then it moves). NULL as parent is the topmost level,
     * `PHP_INT_MAX` puts it last.
     *
     * @return list<Model> the node itself and every sibling whose ordering changed
     */
    public static function placed(Scope $scope, Model $node, ?Model $parent, int $index): array
    {
        $siblings = self::siblings($scope, $parent?->id, $node->type);
        $node->parentNode = $parent;
        $changed = self::place($siblings, $node, $index);
        return in_array($node, $changed, true) ? $changed : [$node, ...$changed];
    }

    /**
     * Put a node right before or after another one, under that one's parent.
     *
     * @return list<Model> what to save
     */
    public static function placedBeside(Scope $scope, Model $node, Model $target, bool $after): array
    {
        $parent = $target->parentNode;
        $others = array_values(array_filter(
            self::siblings($scope, $parent?->id, $node->type),
            static fn(Model $n): bool => $n !== $node,
        ));
        $at = array_search($target, $others, true);
        $at = ($at === false ? count($others) : (int) $at) + ($after ? 1 : 0);
        return self::placed($scope, $node, $parent, $at);
    }

    /**
     * A second place for a node's content: an alias under a parent.
     *
     * The alias is a node of the same type that names the original; it has
     * no row of its own in `cms_pages` or anywhere else. An alias of an alias
     * names the original's original — the content, not the place.
     *
     * @return list<Model> the alias and every sibling whose ordering changed
     */
    public static function aliased(Scope $scope, Model $original, ?Model $parent, int $index): array
    {
        $alias = new Model();
        $alias->type = $original->type;
        $alias->originalNode = Model::withId((int) ($original->relationId('originalNode') ?: $original->id));
        return self::placed($scope, $alias, $parent, $index);
    }

    /** The position of a node among the children of its parent. */
    public static function indexOf(Scope $scope, Model $node): int
    {
        $siblings = self::siblings($scope, $node->relationId('parentNode'), $node->type);
        $index = array_search($node, $siblings, true);
        return $index === false ? count($siblings) : (int) $index;
    }

    /** The position right after a node among the children of a parent; 0 when it is not there. */
    public static function indexAfter(Scope $scope, ?Model $parent, int $nodeId, ?string $type = null): int
    {
        foreach (self::siblings($scope, $parent?->id, $type) as $i => $sibling) {
            if ($sibling->id === $nodeId) {
                return $i + 1;
            }
        }
        return 0;
    }

    /**
     * Every child of a parent of one type, unfiltered, in sibling order —
     * the list a move renumbers.
     *
     * @return list<Model>
     */
    public static function siblings(Scope $scope, ?int $parentId, ?string $type): array
    {
        return NodeChildren::of($scope, $parentId, $type);
    }

    /**
     * 2, 4, 6 … in the order given.
     *
     * @param list<Model> $nodes
     * @return list<Model> the ones whose ordering actually changed
     */
    public static function renumber(array $nodes): array
    {
        $changed = [];
        $ordering = 0;
        foreach ($nodes as $node) {
            $ordering += self::STEP;
            if ($node->ordering !== $ordering) {
                $node->ordering = $ordering;
                $changed[] = $node;
            }
        }
        return $changed;
    }
}
