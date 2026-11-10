<?php

declare(strict_types=1);

namespace Systopic\System\Setup;

use Systopic\Db\Connection;
use Systopic\System\Config\Config;
use Systopic\System\Config\Layout;
use Systopic\System\Config\Schema;
use Systopic\System\Config\Tree;

/**
 * The setup of an installation that already has one: config.local.json of
 * this machine, whatever state the project is in. Reached with the token sync
 * writes (Setup::maintenance()), see .cursor/docs/config-struktur.md, "Setup
 * über Sync".
 *
 * Only what the installation cannot start without: the values the
 * definitions mark `x-setup` (its name, the access to the database `main`).
 * Everything else is set in the admin, Settings › Config, once the site
 * runs; saving here leaves it as it is. A secret never reaches the page: it
 * says whether there is one, and an empty field keeps it.
 *
 * Saving writes the new layout and spends the token.
 */
final class Maintenance
{
    /** @var list<string> */
    private array $errors = [];

    public function __construct(
        private readonly Setup $setup,
        private readonly string $root,
        private readonly Config $config,
    ) {}

    public function handle(): void
    {
        $file = $this->file();
        $current = Layout::lift(Setup::readJson($file) ?? []);
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
        // the name first, then the database
        foreach (['system', 'db'] as $package) {
            $tree = $this->config->package($package);
            $definition = Schema::load($package);
            if ($definition !== [] && (empty($definition['x-optional']) || $tree !== [])) {
                $this->walk($package, $definition, [], $tree, $fields);
            }
        }
        // of the connections only main: every other one may be down without the site noticing (connects())
        return array_filter($fields, static fn(string $name): bool => !str_starts_with($name, 'db.connections.') || str_starts_with($name, 'db.connections.main.'), ARRAY_FILTER_USE_KEY);
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
            } elseif (!empty($child['x-setup'])) {
                $fields[$package . '.' . implode('.', $here)] = [
                    'package' => $package,
                    'path' => $here,
                    // Installation; Database: Host (the connection main is the only one here)
                    'label' => ($path === [] ? '' : (string) (Schema::load($package)['title'] ?? ucfirst($package)) . ': ') . (string) ($child['title'] ?? $key),
                    'definition' => $child,
                ];
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
        // what the form does not show stays as it is
        $file = $current;
        $posted = is_array($_POST['f'] ?? null) ? $_POST['f'] : [];
        foreach ($fields as $name => $field) {
            if (!array_key_exists($name, $posted)) {
                continue; // a field the form did not send stays as it is
            }
            $path = ['packages', $field['package'], ...$field['path']];
            $raw = (string) ($posted[$name] ?? '');
            $definition = $field['definition'];
            if (!empty($definition['writeOnly'])) {
                if ($raw !== '') {
                    Tree::put($file, $path, $raw); // empty: the one that is there stays
                }
                continue;
            }
            $raw = trim($raw);
            if ($raw === '') {
                Tree::drop($file, $path); // empty: what config.json or the package says
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
            Tree::put($file, $path, $raw);
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
        $old = Tree::get($before, $path);
        $new = Tree::get($after, $path);
        if ($old === $new) {
            return true;
        }
        // the access as it will be: config.json under what this file says
        $project = Setup::readJson($this->root . '/config/config.json') ?? [];
        $shared = Layout::packages($project)['db']['connections']['main'] ?? [];
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
            $local = Tree::get($current, ['packages', $field['package'], ...$field['path']]);
            if (!empty($definition['writeOnly'])) {
                $there = (string) Tree::get($this->config->package($field['package']), $field['path']) !== '';
                $html .= '<label><span>' . $h($field['label']) . ' - ' . ($there ? 'set, empty keeps it' : 'not set') . '</span>'
                    . '<input type="password" name="' . $input . '" autocomplete="new-password"></label>';
                continue;
            }
            $value = (string) ($posted[$name] ?? (is_scalar($local) ? $local : ''));
            $shared = Tree::get($this->config->package($field['package']), $field['path']);
            $hint = is_scalar($shared) ? (string) $shared : '';
            if (isset($definition['enum'])) {
                $options = '<option value="">as below' . ($hint === '' ? '' : ": {$h($hint)}") . '</option>';
                foreach ($definition['enum'] as $choice) {
                    $options .= '<option' . ((string) $choice === $value ? ' selected' : '') . '>' . $h((string) $choice) . '</option>';
                }
                $html .= '<label><span>' . $h($field['label']) . '</span><select name="' . $input . '">' . $options . '</select></label>';
                continue;
            }
            $html .= '<label><span>' . $h($field['label']) . '</span><input name="' . $input . '" value="' . $h($value)
                . '" placeholder="' . $h($hint) . '" autocomplete="off"></label>';
        }
        $errors = $this->errors === [] ? '' : '<ul class="errors"><li>' . implode('</li><li>', array_map($h, $this->errors)) . '</li></ul>';
        $this->setup->page('Setup of ' . basename($this->root), <<<HTML
            {$this->databaseStatus()}
            $errors
            <form method="post">
                {$this->setup->tokenField()}
                $html
                <button>save</button>
            </form>
            HTML);
    }

    /** Does the database main answer, with what config.local.json says now? One line. */
    private function databaseStatus(): string
    {
        $h = static fn(string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $access = $this->config->connection();
        $where = (string) ($access['name'] ?? '') . ' on ' . (string) ($access['host'] ?? 'localhost');
        try {
            Connection::open($access);
            return '<p class="ok">✓ Database connected: ' . $h($where) . '</p>';
        } catch (\Throwable $e) {
            return '<p class="errors">✗ No database connection (' . $h($where) . '): ' . $h($e->getMessage()) . '</p>';
        }
    }
}
