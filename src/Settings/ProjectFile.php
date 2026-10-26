<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

/**
 * The project files settings.php includes: page.settings.php and
 * media.settings.php. The CMS edits the literals in place. A value is written
 * back only as a quoted string, a number, a bool, or a list of strings — never
 * as pasted PHP.
 */
final class ProjectFile
{
    /** @var array<string, string> */
    public const FILES = [
        'page' => 'page.settings.php',
        'media' => 'media.settings.php',
    ];

    /**
     * The folder settings.php really lives in. The project root may only hold
     * a symlink; the included files sit next to the target.
     */
    public static function directory(): ?string
    {
        // native from here on, like EnvironmentFile::directory(): every
        // method of this class reads and writes with PHP's own functions
        $settings = \fs::toNative((string) \fs::$projectRoot . 'settings.php');
        $real = realpath($settings);
        if ($real !== false) {
            return dirname($real) . DIRECTORY_SEPARATOR;
        }
        $root = rtrim(\fs::toNative((string) \fs::$projectRoot), '\\/') . DIRECTORY_SEPARATOR;
        return is_dir($root) ? $root : null;
    }

    public static function path(string $which): ?string
    {
        $name = self::FILES[$which] ?? null;
        $dir = self::directory();
        if ($name === null || $dir === null) {
            return null;
        }
        $path = $dir . $name;
        return is_file($path) ? $path : null;
    }

    /**
     * @return list<Leaf>
     */
    public static function leaves(string $path): array
    {
        return (new self(file_get_contents($path) ?: ''))->collect();
    }

    /**
     * Scalars, string lists and record lists, in the order they stand in the file.
     *
     * @return list<Group>
     */
    public static function groups(string $path): array
    {
        $parser = new self(file_get_contents($path) ?: '');
        $parser->collect();
        return $parser->groups;
    }

    /**
     * Replace one leaf and write the file. Returns the text that was written
     * into the file, or null when the leaf or the text is refused.
     */
    public static function replace(string $path, string $id, string $text): ?string
    {
        $parser = new self(file_get_contents($path) ?: '');
        $leaves = $parser->collect();
        foreach ($leaves as $leaf) {
            if ($leaf->id !== $id) {
                continue;
            }
            $encoded = $leaf->encode($text);
            if ($encoded === null) {
                return null;
            }
            $source = substr($parser->source, 0, $leaf->start) . $encoded . substr($parser->source, $leaf->end);
            if (file_put_contents($path, $source) === false) {
                return null;
            }
            return $encoded;
        }
        return null;
    }

    /** @param list<array|string> $tokens */
    private array $tokens;

    private int $i = 0;

    private int $pos = 0;

    private string $category = '';

    /** @var list<Leaf> */
    private array $leaves = [];

    /** @var list<Group> */
    private array $groups = [];

    private function __construct(private string $source)
    {
        $this->tokens = token_get_all($source);
    }

    /** @return list<Leaf> */
    private function collect(): array
    {
        while ($this->i < count($this->tokens)) {
            $this->skip(true);
            if ($this->i >= count($this->tokens)) {
                break;
            }
            $token = $this->tokens[$this->i];
            if (is_array($token) && $token[0] === T_STRING && strcasecmp($token[1], 'define') === 0) {
                $this->parseDefine();
                continue;
            }
            if (is_array($token) && $token[0] === T_VARIABLE) {
                $this->parseAssign();
                continue;
            }
            if (is_array($token) && $token[0] === T_STRING && $this->isCall()) {
                $this->parseCall();
                continue;
            }
            $this->take();
        }
        return $this->leaves;
    }

    private function parseDefine(): void
    {
        $this->take(); // define
        $this->skip();
        $this->take(); // (
        $this->skip();
        $name = $this->stringOf($this->take());
        $this->skip();
        $this->take(); // ,
        $this->skip();
        $this->capture('define:' . $name, $name, '');
        $this->finishCall();
    }

    private function parseAssign(): void
    {
        $name = substr($this->text($this->take()), 1);
        $this->skip();
        if ($this->text($this->tokens[$this->i] ?? '') !== '=') {
            return;
        }
        $this->take();
        $this->skip();
        if (!$this->isLiteral()) {
            $this->slurpExpression();
            return;
        }
        $this->capture('var:' . $name, '$' . $name, '');
        $this->skip();
        if ($this->text($this->tokens[$this->i] ?? '') === ';') {
            $this->take();
        }
    }

