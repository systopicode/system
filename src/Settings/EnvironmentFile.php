<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

use Systopic\System\Config\Config;
use Systopic\System\Config\FileWriter;
use Systopic\System\Config\Layout;
use Systopic\System\Config\Schema;
use Systopic\System\Config\Tree;

/**
 * The instance files in `<project>/config`: `DEV.machine.config.json`,
 * `STAGING.config.json`, `LIVE.config.json`, and `config.local.json`.
 *
 * What may be written is what the definitions say (Config\Schema), under
 * `packages.<package>`. A file in the old layout moves to the new one with its
 * first write, whatever else it holds stays. A secret goes to config.local.json
 * of this machine, whichever file the form shows: it never travels.
 */
final class EnvironmentFile
{
    public const LOCAL = 'config.local.json';

    /** @var list<string> */
    public const MATCH = ['serverName', 'serverIp', 'path', 'site'];

    /** the fields resetMail() fills in */
    private const MAIL_FIELDS = ['host', 'port', 'secure', 'user', 'from', 'fromName', 'testMode', 'testRecipient'];

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
        $current = (string) (Config::current()?->instance->name ?? '');
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
        $local = $dir . '/' . self::LOCAL;
        if (is_file($local)) {
            $files[] = [
                'name' => 'Local overrides',
                'file' => self::LOCAL,
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
            if ($info['file'] !== self::LOCAL) {
                return $info['file'];
            }
            $local = $info['file'];
        }
        return $local;
    }

    /** Does this file speak for the installation that is running? */
    public static function here(string $file): bool
    {
        foreach (self::files() as $info) {
            if ($info['file'] === $file) {
                return $info['current'];
            }
        }
        return false;
    }

