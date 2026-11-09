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
 * The two files in `<project>/config`: config.json, the project, and
 * config.local.json, this installation.
 *
 * What may be written is what the definition says (Config\Schema), under
 * `packages.<package>`, into the file the value belongs to: a value of the
 * project (`x-project`) into config.json, where the last change wins, be it
 * from here, git or sync; a value of the installation and every secret into
 * config.local.json. A value of the project that this installation already
 * overrides (`x-overridable`) is changed where it is overridden. A file in
 * the old layout moves to the new one with its first write, whatever else it
 * holds stays.
 */
final class EnvironmentFile
{
    /** this installation */
    public const LOCAL = 'config.local.json';

    /** the project: what is the same on every installation */
    public const PROJECT = 'config.json';

    /** in ini, as in the definition: they would undo the debug switch */
    private const RESERVED_INI = ['error_log', 'display_errors', 'error_reporting'];

    public static function directory(): ?string
    {
        $dir = rtrim(\fs::toNative((string) \fs::$projectRoot . 'config'), '\\/');
        return is_dir($dir) ? $dir : null;
    }

    /** The file the settings write, null without a config/ folder or a running config. */
    public static function activeFile(): ?string
    {
        return self::directory() !== null && Config::current() !== null ? self::LOCAL : null;
    }