    private function parseCall(): void
    {
        $class = $this->text($this->take());
        $this->take(); // ::
        $method = $this->text($this->take());
        $this->skip();
        $this->take(); // (
        $this->skip();
        $this->capture('call:' . $class . '::' . $method, $class . '::' . $method, '');
        $this->finishCall();
    }

    private function finishCall(): void
    {
        $this->skip();
        if ($this->text($this->tokens[$this->i] ?? '') === ')') {
            $this->take();
        }
        $this->skip();
        if ($this->text($this->tokens[$this->i] ?? '') === ';') {
            $this->take();
        }
    }

    private function capture(string $id, string $label, string $path): void
    {
        if ($this->isListOfStrings()) {
            $this->takeStringList($id, $label);
            return;
        }
        $token = $this->tokens[$this->i] ?? '';
        if ($this->text($token) === '[' || (is_array($token) && $token[0] === T_ARRAY)) {
            $start = $this->pos;
            $group = new Group($id, $this->category, $label, 'records', '', '', $start, $start);
            $this->groups[] = $group;
            $this->walkArray($id, $label, '', $group);
            $group->end = $this->pos;
            return;
        }
        $before = count($this->leaves);
        $this->walkValue($id, $label, $path);
        if (isset($this->leaves[$before])) {
            $leaf = $this->leaves[$before];
            $this->groups[] = new Group(
                $leaf->id,
                $leaf->category,
                $leaf->label,
                'scalar',
                $leaf->kind,
                $leaf->text,
                $leaf->start,
                $leaf->end,
            );
        }
    }

    private function takeStringList(string $id, string $label): void
    {
        $start = $this->pos;
        $this->take();
        $items = [];
        $lines = [];
        while ($this->i < count($this->tokens)) {
            $this->skip();
            if ($this->text($this->tokens[$this->i] ?? '') === ']') {
                $this->take();
                break;
            }
            $token = $this->tokens[$this->i] ?? '';
            $from = $this->pos;
            $raw = $this->text($token);
            $this->take();
            $text = $this->unquote($raw);
            $lines[] = $text;
            $items[] = new Item((string) count($items), $from, $this->pos, $from, $this->pos, $text);
            $this->skip();
            if ($this->text($this->tokens[$this->i] ?? '') === ',') {
                $this->take();
            }
        }
        $this->groups[] = new Group(
            $id,
            $this->category,
            $label,
            'list',
            'lines',
            implode("\n", $lines),
            $start,
            $this->pos,
            $items,
        );
    }

    private function walkValue(string $id, string $label, string $path): void
    {
        $this->skip();
        $token = $this->tokens[$this->i] ?? '';
        if ($this->text($token) === '[' || (is_array($token) && $token[0] === T_ARRAY)) {
            $this->walkArray($id, $label, $path);
            return;
        }
        $start = $this->pos;
        $kind = $this->scalarKind();
        $text = trim($this->slurpExpression());
        if ($kind === 'string') {
            $text = $this->unquote($text);
        } elseif ($kind === 'bool') {
            $text = strcasecmp($text, 'true') === 0 ? 'true' : 'false';
        }
        $leafId = $path === '' ? $id : $id . '/' . $path;
        $leafLabel = $path === '' ? $label : $label . ' / ' . str_replace('/', ' / ', $path);
        $this->leaves[] = new Leaf($leafId, $this->category, $leafLabel, $kind, trim($text), $start, $this->pos);
    }

