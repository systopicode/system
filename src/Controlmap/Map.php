<?php

declare(strict_types=1);

namespace Systopic\System\Controlmap;

/**
 * The controlmap as the configs say it: who may build a panel, run its
 * actions, use a named right — read, merged and inherited, nothing stored.
 * Access asks it at runtime, admin/access/panelmap shows and edits it.
 *
 * **Layers**, per panel, the later replacing the earlier key by key:
 *
 *   1. `panel.config.json` in the panel's folder of each origin, in the
 *      order of the folders (`system`, `cms` …, `project`): the default of
 *      whoever ships the code
 *   2. `config/controlmap.json` of the project, by logical path: what this
 *      installation decides
 *
 * ```json
 * { "build":   ["cmsAdmin", "cmsEditor"],
 *   "actions": ["cmsEditor"],
 *   "action":  { "deletePage": ["cmsAdmin"] },
 *   "rights":  { "move": ["cmsAdmin", "cmsEditor"] },
 *   "linkable": ["activate", "reset"] }
 *
 * // config/controlmap.json
 * { "admin/pages": { "action": { "publish": ["cmsAdmin"] } } }
 * ```
 *
 * A list replaces the one below, it does not add to it: so a project can
 * narrow too, though there is only allowing. The values are role keys,
 * pseudo roles among them (`everyone`, `authenticated`, `anonymous`, see
 * Access) — except `linkable`: the actions that may be opened by a link
 * from anywhere, the panel's own, not inherited (`linkable()`).
 *
 * **Inheritance**, when the panel's layers say nothing:
 *
 *   build         its own, else the parent's build
 *   action.x      its own, else the panel's actions, else its build, else
 *                 the parent's actions (which goes on the same way)
 *   actions       the panel's actions, else its build, else the parent's
 *   right.x       its own, else the parent's right.x … up to the root;
 *                 none at all: whoever may run the panel's actions
 *
 * The root (path '', `panels/panel.config.json`) is the parent of the
 * top-level panels and holds the default (`build: authenticated`). Access
 * registers it; a map from Discover alone has no root. Nothing anywhere:
 * null.
 */
final class Map
{
    public const FILE = 'panel.config.json';

    /** @var array<string, DiscoveredPanel> path => panel */
    private array $byPath = [];

    /** @var array<string, mixed>|null the project's config/controlmap.json */
    private ?array $project = null;

    private ?string $projectError = null;

    /** @var array<string, array<string, Grant>> path => key => its own grant */
    private array $own = [];

    /**
     * @param list<DiscoveredPanel> $panels the top-level panels (Discover)
     * @param string                $projectFile config/controlmap.json of the project
     */
    public function __construct(array $panels, public readonly string $projectFile)
    {
        foreach ($panels as $top) {
            foreach ($top->all() as $panel) {
                $this->byPath[$panel->path] = $panel;
            }
        }
        [$this->project, $this->projectError] = self::read($projectFile);
        if ($this->project !== null) {
            $this->project = self::renamedPanelPaths($this->project);
        }
    }

    /** Resolve saved overrides and links from earlier administration routes. */
    public static function canonicalPanelPath(string $path): string
    {
        $renames = [
            'cms/users/permissions' => 'admin/access/content',
            'admin/users/permissions' => 'admin/access/content',
            'cms/users' => 'admin/access',
            'admin/users' => 'admin/access',
            'cms/controlmap' => 'admin/access/panelmap',
            'admin/controlmap' => 'admin/access/panelmap',
            'cms/auth' => 'auth',
            'admin/auth' => 'auth',
            'cms' => 'admin',
        ];
        foreach ($renames as $old => $new) {
            if ($path === $old || str_starts_with($path, $old . '/')) {
                return $new . substr($path, strlen($old));
            }
        }
        return $path;
    }

    private static function renamedPanelPaths(array $project): array
    {
        foreach ($project as $path => $entry) {
            $target = self::canonicalPanelPath((string) $path);
            if ($target === (string) $path) {
                continue;
            }
            if (!array_key_exists($target, $project)) {
                $project[$target] = $entry;
            }
            unset($project[$path]);
        }
        return $project;
    }

    /** Every panel of the installation (Discover) under the project's configs. */
    public static function ofRequest(): self
    {
        return new self(Discover::ofRequest()->panels(), self::projectFile());
    }

    /** config/controlmap.json of the running project. */
    public static function projectFile(): string
    {
        return rtrim(str_replace('\\', '/', (string) \fs::$projectRoot), '/') . '/config/controlmap.json';
    }