    /**
     * A file in the new layout, whichever way it is written. [] when it is not
     * there or does not parse.
     *
     * @return array<string, mixed>
     */
    public static function read(string $file): array
    {
        $path = self::source($file);
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

    /** @return array<string, mixed> config.json in the new layout */
    public static function readProject(): array
    {
        return self::read(self::PROJECT);
    }

    /**
     * What both files say of a package, config.local.json over config.json.
     *
     * @return array<string, mixed>
     */
    public static function effective(string $package): array
    {
        return Config::merge((array) (self::readProject()['packages'][$package] ?? []), (array) (self::read(self::LOCAL)['packages'][$package] ?? []));
    }

    /**
     * The file a field is written to: a secret and a value of the
     * installation into config.local.json, a value of the project into
     * config.json, unless this installation overrides it already.
     *
     * @param array<string, mixed> $definition
     * @param list<string>         $keys packages, package, path
     */
    public static function target(array $definition, string $owner, array $keys): string
    {
        if (!empty($definition['writeOnly']) || $owner !== 'project') {
            return self::LOCAL;
        }
        $overridden = !empty($definition['x-overridable']) && Tree::get(self::read(self::LOCAL), $keys) !== null;
        return $overridden ? self::LOCAL : self::PROJECT;
    }

    /**
     * One field from a form, into config.local.json. Empty: the value goes,
     * and what lies below it counts again; a secret stays then.
     */
    public static function set(string $package, string $path, string $raw): bool
    {
        $field = Schema::field($package, $path) ?? self::freeField($package, $path);
        if ($field === null) {
            return false;
        }
        [$definition, $owner] = $field;
        $keys = ['packages', $package, ...explode('.', $path)];
        $file = self::target($definition, $owner, $keys);
        $write = static fn(callable $change): bool => $file === self::LOCAL ? self::updateLocal($change) : self::update(self::PROJECT, $change);
        if (!empty($definition['writeOnly'])) {
            return $raw === '' || self::updateLocal(static function (array $data) use ($keys, $raw): array {
                Tree::put($data, $keys, $raw);
                return $data;
            });
        }
        $raw = trim($raw);
        if ($raw === '') {
            // a required value is not emptied: the installation would lose its name
            if (($definition['x-mandatory'] ?? '') === 'input') {
                return false;
            }
            return $write(static function (array $data) use ($keys): array {
                Tree::drop($data, $keys);
                return $data;
            });
        }
        [$valid, $value] = Schema::parse($definition, $raw);
        if (!$valid) {
            return false;
        }
        return $write(static function (array $data) use ($keys, $value): array {
            Tree::put($data, $keys, $value);
            return $data;
        });
    }

    /**
     * Connects this installation to the hub with a block from the hub's own
     * config (CentrifugoHub::relayBlock()). The keys are not returned and
     * must not be shown.
     *
     * @param array{api: string, apiKey: string, secret: string, client: string, plainTopics: bool} $relay
     */
    public static function connectRelay(array $relay): bool
    {
        return self::updateLocal(static function (array $data) use ($relay): array {
            Tree::put($data, ['packages', 'relay'], Config::merge((array) Tree::get($data, ['packages', 'relay']), $relay));
            return $data;
        });
    }

    /** Switch this installation only; the shared hub process is untouched. */
    public static function enableRelay(bool $enabled): bool
    {
        $relay = self::effective('relay');
        if (empty($relay['api']) || empty($relay['secret'])) {
            return false;
        }
        return self::updateLocal(static function (array $data) use ($enabled): array {
            Tree::put($data, ['packages', 'relay', 'enabled'], $enabled);
            return $data;
        });
    }

    /** The sign-in key of this installation, '' when there is none. */
    public static function authSecret(): string
    {
        return (string) (self::effective('system')['auth']['secret'] ?? '');
    }

    /** Drop the sign-in key. Lifetimes stay. Existing codes stop verifying. */
    public static function removeAuth(): bool
    {
        return self::path(self::LOCAL) === null || self::updateLocal(static function (array $data): array {
            Tree::drop($data, ['packages', 'system', 'auth', 'secret']);
            return $data;
        });
    }

    /**
     * A sign-in key for this installation when there is none. `$replace`
     * rotates one that is there. The key is random and is not returned.
     */
    public static function createAuth(bool $replace = false): bool
    {
        $keys = ['packages', 'system', 'auth', 'secret'];
        return self::updateLocal(static function (array $data) use ($replace, $keys): ?array {
            // read inside the lock: two requests at once make one key
            if (!$replace && strlen((string) Tree::get($data, $keys)) >= 32) {
                return null;
            }
            Tree::put($data, $keys, bin2hex(random_bytes(32)));
            return $data;
        }, true);
    }

    /**
     * The layers: config.json and, unless only the project is asked for,
     * config.local.json. Each the `packages` of its file.
     *
     * @return array{project: array<string, mixed>, local: array<string, mixed>|null}
     */
    public static function layers(bool $projectOnly = false): array
    {
        return [
            'project' => (array) (self::readProject()['packages'] ?? []),
            'local' => $projectOnly ? null : (array) (self::read(self::LOCAL)['packages'] ?? []),
        ];
    }

    /** the stage of this installation: DEV, STAGING, LIVE … */
    public static function stage(): string
    {
        return (string) (Config::current()?->instance->stage ?? '');
    }

    /**
     * Takes out of config.local.json what only repeats what lies below it:
     * a default of the package or config.json.
     *
     * @return list<string>|false what went
     */
    public static function prune(): array|false
    {
        $found = Explain::redundantIn(self::layers(), 'local', self::stage());
        if ($found === []) {
            return [];
        }
        $ok = self::updateLocal(static function (array $data) use ($found): array {
            foreach ($found as $value) {
                Tree::drop($data, ['packages', $value['package'], ...explode('.', $value['path'])]);
            }
            return $data;
        });
        return $ok ? array_map(static fn(array $v): string => $v['package'] . '.' . $v['path'], $found) : false;
    }

    /**
     * Takes out of config.local.json what nobody defines: all of it, or one
     * (`package.path`). config.json is left alone: it counts for every
     * installation, another one may run code that still reads it.
     */
    public static function dropOrphans(?string $only = null): bool
    {
        return self::updateLocal(static fn(array $data): array => Reconcile::withoutOrphans(self::LOCAL, $data, $only === null ? null : [$only]));
    }

    /**
     * A named entry of a block (`connections.revolver`), when the definition
     * says what a name may be (`propertyNames`). What is marked `x-entry-name`
     * starts with the name (a connection: its database).
     */
    public static function addEntry(string $package, string $path, string $name): bool
    {
        $node = self::node($package, $path);
        $pattern = (string) ($node['propertyNames']['pattern'] ?? '');
        $entry = $node['additionalProperties'] ?? null;
        $keys = ['packages', $package, ...explode('.', $path), $name];
        if ($pattern === '' || !is_array($entry) || preg_match('~' . str_replace('~', '\~', $pattern) . '~', $name) !== 1
            || Tree::get(self::read(self::LOCAL), $keys) !== null || Tree::get(self::readProject(), $keys) !== null) {
            return false;
        }
        $start = [];
        $project = false;
        foreach ($entry['properties'] ?? [] as $key => $field) {
            if (!empty($field['x-entry-name'])) {
                $start[$key] = $name;
            }
            $project = $project || ($field['x-file'] ?? '') === 'project';
        }
        $change = static function (array $data) use ($keys, $start): array {
            Tree::put($data, $keys, $start === [] ? new \stdClass() : $start);
            return $data;
        };
        // an entry of the project (a language) goes to config.json
        return $project ? self::update(self::PROJECT, $change) : self::updateLocal($change);
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
     * A block, out of the files that have it. Only optional blocks and named
     * entries the definition does not require.
     */
    public static function removeBlock(string $package, string $path): bool
    {
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
        // out of every file that has it
        $done = false;
        foreach ([self::LOCAL, self::PROJECT] as $file) {
            if (Tree::get(self::read($file), $keys) !== null) {
                if (!self::update($file, $drop)) {
                    return false;
                }
                $done = true;
            }
        }
        return $done;
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
     * The file as it is written: for the lines of its keys (JsonLines). ''
     * when it is not there.
     */
    public static function text(string $file): string
    {
        $path = self::source($file);
        return $path === null ? '' : (string) file_get_contents($path);
    }

    /** where config.json or config.local.json lies; null when it is not there */
    public static function source(string $file): ?string
    {
        $dir = self::directory();
        if ($dir === null || !in_array($file, [self::PROJECT, self::LOCAL], true) || is_link($dir . DIRECTORY_SEPARATOR . $file)) {
            return null;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        return is_file($path) ? $path : null;
    }

    /** where a file that may be written lies, config.json or config.local.json; null when it is not there */
    public static function path(string $file = self::LOCAL): ?string
    {
        return self::source($file);
    }

    /**
     * Read, change, write config.local.json as one step, the file made when
     * it is missing.
     *
     * @param callable(array<string, mixed>): ?array<string, mixed> $change null = nothing to write
     * @param bool $unchangedIsFine a change that decides there is nothing to do
     */
    public static function updateLocal(callable $change, bool $unchangedIsFine = false): bool
    {
        $dir = self::directory();
        if ($dir === null) {
            return false;
        }
        if (self::path() === null && !FileWriter::write($dir . DIRECTORY_SEPARATOR . self::LOCAL, "{}\n", 0600)) {
            return false;
        }
        return self::update(self::LOCAL, $change, $unchangedIsFine);
    }

    /**
     * Read, change, write as one step: every field saves on its own request,
     * and two overlapping saves would otherwise each write back the state they
     * read - the later one dropping the earlier value. The lock lives outside
     * config/; the file is replaced by rename, so a reader never sees it half
     * written. A file that does not parse is left alone instead of being
     * overwritten with what the form knows, nor one with comments (JSONC).
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