    private function walkArray(string $id, string $label, string $path, ?Group $group = null): void
    {
        $this->take(); // [ or array
        if (strtolower($this->text($this->tokens[$this->i - 1])) === 'array') {
            $this->skip();
            $this->take(); // (
        }
        while ($this->i < count($this->tokens)) {
            $this->skip();
            $closer = $this->text($this->tokens[$this->i] ?? '');
            if ($closer === ']' || $closer === ')') {
                $this->take();
                return;
            }
            $itemStart = $this->pos;
            $keyStart = $this->pos;
            $key = (string) count($this->leaves);
            if ($this->looksLikeKey()) {
                $keyStart = $this->pos;
                $key = $this->unquote($this->slurpExpression());
                $keyEnd = $this->pos;
                $this->skip();
                $arrow = $this->tokens[$this->i] ?? '';
                if (is_array($arrow) && $arrow[0] === T_DOUBLE_ARROW) {
                    $this->take();
                } elseif ($this->text($arrow) === '=' && $this->text($this->tokens[$this->i + 1] ?? '') === '>') {
                    $this->take();
                    $this->take();
                }
            } else {
                $keyEnd = $this->pos;
            }
            $child = $path === '' ? $key : $path . '/' . $key;
            $from = count($this->leaves);
            $this->walkValue($id, $label, $child);
            if ($group !== null) {
                $group->items[] = new Item($key, $itemStart, $this->pos, $keyStart, $keyEnd, '', array_slice($this->leaves, $from));
            }
            $this->skip();
            if ($this->text($this->tokens[$this->i] ?? '') === ',') {
                $this->take();
            }
        }
    }

    private function looksLikeKey(): bool
    {
        $savedI = $this->i;
        $savedPos = $this->pos;
        $this->slurpExpression();
        $this->skip();
        $next = $this->tokens[$this->i] ?? '';
        $arrow = (is_array($next) && $next[0] === T_DOUBLE_ARROW)
            || ($this->text($next) === '=' && $this->text($this->tokens[$this->i + 1] ?? '') === '>');
        $this->i = $savedI;
        $this->pos = $savedPos;
        return $arrow;
    }

    /** A [...] whose elements are all quoted strings. */
    private function isListOfStrings(): bool
    {
        $token = $this->tokens[$this->i] ?? '';
        if ($this->text($token) !== '[') {
            return false;
        }
        $savedI = $this->i;
        $savedPos = $this->pos;
        $this->take();
        $ok = true;
        $any = false;
        while ($this->i < count($this->tokens)) {
            $this->skip();
            if ($this->text($this->tokens[$this->i] ?? '') === ']') {
                $this->take();
                break;
            }
            $any = true;
            $item = $this->tokens[$this->i] ?? '';
            if (!is_array($item) || !in_array($item[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                $ok = false;
                break;
            }
            $this->take();
            $this->skip();
            if ($this->text($this->tokens[$this->i] ?? '') === ',') {
                $this->take();
            }
        }
        $this->i = $savedI;
        $this->pos = $savedPos;
        return $ok;
    }

    private function scalarKind(): string
    {
        $token = $this->tokens[$this->i] ?? '';
        if (!is_array($token)) {
            return 'number';
        }
        return match ($token[0]) {
            T_CONSTANT_ENCAPSED_STRING => 'string',
            T_LNUMBER, T_DNUMBER => 'number',
            T_STRING => match (strtolower($token[1])) {
                'true', 'false' => 'bool',
                default => 'number',
            },
            default => 'number',
        };
    }

    private function isLiteral(): bool
    {
        $token = $this->tokens[$this->i] ?? '';
        if ($this->text($token) === '[' || (is_array($token) && $token[0] === T_ARRAY)) {
            return true;
        }
        if (!is_array($token) || !in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER, T_STRING], true)) {
            return false;
        }
        if ($token[0] === T_STRING) {
            $next = $this->tokens[$this->i + 1] ?? '';
            if ($this->text($next) === '(' || (is_array($next) && $next[0] === T_DOUBLE_COLON)) {
                return false;
            }
        }
        return true;
    }

    /** @return string the raw text that was consumed */
    private function slurpExpression(): string
    {
        $start = $this->pos;
        $depth = 0;
        while ($this->i < count($this->tokens)) {
            $text = $this->text($this->tokens[$this->i]);
            if ($depth === 0 && ($text === ',' || $text === ';' || $text === ']' || $text === ')')) {
                break;
            }
            if ($depth === 0 && is_array($this->tokens[$this->i]) && $this->tokens[$this->i][0] === T_DOUBLE_ARROW) {
                break;
            }
            if ($text === '[' || $text === '(' || (is_array($this->tokens[$this->i]) && $this->tokens[$this->i][0] === T_ARRAY)) {
                $depth++;
            } elseif ($text === ']' || $text === ')') {
                $depth--;
            }
            if ($depth === 0 && $text === '=' && $this->text($this->tokens[$this->i + 1] ?? '') === '>') {
                break;
            }
            $this->take();
        }
        return substr($this->source, $start, $this->pos - $start);
    }

