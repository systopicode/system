<?php

declare(strict_types=1);
namespace Systopic\System\Dashboard;

/** Pure layout rules. Legacy order is the three-column layout; other views opt in on first sort. */
final class Layout
{
    public static function read(mixed $data): array
    {
        $data = is_array($data) ? $data : [];
        $strings = static fn($v) => array_values(array_filter(is_array($v) ? $v : [], 'is_string'));
        $layouts = [];
        foreach ([1, 2, 3] as $count) {
            if (is_array($data['layouts'][$count] ?? null)) $layouts[$count] = $strings($data['layouts'][$count]);
        }
        return ['order' => $strings($data['order'] ?? []), 'hidden' => array_values(array_unique($strings($data['hidden'] ?? []))), 'layouts' => $layouts];
    }

    public static function columns(array $keys, array $order, int $count = 3): array
    {
        $count = max(1, min(3, $count));
        $columns = array_fill(0, $count, []);
        $seen = []; $column = 0;
        foreach ($order as $key) {
            if ($key === '|') { $column = min($column + 1, $count - 1); continue; }
            if (is_string($key) && in_array($key, $keys, true) && !isset($seen[$key])) {
                $columns[$column][] = $key; $seen[$key] = true;
            }
        }
        $new = array_values(array_diff($keys, array_keys($seen)));
        if (!in_array('|', $order, true)) {
            // Preserve the historical column-major interpretation of old flat layouts.
            $all = [...array_merge(...$columns), ...$new];
            return array_pad(array_chunk($all, max(1, (int) ceil(count($all) / $count))), $count, []);
        }
        foreach ($new as $key) {
            $sizes = array_map('count', $columns);
            $columns[array_search(min($sizes), $sizes, true)][] = $key;
        }
        return $columns;
    }

    public static function order(array $columns): array
    {
        $order = [];
        foreach ($columns as $index => $column) {
            if ($index) $order[] = '|';
            array_push($order, ...$column);
        }
        return $order;
    }

    public static function save(array $data, array $order, array $hidden, int $count): array
    {
        $data = self::read($data);
        $data['layouts'][$count] = $order;
        ksort($data['layouts']);
        if ($count === 3) $data['order'] = $order;
        $data['hidden'] = array_values(array_unique($hidden));
        return $data;
    }
}
