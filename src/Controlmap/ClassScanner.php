<?php

declare(strict_types=1);

namespace Systopic\System\Controlmap;

/**
 * What a panel's class file says about access — read with the tokenizer,
 * never included.
 *
 *   - the class, and the class it extends (names resolved through the
 *     file's namespace and `use` lines)
 *   - its own `buildGranted()` / `actionGranted()`: the roles they ask for
 *     (`hasRole('x')`, `hasOneRole('x', 'y')`), `superuser` for
 *     `isSuperuser()`, `signed in` for `known()` / `active()`, `custom`
 *     for anything else
 *   - the methods app.php calls as an action: public, with `#[Action]`
 *   - `$entryActions`: the actions that may be opened from outside
 *     (ActionGuard)
 */
final class ClassScanner
{
    /**
     * @return array{
     *     class: ?string, extends: ?string,
     *     grants: array{build?: list<string>, action?: list<string>},
     *     methods: array<string, int>, entry: list<string>,
     * } methods: name => line
     */
    public static function scan(string $file): array
    {
        $result = ['class' => null, 'extends' => null, 'grants' => [], 'methods' => [], 'entry' => []];
        if (!\fs::isFile($file)) {
            return $result;
        }
        $tokens = Tokens::of(\fs::toNative($file));
        $n = count($tokens->list);

        $namespace = '';
        $uses = [];
        $classAt = null;
        for ($i = 0; $i < $n; $i++) {
            $t = $tokens->list[$i];
            if ($t->is(T_NAMESPACE)) {
                $namespace = $tokens->text($i + 1);
            } elseif ($t->is(T_USE) && $classAt === null) {
                $name = ltrim($tokens->text($i + 1), '\\');
                $alias = $tokens->text($i + 2) === 'as' ? $tokens->text($i + 3) : substr((string) strrchr('\\' . $name, '\\'), 1);
                $uses[strtolower($alias)] = $name;
            } elseif ($t->is(T_CLASS) && $tokens->text($i - 1) !== '::' && $tokens->text($i - 1) !== 'new' && $tokens->at($i + 1)?->is(T_STRING)) {
                $classAt = $i;
                break;
            }
        }
        if ($classAt === null) {
            return $result;
        }

        $resolve = static function (string $name) use ($namespace, $uses): string {
            if (str_starts_with($name, '\\')) {
                return ltrim($name, '\\');
            }
            $first = strtolower(explode('\\', $name)[0]);
            if (isset($uses[$first])) {
                $rest = substr($name, strlen($first));
                return $uses[$first] . $rest;
            }
            return ltrim($namespace . '\\' . $name, '\\');
        };
        $result['class'] = $resolve($tokens->text($classAt + 1));
        $i = $classAt + 2;
        if ($tokens->at($i)?->is(T_EXTENDS)) {
            $result['extends'] = $resolve($tokens->text($i + 1));
        }
        while ($i < $n && $tokens->text($i) !== '{') {
            $i++;
        }
        $bodyEnd = $tokens->after($i);

        $depth = 0;
        for (; $i < $bodyEnd; $i++) {
            if ($tokens->opens($i)) {
                $depth++;
                continue;
            }
            if ($tokens->text($i) === '}') {
                $depth--;
                continue;
            }
            if ($depth === 1 && $tokens->text($i) === '$entryActions' && $tokens->text($i + 1) === '=' && $tokens->text($i + 2) === '[') {
                $close = $tokens->after($i + 2) - 1;
                for ($k = $i + 3; $k < $close; $k++) {
                    $name = $tokens->literal($k);
                    if ($name !== null) {
                        $result['entry'][] = $name;
                    }
                }
                $i = $close;
                continue;
            }
            if ($depth !== 1 || !$tokens->at($i)?->is(T_FUNCTION)) {
                continue;
            }
            $nameAt = $tokens->text($i + 1) === '&' ? $i + 2 : $i + 1;
            $name = $tokens->text($nameAt);
            $paramsOpen = $nameAt + 1;
            if ($tokens->text($paramsOpen) !== '(') {
                continue;
            }
            $paramsEnd = $tokens->after($paramsOpen);
            $modifiers = self::modifiers($tokens, $i);
            $body = $paramsEnd;
            while ($body < $bodyEnd && !in_array($tokens->text($body), ['{', ';'], true)) {
                $body++;
            }

            $lower = strtolower($name);
            if (($lower === 'buildgranted' || $lower === 'actiongranted') && $tokens->text($body) === '{') {
                $result['grants'][$lower === 'buildgranted' ? 'build' : 'action'] = self::grantTerms($tokens, $body + 1, $tokens->after($body) - 1);
            }

            $public = !in_array('private', $modifiers, true) && !in_array('protected', $modifiers, true);
            if ($public && self::hasActionAttribute($tokens, $i - count($modifiers) - 1)) {
                $result['methods'][$name] = $tokens->at($i)->line;
            }
            $i = $tokens->text($body) === '{' ? $tokens->after($body) - 1 : $body;
        }
        return $result;
    }

