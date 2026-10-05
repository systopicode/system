<?php

declare(strict_types=1);

namespace Systopic\System\Queries\TagGroups;

use Systopic\Db\Result\Completion;
use Systopic\Db\Schema\Provisioner;
use Systopic\Db\Schema\Registry;
use Systopic\Db\Scope;
use Systopic\System\Tables\Tags\Model as Tag;
use Systopic\System\Tables\TagGroups\Model as TagGroup;
use Systopic\System\Tables\TagsInNodes\Model as TagInNode;

/**
 * Loads every tag group with its tags.
 *
 * The successor of `tagGroups::createGroups()`. Three levels of separators get
 * unpacked from one packed column there:
 *
 *     $tagsData = explode(',', $result->tags_data);      // records
 *     $fieldsData = explode(';', $tagData);              // fields
 *     list($key, $value) = explode(':', $fieldData);     // key/value
 *     … and node_ids_spl once more with ' '
 *
 * Here they are three result sets in one roundtrip, every column out of a real
 * table, and the assignment runs over the foreign key the child row carries
 * anyway. No `explode`, no ordering anybody has to rely on, and no silent
 * truncation at 1024 bytes.
 *
 * The groups live in the scope, once per request. A write to any of the three
 * tables throws them away (`Scope::committed()`); the next `load()` reads
 * afresh. That replaced `forget()` and `linked()`.
 *
 * Why this lives here and not on `TagGroup`: loading is a verb, and `TagGroup`
 * is a row. The same split as with `Page` and `Tables\Pages\Rewritepaths` — the row
 * holds values, the neighbour does something with them.
 */
final class TagGroups
{
    /**
     * Every group with `$tags` filled in.
     *
     * @return list<TagGroup>
     */
    public static function load(Scope $scope): array
    {
        return $scope->objectFor(self::class, 'groups', static fn(): array => self::build($scope));
    }

    /** @return list<TagGroup> */
    private static function build(Scope $scope): array
    {
        Registry::register(TagGroup::class);
        Registry::register(Tag::class);
        Registry::register(TagInNode::class);

        // The first caller on the provisioning path. If a declared column has
        // gone missing from `cms_taggroups`, `cms_tags` or the junction, the
        // query fails with 1054, the provisioner adds it back from the
        // declaration and the query runs again — the panel notices nothing.
        //
        // Opt-in per query and not globally: during the rebuild a missing
        // column should stay loud everywhere it has not been thought through.
        $rows = $scope->rows(
            __DIR__ . '/TagGroups.sql', [], Completion::Group,
            provisioner: new Provisioner($scope->connection),
        );

        /** @var list<TagGroup> $groups */
        $groups = $rows->set(0)?->all(TagGroup::class) ?? [];
        /** @var list<Tag> $tags */
        $tags = $rows->set(1)?->all(Tag::class) ?? [];

        // The assignment tag -> node comes from the third result set, and it is
        // a row like any other now: `TagInNode`, keyed on both its columns.
        //
        // `relationId()` rather than `$link->tag`: both answer the same question,
        // but the property would go through the resolver, and for every tag
        // that is not in result set 2 that means a lazy ghost built to be
        // asked for its id and then dropped. The index being built here is an
        // index of ids.
        $nodesPerTag = [];
        foreach ($rows->set(2)?->all(TagInNode::class) ?? [] as $link) {
            $tagId  = $link->relationId('tag');
            $nodeId = $link->relationId('node');
            if ($tagId !== null && $nodeId !== null) {
                $nodesPerTag[$tagId][] = $nodeId;
            }
        }

        $tagsPerGroup = [];
        foreach ($tags as $tag) {
            $tag->nodeIds = $nodesPerTag[(int) $tag->id] ?? [];
            // The group sits in the row as an id; within the same execution it
            // is already there as an object, so it gets wired instead of loaded.
            $groupId = $tag->relationId('taggroup');
            if ($groupId !== null) {
                $tagsPerGroup[$groupId][] = $tag;
            }
        }

        foreach ($groups as $group) {
            $group->tags = $tagsPerGroup[(int) $group->id] ?? [];
            foreach ($group->tags as $tag) {
                // hydrate() instead of an assignment: the relation comes out of
                // the database, so it is not a change. Through the set hook it
                // would land in $modified, and the next save() would write back
                // what had just come from there.
                $tag->hydrate('taggroup', $group);
            }
        }

        return $groups;
    }

    /**
     * Tags that have a page of their own: tag id => node id.
     *
     * A page with a template starting `tagrelated` that carries a tag is that
     * tag's page. `tags::getRelatedNodesByTagIds()` in the legacy code.
     *
     * @return array<int, int>
     */
    public static function relatedNodes(Scope $scope, string $lang): array
    {
        $related = [];
        foreach ($scope->connection->all(
            (string) file_get_contents(__DIR__ . '/TagGroups.related.sql'),
            ['lang' => $lang, 'template' => 'tagrelated'],
        ) as $row) {
            $related[(int) $row['tin_tag_id']] = (int) $row['tin_node_id'];
        }
        return $related;
    }

    public static function byId(Scope $scope, int $id): ?TagGroup
    {
        foreach (self::load($scope) as $group) {
            if ((int) $group->id === $id) {
                return $group;
            }
        }
        return null;
    }

    public static function tagById(Scope $scope, int $id): ?Tag
    {
        foreach (self::load($scope) as $group) {
            foreach ($group->tags as $tag) {
                if ((int) $tag->id === $id) {
                    return $tag;
                }
            }
        }
        return null;
    }
}
