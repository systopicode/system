<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

/**
 * The instance files in `<project>/config`: `DEV.machine.config.json`,
 * `STAGING.config.json`, `LIVE.config.json`, and `config.local.json`.
 * Values are written back as JSON. Nothing the form does not know is dropped.
 */
final class EnvironmentFile
{
    /** @var array<string, array<string, string>> group => field => string|bool|int */
    public const GROUPS = [
        'db' => ['host' => 'string', 'name' => 'string', 'user' => 'string', 'pass' => 'string'],
        'debug' => ['debug' => 'bool', 'render' => 'bool', 'time' => 'bool', 'displayErrors' => 'bool'],
        'mail' => [
            'host' => 'string', 'port' => 'int', 'secure' => 'string',
            'user' => 'string', 'password' => 'string', 'from' => 'string', 'fromName' => 'string',
        ],
    ];

    /** @var list<string> */
    public const SCALARS = ['versionAppendix', 'versionDate'];

    /** @var list<string> */
    public const MATCH = ['serverName', 'serverIp', 'path', 'site'];

    public static function directory(): ?string
    {
        $dir = rtrim(\fs::toNative((string) \fs::$projectRoot . 'config'), '\\/');
        return is_dir($dir) ? $dir : null;
    }

    /**
     * @return list<array{name: string, file: string, path: string, current: bool}>
     */
    public static function files(): array
    {
        $dir = self::directory();
        if ($dir === null) {
            return [];
        }
        $current = (string) (\Systopic\System\Config\Config::current()?->instance->name ?? '');
        $found = glob($dir . '/*.config.json') ?: [];
        sort($found, SORT_STRING);
        $files = [];
        foreach ($found as $path) {
            $base = basename($path);
            if (!str_ends_with($base, '.config.json') || $base === 'config.json') {
                continue;
            }
            $name = substr($base, 0, -strlen('.config.json'));
            if ($name === '' || $name === 'config') {
                continue;
            }
            $files[] = [
                'name' => $name,
                'file' => $base,
                'path' => $path,
                'current' => $name === $current,
            ];
        }
        $local = $dir . '/config.local.json';
        if (is_file($local)) {
            $files[] = [
                'name' => 'This machine',
                'file' => 'config.local.json',
                'path' => $local,
                'current' => true,
            ];
        }
        return $files;
    }

    /** @return array<string, mixed> */
    public static function read(string $file): array
    {
        $path = self::path($file);
        if ($path === null) {
            return [];
        }
        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        return is_array($data) ? $data : [];
    }

    public static function set(string $file, string $group, string $key, string $raw): bool
    {
        $data = self::read($file);
        if ($group === '') {
            if (!in_array($key, self::SCALARS, true) && !self::isExtraScalar($data, $key)) {
                return false;
            }
            $data[$key] = $raw;
            return self::write($file, $data);
        }
        $kind = self::GROUPS[$group][$key] ?? null;
        if ($kind === null) {
            return false;
        }
        $bag = is_array($data[$group] ?? null) ? $data[$group] : [];
        $bag[$key] = self::cast($raw, $kind);
        $data[$group] = $bag;
        return self::write($file, $data);
    }

    public static function setMatch(string $file, int $index, string $field, string $raw): bool
    {
        if (!in_array($field, self::MATCH, true)) {
            return false;
        }
        $data = self::read($file);
        $rules = array_values(is_array($data['matches'] ?? null) ? $data['matches'] : []);
        if (!isset($rules[$index]) || !is_array($rules[$index])) {
            return false;
        }
        if (trim($raw) === '') {
            unset($rules[$index][$field]);
        } else {
            $rules[$index][$field] = $raw;
        }
        $data['matches'] = array_values($rules);
        return self::write($file, $data);
    }

    public static function addMatch(string $file): bool
    {
        $data = self::read($file);
        $rules = array_values(is_array($data['matches'] ?? null) ? $data['matches'] : []);
        $rules[] = ['serverName' => '', 'path' => ''];
        $data['matches'] = $rules;
        return self::write($file, $data);
    }

    public static function removeMatch(string $file, int $index): bool
    {
        $data = self::read($file);
        $rules = array_values(is_array($data['matches'] ?? null) ? $data['matches'] : []);
        if (!isset($rules[$index])) {
            return false;
        }
        array_splice($rules, $index, 1);
        $data['matches'] = $rules;
        return self::write($file, $data);
    }

    public static function path(string $file): ?string
    {
        $dir = self::directory();
        $base = basename($file);
        if ($dir === null || !preg_match('/^(?:[A-Za-z0-9][A-Za-z0-9._-]*\.config\.json|config\.local\.json)$/', $base)) {
            return null;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $base;
        return is_file($path) ? $path : null;
    }

    /** @param array<string, mixed> $data */
    private static function isExtraScalar(array $data, string $key): bool
    {
        if (!array_key_exists($key, $data) || is_array($data[$key])) {
            return false;
        }
        return !in_array($key, ['matches', 'db', 'debug', 'mail', ...self::SCALARS], true);
    }

    private static function cast(string $raw, string $kind): string|int|bool
    {
        return match ($kind) {
            'bool' => $raw === '1' || strcasecmp($raw, 'true') === 0,
            'int' => (int) $raw,
            default => $raw,
        };
    }

    /** @param array<string, mixed> $data */
    private static function write(string $file, array $data): bool
    {
        $path = self::path($file);
        if ($path === null) {
            return false;
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }
        return file_put_contents($path, $json . "\n") !== false;
    }
}