    /** @return list<string> public, static, abstract … in front of `function` at `$i` */
    private static function modifiers(Tokens $tokens, int $i): array
    {
        $out = [];
        for ($k = $i - 1; $k >= 0; $k--) {
            $t = $tokens->at($k);
            if ($t === null || !$t->is([T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL])) {
                break;
            }
            $out[] = strtolower($t->text);
        }
        return $out;
    }

    /** Do the attributes that end at `$i` (before the modifiers) include `#[Action]`? */
    private static function hasActionAttribute(Tokens $tokens, int $i): bool
    {
        while ($tokens->text($i) === ']') {
            $depth = 0;
            $k = $i;
            for (; $k >= 0; $k--) {
                $text = $tokens->text($k);
                if ($text === ']') {
                    $depth++;
                } elseif ($text === '[' || $tokens->at($k)?->is(T_ATTRIBUTE)) {
                    if (--$depth === 0) {
                        break;
                    }
                }
            }
            if ($k < 0) {
                return false;
            }
            for ($n = $k + 1; $n < $i; $n++) {
                $name = $tokens->text($n);
                if (($name === 'Action' || str_ends_with($name, '\Action')) && $tokens->text($n - 1) !== '::') {
                    return true;
                }
            }
            $i = $k - 1;
        }
        return false;
    }

    /** @return list<string> what a grant method asks for */
    private static function grantTerms(Tokens $tokens, int $from, int $to): array
    {
        $terms = [];
        $custom = false;
        for ($i = $from; $i < $to; $i++) {
            $t = $tokens->at($i);
            if (!$t?->is(T_STRING)) {
                continue;
            }
            if ($tokens->text($i + 1) !== '(') {
                // a property: user::getActive()->is_superuser, Session::current()->isSuperuser
                if ($tokens->text($i - 1) === '->' && in_array(strtolower($t->text), ['is_superuser', 'issuperuser'], true)) {
                    $terms[] = 'superuser';
                }
                continue;
            }
            switch (strtolower($t->text)) {
                case 'hasrole':
                case 'hasonerole':
                    $close = $tokens->after($i + 1) - 1;
                    for ($k = $i + 2; $k < $close; $k++) {
                        $role = $tokens->literal($k);
                        if ($role !== null) {
                            $terms[] = $role;
                        }
                    }
                    $i = $close;
                    break;
                case 'issuperuser':
                    $terms[] = 'superuser';
                    break;
                case 'known':
                case 'active':
                    $terms[] = 'signed in';
                    break;
                case 'buildgranted':
                case 'actiongranted':
                    $terms[] = 'parent';
                    break;
                case 'current':
                case 'getactive':
                    break;   // Session::current()->…, user::getActive()->… — the call that follows says what
                default:
                    $custom = true;
            }
        }
        if ($terms === [] && !$custom) {
            $code = strtolower($tokens->join($from, $to));
            $terms[] = match ($code) {
                'returntrue;' => 'everyone',
                'returnfalse;' => 'nobody',
                default => 'custom',
            };
        } elseif ($custom) {
            $terms[] = 'custom';
        }
        return array_values(array_unique($terms));
    }
}
