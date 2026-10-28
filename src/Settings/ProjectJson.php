<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

/**
 * global.settings.json and media.settings.json. The CMS still speaks in the
 * same groups the PHP files produced (define:PUBLIC_PAGE_NAME, …). A write
 * rebuilds the JSON document. It never splices text into a file PHP will run.
 */
final class ProjectJson
{
    /** @var array<string, array{0: string, 1: ?string}> group id => [section, inner key] */
    private const GROUP = [
        'define:LANGUAGES' => ['languages', 'LANGUAGES'],
        'call:mediaFormats::add' => ['formats', null],
        'define:VIDEO_FORMATS' => ['videoFormats', null],
        'var:media_groups' => ['groups', null],
        'define:EXTERNAL_MEDIAS' => ['external', null],
    ];

    /** @param array<string, mixed> $data */
    private function __construct(
        private string $path,
        private string $which,
        private array $data,
    ) {}

    public static function open(string $path): ?self
    {
        $which = self::whichOf($path);
        if ($which === null || !is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        try {
            $data = json_decode(ltrim($raw, "\xEF\xBB\xBF"), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($data)) {
            return null;
        }
        return new self($path, $which, $data);
    }

    /** @return list<Group> */
    public static function groups(string $path): array
    {
        return self::open($path)?->build() ?? [];
    }

    /**
     * @param list<Group> $groups
     * @return array<string, mixed>
     */
    public static function documentFromGroups(string $which, array $groups): array
    {
        if ($which === 'page') {
            return self::pageDocument($groups);
        }
        if ($which === 'media') {
            return self::mediaDocument($groups);
        }
        return [];
    }

    /** @param array<string, mixed> $data */
    public static function put(string $path, array $data): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        try {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return false;
        }
        return file_put_contents($path, $json . "\n", LOCK_EX) !== false;
    }

    public static function replace(string $path, string $id, string $text): ?string
    {
        return self::open($path)?->replaceLeaf($id, $text);
    }

    public static function saveItem(string $path, string $id, string $key, string $text): ?bool
    {
        return self::open($path)?->writeItem($id, $key, $text);
    }

    public static function renameItem(string $path, string $id, string $key, string $text): ?bool
    {
        return self::open($path)?->rename($id, $key, $text);
    }

    public static function addItem(string $path, string $id): ?bool
    {
        return self::open($path)?->add($id);
    }

    public static function removeItem(string $path, string $id, string $key): ?bool
    {
        return self::open($path)?->remove($id, $key);
    }

    public static function moveItem(string $path, string $id, string $from, string $to, bool $after): ?bool
    {
        return self::open($path)?->move($id, $from, $to, $after);
    }

    public static function setField(string $path, string $id, string $key, string $field, string $text): ?bool
    {
        return self::open($path)?->putField($id, $key, $field, $text);
    }

    /** @return list<Group> */
    private function build(): array
    {
        $layout = $this->which === 'page' ? self::pageLayout() : self::mediaLayout();
        $groups = [];
        foreach ($layout as $section => $spec) {
            if (!isset($this->data[$section]) || !is_array($this->data[$section])) {
                continue;
            }
            if ($spec['mode'] === 'scalars') {
                foreach ($this->data[$section] as $name => $value) {
                    if (!is_string($name) || is_array($value) || !ProjectSettings::scalarAllowed($name)) {
                        continue;
                    }
                    $groups[] = self::scalar($spec['category'], $name, $value);
                }
                continue;
            }
            if ($spec['mode'] === 'languages') {
                foreach ($this->data[$section] as $name => $value) {
                    if ($name === 'LANGUAGES' && is_array($value)) {
                        $groups[] = self::records('define:LANGUAGES', $spec['category'], 'LANGUAGES', $value);
                        continue;
                    }
                    if (!is_string($name) || is_array($value) || !ProjectSettings::scalarAllowed($name)) {
                        continue;
                    }
                    $groups[] = self::scalar($spec['category'], $name, $value);
                }
                continue;
            }
            $groups[] = $spec['mode'] === 'list'
                ? self::lines($spec['id'], $spec['category'], $spec['label'], $this->data[$section])
                : self::records($spec['id'], $spec['category'], $spec['label'], $this->data[$section]);
        }
        return $groups;
    }

    private function replaceLeaf(string $id, string $text): ?string
    {
        if (str_contains($id, '/')) {
            $at = self::locate($id);
            if ($at === null || $at['field'] === null) {
                return null;
            }
            $rows = $this->recordRows($at['group']);
            $key = self::rowKey($rows, $at['key']);
            if ($key === null || !is_array($rows[$key]) || !array_key_exists($at['field'], $rows[$key]) || is_array($rows[$key][$at['field']])) {
                return null;
            }
            $kind = self::kindOf($rows[$key][$at['field']]);
            $value = self::cast($kind, $text);
            if ($value === null) {
                return null;
            }
            if ($rows[$key][$at['field']] === $value) {
                return self::textOf($value);
            }
            $rows[$key][$at['field']] = $value;
            $this->putRecords($at['group'], $rows);
            return $this->save() ? self::textOf($value) : null;
        }
        if (!str_starts_with($id, 'define:')) {
            return null;
        }
        $name = substr($id, strlen('define:'));
        if (!ProjectSettings::scalarAllowed($name)) {
            return null;
        }
        $section = $this->scalarSection($name);
        if ($section === null) {
            return null;
        }
        $kind = self::kindOf($this->data[$section][$name]);
        $value = self::cast($kind, $text);
        if ($value === null) {
            return null;
        }
        if ($this->data[$section][$name] === $value) {
            return self::textOf($value);
        }
        $this->data[$section][$name] = $value;
        return $this->save() ? self::textOf($value) : null;
    }

    private function writeItem(string $id, string $key, string $text): ?bool
    {
        if ($id !== 'define:EXTERNAL_MEDIAS') {
            return false;
        }
        $list = $this->list();
        $index = self::listIndex($list, $key);
        if ($index === null) {
            return false;
        }
        if ($list[$index] === $text) {
            return null;
        }
        $list[$index] = $text;
        $this->data['external'] = array_values($list);
        return $this->save() ? true : false;
    }

    private function rename(string $id, string $key, string $text): ?bool
    {
        $text = trim($text);
        if ($text === '' || str_contains($text, "\n") || str_contains($text, '/') || !isset(self::GROUP[$id]) || $id === 'define:EXTERNAL_MEDIAS') {
            return false;
        }
        $rows = $this->recordRows($id);
        $current = self::rowKey($rows, $key);
        if ($current === null) {
            return false;
        }
        if ((string) $current === $text) {
            return null;
        }
        foreach ($rows as $other => $row) {
            if ((string) $other === $text) {
                return false;
            }
        }
        $next = [];
        foreach ($rows as $other => $row) {
            $next[(string) $other === (string) $current ? $text : $other] = $row;
        }
        $this->putRecords($id, $next);
        return $this->save() ? true : false;
    }

    private function add(string $id): ?bool
    {
        if (!isset(self::GROUP[$id])) {
            return false;
        }
        if ($id === 'define:EXTERNAL_MEDIAS') {
            $list = $this->list();
            $list[] = self::freshKey($list, 'new');
            $this->data['external'] = array_values($list);
            return $this->save() ? true : false;
        }
        $rows = $this->recordRows($id);
        $keys = array_map(static fn($key): string => (string) $key, array_keys($rows));
        $newKey = self::freshKey($keys, 'new');
        $sample = null;
        if ($rows !== []) {
            $sample = $rows[array_key_last($rows)];
        }
        $row = [];
        if (is_array($sample)) {
            foreach ($sample as $name => $value) {
                if (is_array($value)) {
                    continue;
                }
                $row[(string) $name] = self::blank(self::kindOf($value));
            }
        }
        $rows[$newKey] = $row;
        $this->putRecords($id, $rows);
        return $this->save() ? true : false;
    }

    private function remove(string $id, string $key): ?bool
    {
        if (!isset(self::GROUP[$id])) {
            return false;
        }
        if ($id === 'define:EXTERNAL_MEDIAS') {
            $list = $this->list();
            $index = self::listIndex($list, $key);
            if ($index === null) {
                return false;
            }
            unset($list[$index]);
            $this->data['external'] = array_values($list);
            return $this->save() ? true : false;
        }
        $rows = $this->recordRows($id);
        $current = self::rowKey($rows, $key);
        if ($current === null) {
            return false;
        }
        if (count($rows) <= 1) {
            return false;
        }
        unset($rows[$current]);
        $this->putRecords($id, $rows);
        return $this->save() ? true : false;
    }

    private function move(string $id, string $from, string $to, bool $after): ?bool
    {
        if ($from === '' || $to === '' || !isset(self::GROUP[$id])) {
            return false;
        }
        if ($id === 'define:EXTERNAL_MEDIAS') {
            $list = $this->list();
            $order = array_map('strval', array_keys($list));
            $next = self::reorder($order, $from, $to, $after);
            if ($next === null) {
                return false;
            }
            if ($next === $order) {
                return null;
            }
            $rebuilt = [];
            foreach ($next as $key) {
                $rebuilt[] = $list[(int) $key];
            }
            $this->data['external'] = $rebuilt;
            return $this->save() ? true : false;
        }
        $rows = $this->recordRows($id);
        $order = array_map(static fn($key): string => (string) $key, array_keys($rows));
        $next = self::reorder($order, $from, $to, $after);
        if ($next === null) {
            return false;
        }
        if ($next === $order) {
            return null;
        }
        $rebuilt = [];
        foreach ($next as $key) {
            $rebuilt[$key] = $rows[self::rowKey($rows, $key)];
        }
        $this->putRecords($id, $rebuilt);
        return $this->save() ? true : false;
    }

    private function putField(string $id, string $key, string $field, string $text): ?bool
    {
        if ($field === '' || str_contains($field, '/') || !isset(self::GROUP[$id]) || $id === 'define:EXTERNAL_MEDIAS') {
            return false;
        }
        $rows = $this->recordRows($id);
        $current = self::rowKey($rows, $key);
        if ($current === null || !is_array($rows[$current])) {
            return false;
        }
        $kind = 'string';
        foreach ($rows as $row) {
            if (is_array($row) && array_key_exists($field, $row) && !is_array($row[$field])) {
                $kind = self::kindOf($row[$field]);
            }
        }
        $exists = array_key_exists($field, $rows[$current]) && !is_array($rows[$current][$field]);
        if (!$exists && trim($text) === '') {
            return null;
        }
        $value = self::cast($kind, $text);
        if ($value === null) {
            return false;
        }
        if ($exists && $rows[$current][$field] === $value) {
            return null;
        }
        $rows[$current][$field] = $value;
        $this->putRecords($id, $rows);
        return $this->save() ? true : false;
    }

    /** @param list<Group> $groups @return array<string, mixed> */
    private static function pageDocument(array $groups): array
    {
        $data = ['general' => [], 'meta' => [], 'languages' => []];
        foreach ($groups as $group) {
            if ($group->id === 'define:LANGUAGES' && $group->shape === 'records') {
                $data['languages']['LANGUAGES'] = self::rowsOf($group);
                continue;
            }
            if ($group->shape !== 'scalar' || !str_starts_with($group->id, 'define:')) {
                continue;
            }
            $name = substr($group->id, strlen('define:'));
            $section = match ($group->category) {
                'Meta' => 'meta',
                'Languages' => 'languages',
                default => 'general',
            };
            $data[$section][$name] = self::stored($group->kind, $group->text);
        }
        return self::dropEmpty($data);
    }

    /** @param list<Group> $groups @return array<string, mixed> */
    private static function mediaDocument(array $groups): array
    {
        $data = [];
        $images = [];
        foreach ($groups as $group) {
            if ($group->id === 'call:mediaFormats::add') {
                $data['formats'] = self::rowsOf($group);
                continue;
            }
            if ($group->id === 'define:VIDEO_FORMATS') {
                $data['videoFormats'] = self::rowsOf($group);
                continue;
            }
            if ($group->id === 'var:media_groups') {
                $data['groups'] = self::rowsOf($group);
                continue;
            }
            if ($group->id === 'define:EXTERNAL_MEDIAS') {
                $data['external'] = array_map(static fn(Item $item): string => $item->text, $group->items);
                continue;
            }
            if ($group->shape === 'scalar' && str_starts_with($group->id, 'define:')) {
                $images[substr($group->id, strlen('define:'))] = self::stored($group->kind, $group->text);
            }
        }
        if ($images !== []) {
            $data = ['images' => $images] + $data;
        }
        return $data;
    }

    /** @return array<string, mixed> */
    private static function rowsOf(Group $group): array
    {
        $rows = [];
        foreach ($group->items as $item) {
            $row = [];
            foreach ($item->fields as $leaf) {
                $name = ProjectFile::fieldName($group, $item, $leaf);
                if ($name === '') {
                    continue;
                }
                $row[$name] = self::stored($leaf->kind, $leaf->text);
            }
            $rows[$item->key] = $row;
        }
        return $rows;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private static function dropEmpty(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value === []) {
                unset($data[$key]);
            }
        }
        return $data;
    }

