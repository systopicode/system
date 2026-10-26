<?php

declare(strict_types=1);

namespace Systopic\System\Queries\TagGroups;

use Systopic\System\Tables\Tags\Model as Tag;
use Systopic\System\Tables\TagGroups\Model as TagGroup;

/**
 * The tag panel under cms/settings/tags/ — the view, not the data.
 *
 * The successor of `tagGroup::toSettingsPanel()` and `tag::toSettingsItem()`.
 * Why that does not stay on the row classes is in section 13 of the design:
 * **no verbs on shared nouns.** A tag is a row; that it looks one way in *this*
 * panel and another way in the sidebar is a property of the context, not of the
 * row.
 *
 * The test for it: would the method still make sense without the other type?
 * `$tag->name` yes. `$tag->toSettingsItem()` no — the word "settings" is
 * already in it.
 *
 * If a second view came along it would get a class of its own beside this one;
 * the row classes would stay untouched.
 */
final class TagGroupsPanel
{
    /**
     * Every group as a widget.
     *
     * @param list<TagGroup> $groups
     * @return list<mixed> html objects, the way the panel expects them
     */
    public static function render(array $groups): array
    {
        return array_map(self::group(...), $groups);
    }

    /** One group widget. */
    public static function group(TagGroup $group): mixed
    {
        return \html::create('div.widget.open#widget')
            ->data([
                'group'       => $group->entitiesName,
                'group_id'    => $group->id,
                'drop_accept' => 'tag',
                'on_drop'     => 'insertIntoGroup',
            ])
            ->append(self::header($group))
            ->get('widget')->append('main')->attr('onmousedown', 'event.stopPropagation()')
            ->append('div.row div.tagsRow')->foreach($group->tags, static function ($div, Tag $tag) {
                $div->append(self::item($tag));
            })->close()
            ->append('div.row input')->placeholder('Add tag')->data([
                'on_keyup' => 'enterNewTag',
            ]);
    }

    /** One tag inside the widget. */
    public static function item(Tag $tag): mixed
    {
        $el = \html::create('div.tag.active')
            ->style('--tag-color', $tag->colorStyle !== '' ? $tag->colorStyle : '#000')
            ->style('background-color', $tag->colorStyle)
            ->style('border-color', $tag->colorStyle)
            ->click('edit')
            ->text($tag->name)
				->data([
					'drag_type'   => 'tag',
					'drop_accept' => 'tag',
					'on_drop'     => 'join',
					'id'          => $tag->id,
					'label'       => $tag->name,
				])
				->append('div.sort.h.insertBefore')->data([
					'drop_accept' => 'tag',
					'on_drop'     => 'insertTagBefore',
					'id'          => $tag->id,
				])->close()
				->append('div.sort.h.insertAfter')->data([
					'drop_accept' => 'tag',
					'on_drop'     => 'insertTagAfter',
					'id'          => $tag->id,
				])->close();

        // A tag that hangs on no page may go; one with usages shows their
        // count.
        //
        // Deliberately with if/else instead of the legacy ->if() chain: there
        // the condition only applies to the next ->append(), and the following
        // ->text() runs regardless — which put a bare "0" into the markup for
        // the number zero.
        if ($tag->nodeCount > 0) {
            $el->append('number')->text($tag->nodeCount);
        } else {
            $el->append('i.far.fa-trash')->click('delete')->data(['label' => $tag->name]);
        }
        return $el;
    }

    // ------------------------------------------------ the page sidebar

    /**
     * Every group with its tags, for one page — `tags::toPagePanel()`.
     *
     * A click toggles the tag on the page; a tag that has a page of its own
     * (template `tagrelated…`) gets an arrow to it.
     *
     * @param list<TagGroup> $groups
     * @param list<int> $active the tag ids the page carries — `TagLinks::tagIdsOf()`,
     *        not `$tag->nodeIds`, which only knows published pages
     * @param array<int, int> $related tag id => node id of its page
     * @param string $action path of the sidebar actions, relative to the panel
     *        that is on screen (`sidebar/` on a page, `../../sidebar/` in the
     *        file editor)
     * @param string $pageBase href of a tag's own page, same idea
     */
    public static function forPage(array $groups, int $nodeId, array $active, array $related = [], string $action = 'sidebar/', string $pageBase = '?id=', string $query = ''): mixed
    {
        return \html::create('div')->foreach($groups, static function ($div, TagGroup $group) use ($nodeId, $active, $related, $action, $pageBase, $query) {
            $div->append('div.taggroup h6.taggroup')->text($group->name)->close()
                ->append('div.tags')
                ->foreach($group->tags, static function ($div, Tag $tag) use ($nodeId, $active, $related, $action, $pageBase, $query) {
                    $div->append(self::pageItem($tag, $nodeId, in_array((int) $tag->id, $active, true), $related[(int) $tag->id] ?? null, $action, $pageBase, $query));
                })
                ->close()
                ->append('form.tagAdd')->attr('method', 'put')->attr('action', $action . 'addTag' . $query)
                ->append('input[name=name]')->placeholder('Add tag')->attr('autocomplete', 'off')->close()
                ->append('input[type=hidden][name=group_id]')->value((string) $group->id)->close()
                ->append('input[type=hidden][name=node_id]')->value((string) $nodeId);
        });
    }

    /** One tag in the page sidebar — `tag::toPagePanelItem()`. */
    public static function pageItem(Tag $tag, int $nodeId, bool $active, ?int $relatedNodeId = null, string $action = 'sidebar/', string $pageBase = '?id=', string $query = ''): mixed
    {
        $el = \html::create('div.tag')
            ->if($active)->class('active')
            ->style('background-color', $tag->colorStyle)
            ->style('border-color', $tag->colorStyle)
            ->style('color', $tag->colorStyle)
            ->click($action . ($active ? 'removeTagFrom_page' : 'addTagTo_page') . $query)
            ->text($tag->label)
            ->data([
                'id'     => $nodeId,
                'tag_id' => $tag->id,
                'label'  => $tag->name,
            ]);
        if ($relatedNodeId !== null) {
            $el->append('a.ajax')->href($pageBase . $relatedNodeId)
                ->style('color', $tag->colorStyle)
                ->append('i.fa-regular.fa-arrow-turn-down-right');
        }
        return $el;
    }

    /** Title, rename, colour, and delete when the group is empty. */
    private static function header(TagGroup $group): mixed
    {
        $header = \html::create('header');
        $header->append('h1')->doc('groupName')->text($group->name);
        $header->append('button.tagGroupRename')->attr('type', 'button')->title('Rename')
            ->data(['on_click' => 'rename'])
            ->append('i.fa-regular.fa-pen');
        $header->append('input.tagGroupColor[type=color][name=color]')
            ->value($group->colorStyle !== '' ? $group->colorStyle : '#888888')
            ->title('Colour')
            ->data(['on_input' => 'updateColor']);
        if ($group->tags === []) {
            $header->append('button.tagGroupDelete')->attr('type', 'button')->title('Delete group')
                ->data(['on_click' => 'deleteGroup'])
                ->append('i.fa-regular.fa-trash');
        }
        return $header;
    }
}
