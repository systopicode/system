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
            'testMode' => 'bool', 'testRecipient' => 'string',
        ],
        'auth' => [
            'deviceDays' => 'int',
            'screenDays' => 'nullint',
            'defaultSignInMethod' => 'string',
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
                'name' => 'Local overrides',
                'file' => 'config.local.json',
                'path' => $local,
                'current' => true,
            ];
        }
        return $files;
    }

    /** The config the sign-in default is written to: this server, else this machine. */
    public static function activeFile(): ?string
    {
        $local = null;
        foreach (self::files() as $info) {
            if (!$info['current']) {
                continue;
            }
            if ($info['file'] !== 'config.local.json') {
                return $info['file'];
            }
            $local = $info['file'];
        }
        return $local;
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
        return self::update($file, static function (array $data) use ($group, $key, $raw): ?array {
            if ($group === '') {
                if (!in_array($key, self::SCALARS, true) && !self::isExtraScalar($data, $key)) {
                    return null;
                }
                $data[$key] = $raw;
                return $data;
            }
            $kind = self::GROUPS[$group][$key] ?? null;
            if ($kind === null) {
                return null;
            }
            // Reject invalid values instead of silently casting them to zero/false.
            if ($kind === 'bool' && !in_array(strtolower($raw), ['0', '1', 'true', 'false'], true)) return null;
            if (in_array($kind, ['int', 'nullint'], true) && !($kind === 'nullint' && trim($raw) === '')) {
                if (!preg_match('/^[0-9]+$/D', $raw) || filter_var($raw, FILTER_VALIDATE_INT) === false || (int) $raw < 1) return null;
                if ($group === 'mail' && $key === 'port' && (int) $raw > 65535) return null;
            }
            if ($group === 'mail' && $key === 'secure' && !in_array($raw, ['tls', 'ssl', 'none'], true)) return null;
            if ($group === 'mail' && $key === 'from' && $raw !== '' && !filter_var($raw, FILTER_VALIDATE_EMAIL)) return null;
            if ($group === 'auth' && $key === 'defaultSignInMethod' && !in_array($raw, ['', 'code', 'device_code', 'passkey', 'password'], true)) return null;
            if ($group === 'mail' && $key === 'testRecipient') {
                $raw = trim($raw);
                if ($raw !== '' && !filter_var($raw, FILTER_VALIDATE_EMAIL)) return null;
            }
            $bag = is_array($data[$group] ?? null) ? $data[$group] : [];
            $bag[$key] = self::cast($raw, $kind);
            $data[$group] = $bag;
            return $data;
        });
    }

    public static function resetMail(string $file): bool
    {
        return self::update($file, static function (array $data): array {
            $mail = is_array($data['mail'] ?? null) ? $data['mail'] : [];
            $data['mail'] = array_diff_key($mail, self::GROUPS['mail']) + [
                'host' => \Systopic\System\Mail\Delivery::DEFAULT_HOST,
                'port' => 587,
                'secure' => 'tls',
                'user' => \Systopic\System\Mail\Delivery::DEFAULT_SENDER,
                'from' => \Systopic\System\Mail\Delivery::DEFAULT_SENDER,
                'fromName' => 'fluxo',
            ];
            return $data;
        });
    }

    public static function setMatch(string $file, int $index, string $field, string $raw): bool
    {
        if (!in_array($field, self::MATCH, true)) {
            return false;
        }
        if ($field === 'serverIp' && trim($raw) !== '' && !filter_var(trim($raw), FILTER_VALIDATE_IP)) return false;
        return self::update($file, static function (array $data) use ($index, $field, $raw): ?array {
            $rules = array_values(is_array($data['matches'] ?? null) ? $data['matches'] : []);
            if (!isset($rules[$index]) || !is_array($rules[$index])) {
                return null;
            }
            if (trim($raw) === '') {
                unset($rules[$index][$field]);
            } else {
                $rules[$index][$field] = $raw;
            }
            $data['matches'] = array_values($rules);
            return $data;
        });
    }

    public static function addMatch(string $file): bool
    {
        return self::update($file, static function (array $data): array {
            $rules = array_values(is_array($data['matches'] ?? null) ? $data['matches'] : []);
            $rules[] = ['serverName' => '', 'path' => ''];
            $data['matches'] = $rules;
            return $data;
        });
    }

    /**
     * Point this environment at the hub on this machine. The block is the
     * hub's own key. It is not returned and must not be shown.
     *
     * @param array{api: string, apiKey: string, secret: string, client: string, plainTopics: bool} $relay
     */
    public static function connectRelay(string $file, array $relay): bool
    {
        return self::update($file, static function (array $data) use ($relay): array {
            $data['relay'] = $relay;
            return $data;
        }) ?? false;
    }

    /** Switch this environment only; the shared hub process is untouched. */
    public static function enableRelay(string $file, bool $enabled): bool
    {
        return self::update($file, static function (array $data) use ($enabled): ?array {
            if (!is_array($data['relay'] ?? null) || empty($data['relay']['api']) || empty($data['relay']['secret'])) {
                return null;
            }
            $data['relay']['enabled'] = $enabled;
            return $data;
        });
    }

    /** Drop the sign-in key. Lifetimes stay. Existing codes stop verifying. */
    public static function removeAuth(string $file): bool
    {
        return self::update($file, static function (array $data): array {
            $auth = is_array($data['auth'] ?? null) ? $data['auth'] : [];
            unset($auth['secret']);
            if ($auth === []) {
                unset($data['auth']);
            } else {
                $data['auth'] = $auth;
            }
            return $data;
        }) ?? false;
    }

    /**
     * Writes the auth block when it is missing, and a sign-in key when there
     * is none. `$replace` rotates a key that is already there. The key is
     * random and is not returned.
     */
    public static function createAuth(string $file, bool $replace = false): bool
    {
        return self::update($file, static function (array $data) use ($replace): ?array {
            $auth = is_array($data['auth'] ?? null) ? $data['auth'] : [];
            $secret = (string) ($auth['secret'] ?? '');
            if (strlen($secret) >= 32 && !$replace) {
                return null;
            }
            if (!array_key_exists('deviceDays', $auth)) {
                $auth['deviceDays'] = \Systopic\System\Auth\AuthConfig::DEVICE_DAYS;
            }
            if (!array_key_exists('screenDays', $auth)) {
                $auth['screenDays'] = null;
            }
            $auth['secret'] = bin2hex(random_bytes(32));
            $data['auth'] = $auth;
            return $data;
        });
    }

    public static function removeMatch(string $file, int $index): bool
    {
        return self::update($file, static function (array $data) use ($index): ?array {
            $rules = array_values(is_array($data['matches'] ?? null) ? $data['matches'] : []);
            if (!isset($rules[$index])) {
                return null;
            }
            array_splice($rules, $index, 1);
            $data['matches'] = $rules;
            return $data;
        });
    }

    public static function path(string $file): ?string
    {
        $dir = self::directory();
        $base = basename($file);
        if ($file !== $base || is_link(($dir ?? '') . DIRECTORY_SEPARATOR . $base)) return null;
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

    private static function cast(string $raw, string $kind): string|int|bool|null
    {
        return match ($kind) {
            'bool' => $raw === '1' || strcasecmp($raw, 'true') === 0,
            'int' => (int) $raw,
            'nullint' => trim($raw) === '' ? null : max(1, (int) $raw),
            default => $raw,
        };
    }

    /**
     * Read, change, write as one step: every field saves on its own request,
     * and two overlapping saves would otherwise each write back the state they
     * read - the later one dropping the earlier value. The lock lives outside
     * config/ (the folder is synced); the file is replaced by rename, so a
     * reader never sees it half written. A file that does not parse is left
     * alone instead of being overwritten with what the form knows.
     *
     * @param callable(array<string, mixed>): ?array<string, mixed> $change null = nothing to write
     */
    private static function update(string $file, callable $change): bool
    {
        $path = self::path($file);
        if ($path === null) {
            return false;
        }
        return \Systopic\System\Config\FileWriter::locked($path, static function () use ($path, $change): bool {
            $raw = file_get_contents($path);
            try {
                $data = $raw === false ? null : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $data = null;
            }
            if (!is_array($data)) {
                return false;
            }
            $data = $change($data);
            if ($data === null) {
                return false;
            }
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return $json !== false && \Systopic\System\Config\FileWriter::write($path, $json . "\n", 0600);
        });
    }
}