    private static function scalar(string $category, string $name, mixed $value): Group
    {
        return new Group(
            'define:' . $name,
            $category,
            $name,
            'scalar',
            self::kindOf($value),
            self::textOf($value),
            0,
            0,
        );
    }

    /** @param array<mixed> $rows */
    private static function records(string $id, string $category, string $label, array $rows): Group
    {
        $items = [];
        foreach ($rows as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $fields = [];
            foreach ($row as $field => $value) {
                if (is_array($value)) {
                    continue;
                }
                $field = (string) $field;
                $fields[] = new Leaf(
                    $id . '/' . $key . '/' . $field,
                    $category,
                    $field,
                    self::kindOf($value),
                    self::textOf($value),
                    0,
                    0,
                );
            }
            $items[] = new Item((string) $key, 0, 0, 0, 0, '', $fields);
        }
        return new Group($id, $category, $label, 'records', '', '', 0, 0, $items);
    }

    /** @param array<mixed> $values */
    private static function lines(string $id, string $category, string $label, array $values): Group
    {
        $texts = [];
        foreach (array_values($values) as $value) {
            if (is_array($value)) {
                continue;
            }
            $texts[] = self::textOf($value);
        }
        $items = [];
        foreach ($texts as $index => $text) {
            $items[] = new Item((string) $index, 0, 0, 0, 0, $text);
        }
        return new Group($id, $category, $label, 'list', 'lines', implode("\n", $texts), 0, 0, $items);
    }