    /**
     * The file in the new layout, whichever way it is written.
     *
     * @return array<string, mixed>
     */
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
        return is_array($data) ? Layout::lift($data) : [];
    }

    /**
     * What a package says in this file, with config.local.json over it when the
     * file is the one running here: what the secrets of an instance are now.
     *
     * @return array<string, mixed>
     */
    public static function effective(string $file, string $package): array
    {
        $tree = (array) (self::read($file)['packages'][$package] ?? []);
        if ($file !== self::LOCAL && self::here($file)) {
            $tree = Config::merge($tree, (array) (self::read(self::LOCAL)['packages'][$package] ?? []));
        }
        return $tree;
    }

    /**
     * May this file hold the field? An instance file what the installation
     * defines, config.local.json what may be overridden. Secrets are not
     * asked here: they have one place.
     *
     * @param array<string, mixed> $definition
     */
    public static function holds(string $file, array $definition, string $owner): bool
    {
        $overridable = !empty($definition['x-overridable']);
        return $file === self::LOCAL ? $overridable : $owner === 'machine' || $overridable;
    }

    /** One field from a form. Empty: the value goes, and what lies below it counts again. */
    public static function set(string $file, string $package, string $path, string $raw): bool
    {
        $field = Schema::field($package, $path);
        if ($field === null) {
            return false;
        }
        [$definition, $owner] = $field;
        $keys = ['packages', $package, ...explode('.', $path)];
        if (!empty($definition['writeOnly'])) {
            // a secret of another machine stays there; empty keeps the one there is
            if (!self::here($file)) {
                return false;
            }
            return $raw === '' || self::updateLocal(static function (array $data) use ($keys, $raw): array {
                Tree::put($data, $keys, $raw);
                return $data;
            });
        }
        if (!self::holds($file, $definition, $owner)) {
            return false;
        }
        $raw = trim($raw);
        if ($raw === '') {
            return self::update($file, static function (array $data) use ($keys): array {
                Tree::drop($data, $keys);
                return $data;
            });
        }
        [$valid, $value] = Schema::parse($definition, $raw);
        if (!$valid) {
            return false;
        }
        return self::update($file, static function (array $data) use ($keys, $value): array {
            Tree::put($data, $keys, $value);
            return $data;
        });
    }

    public static function resetMail(string $file): bool
    {
        return self::update($file, static function (array $data): array {
            $mail = (array) Tree::get($data, ['packages', 'system', 'mail']);
            Tree::put($data, ['packages', 'system', 'mail'], array_diff_key($mail, array_flip(self::MAIL_FIELDS)) + [
                'host' => \Systopic\System\Mail\Delivery::DEFAULT_HOST,
                'port' => 587,
                'secure' => 'tls',
                'user' => \Systopic\System\Mail\Delivery::DEFAULT_SENDER,
                'from' => \Systopic\System\Mail\Delivery::DEFAULT_SENDER,
                'fromName' => 'fluxo',
            ]);
            return $data;
        });
    }

    /** A value of a rule. Several, separated by commas, make a list. */
    public static function setMatch(string $file, int $index, string $field, string $raw): bool
    {
        if (!in_array($field, self::MATCH, true)) {
            return false;
        }
        $values = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn(string $v): bool => $v !== ''));
        if ($field === 'serverIp') {
            foreach ($values as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    return false;
                }
            }
        }
        return self::update($file, static function (array $data) use ($index, $field, $values): ?array {
            $rules = array_values(is_array($data['matches'] ?? null) ? $data['matches'] : []);
            if (!isset($rules[$index]) || !is_array($rules[$index])) {
                return null;
            }
            if ($values === []) {
                unset($rules[$index][$field]);
            } else {
                $rules[$index][$field] = count($values) === 1 ? $values[0] : $values;
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

    /**
     * Point this environment at the hub on this machine. The block is the
     * hub's own key. It is not returned and must not be shown. Its secrets go
     * to config.local.json like every other.
     *
     * @param array{api: string, apiKey: string, secret: string, client: string, plainTopics: bool} $relay
     */
    public static function connectRelay(string $file, array $relay): bool
    {
        $secrets = array_intersect_key($relay, ['apiKey' => true, 'secret' => true]);
        $shared = array_diff_key($relay, $secrets);
        return self::updateLocal(static function (array $data) use ($secrets): array {
            Tree::put($data, ['packages', 'relay'], Config::merge((array) Tree::get($data, ['packages', 'relay']), $secrets));
            return $data;
        }) && self::update($file, static function (array $data) use ($shared): array {
            Tree::put($data, ['packages', 'relay'], Config::merge((array) Tree::get($data, ['packages', 'relay']), $shared));
            return $data;
        });
    }

    /** Switch this environment only; the shared hub process is untouched. */
    public static function enableRelay(string $file, bool $enabled): bool
    {
        $relay = self::effective($file, 'relay');
        if (empty($relay['api']) || empty($relay['secret'])) {
            return false;
        }
        return self::update($file, static function (array $data) use ($enabled): array {
            Tree::put($data, ['packages', 'relay', 'enabled'], $enabled);
            return $data;
        });
    }

    /** The sign-in key of this installation: config.local.json, then a file that has not moved yet. */
    public static function authSecret(string $file): string
    {
        return (string) (self::effective($file, 'system')['auth']['secret'] ?? '');
    }

    /** Drop the sign-in key, wherever it lies. Lifetimes stay. Existing codes stop verifying. */
    public static function removeAuth(string $file): bool
    {
        $keys = ['packages', 'system', 'auth', 'secret'];
        $drop = static function (array $data) use ($keys): array {
            Tree::drop($data, $keys);
            return $data;
        };
        return (self::path(self::LOCAL) === null || self::update(self::LOCAL, $drop)) && self::update($file, $drop);
    }

    /**
     * A sign-in key for this installation when there is none. `$replace`
     * rotates one that is there. The key is random, goes to config.local.json
     * and is not returned.
     */
    public static function createAuth(string $file, bool $replace = false): bool
    {
        $keys = ['packages', 'system', 'auth', 'secret'];
        return self::updateLocal(static function (array $data) use ($file, $replace, $keys): ?array {
            // read inside the lock: two requests at once make one key
            $secret = (string) (Tree::get($data, $keys) ?? Tree::get(self::read($file), $keys) ?? '');
            if (!$replace && strlen($secret) >= 32) {
                return null;
            }
            Tree::put($data, $keys, bin2hex(random_bytes(32)));
            return $data;
        }, true) && (!$replace || self::dropFrom($file, $keys));
    }

    /** @param list<string> $keys */
    private static function dropFrom(string $file, array $keys): bool
    {
        if ($file === self::LOCAL) {
            return true;
        }
        return self::update($file, static function (array $data) use ($keys): array {
            Tree::drop($data, $keys);
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

    /**
     * config.local.json, which may not exist yet.
     *
     * @param callable(array<string, mixed>): ?array<string, mixed> $change
     * @param bool $unchangedIsFine a change that decides there is nothing to do
     */
    private static function updateLocal(callable $change, bool $unchangedIsFine = false): bool
    {
        $dir = self::directory();
        if ($dir === null) {
            return false;
        }
        if (self::path(self::LOCAL) === null && !FileWriter::write($dir . DIRECTORY_SEPARATOR . self::LOCAL, "{}\n", 0600)) {
            return false;
        }
        return self::update(self::LOCAL, $change, $unchangedIsFine);
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
    private static function update(string $file, callable $change, bool $unchangedIsFine = false): bool
    {
        $path = self::path($file);
        if ($path === null) {
            return false;
        }
        return FileWriter::locked($path, static function () use ($path, $change, $unchangedIsFine): bool {
            $raw = file_get_contents($path);
            try {
                $data = $raw === false ? null : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $data = null;
            }
            if (!is_array($data)) {
                return false;
            }
            $data = $change(Layout::lift($data));
            if ($data === null) {
                return $unchangedIsFine;
            }
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return $json !== false && FileWriter::write($path, $json . "\n", 0600);
        });
    }
}