    private function isCall(): bool
    {
        $colon = $this->tokens[$this->i + 1] ?? '';
        $method = $this->tokens[$this->i + 2] ?? '';
        return is_array($colon) && $colon[0] === T_DOUBLE_COLON && is_array($method) && $method[0] === T_STRING;
    }

    private function skip(bool $category = false): void
    {
        while ($this->i < count($this->tokens)) {
            $token = $this->tokens[$this->i];
            if (!is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                return;
            }
            if ($category && $token[0] !== T_WHITESPACE && preg_match('/KATEGORIE:\s*(.+)/', $token[1], $match)) {
                $this->category = trim($match[1]);
            }
            $this->take();
        }
    }

    private function take(): array|string
    {
        $token = $this->tokens[$this->i++];
        $this->pos += strlen($this->text($token));
        return $token;
    }

    private function text(array|string $token): string
    {
        return is_array($token) ? $token[1] : $token;
    }

    private function stringOf(array|string $token): string
    {
        return $this->unquote($this->text($token));
    }

    private function unquote(string $text): string
    {
        $text = trim($text);
        if (strlen($text) >= 2 && ($text[0] === "'" || $text[0] === '"') && $text[0] === $text[strlen($text) - 1]) {
            return stripcslashes(substr($text, 1, -1));
        }
        return $text;
    }

    /**
     * The name of a field on a record row. Empty when the row value is a scalar.
     */
    public static function fieldName(Group $group, Item $item, Leaf $leaf): string
    {
        $prefix = $group->id . '/' . $item->key . '/';
        return str_starts_with($leaf->id, $prefix) ? substr($leaf->id, strlen($prefix)) : '';
    }

    /** @return list<string> field names in the order they first appear */
    public static function columns(Group $group): array
    {
        $columns = [];
        foreach ($group->items as $item) {
            foreach ($item->fields as $leaf) {
                $name = self::fieldName($group, $item, $leaf);
                if ($name !== '' && !in_array($name, $columns, true)) {
                    $columns[] = $name;
                }
            }
        }
        return $columns;
    }

    /**
     * Write one list entry. Null when nothing changed, false when refused.
     */
    public static function saveItem(string $path, string $id, string $key, string $text): ?bool
    {
        $parser = self::open($path);
        $group = $parser?->find($id);
        if ($group === null || $group->shape !== 'list') {
            return false;
        }
        $texts = [];
        $found = false;
        foreach ($group->items as $item) {
            if ($item->key === $key) {
                $texts[] = $text;
                $found = true;
            } else {
                $texts[] = $item->text;
            }
        }
        if (!$found) {
            return false;
        }
        return self::commit($path, $group->start, $group->end, self::renderList($texts));
    }

    /** @return null unchanged, true written, false refused */
    public static function renameItem(string $path, string $id, string $key, string $text): ?bool
    {
        $text = trim($text);
        if ($text === '' || str_contains($text, "\n") || str_contains($text, '/')) {
            return false;
        }
        $parser = self::open($path);
        $group = $parser?->find($id);
        if ($group === null || $group->shape !== 'records') {
            return false;
        }
        $item = self::item($group, $key);
        if ($item === null) {
            return false;
        }
        if ($item->key === $text) {
            return null;
        }
        foreach ($group->items as $other) {
            if ($other->key === $text) {
                return false;
            }
        }
        return self::commit($path, $item->keyStart, $item->keyEnd, var_export($text, true));
    }

    /** @return null unchanged, true written, false refused */
    public static function addItem(string $path, string $id): ?bool
    {
        $parser = self::open($path);
        $group = $parser?->find($id);
        if ($group === null || $group->shape === 'scalar') {
            return false;
        }
        if ($group->shape === 'list') {
            $texts = array_map(static fn(Item $item): string => $item->text, $group->items);
            $texts[] = self::freshKey($texts, 'new');
            return self::commit($path, $group->start, $group->end, self::renderList($texts));
        }
        $keys = array_map(static fn(Item $item): string => $item->key, $group->items);
        $newKey = self::freshKey($keys, 'new');
        $sample = $group->items[array_key_last($group->items)] ?? null;
        $lines = [];
        if ($sample !== null) {
            foreach ($sample->fields as $leaf) {
                $name = self::fieldName($group, $sample, $leaf);
                if ($name === '') {
                    continue;
                }
                $lines[] = "\t\t" . var_export($name, true) . ' => ' . self::defaultLiteral($leaf->kind);
            }
        }
        $row = $lines === []
            ? var_export($newKey, true) . " => [\n\t]"
            : var_export($newKey, true) . " => [\n" . implode(",\n", $lines) . ",\n\t]";
        $slices = [];
        foreach ($group->items as $item) {
            $slices[] = trim(substr($parser->source, $item->start, $item->end - $item->start));
        }
        $slices[] = $row;
        return self::commit($path, $group->start, $group->end, self::renderRecords($slices));
    }