    /** @return array<string, array{category: string, mode: string, id?: string, label?: string}> */
    private static function pageLayout(): array
    {
        return [
            'general' => ['category' => 'General', 'mode' => 'scalars'],
            'meta' => ['category' => 'Meta', 'mode' => 'scalars'],
            'languages' => ['category' => 'Languages', 'mode' => 'languages'],
        ];
    }

    /** @return array<string, array{category: string, mode: string, id?: string, label?: string}> */
    private static function mediaLayout(): array
    {
        return [
            'images' => ['category' => 'Images', 'mode' => 'scalars'],
            'formats' => ['category' => 'Image formats', 'mode' => 'records', 'id' => 'call:mediaFormats::add', 'label' => 'mediaFormats::add'],
            'videoFormats' => ['category' => 'Video formats', 'mode' => 'records', 'id' => 'define:VIDEO_FORMATS', 'label' => 'VIDEO_FORMATS'],
            'groups' => ['category' => 'Media groups', 'mode' => 'records', 'id' => 'var:media_groups', 'label' => '$media_groups'],
            'external' => ['category' => 'External medias', 'mode' => 'list', 'id' => 'define:EXTERNAL_MEDIAS', 'label' => 'EXTERNAL_MEDIAS'],
        ];
    }

    private function scalarSection(string $name): ?string
    {
        foreach (['general', 'meta', 'languages', 'images'] as $section) {
            if (!isset($this->data[$section]) || !is_array($this->data[$section])) {
                continue;
            }
            if (!array_key_exists($name, $this->data[$section]) || is_array($this->data[$section][$name])) {
                continue;
            }
            return $section;
        }
        return null;
    }

