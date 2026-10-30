<?php

declare(strict_types=1);

namespace Systopic\System\Controlmap;

/**
 * The actions an `actions.php` names — read with the tokenizer, never run.
 *
 * What counts is the action of the request, `$this->action` (or the legacy
 * `$action` / `$module->action` that PanelNode::runActions() hands over):
 *
 *   switch ($this->action) { case 'x': … }      the cases of that switch
 *                                               only, not of one nested in it
 *   $this->action === 'x', 'x' !== $action …    comparisons
 *   in_array($this->action, ['x', 'y'], …)       lists
 *
 * Files the actions include from their own folder are read as well, with a
 * literal name (`include 'actions.tags.php'`, `__DIR__ . '/x.php'`) — the
 * sidebar of cms/pages keeps its actions in four of them. An include from
 * another panel's folder belongs to that panel and is not followed.
 */
final class ActionScanner
{
    /** @return array<string, DiscoveredAction> name => action, in the order of the file */
    public static function scan(string $panelDir, string $file = 'actions.php'): array
    {
        $found = [];
        self::read(rtrim($panelDir, '/'), $file, $found, []);
        return $found;
    }

    /**
     * @param array<string, DiscoveredAction> $found
     * @param array<string, true>             $seen
     */
    private static function read(string $dir, string $file, array &$found, array $seen): void
    {
        $abs = $dir . '/' . $file;
        if (isset($seen[$file]) || !\fs::isFile($abs)) {
            return;
        }
        $seen[$file] = true;
        $tokens = Tokens::of(\fs::toNative($abs));
        $n = count($tokens->list);

        $add = static function (string $name, string $kind, int $line) use (&$found, $file): void {
            if ($name === '') {
                return;
            }
            $found[$name] ??= new DiscoveredAction($name);
            $found[$name]->add($kind, $file, $line);
        };

        $depth = 0;
        $switches = [];   // depth of the body of each open action switch
        for ($i = 0; $i < $n; $i++) {
            $t = $tokens->list[$i];

            if ($tokens->opens($i)) {
                $depth++;
                continue;
            }
            if ($t->text === '}') {
                if ($switches !== [] && end($switches) === $depth) {
                    array_pop($switches);
                }
                $depth--;
                continue;
            }

            if ($t->is(T_SWITCH) && $tokens->text($i + 1) === '(') {
                $end = $tokens->after($i + 1);
                if (self::isSubject($tokens->join($i + 2, $end - 1)) && $tokens->text($end) === '{') {
                    $switches[] = $depth + 1;
                }
                $i = $end - 1;
                continue;
            }

            if ($t->is(T_CASE) && $switches !== [] && end($switches) === $depth) {
                $name = $tokens->literal($i + 1);
                if ($name !== null) {
                    $add($name, 'case', $t->line);
                }
                continue;
            }

            if ($t->is(T_STRING) && strtolower($t->text) === 'in_array' && $tokens->text($i + 1) === '(') {
                $length = self::subjectAt($tokens, $i + 2);
                if ($length > 0 && $tokens->text($i + 2 + $length) === ',') {
                    $open = $i + 3 + $length;
                    if ($tokens->text($open) === '[') {
                        $close = $tokens->after($open) - 1;
                        for ($k = $open + 1; $k < $close; $k++) {
                            $name = $tokens->literal($k);
                            if ($name !== null) {
                                $add($name, 'list', $tokens->list[$k]->line);
                            }
                        }
                    }
                }
                continue;
            }

            if ($t->is([T_IS_IDENTICAL, T_IS_EQUAL, T_IS_NOT_IDENTICAL, T_IS_NOT_EQUAL])) {
                // the subject on the left, a literal on the right — or the other way round
                $name = $tokens->literal($i + 1);
                if ($name !== null && self::subjectEndsAt($tokens, $i - 1)) {
                    $add($name, 'compare', $t->line);
                    continue;
                }
                $name = $tokens->literal($i - 1);
                if ($name !== null && self::subjectAt($tokens, $i + 1) > 0) {
                    $add($name, 'compare', $t->line);
                }
                continue;
            }

            if ($t->is([T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE])) {
                $included = self::includedFile($tokens, $i + 1);
                if ($included !== null) {
                    self::read($dir, $included, $found, $seen);
                }
            }
        }
    }

    private static function isSubject(string $code): bool
    {
        return in_array($code, ['$this->action', '$action', '$module->action'], true);
    }

    /** How many tokens the subject takes at `$i` — 0 when it is not there. */
    private static function subjectAt(Tokens $tokens, int $i): int
    {
        if ($tokens->text($i) === '$action' && !in_array($tokens->text($i + 1), ['->', '[', '::'], true)) {
            return 1;
        }
        if (in_array($tokens->text($i), ['$this', '$module'], true)
            && $tokens->text($i + 1) === '->' && $tokens->text($i + 2) === 'action'
            && !in_array($tokens->text($i + 3), ['->', '[', '('], true)) {
            return 3;
        }
        return 0;
    }

    /** Does the subject end at `$i` (the token before an operator)? */
    private static function subjectEndsAt(Tokens $tokens, int $i): bool
    {
        if ($tokens->text($i) === '$action') {
            return !in_array($tokens->text($i - 1), ['->', '::'], true);
        }
        return $tokens->text($i) === 'action' && $tokens->text($i - 1) === '->'
            && in_array($tokens->text($i - 2), ['$this', '$module'], true);
    }

    /** A file of the same folder an include names literally; null for anything else. */
    private static function includedFile(Tokens $tokens, int $i): ?string
    {
        if ($tokens->text($i) === '(') {
            $i++;
        }
        $name = $tokens->literal($i);
        if ($name === null && $tokens->at($i)?->is(T_DIR) && $tokens->text($i + 1) === '.') {
            $name = $tokens->literal($i + 2);
            $name = $name === null ? null : ltrim($name, '/');
        }
        if ($name === null || $name === '' || str_contains($name, '..') || str_contains($name, '/') || str_contains($name, '\\')) {
            return null;
        }
        return $name;
    }
}
