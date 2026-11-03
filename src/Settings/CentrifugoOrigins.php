<?php

declare(strict_types=1);
namespace Systopic\System\Settings;

/** Additive origin management. Pre-existing hub permissions are never removed. */
final class CentrifugoOrigins
{
    /** @return array{managed: list<string>, owners: array<string, list<string>>}|null */
    public static function read(string $path): ?array
    {
        if (!is_file($path)) return ['managed' => [], 'owners' => []];
        $data = json_decode((string) @file_get_contents($path), true);
        if (!is_array($data) || !is_array($data['managed'] ?? null) || !is_array($data['owners'] ?? null)) return null;
        foreach ($data['managed'] as $origin) if (!is_string($origin)) return null;
        foreach ($data['owners'] as $origins) {
            if (!is_array($origins)) return null;
            foreach ($origins as $origin) if (!is_string($origin)) return null;
        }
        return $data;
    }

    public static function change(string $configPath, string $registryPath, string $owner, string $origin, bool $add): bool
    {
        if (!preg_match('~^https?://(?:[a-z0-9.-]+|\[[a-f0-9:]+\])(?::[0-9]+)?$~iD', $origin)) return false;
        $lock = @fopen($registryPath . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) { if ($lock) fclose($lock); return false; }
        try {
            $registry = self::read($registryPath);
            $raw = @file_get_contents($configPath);
            $config = $raw === false ? null : json_decode($raw, true);
            if ($registry === null || !is_array($config) || !is_array($config['client']['allowed_origins'] ?? null)) return false;
            $origins = $config['client']['allowed_origins'];
            $owned = $registry['owners'][$owner] ?? [];
            if ($add) {
                if (!in_array($origin, $owned, true)) $owned[] = $origin;
                if (!in_array($origin, $origins, true)) {
                    $origins[] = $origin;
                    if (!in_array($origin, $registry['managed'], true)) $registry['managed'][] = $origin;
                }
                $registry['owners'][$owner] = $owned;
            } else {
                if (!in_array($origin, $owned, true)) return false;
                $registry['owners'][$owner] = array_values(array_diff($owned, [$origin]));
                $used = false;
                foreach ($registry['owners'] as $other) if (in_array($origin, $other, true)) $used = true;
                if (!$used && in_array($origin, $registry['managed'], true)) {
                    $origins = array_values(array_diff($origins, [$origin]));
                    $registry['managed'] = array_values(array_diff($registry['managed'], [$origin]));
                }
            }
            $config['client']['allowed_origins'] = $origins;
            $oldRegistry = is_file($registryPath) ? (string) file_get_contents($registryPath) : null;
            if (!self::write($registryPath, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))) return false;
            if (self::write($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))) return true;
            if ($oldRegistry === null) @unlink($registryPath); else self::write($registryPath, $oldRegistry);
            return false;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private static function write(string $path, string $raw): bool
    {
        $temp = @tempnam(dirname($path), '.origins-');
        if ($temp === false) return false;
        $perms = is_file($path) ? (fileperms($path) & 0777) : 0600;
        if (file_put_contents($temp, $raw . "\n") === false || !chmod($temp, $perms) || !rename($temp, $path)) {
            @unlink($temp); return false;
        }
        return true;
    }
}