    /** Add a panel the constructor did not get — Access adds the ones a request builds, one by one. */
    public function register(DiscoveredPanel $panel): void
    {
        $this->byPath[$panel->path] ??= $panel;
    }

    public function panel(string $path): ?DiscoveredPanel
    {
        return $this->byPath[$path] ?? null;
    }

    /**
     * The layers of a panel, in order: each origin's panel.config.json, then
     * its entry in config/controlmap.json.
     *
     * @return list<array{layer: string, file: string, exists: bool, data: ?array, error: ?string}>
     */
    public function layers(DiscoveredPanel $panel): array
    {
        $out = [];
        foreach ($panel->dirs as $origin => $dir) {
            $file = "$dir/" . self::FILE;
            [$data, $error] = self::read($file);
            $out[] = ['layer' => $origin, 'file' => $file, 'exists' => is_file(\fs::toNative($file)), 'data' => $data, 'error' => $error];
        }
        $entry = $this->project[$panel->path] ?? null;
        $out[] = [
            'layer' => 'config',
            'file' => $this->projectFile,
            'exists' => $entry !== null,
            'data' => is_array($entry) ? $entry : null,
            'error' => $this->projectError ?? ($entry !== null && !is_array($entry) ? "\"{$panel->path}\" is not an object" : null),
        ];
        return $out;
    }

    /**
     * What the panel's own layers say, merged: key => grant. Keys: `build`,
     * `actions`, `action.<name>`, `right.<name>`.
     *
     * @return array<string, Grant>
     */
    public function own(DiscoveredPanel $panel): array
    {
        if (isset($this->own[$panel->path])) {
            return $this->own[$panel->path];
        }
        $own = [];
        foreach ($this->layers($panel) as $layer) {
            foreach (self::flatten($layer['data'] ?? []) as $key => $roles) {
                $own[$key] = new Grant($roles, $panel->path, $layer['layer'], $key);
            }
        }
        return $this->own[$panel->path] = $own;
    }

    /** Who may, for a key of the panel (see the class), or null: the code decides. */
    public function effective(DiscoveredPanel $panel, string $key): ?Grant
    {
        $own = $this->own($panel);
        $parent = $this->parent($panel);
        if ($key === 'build') {
            return $own['build'] ?? ($parent ? $this->effective($parent, 'build') : null);
        }
        if ($key === 'actions') {
            return $own['actions'] ?? $own['build'] ?? ($parent ? $this->effective($parent, 'actions') : null);
        }
        if (str_starts_with($key, 'action.')) {
            return $own[$key] ?? $this->effective($panel, 'actions');
        }
        if (str_starts_with($key, 'right.')) {
            for ($p = $panel; $p !== null; $p = $this->parent($p)) {
                if (isset($this->own($p)[$key])) {
                    return $this->own($p)[$key];
                }
            }
            return $this->effective($panel, 'actions');
        }
        throw new \InvalidArgumentException("controlmap: no key $key");
    }

    /** @return list<string> the named rights any layer of the panel or above it names */
    public function rights(DiscoveredPanel $panel): array
    {
        $rights = [];
        for ($p = $panel; $p !== null; $p = $this->parent($p)) {
            foreach (array_keys($this->own($p)) as $key) {
                if (str_starts_with($key, 'right.')) {
                    $rights[substr($key, 6)] = true;
                }
            }
        }
        return array_keys($rights);
    }

    /**
     * Every role key the controlmap names: in a layer of any panel, or in
     * a panel's own code (`hasRole('x')`, see ClassScanner).
     *
     * @return array<string, true>
     */
    public function roleKeys(): array
    {
        $keys = [];
        foreach ($this->byPath as $panel) {
            foreach ($this->own($panel) as $grant) {
                foreach ($grant->roles as $role) {
                    $keys[$role] = true;
                }
            }
            foreach ($panel->grants as $terms) {
                foreach (array_keys($terms) as $term) {
                    $keys[(string) $term] = true;
                }
            }
        }
        return $keys;
    }

    /** The panel above; a top-level panel's is the root (path ''), where it is registered. */
    private function parent(DiscoveredPanel $panel): ?DiscoveredPanel
    {
        if ($panel->path === '') {
            return null;
        }
        $slash = strrpos($panel->path, '/');
        return $this->byPath[$slash === false ? '' : substr($panel->path, 0, $slash)] ?? null;
    }

