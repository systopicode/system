<?php

declare(strict_types=1);

namespace Systopic\System\Config;

/**
 * Where a key of a JSON text stands: its line. json_decode() forgets that,
 * so the text is walked once more, without decoding anything: keys of
 * objects and the positions of list items, each with the line it starts on.
 *
 *   JsonLines::line($text, ['packages', 'system', 'debug']) → 12
 *
 * A key path is a list, not a dotted string: keys may contain dots (`ini`
 * takes `session.cookie_lifetime`). List items count from 0, as strings.
 */
final class JsonLines
{
    private const SEP = "\x1F";

    /** @var array<string, array<string, int>> text hash => path => line */
    private static array $scanned = [];

    /** @param list<string|int> $keys */
    public static function line(string $json, array $keys): ?int
    {
        $map = self::$scanned[md5($json)] ??= self::scan($json);
        return $map[implode(self::SEP, array_map('strval', $keys))] ?? null;
    }

    /**
     * The first of several key paths that the text has: a file in the old
     * layout keeps a key where the new one does not.
     *
     * @param list<list<string|int>> $candidates
     */
    public static function first(string $json, array $candidates): ?int
    {
        foreach ($candidates as $keys) {
            $line = self::line($json, $keys);
            if ($line !== null) {
                return $line;
            }
        }
        return null;
    }

    /**
     * Every key and list item of a JSON text, with its line. Broken JSON
     * gives what was readable up to the break.
     *
     * @return array<string, int> keys joined by \x1F => line, 1 based
     */
    public static function scan(string $json): array
    {
        $lines = [];
        /** @var list<array{type: string, at: string|null, await: bool}> $stack */
        $stack = [];
        $line = 1;
        $length = strlen($json);
        $path = static function () use (&$stack): string {
            return implode(self::SEP, array_map(static fn(array $level): string => (string) $level['at'], $stack));
        };
        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            if ($char === "\n") {
                $line++;
                continue;
            }
            if ($char === ' ' || $char === "\t" || $char === "\r") {
                continue;
            }
            $top = count($stack) - 1;
            // a list item starts here
            if ($top >= 0 && $stack[$top]['type'] === '[' && $stack[$top]['await'] && $char !== ']') {
                $stack[$top]['await'] = false;
                $stack[$top]['at'] = (string) ($stack[$top]['at'] === null ? 0 : (int) $stack[$top]['at'] + 1);
                $lines[$path()] = $line;
            }
            switch ($char) {
                case '"':
                    $start = $i + 1;
                    for ($i = $start; $i < $length && $json[$i] !== '"'; $i++) {
                        if ($json[$i] === '\\') {
                            $i++;
                        }
                    }
                    if ($top >= 0 && $stack[$top]['type'] === '{' && $stack[$top]['await']) {
                        $key = json_decode('"' . substr($json, $start, $i - $start) . '"');
                        $stack[$top]['at'] = is_string($key) ? $key : substr($json, $start, $i - $start);
                        $stack[$top]['await'] = false;
                        $lines[$path()] = $line;
                    }
                    break;
                case '{':
                case '[':
                    $stack[] = ['type' => $char, 'at' => null, 'await' => true];
                    break;
                case '}':
                case ']':
                    array_pop($stack);
                    break;
                case ',':
                    if ($top >= 0) {
                        $stack[$top]['await'] = true;
                    }
                    break;
            }
        }
        return $lines;
    }
}
