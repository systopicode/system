<?php

declare(strict_types=1);

namespace Systopic\System\Debug;

/**
 * Debug trees as data — drawn by the debug tool's `boxTree` prompt
 * (packages/tools/debug/js/boxTreePrompt.js) as nested boxes.
 *
 *   p($referenceTree, $renderTree)->target('domLayersServer')->type('boxTree');
 *
 * Every argument is one root or a list of roots; all roots stand side by
 * side. A node:
 *
 *   label     string                what the box header reads
 *   marks     list<string>          how the box looks - css classes (diff type,
 *                                   'placeholder', 'replaced', 'selected' …)
 *   info      array<string, mixed>  the hover table; a value shaped like
 *                                   self::caller() becomes a syscoder link
 *   children  list<node>            nested boxes
 *
 * Why data and not html: the server tree (php) and the client tree (js) used
 * to be built twice, as two html fragments that had to agree by hand. Now
 * both hand over the same structure and one renderer draws it. And the
 * prompt's "copy" becomes JSON that can be read without a browser.
 */
final class BoxTree
{
    /**
     * One box.
     *
     * @param array<string, mixed>         $info
     * @param list<string|null|false>      $marks empty entries are dropped, so
     *                                            conditional marks can be written inline
     * @param list<array<string, mixed>>   $children
     * @return array{label: string, marks: list<string>, info: array<string, mixed>, children: list<array<string, mixed>>}
     */
    public static function node(string $label, array $info = [], array $marks = [], array $children = []): array
    {
        return [
            'label'    => $label,
            'marks'    => array_values(array_filter($marks, static fn($mark) => is_string($mark) && $mark !== '')),
            'info'     => $info,
            'children' => $children,
        ];
    }

    /**
     * An info value that opens a file in syscoder: path, file, line.
     * NULL stays NULL — the table then shows nothing for it.
     *
     * @return array{caller: string, path: string, file: string, line: int}|null
     */
    public static function caller(?string $path, ?string $file, int $line = 0): ?array
    {
        if ($file === null || $file === '') {
            return null;
        }
        return [
            // readable on its own, for the JSON copy - the rest is for the link
            'caller' => $file . ($line > 0 ? ':' . $line : ''),
            'path'   => (string) $path,
            'file'   => $file,
            'line'   => $line,
        ];
    }
}