    /**
     * The actions of the panel that may be opened by a link from anywhere
     * (`linkable`, see ActionGuard): its own layers, the later replacing the
     * earlier. Not inherited, not role keys — action names.
     *
     * @return list<string>
     */
    public function linkable(DiscoveredPanel $panel): array
    {
        $names = [];
        foreach ($this->layers($panel) as $layer) {
            $list = $layer['data']['linkable'] ?? null;
            if (is_array($list) && array_is_list($list)) {
                $names = array_values(array_filter($list, 'is_string'));
            }
        }
        return $names;
    }

    /**
     * One layer's data as keys: `build`, `actions`, `action.x`, `right.x`
     * => role keys. What is not a list of strings is left out.
     *
     * @param array<string, mixed> $data
     * @return array<string, list<string>>
     */
    public static function flatten(array $data): array
    {
        $out = [];
        $roles = static fn($v): ?array => is_array($v) && array_is_list($v) && array_filter($v, 'is_string') === $v ? array_values($v) : null;
        foreach (['build', 'actions'] as $key) {
            if (($list = $roles($data[$key] ?? null)) !== null) {
                $out[$key] = $list;
            }
        }
        foreach (['action' => 'action', 'rights' => 'right'] as $section => $prefix) {
            foreach ((array) ($data[$section] ?? []) as $name => $value) {
                if (($list = $roles($value)) !== null) {
                    $out["$prefix.$name"] = $list;
                }
            }
        }
        return $out;
    }

    /** @return array<string, list<string>> what the project's layer (config/controlmap.json) says about the panel, by key */
    public function projectSays(DiscoveredPanel $panel): array
    {
        $entry = $this->project[$panel->path] ?? null;
        return is_array($entry) ? self::flatten($entry) : [];
    }

    /**
     * Set one key of a panel in the project's layer (config/controlmap.json)
     * — or, with null, take it out, so the panel inherits again. Sections
     * and panels left empty go; the panels stay sorted by path. Written to
     * a temporary file and moved, so a reader never sees half of it; left empty, the file goes.
     *
     * Only the project's layer is written: a package's panel.config.json is
     * the package's (and in a project a symlink into packages/).
     *
     * @param list<string>|null $roles
     * @throws \RuntimeException when the file is there but no JSON object
     */
    public function write(string $path, string $key, ?array $roles): void
    {
        if ($this->projectError !== null) {
            throw new \RuntimeException("controlmap: {$this->projectFile}: {$this->projectError}");
        }
        $data = $this->project ?? [];
        $entry = is_array($data[$path] ?? null) ? $data[$path] : [];
        [$section, $name] = match (true) {
            $key === 'build', $key === 'actions' => [$key, null],
            str_starts_with($key, 'action.') => ['action', substr($key, 7)],
            str_starts_with($key, 'right.') => ['rights', substr($key, 6)],
            default => throw new \InvalidArgumentException("controlmap: no key $key"),
        };
        $roles = $roles === null ? null : array_values(array_unique($roles));
        // an empty list of roles ("nobody") stays, an empty section goes
        if ($name === null) {
            if ($roles === null) {
                unset($entry[$section]);
            } else {
                $entry[$section] = $roles;
            }
        } else {
            $list = (array) ($entry[$section] ?? []);
            if ($roles === null) {
                unset($list[$name]);
            } else {
                $list[$name] = $roles;
            }
            ksort($list);
            if ($list === []) {
                unset($entry[$section]);
            } else {
                $entry[$section] = $list;
            }
        }
        if ($entry === []) {
            unset($data[$path]);
        } else {
            $data[$path] = $entry;
        }
        ksort($data);

        $file = \fs::toNative($this->projectFile);
        if ($data === []) {   // nothing decided here any more: no file
            if (is_file($file) && !unlink($file)) {
                throw new \RuntimeException("controlmap: cannot remove $file");
            }
            $this->project = null;
            $this->own = [];
            return;
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, $json) === false || !rename($tmp, $file)) {
            @unlink($tmp);
            throw new \RuntimeException("controlmap: cannot write $file");
        }
        $this->project = $data;
        $this->own = [];
    }

    /** @return array{0: ?array, 1: ?string} the decoded file, or null; an error when it is there but no JSON object */
    private static function read(string $file): array
    {
        $file = \fs::toNative($file);
        if (!is_file($file)) {
            return [null, null];
        }
        $raw = (string) file_get_contents($file);
        $raw = str_starts_with($raw, "\xEF\xBB\xBF") ? substr($raw, 3) : $raw;
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return [null, $e->getMessage()];
        }
        return is_array($data) && ($data === [] || !array_is_list($data)) ? [$data, null] : [null, 'not a JSON object'];
    }
}
