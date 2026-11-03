<?php

declare(strict_types=1);

namespace Systopic\System\Controlmap;

/**
 * A PHP file as tokens without whitespace and comments — what the scanners
 * walk. Reads only: nothing of the file is included or run.
 */
final class Tokens
{
    /** @param list<\PhpToken> $list */
    private function __construct(public readonly array $list) {}

    public static function of(string $nativeFile): self
    {
        $code = @file_get_contents($nativeFile);
        if ($code === false) {
            return new self([]);
        }
        try {
            $tokens = \PhpToken::tokenize($code);
        } catch (\Throwable) {
            return new self([]);
        }
        return new self(array_values(array_filter($tokens, static fn(\PhpToken $t) => !$t->isIgnorable())));
    }

    public function at(int $i): ?\PhpToken
    {
        return $this->list[$i] ?? null;
    }

    public function text(int $i): string
    {
        return $this->list[$i]->text ?? '';
    }

    /** Does a brace open here? `{`, and `{$` / `${` inside strings. */
    public function opens(int $i): bool
    {
        $t = $this->list[$i] ?? null;
        return $t !== null && ($t->text === '{' || $t->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES]));
    }

    /** The index after the bracket that closes the one at `$i` (`(`, `[` or `{`). */
    public function after(int $i): int
    {
        $pairs = ['(' => ')', '[' => ']', '{' => '}'];
        $open = $this->text($i);
        $close = $pairs[$open] ?? null;
        if ($close === null) {
            return $i + 1;
        }
        $depth = 0;
        for ($n = count($this->list); $i < $n; $i++) {
            $text = $this->list[$i]->text;
            if ($text === $open || ($open === '{' && $this->opens($i))) {
                $depth++;
            } elseif ($text === $close && --$depth === 0) {
                return $i + 1;
            }
        }
        return $i;
    }

    /** The source between two indices, without spaces — `$this->action`. */
    public function join(int $from, int $to): string
    {
        $out = '';
        for ($i = $from; $i < $to; $i++) {
            $out .= $this->list[$i]->text ?? '';
        }
        return $out;
    }

    /** A string literal's value; null for anything else (a string with variables in it). */
    public function literal(int $i): ?string
    {
        $t = $this->list[$i] ?? null;
        if ($t === null || !$t->is(T_CONSTANT_ENCAPSED_STRING)) {
            return null;
        }
        $inner = substr($t->text, 1, -1);
        return $t->text[0] === "'"
            ? str_replace(["\\'", '\\\\'], ["'", '\\'], $inner)
            : stripcslashes($inner);
    }
}