    /** @return null unchanged, true written, false refused */
    public static function removeItem(string $path, string $id, string $key): ?bool
    {
        $parser = self::open($path);
        $group = $parser?->find($id);
        if ($group === null || self::item($group, $key) === null) {
            return false;
        }
        if ($group->shape === 'records' && count($group->items) <= 1) {
            return false;
        }
        if ($group->shape === 'list') {
            $texts = [];
            foreach ($group->items as $item) {
                if ($item->key !== $key) {
                    $texts[] = $item->text;
                }
            }
            return self::commit($path, $group->start, $group->end, self::renderList($texts));
        }
        $slices = [];
        foreach ($group->items as $item) {
            if ($item->key === $key) {
                continue;
            }
            $slices[] = trim(substr($parser->source, $item->start, $item->end - $item->start));
        }
        return self::commit($path, $group->start, $group->end, self::renderRecords($slices));
    }

    /** @return null unchanged, true written, false refused */
    public static function moveItem(string $path, string $id, string $from, string $to, bool $after): ?bool
    {
        $parser = self::open($path);
        $group = $parser?->find($id);
        if ($group === null || $from === '' || $to === '') {
            return false;
        }
        $items = $group->items;
        $fromAt = self::indexOf($items, $from);
        $toAt = self::indexOf($items, $to);
        if ($fromAt === null || $toAt === null) {
            return false;
        }
        $moved = array_splice($items, $fromAt, 1)[0];
        $toAt = self::indexOf($items, $to);
        if ($toAt === null) {
            return false;
        }
        if ($after) {
            $toAt++;
        }
        array_splice($items, $toAt, 0, [$moved]);
        if ($group->shape === 'list') {
            $encoded = self::renderList(array_map(static fn(Item $item): string => $item->text, $items));
        } else {
            $encoded = self::renderRecords(array_map(
                static fn(Item $item): string => trim(substr($parser->source, $item->start, $item->end - $item->start)),
                $items,
            ));
        }
        return self::commit($path, $group->start, $group->end, $encoded);
    }

    /**
     * Put a value on a record field, adding the field when the row does not have it yet.
     *
     * @return null unchanged, true written, false refused
     */
    public static function setField(string $path, string $id, string $key, string $field, string $text): ?bool
    {
        if ($field === '' || str_contains($field, '/')) {
            return false;
        }
        $parser = self::open($path);
        $group = $parser?->find($id);
        $item = $group === null ? null : self::item($group, $key);
        if ($group === null || $item === null || $group->shape !== 'records') {
            return false;
        }
        foreach ($item->fields as $leaf) {
            if (self::fieldName($group, $item, $leaf) === $field) {
                $encoded = $leaf->encode($text);
                if ($encoded === null) {
                    return false;
                }
                return self::commit($path, $leaf->start, $leaf->end, $encoded);
            }
        }
        $kind = 'string';
        foreach ($group->items as $row) {
            foreach ($row->fields as $leaf) {
                if (self::fieldName($group, $row, $leaf) === $field) {
                    $kind = $leaf->kind;
                }
            }
        }
        $literal = (new Leaf('', '', '', $kind, '', 0, 0))->encode($text);
        if ($literal === null || trim($text) === '') {
            return null;
        }
        $raw = substr($parser->source, $item->start, $item->end - $item->start);
        $close = strrpos($raw, ']');
        if ($close === false) {
            return false;
        }
        $head = rtrim(substr($raw, 0, $close));
        if (str_ends_with($head, ',')) {
            $head = rtrim(substr($head, 0, -1));
        }
        $row = $head . ",\n\t\t" . var_export($field, true) . ' => ' . $literal . ",\n\t]";
        return self::commit($path, $item->start, $item->end, $row);
    }