    /** @return array<mixed> */
    private function recordRows(string $groupId): array
    {
        [$section, $inner] = self::GROUP[$groupId];
        if (!isset($this->data[$section]) || !is_array($this->data[$section])) {
            return [];
        }
        if ($inner === null) {
            return $this->data[$section];
        }
        $bag = $this->data[$section][$inner] ?? null;
        return is_array($bag) ? $bag : [];
    }

    /** @param array<mixed> $rows */
    private function putRecords(string $groupId, array $rows): void
    {
        [$section, $inner] = self::GROUP[$groupId];
        if ($inner === null) {
            $this->data[$section] = $rows;
            return;
        }
        if (!isset($this->data[$section]) || !is_array($this->data[$section])) {
            $this->data[$section] = [];
        }
        $this->data[$section][$inner] = $rows;
    }

    /** @return list<string> */
    private function list(): array
    {
        $list = $this->data['external'] ?? null;
        if (!is_array($list)) {
            return [];
        }
        $texts = [];
        foreach (array_values($list) as $value) {
            if (!is_array($value)) {
                $texts[] = self::textOf($value);
            }
        }
        return $texts;
    }

    /** @param array<mixed> $rows */
    private static function rowKey(array $rows, string $key): int|string|null
    {
        foreach ($rows as $other => $row) {
            if ((string) $other === $key) {
                return $other;
            }
        }
        return null;
    }

