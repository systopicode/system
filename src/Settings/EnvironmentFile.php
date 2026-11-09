<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

use Systopic\System\Config\Config;
use Systopic\System\Config\Explain;
use Systopic\System\Config\FileWriter;
use Systopic\System\Config\Layout;
use Systopic\System\Config\Reconcile;
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
    public const MATCH = ['serverName', 'serverIp', 'rootDir', 'path', 'site'];

    /** the file of the project, read here, never written */
    public const PROJECT = 'config.json';

    /** in ini, as in the definition: they would undo the debug switch */
    private const RESERVED_INI = ['error_log', 'display_errors', 'error_reporting'];

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
        $field = Schema::field($package, $path) ?? self::freeField($package, $path);
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

    /**
     * The secrets of the file running here to config.local.json. Local first: a
     * failure leaves them where they were, and a sync never takes one away.
     */
    public static function moveSecrets(string $file): bool
    {
        if ($file === self::LOCAL || !self::here($file)) {
            return false;
        }
        $data = self::read($file);
        $kept = $data;
        return self::updateLocal(static function (array $local) use (&$kept): array {
            Reconcile::moveSecrets($kept, $local);
            return $local;
        }) && self::update($file, static function (array $data): array {
            $ignored = [];
            Reconcile::moveSecrets($data, $ignored);
            return $data;
        });
    }

    /** A value of config.local.json that belongs to the instance file running here. */
    public static function moveToInstance(string $package, string $path): bool
    {
        $target = self::activeFile();
        $keys = ['packages', $package, ...explode('.', $path)];
        $value = Tree::get(self::read(self::LOCAL), $keys);
        if ($target === null || $target === self::LOCAL || $value === null) {
            return false;
        }
        return self::update($target, static function (array $data) use ($keys, $value): array {
            Tree::put($data, $keys, $value);
            return $data;
        }) && self::update(self::LOCAL, static function (array $data) use ($keys): array {
            Tree::drop($data, $keys);
            return $data;
        });
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

    /**
     * The layers of an instance, each the `packages` of its file: config.json,
     * the instance file, and config.local.json when the instance runs here.
     * For config.json itself only the project.
     *
     * @return array{project: array<string, mixed>, instance: array<string, mixed>|null, local: array<string, mixed>|null}
     */
    public static function layers(string $file): array
    {
        $project = (array) (self::readProject()['packages'] ?? []);
        if ($file === self::PROJECT) {
            return ['project' => $project, 'instance' => null, 'local' => null];
        }
        return [
            'project' => $project,
            'instance' => (array) (self::read($file)['packages'] ?? []),
            'local' => self::here($file) ? (array) (self::read(self::LOCAL)['packages'] ?? []) : null,
        ];
    }

    /** DEV, STAGING, LIVE …; empty for config.json */
    public static function stage(string $file): string
    {
        return $file === self::PROJECT || $file === self::LOCAL ? '' : strtoupper(explode('.', $file)[0]);
    }

    /**
     * config.json in the new layout.
     *
     * @return array<string, mixed>
     */
    public static function readProject(): array
    {
        $dir = self::directory();
        $path = $dir === null ? '' : $dir . DIRECTORY_SEPARATOR . self::PROJECT;
        if ($path === '' || !is_file($path)) {
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
     * Takes out of the instance file what only repeats what lies below it.
     * Only the file running here: another one may run other code, with other
     * defaults.
     *
     * @return list<string>|false what went
     */
    public static function prune(string $file): array|false
    {
        if ($file === self::LOCAL || !self::here($file)) {
            return false;
        }
        $layers = self::layers($file);
        $layers['local'] = null;
        $found = Explain::redundantIn($layers, 'instance', self::stage($file));
        if ($found === []) {
            return [];
        }
        $ok = self::update($file, static function (array $data) use ($found): array {
            foreach ($found as $value) {
                Tree::drop($data, ['packages', $value['package'], ...explode('.', $value['path'])]);
            }
            return $data;
        });
        return $ok ? array_map(static fn(array $v): string => $v['package'] . '.' . $v['path'], $found) : false;
    }

    /**
     * Takes out what nobody defines: all of it, or one (`package.path`). In
     * the instance file running here and in config.local.json. Another
     * instance may run code that still reads it.
     */
    public static function dropOrphans(string $file, ?string $only = null): bool
    {
        if ($file !== self::LOCAL && !self::here($file)) {
            return false;
        }
        $name = basename($file);
        return self::update($file, static fn(array $data): array => Reconcile::withoutOrphans($name, $data, $only === null ? null : [$only]));
    }

    /**
     * May this instance file go? Not while a rule points at it, not the LIVE
     * default, not the one running here.
     */
    public static function deletable(string $file): bool
    {
        if ($file === self::LOCAL || $file === self::PROJECT || self::path($file) === null || self::here($file)) {
            return false;
        }
        $data = self::read($file);
        $rules = array_filter((array) ($data['matches'] ?? []), static fn($rule): bool => is_array($rule) && array_filter($rule, static fn($v): bool => $v !== '' && $v !== []) !== []);
        return $rules === [] && !(self::stage($file) === 'LIVE' && !empty($data['default']));
    }

    public static function deleteInstance(string $file): bool
    {
        $path = self::path($file);
        return $path !== null && self::deletable($file) && unlink($path);
    }

    /** `default` of an instance file: taken when no rule matches. LIVE only. */
    public static function setDefault(string $file, bool $on): bool
    {
        if ($file === self::LOCAL || ($on && self::stage($file) !== 'LIVE')) {
            return false;
        }
        return self::update($file, static function (array $data) use ($on): array {
            if ($on) {
                $data['default'] = true;
            } else {
                unset($data['default']);
            }
            return $data;
        });
    }

    /**
     * A named entry of a block (`connections.revolver`), when the definition
     * says what a name may be (`propertyNames`). What is marked `x-entry-name`
     * starts with the name (a connection: its database).
     */
    public static function addEntry(string $file, string $package, string $path, string $name): bool
    {
        $node = self::node($package, $path);
        $pattern = (string) ($node['propertyNames']['pattern'] ?? '');
        $entry = $node['additionalProperties'] ?? null;
        $keys = ['packages', $package, ...explode('.', $path), $name];
        if ($file === self::LOCAL || $pattern === '' || !is_array($entry) || preg_match('~' . str_replace('~', '\~', $pattern) . '~', $name) !== 1 || Tree::get(self::read($file), $keys) !== null) {
            return false;
        }
        $start = [];
        foreach ($entry['properties'] ?? [] as $key => $field) {
            if (!empty($field['x-entry-name'])) {
                $start[$key] = $name;
            }
        }
        return self::update($file, static function (array $data) use ($keys, $start): array {
            Tree::put($data, $keys, $start === [] ? new \stdClass() : $start);
            return $data;
        });
    }

    /** Is it a named entry the definition requires (`connections.main`)? */
    public static function requiredEntry(string $package, array $path): bool
    {
        $name = array_pop($path);
        $node = self::node($package, implode('.', $path));
        return is_array($node['additionalProperties'] ?? null) && in_array($name, (array) ($node['required'] ?? []), true);
    }

    /**
     * The definition of a block, by its path; an entry through
     * additionalProperties, marked `x-entry`. null: nothing defines it.
     *
     * @return array<string, mixed>|null
     */
    private static function node(string $package, string $path): ?array
    {
        $node = Explain::definition($package);
        foreach ($path === '' ? [] : explode('.', $path) as $key) {
            $node = $node['properties'][$key] ?? (is_array($node['additionalProperties'] ?? null) ? $node['additionalProperties'] + ['x-entry' => true] : null);
            if (!is_array($node)) {
                return null;
            }
        }
        return $node;
    }

    /**
     * A block, out of the instance file and, when it runs here, out of
     * config.local.json too. Only optional blocks and named entries.
     */
    public static function removeBlock(string $file, string $package, string $path): bool
    {
        if ($file === self::LOCAL) {
            return false;
        }
        $node = self::node($package, $path);
        if ($node === null || empty($node['x-optional']) && empty($node['x-entry'])) {
            return false;
        }
        if (!empty($node['x-entry']) && self::requiredEntry($package, explode('.', $path))) {
            return false;
        }
        $keys = ['packages', $package, ...($path === '' ? [] : explode('.', $path))];
        $drop = static function (array $data) use ($keys): array {
            Tree::drop($data, $keys);
            return $data;
        };
        return self::update($file, $drop) && (!self::here($file) || self::path(self::LOCAL) === null || self::update(self::LOCAL, $drop));
    }

    /**
     * This machine takes a value of its own, starting with the one that counts
     * now. Only what may be overridden, never a secret.
     */
    public static function overrideLocal(string $file, string $package, string $path): bool
    {
        $field = Schema::field($package, $path) ?? self::freeField($package, $path);
        if ($field === null || empty($field[0]['x-overridable']) || !empty($field[0]['writeOnly']) || $file === self::LOCAL || !self::here($file)) {
            return false;
        }
        $layers = self::layers($file);
        $layers['local'] = null;
        $keys = explode('.', $path);
        $now = Explain::value($field[0], $layers, [$package, ...$keys], self::stage($file))['value'];
        return self::updateLocal(static function (array $data) use ($package, $keys, $now): array {
            Tree::put($data, ['packages', $package, ...$keys], $now ?? '');
            return $data;
        });
    }

    /**
     * A key of a block that takes anything (`ini`): a string, and it may be
     * overridden, like the block.
     *
     * @return array{0: array<string, mixed>, 1: string}|null
     */
    private static function freeField(string $package, string $path): ?array
    {
        $keys = explode('.', $path);
        $key = (string) end($keys);
        if (count($keys) !== 2 || !Schema::open($package, $path) || preg_match('~^[a-z][a-z0-9_]*$~', $key) !== 1 || in_array($key, self::RESERVED_INI, true)) {
            return null;
        }
        return [['type' => 'string', 'x-overridable' => true], 'project'];
    }

    /**
     * The file as it is written, config.json included: for the lines of its
     * keys (JsonLines). '' when it is not there.
     */
    public static function text(string $file): string
    {
        $path = self::source($file);
        return $path === null ? '' : (string) file_get_contents($path);
    }

    /** where a config file lies, config.json included; null when it is not there */
    public static function source(string $file): ?string
    {
        if ($file !== self::PROJECT) {
            return self::path($file);
        }
        $dir = self::directory();
        $path = $dir === null ? '' : $dir . DIRECTORY_SEPARATOR . self::PROJECT;
        return $path !== '' && is_file($path) ? $path : null;
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
    public static function update(string $file, callable $change, bool $unchangedIsFine = false): bool
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
            // the change and nothing else: defaults stay in the definitions
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return $json !== false && FileWriter::write($path, $json . "\n", 0600);
        });
    }
}
