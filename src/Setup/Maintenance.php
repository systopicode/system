<?php

declare(strict_types=1);

namespace Systopic\System\Setup;

use Systopic\Db\Connection;
use Systopic\System\Config\Config;
use Systopic\System\Config\Layout;
use Systopic\System\Config\Schema;

/**
 * The setup of an installation that already has one: config.local.json of
 * this machine, whatever state the project is in. Reached with the token sync
 * writes (Setup::maintenance()), see .cursor/docs/config-struktur.md, "Setup
 * über Sync".
 *
 * The form comes from the definitions: every secret, and every value that may
 * be overridden here. Whatever else the file holds is a block of JSON below
 * them. A secret never reaches the page: it says whether there is one, and an
 * empty field keeps it.
 *
 * Saving writes the new layout and spends the token.
 */
final class Maintenance
{
    /** @var list<string> */
    private array $errors = [];

    /** the names a secret goes by - also for keys no definition knows */
    private const SECRET_KEYS = ['pass', 'password', 'secret', 'apiKey'];

    public function __construct(
        private readonly Setup $setup,
        private readonly string $root,
        private readonly Config $config,
    ) {}

    public function handle(): void
    {
        $file = $this->file();
        $current = self::lift(Setup::readJson($file) ?? []);
        $fields = $this->fields();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $saved = $this->save($current, $fields);
            if ($saved !== null) {
                if (!Setup::writeJson($file, $saved)) {
                    $this->errors[] = 'cannot write config/config.local.json';
                } else {
                    $this->setup->finish();
                    $this->setup->page('Saved', '<p>config/config.local.json is written, the setup link is spent.</p><p><a href="./">Open the site</a></p>');
                    return;
                }
            }
        }
        $this->form($current, $fields);
    }

    private function file(): string
    {
        return $this->root . '/config/config.local.json';
    }

    /**
     * What the form offers.
     *
     * @return array<string, array{package: string, path: list<string>, label: string, definition: array<string, mixed>}> field name => field
     */
    private function fields(): array
    {
        $fields = [];
        foreach (['db', 'system', 'relay'] as $package) {
            $tree = $this->config->package($package);
            foreach (Schema::load($package) as $definition) {
                if (empty($definition['x-optional']) || $tree !== []) {
                    $this->walk($package, $definition, [], $tree, $fields);
                }
            }
        }
        return $fields;
    }

    /**
     * @param array<string, mixed> $node
     * @param list<string>         $path
     * @param array<string, mixed> $tree
     * @param array<string, array<string, mixed>> $fields
     */
    private function walk(string $package, array $node, array $path, array $tree, array &$fields): void
    {
        foreach ($node['properties'] ?? [] as $key => $child) {
            $here = [...$path, (string) $key];
            if (is_array($child['additionalProperties'] ?? null)) {
                foreach (is_array($tree[$key] ?? null) ? $tree[$key] : [] as $name => $entry) {
                    $this->walk($package, $child['additionalProperties'], [...$here, (string) $name], is_array($entry) ? $entry : [], $fields);
                }
            } elseif (isset($child['properties'])) {
                if (empty($child['x-optional']) || isset($tree[$key])) {
                    $this->walk($package, $child, $here, is_array($tree[$key] ?? null) ? $tree[$key] : [], $fields);
                }
            } elseif (!empty($child['writeOnly']) || !empty($child['x-overridable'])) {
                $fields[$package . '.' . implode('.', $here)] = [
                    'package' => $package,
                    'path' => $here,
                    'label' => ucfirst($package) . ($path === [] ? '' : ' ' . implode(' ', array_diff($path, ['connections']))) . ': ' . (string) ($child['title'] ?? $key),
                    'definition' => $child,
                ];
            }
        }
    }

    /**
     * The file in the new layout. Lifting it here also ends the old block in
     * it: a value of the form and one left at the old place would disagree.
     *
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    private static function lift(array $file): array
    {
        $packages = Layout::packages($file);
        foreach (Layout::MOVED as $key) {
            unset($file[$key]);
        }
        foreach (Layout::SYSTEM_CONSTANTS as $name) {
            unset($file['constants'][$name]);
        }
        if (($file['constants'] ?? null) === []) {
            unset($file['constants']);
        }
        unset($file['packages']);
        return $packages === [] ? $file : $file + ['packages' => $packages];
    }

    /** @param list<string> $path */
    private static function value(array $tree, array $path): mixed
    {
        foreach ($path as $key) {
            if (!is_array($tree) || !array_key_exists($key, $tree)) {
                return null;
            }
            $tree = $tree[$key];
        }
        return $tree;
    }

    /** @param list<string> $path */
    private static function put(array &$tree, array $path, mixed $value): void
    {
        $node = &$tree;
        foreach ($path as $key) {
            if (!is_array($node[$key] ?? null)) {
                $node[$key] = [];
            }
            $node = &$node[$key];
        }
        $node = $value;
    }

    /** @param list<string> $path */
    private static function drop(array &$tree, array $path): void
    {
        $key = array_shift($path);
        if (!array_key_exists($key, $tree)) {
            return;
        }
        if ($path === []) {
            unset($tree[$key]);
            return;
        }
        if (is_array($tree[$key])) {
            self::drop($tree[$key], $path);
            if ($tree[$key] === []) {
                unset($tree[$key]);
            }
        }
    }

    /**
     * The file in two halves: what the block of JSON shows, and what stays off
     * the page - the fields, and every secret, known to a definition or not.
     *
     * @param array<string, mixed> $file
     * @param array<string, array<string, mixed>> $fields
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} shown, kept
     */
    private static function split(array $file, array $fields): array
    {
        $kept = [];
        foreach ($fields as $field) {
            $path = ['packages', $field['package'], ...$field['path']];
            $value = self::value($file, $path);
            if ($value !== null) {
                self::put($kept, $path, $value);
                self::drop($file, $path);
            }
        }
        self::hideSecrets($file, [], $kept);
        return [$file, $kept];
    }

    /** @param list<string> $path */
    private static function hideSecrets(array &$node, array $path, array &$kept): void
    {
        foreach ($node as $key => &$value) {
            if (is_array($value)) {
                self::hideSecrets($value, [...$path, (string) $key], $kept);
                if ($value === []) {
                    unset($node[$key]);
                }
            } elseif (in_array($key, self::SECRET_KEYS, true)) {
                self::put($kept, [...$path, (string) $key], $value);
                unset($node[$key]);
            }
        }
    }

    /**
     * The new file - NULL when something in the form is wrong.
     *
     * @param array<string, mixed> $current
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, mixed>|null
     */
    private function save(array $current, array $fields): ?array
    {
        $rest = trim((string) ($_POST['rest'] ?? ''));
        $shown = $rest === '' ? [] : json_decode($rest, true);
        if (!is_array($shown)) {
            $this->errors[] = 'the block of JSON is no JSON object';
            return null;
        }
        [, $kept] = self::split($current, $fields);
        $file = Config::merge($shown, $kept);
        $posted = is_array($_POST['f'] ?? null) ? $_POST['f'] : [];
        foreach ($fields as $name => $field) {
            $path = ['packages', $field['package'], ...$field['path']];
            $raw = (string) ($posted[$name] ?? '');
            $definition = $field['definition'];
            if (!empty($definition['writeOnly'])) {
                if ($raw !== '') {
                    self::put($file, $path, $raw); // empty: the one that is there stays
                }
                continue;
            }
            $raw = trim($raw);
            if ($raw === '') {
                self::drop($file, $path); // empty: what the instance says
                continue;
            }
            if (($definition['type'] ?? '') === 'integer') {
                if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
                    $this->errors[] = $field['label'] . ': not a number';
                    continue;
                }
                $raw = (int) $raw;
            }
            if (isset($definition['enum']) && !in_array($raw, $definition['enum'], true)) {
                $this->errors[] = $field['label'] . ': not one of the choices';
                continue;
            }
            self::put($file, $path, $raw);
        }
        if ($this->errors !== [] || !$this->connects($current, $file)) {
            return null;
        }
        return $file;
    }

    /**
     * The database is tried before anything is written, when its access
     * changed. A password that does not work must not lock the site out. Only
     * `main`: every other connection may be down without the site noticing.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    private function connects(array $before, array $after): bool
    {
        $path = ['packages', 'db', 'connections', 'main'];
        $old = self::value($before, $path);
        $new = self::value($after, $path);
        if ($old === $new) {
            return true;
        }
        // the access as it will be: the instance file under what this file says
        $instance = Setup::readJson($this->root . '/config/' . $this->config->instance->name . '.config.json') ?? [];
        $shared = Layout::packages($instance)['db']['connections']['main'] ?? [];
        $access = array_merge($shared, is_array($new) ? $new : []);
        if (($access['driver'] ?? 'mysql') !== 'mysql') {
            return true;
        }
        try {
            Connection::open($access);
            return true;
        } catch (\Throwable $e) {
            $this->errors[] = 'not saved, the database does not answer: ' . $e->getMessage();
            return false;
        }
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, array<string, mixed>> $fields
     */
    private function form(array $current, array $fields): void
    {
        $h = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $posted = is_array($_POST['f'] ?? null) ? $_POST['f'] : [];
        $html = '';
        foreach ($fields as $name => $field) {
            $definition = $field['definition'];
            $input = 'f[' . $h($name) . ']';
            $local = self::value($current, ['packages', $field['package'], ...$field['path']]);
            if (!empty($definition['writeOnly'])) {
                $there = (string) self::value($this->config->package($field['package']), $field['path']) !== '';
                $html .= '<label><span>' . $h($field['label']) . ' - ' . ($there ? 'set, empty keeps it' : 'not set') . '</span>'
                    . '<input type="password" name="' . $input . '" autocomplete="new-password"></label>';
                continue;
            }
            $value = (string) ($posted[$name] ?? (is_scalar($local) ? $local : ''));
            $shared = self::value($this->config->package($field['package']), $field['path']);
            $hint = is_scalar($shared) ? (string) $shared : '';
            if (isset($definition['enum'])) {
                $options = '<option value="">from the instance' . ($hint === '' ? '' : ": {$h($hint)}") . '</option>';
                foreach ($definition['enum'] as $choice) {
                    $options .= '<option' . ((string) $choice === $value ? ' selected' : '') . '>' . $h((string) $choice) . '</option>';
                }
                $html .= '<label><span>' . $h($field['label']) . '</span><select name="' . $input . '">' . $options . '</select></label>';
                continue;
            }
            $html .= '<label><span>' . $h($field['label']) . '</span><input name="' . $input . '" value="' . $h($value)
                . '" placeholder="' . $h($hint) . '" autocomplete="off"></label>';
        }
        [$shown] = self::split($current, $fields);
        $rest = (string) ($_POST['rest'] ?? ($shown === [] ? '' : json_encode($shown, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
        $errors = $this->errors === [] ? '' : '<ul class="errors"><li>' . implode('</li><li>', array_map($h, $this->errors)) . '</li></ul>';
        $this->setup->page('Setup of ' . basename($this->root), <<<HTML
            <p>Installation <b>{$h($this->config->instance->name)}</b>. This writes <code>config/config.local.json</code>, on this machine only.
            An empty field falls back to the instance file, the grey value.</p>
            $errors
            <form method="post">
                {$this->setup->tokenField()}
                $html
                <label><span>Everything else in the file, as JSON</span>
                    <textarea name="rest" rows="8" spellcheck="false" style="width:100%;box-sizing:border-box;background:#000;color:#fff;border:1px solid #333;font:13px monospace">{$h($rest)}</textarea></label>
                <button>save</button>
            </form>
            HTML);
    }
}