    /** @param list<string> $list */
    private static function listIndex(array $list, string $key): ?int
    {
        if (!ctype_digit($key)) {
            return null;
        }
        $index = (int) $key;
        return array_key_exists($index, $list) ? $index : null;
    }

    /**
     * @param list<string> $order
     * @return list<string>|null
     */
    private static function reorder(array $order, string $from, string $to, bool $after): ?array
    {
        $fromAt = array_search($from, $order, true);
        $toAt = array_search($to, $order, true);
        if ($fromAt === false || $toAt === false) {
            return null;
        }
        $moved = $order[$fromAt];
        array_splice($order, $fromAt, 1);
        $toAt = array_search($to, $order, true);
        if ($toAt === false) {
            return null;
        }
        if ($after) {
            $toAt++;
        }
        array_splice($order, $toAt, 0, [$moved]);
        return $order;
    }

    /** @param list<string> $used */
    private static function freshKey(array $used, string $base): string
    {
        $used = array_map(static fn($key): string => (string) $key, $used);
        if (!in_array($base, $used, true)) {
            return $base;
        }
        $n = 2;
        while (in_array($base . '_' . $n, $used, true)) {
            $n++;
        }
        return $base . '_' . $n;
    }

    private function save(): bool
    {
        return self::put($this->path, $this->data);
    }

    private static function whichOf(string $path): ?string
    {
        $base = basename($path);
        foreach (ProjectFile::FILES as $which => $name) {
            if ($base === $name) {
                return $which;
            }
        }
        return null;
    }

    /** @return array{group: string, key: string, field: ?string}|null */
    private static function locate(string $id): ?array
    {
        foreach (self::GROUP as $groupId => $spec) {
            $prefix = $groupId . '/';
            if (!str_starts_with($id, $prefix)) {
                continue;
            }
            $rest = substr($id, strlen($prefix));
            $slash = strpos($rest, '/');
            if ($slash === false) {
                return ['group' => $groupId, 'key' => $rest, 'field' => null];
            }
            return [
                'group' => $groupId,
                'key' => substr($rest, 0, $slash),
                'field' => substr($rest, $slash + 1),
            ];
        }
        return null;
    }

