<?php

declare(strict_types=1);

namespace Systopic\System\Tables\Medias;

use DateTimeImmutable;
use Systopic\Db\Scope;
use Systopic\Db\TableRow;
use Systopic\System\Queries\MediaNodes\MediaNodes;
use Systopic\System\Tables\Nodes\Model as Node;
use Systopic\System\Tables\Users\Model as User;

/**
 * The media rows of a page: a new medium, its place in a group, its stamp.
 *
 * Stateless and it does not save — it returns what to persist, the caller
 * commits (orm-migration.md §1). The files belong to `Files`, deleting to
 * `Removal`.
 *
 * **Order is per group.** A page's media share one parent node; the library
 * shows them per group (`main`, `galleryLeft` …) and numbers each group
 * 2, 4, 6 on its own — so `node_ordering` repeats across groups, as it always
 * did. Placing a medium renumbers the group it leaves and the one it enters.
 * The legacy computed "before X" as `ordering(X) - 1`, which in an unsigned
 * column turned 0 - 1 into 0: the first place was a lottery.
 */
final class Operator
{
    public const NODE_TYPE = 'media';
    private const STEP = 2;

    /**
     * A new medium at the end of a group below a node.
     *
     * @return array{0: Model, 1: list<TableRow>} the media row, and what to persist
     */
    public static function create(Scope $scope, int $parentNodeId, string $group, ?int $userId): array
    {
        $node = new Node();
        $node->type = self::NODE_TYPE;
        $node->parentNode = Node::withId($parentNodeId);
        if ($userId !== null && $userId > 0) {
            $node->user = User::withId($userId);
        }

        $media = new Model();
        $media->node = $node;
        $media->group = $group;
        $media->createdDate = new DateTimeImmutable();
        if ($userId !== null && $userId > 0) {
            $media->createdUser = User::withId($userId);
        }

        $siblings = self::groupOf($scope, $parentNodeId, $group);
        $node->ordering = (count($siblings) + 1) * self::STEP;
        return [$media, [$node, $media]];
    }

    /**
     * Put a medium into a group below a node, right before or after another
     * medium, or at the end when neither is given.
     *
     * @return list<TableRow> what to persist
     */
    public static function placed(Scope $scope, Model $media, int $parentNodeId, string $group, ?int $beforeNodeId = null, ?int $afterNodeId = null): array
    {
        $node = $media->node;
        if ($node === null) {
            return [];
        }
        $fromParent = $node->relationId('parentNode');
        $fromGroup = (string) $media->group;

        // the target group without the medium, then the medium at its place
        $target = array_values(array_filter(
            self::groupOf($scope, $parentNodeId, $group),
            static fn(Model $m): bool => $m !== $media,
        ));
        $at = count($target);
        foreach ($target as $i => $m) {
            if ($beforeNodeId !== null && $m->relationId('node') === $beforeNodeId) {
                $at = $i;
                break;
            }
            if ($afterNodeId !== null && $m->relationId('node') === $afterNodeId) {
                $at = $i + 1;
                break;
            }
        }
        array_splice($target, $at, 0, [$media]);

        $changed = [];
        if ($fromParent !== $parentNodeId) {
            $node->parentNode = Node::withId($parentNodeId);
            $changed[] = $node;
        }
        if ($fromGroup !== $group) {
            $media->group = $group;
            $changed[] = $media;
        }
        $changed = [...$changed, ...self::renumber($target)];

        // the group it left closes the gap
        if ($fromParent !== null && ($fromParent !== $parentNodeId || $fromGroup !== $group)) {
            $left = array_values(array_filter(
                self::groupOf($scope, $fromParent, $fromGroup),
                static fn(Model $m): bool => $m !== $media,
            ));
            $changed = [...$changed, ...self::renumber($left)];
        }
        $unique = [];
        foreach ($changed as $row) {
            $unique[spl_object_id($row)] = $row;
        }
        return array_values($unique);
    }

    /** Stamp a change: date and user. */
    public static function touch(Model $media, ?int $userId): void
    {
        $media->modifiedDate = new DateTimeImmutable();
        if ($userId !== null && $userId > 0) {
            $media->modifiedUser = User::withId($userId);
        }
    }

    /**
     * The media of one group below a node, in their order — trashed ones too:
     * they keep their number, and renumbering around them would collide.
     *
     * @return list<Model>
     */
    public static function groupOf(Scope $scope, int $parentNodeId, string $group): array
    {
        $all = MediaNodes::underParents($scope, [$parentNodeId], true)[$parentNodeId] ?? [];
        return array_values(array_filter($all, static fn(Model $m): bool => (string) $m->group === $group
            && $m->node?->type === self::NODE_TYPE));
    }

    /**
     * 2, 4, 6 … in the order given.
     *
     * @param list<Model> $media
     * @return list<Node> the nodes whose ordering changed
     */
    private static function renumber(array $media): array
    {
        $changed = [];
        $ordering = 0;
        foreach ($media as $m) {
            $ordering += self::STEP;
            $node = $m->node;
            if ($node !== null && $node->ordering !== $ordering) {
                $node->ordering = $ordering;
                $changed[] = $node;
            }
        }
        return $changed;
    }
}