    private static function open(string $path): ?self
    {
        if (!is_file($path)) {
            return null;
        }
        $parser = new self((string) file_get_contents($path));
        $parser->collect();
        return $parser;
    }

    private function find(string $id): ?Group
    {
        foreach ($this->groups as $group) {
            if ($group->id === $id) {
                return $group;
            }
        }
        return null;
    }

    private static function item(Group $group, string $key): ?Item
    {
        foreach ($group->items as $item) {
            if ($item->key === $key) {
                return $item;
            }
        }
        return null;
    }

    /** @param list<Item> $items */
    private static function indexOf(array $items, string $key): ?int
    {
        foreach ($items as $index => $item) {
            if ($item->key === $key) {
                return $index;
            }
        }
        return null;
    }

    /** @param list<string> $used */
    private static function freshKey(array $used, string $base): string
    {
        if (!in_array($base, $used, true)) {
            return $base;
        }
        $n = 2;
        while (in_array($base . '_' . $n, $used, true)) {
            $n++;
        }
        return $base . '_' . $n;
    }

    private static function defaultLiteral(string $kind): string
    {
        return match ($kind) {
            'bool' => 'false',
            'number' => '0',
            default => "''",
        };
    }

    /** @param list<string> $texts */
    private static function renderList(array $texts): string
    {
        if ($texts === []) {
            return '[]';
        }
        $body = implode(",\n\t", array_map(static fn(string $line): string => var_export($line, true), $texts));
        return "[\n\t$body,\n]";
    }

    /** @param list<string> $rows */
    private static function renderRecords(array $rows): string
    {
        if ($rows === []) {
            return "array(\n)";
        }
        return "[\n\t" . implode(",\n\t", $rows) . ",\n]";
    }

    /** @return null unchanged, true written, false refused */
    private static function commit(string $path, int $start, int $end, string $encoded): ?bool
    {
        $source = file_get_contents($path);
        if ($source === false) {
            return false;
        }
        $original = substr($source, $start, $end - $start);
        if ($original === $encoded) {
            return null;
        }
        $next = substr($source, 0, $start) . $encoded . substr($source, $end);
        try {
            token_get_all($next, TOKEN_PARSE);
        } catch (\Throwable) {
            return false;
        }
        return file_put_contents($path, $next) !== false ? true : false;
    }
}

/**
 * One editable literal inside a project settings file.
 */
final class Leaf
{
    public function __construct(
        public readonly string $id,
        public readonly string $category,
        public readonly string $label,
        public readonly string $kind,
        public readonly string $text,
        public readonly int $start,
        public readonly int $end,
    ) {}

    public function encode(string $text): ?string
    {
        if ($this->kind === 'bool') {
            $on = in_array(strtolower(trim($text)), ['1', 'true', 'on', 'yes'], true);
            return $on ? 'true' : 'false';
        }
        if ($this->kind === 'number') {
            $text = trim($text);
            if (preg_match('/^-?\d+(\.\d+)?$/', $text) === 1) {
                return $text;
            }
            if (preg_match('/^[\d\s+\-*\/().]+$/', $text) === 1 && $text !== '') {
                return $text;
            }
            return null;
        }
        if ($this->kind === 'lines') {
            $lines = array_values(array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $text) ?: []), static fn(string $line): bool => $line !== ''));
            $body = implode(",\n\t", array_map(static fn(string $line): string => var_export($line, true), $lines));
            return "[\n\t$body,\n]";
        }
        return var_export($text, true);
    }
}

/**
 * One value in a project settings file: a scalar, a list of strings, or a list of records.
 */
final class Group
{
    /**
     * @param list<Item> $items
     */
    public function __construct(
        public readonly string $id,
        public readonly string $category,
        public readonly string $label,
        public readonly string $shape,
        public readonly string $kind,
        public readonly string $text,
        public readonly int $start,
        public int $end,
        public array $items = [],
    ) {}
}

/**
 * One entry of a list or a record list.
 */
final class Item
{
    /**
     * @param list<Leaf> $fields
     */
    public function __construct(
        public readonly string $key,
        public readonly int $start,
        public readonly int $end,
        public readonly int $keyStart,
        public readonly int $keyEnd,
        public readonly string $text = '',
        public readonly array $fields = [],
    ) {}
}