    private static function kindOf(mixed $value): string
    {
        if (is_bool($value)) {
            return 'bool';
        }
        if (is_int($value) || is_float($value)) {
            return 'number';
        }
        return 'string';
    }

    private static function textOf(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            $text = json_encode($value);
            return is_string($text) ? $text : '0';
        }
        return (string) $value;
    }

    private static function blank(string $kind): mixed
    {
        return match ($kind) {
            'bool' => false,
            'number' => 0,
            default => '',
        };
    }

    private static function cast(string $kind, string $text): mixed
    {
        if ($kind === 'bool') {
            return in_array(strtolower(trim($text)), ['1', 'true', 'on', 'yes'], true);
        }
        if ($kind === 'number') {
            $text = trim($text);
            if (preg_match('/^-?\d+$/', $text) === 1) {
                return (int) $text;
            }
            if (preg_match('/^-?\d+\.\d+$/', $text) === 1) {
                return (float) $text;
            }
            if (preg_match('/^[\d\s+\-*\/().]+$/', $text) === 1 && $text !== '') {
                return self::arithmetic($text);
            }
            return null;
        }
        return $text;
    }

    private static function stored(string $kind, string $text): mixed
    {
        $value = self::cast($kind, $text);
        if ($value === null) {
            throw new \InvalidArgumentException($text);
        }
        return $value;
    }

    private static function arithmetic(string $expr): int|float|null
    {
        $expr = str_replace(' ', '', trim($expr));
        if ($expr === '' || preg_match('/^[\d+\-*\/().]+$/', $expr) !== 1) {
            return null;
        }
        $i = 0;
        try {
            $value = self::parseAdd($expr, $i);
            if ($i !== strlen($expr)) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }
        if (is_float($value) && (is_nan($value) || is_infinite($value))) {
            return null;
        }
        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return (int) $value;
        }
        return $value;
    }

    private static function parseAdd(string $expr, int &$i): int|float
    {
        $value = self::parseMul($expr, $i);
        $length = strlen($expr);
        while ($i < $length && ($expr[$i] === '+' || $expr[$i] === '-')) {
            $op = $expr[$i++];
            $right = self::parseMul($expr, $i);
            $value = $op === '+' ? $value + $right : $value - $right;
        }
        return $value;
    }

    private static function parseMul(string $expr, int &$i): int|float
    {
        $value = self::parseUnary($expr, $i);
        $length = strlen($expr);
        while ($i < $length && ($expr[$i] === '*' || $expr[$i] === '/')) {
            $op = $expr[$i++];
            $right = self::parseUnary($expr, $i);
            if ($op === '/' && (float) $right === 0.0) {
                throw new \DivisionByZeroError();
            }
            $value = $op === '*' ? $value * $right : $value / $right;
        }
        return $value;
    }

    private static function parseUnary(string $expr, int &$i): int|float
    {
        if ($i < strlen($expr) && ($expr[$i] === '+' || $expr[$i] === '-')) {
            $op = $expr[$i++];
            $value = self::parseUnary($expr, $i);
            return $op === '-' ? -$value : $value;
        }
        return self::parsePrimary($expr, $i);
    }

    private static function parsePrimary(string $expr, int &$i): int|float
    {
        if ($i < strlen($expr) && $expr[$i] === '(') {
            $i++;
            $value = self::parseAdd($expr, $i);
            if ($i >= strlen($expr) || $expr[$i] !== ')') {
                throw new \InvalidArgumentException();
            }
            $i++;
            return $value;
        }
        $start = $i;
        $length = strlen($expr);
        while ($i < $length && (ctype_digit($expr[$i]) || $expr[$i] === '.')) {
            $i++;
        }
        if ($start === $i || substr_count(substr($expr, $start, $i - $start), '.') > 1) {
            throw new \InvalidArgumentException();
        }
        $number = substr($expr, $start, $i - $start);
        return str_contains($number, '.') ? (float) $number : (int) $number;
    }
}
