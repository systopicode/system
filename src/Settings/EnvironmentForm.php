<?php

declare(strict_types=1);

namespace Systopic\System\Settings;

use Systopic\System\Auth\Session;
use Systopic\System\Config\Explain;
use Systopic\System\Config\JsonLines;
use Systopic\System\Debug\Syscoder;
use Systopic\System\Config\Reconcile;
use Systopic\System\Config\Schema;
use Systopic\System\Config\Tree;
use Systopic\System\Panels\PanelNode;

/**
 * The configuration of this installation, built from the definitions of the
 * packages, in their order. Every value shows where it comes from: the
 * package, the project (config.json) or this installation
 * (config.local.json). Everything is shown, secrets never show. See
 * .cursor/plans/settings-gui.plan.md.
 *
 * One package at a time (the settings menu lists every package with a
 * definition, `?package=`), its blocks open and close. Leaving a field
 * writes that value, and nothing else, to the file it belongs to
 * (EnvironmentFile::target()): a value of the project to config.json, one of
 * the installation and every secret to config.local.json.
 */
final class EnvironmentForm
{
    private const SOURCE = [
        'package' => 'Package',
        'stage' => 'Package',
        'computed' => 'Automatic',
        'project' => 'Project',
        'local' => 'Installation',
        'none' => 'Not set',
    ];

    /**
     * The view being rendered.
     *
     * @var array{file: string, here: bool, project: bool, stage: string, layers: array<string, mixed>, data: array<string, mixed>, findings: array<string, list<array{status: string, file: string, owner: string}>>}
     */
    private static array $view = [];

    /** the main sections open, by their fold key (panel state, see fold()) */
    private static array $open = [];

    /** the package shown (panel state) */
    private static string $package = '';

    /**
     * The packages with a definition, in the order of Reconcile::PACKAGES:
     * each has its configuration in the settings menu.
     *
     * @return list<string>
     */
    public static function packages(): array
    {
        return array_values(array_filter(Reconcile::PACKAGES, static fn(string $package): bool => Schema::installed($package) && Schema::load($package) !== []));
    }

    /** the title of a package, from its definition */
    public static function packageTitle(string $package): string
    {
        return (string) (Schema::load($package)['title'] ?? ucfirst($package));
    }

    /** the icon of a package in the settings menu (`x-icon`), its classes */
    public static function packageIcon(string $package): string
    {
        $icon = (string) (Schema::load($package)['x-icon'] ?? '');
        return 'cmsIcon cmsIcon-' . (preg_match('/^[a-z0-9-]+$/', $icon) === 1 ? $icon : 'cube');
    }

    /** What the person keeps between calls: the panel's state. */
    private static function remember(PanelNode $panel): void
    {
        $state = $panel->state ?? null;
        self::$open = array_values(array_map('strval', (array) ($state->open ?? [])));
        self::$package = in_array((string) ($state->package ?? ''), self::packages(), true) ? (string) $state->package : (self::packages()[0] ?? '');
    }

    /**
     * Calls that only change what is shown, nothing in a file: a block
     * opened or closed (`fold`, the keys of all open ones). Nothing is drawn
     * again, the page did it already.
     */
    public static function viewState(PanelNode $panel): bool
    {
        if ($panel->action === 'fold') {
            $open = trim((string) \http::dataset('open'));
            $panel->state->open = $open === '' ? [] : array_values(array_unique(array_filter(explode(',', $open), static fn(string $key): bool => (bool) preg_match('/^[a-z][a-zA-Z0-9:._-]*$/', $key))));
            return true;
        }
        return false;
    }

    public static function render(PanelNode $panel): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        self::remember($panel);
        echo '<div class="projectFiles envSettings" data-package="' . $e(self::$package) . '">';
        echo \message::flush();
        if (EnvironmentFile::directory() === null) {
            echo '<p>No config folder was found.</p></div>';
            return;
        }
        self::view();
        echo '</div>';
    }

    /**
     * This installation side by side with what lies below it: self::$view.
     * The findings of both files, config.json as well: its rows say so.
     */
    private static function prepare(): void
    {
        $data = EnvironmentFile::read(EnvironmentFile::LOCAL);
        self::$view = [
            'file' => EnvironmentFile::LOCAL,
            'here' => true,
            'project' => false,
            'stage' => EnvironmentFile::stage(),
            'layers' => EnvironmentFile::layers(),
            'data' => $data,
            'findings' => [],
        ];
        foreach ([EnvironmentFile::PROJECT => EnvironmentFile::readProject(), EnvironmentFile::LOCAL => $data] as $file => $content) {
            foreach (Reconcile::findings($file, $content) as $finding) {
                self::$view['findings'][trim($finding['package'] . '.' . $finding['path'], '.')][] = ['status' => $finding['status'], 'file' => $file, 'owner' => $finding['owner']];
            }
        }
    }

    private static function view(): void
    {
        self::prepare();
        if (self::$package !== '') {
            self::package(self::$package);
        }
        // what belongs to no package of this installation: with the first one
        if (self::$package === (self::packages()[0] ?? '')) {
            self::foreign();
            self::rest();
        }
    }

    /**
     * The header of the page: open and close all blocks, Check with what
     * there is to look at in both files.
     */
    public static function controls(PanelNode $panel): void
    {
        self::remember($panel);
        if (EnvironmentFile::directory() === null) {
            return;
        }
        $count = count(self::checks());
        echo '<div class="rightAlign envControls">';
        echo '<button type="button" class="button transparent envFoldAll" data-open="1" title="Open all blocks"><i class="cmsIcon cmsIcon-chevron-down"></i><span>Open all</span></button>';
        echo '<button type="button" class="button transparent envFoldAll" data-open="0" title="Close all blocks"><i class="cmsIcon cmsIcon-chevron-up"></i><span>Close all</span></button>';
        echo '<a class="ajax button transparent envCheckButton" href="check/" title="What to look at in config.json and config.local.json: repeated, unknown and misplaced values, secrets, required values">'
            . '<i class="cmsIcon cmsIcon-list-check"></i><span>Check</span><b class="envBadge"' . ($count === 0 ? ' hidden' : '') . '>' . $count . '</b></a>';
        echo '</div>';
    }

    /**
     * The Check lightbox: every point of both files, each with what can be
     * done about it here.
     */
    public static function checkList(): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $checks = EnvironmentFile::directory() === null ? [] : self::checks();
        echo '<div class="cmslightbox cms envCheck" data-size="1"><section><header><h1>Check<small>config.json, config.local.json</small></h1>'
            . '<ul class="barmenu"><li><a class="ajax" href="../" aria-label="Close"><i class="cmsIcon cmsIcon-xmark"></i></a></li></ul></header><main>';
        echo \message::flush();
        if ($checks === []) {
            echo '<p class="envCheckEmpty"><i class="cmsIcon cmsIcon-circle-check"></i>Nothing to look at.</p>';
        } else {
            echo '<section class="widget is-list"><div class="tableScroll"><table class="envChecks"><tbody>';
            foreach ($checks as $check) {
                echo '<tr class="is-' . $e($check['kind']) . '"><td class="envCheckIcon"><i class="cmsIcon ' . $e($check['icon']) . '"></i></td><td><strong>' . $e($check['title']) . '</strong><p>' . $check['text'] . '</p></td><td class="envCheckAction">' . $check['action'] . '</td></tr>';
            }
            echo '</tbody></table></div></section>';
        }
        echo '<p class="tagsHint">Repeated values are marked “same as below” in the rows, unknown ones are struck through, misplaced and required ones carry a red note.</p>';
        echo '</main></section></div>';
    }

    /**
     * What the marks of the rows mean. Below the menu of the settings
     * (030_settings/pylon.tpl.php), while Environment is open.
     */
    public static function legend(): void
    {
        $item = static fn(string $mark, string $text): string => '<li>' . $mark . '<span>' . $text . '</span></li>';
        echo '<div class="envLegend"><h2 class="pylonGroupTitle">Where values come from</h2><ul>'
            . $item(self::chip('package'), 'default of the package')
            . $item(self::chip('project'), 'config.json, the project')
            . $item(self::chip('local'), 'config.local.json, this installation')
            . '</ul><h2 class="pylonGroupTitle">Marks</h2><ul>'
            . $item('<span class="envInfo"><i class="cmsIcon cmsIcon-circle-info"></i></span>', 'the definition: what it is')
            . $item('<span class="envInfo envPlace is-project"><i class="cmsIcon cmsIcon-circle-info"></i></span>', 'set in config.json')
            . $item('<span class="envInfo envPlace is-local"><i class="cmsIcon cmsIcon-circle-info"></i></span>', 'set in config.local.json')
            . $item('<i class="cmsIcon cmsIcon-database"></i>', 'travels with a backup')
            . '</ul><p>Without a backup mark a value stays on the installation. A click on an (i) opens it in its file.</p></div>';
    }

    /** ' envFold', ' is-open' as it was left: the classes of a main section */
    private static function foldClass(string $key): string
    {
        return ' envFold' . (in_array($key, self::$open, true) ? ' is-open' : '');
    }

    /** the chevron that opens and closes a main section (panel.js) */
    private static function foldToggle(string $key): string
    {
        $open = in_array($key, self::$open, true);
        return '<button type="button" class="envFoldToggle" aria-expanded="' . ($open ? 'true' : 'false') . '" title="Open or close"><i class="cmsIcon cmsIcon-chevron-right"></i></button>';
    }

    /**
     * What to look at in both files: values that only repeat what applies
     * anyway, values nobody defines, secrets and values in the wrong file,
     * packages not installed here, required values that are missing. Each
     * with what can be done about it here; the rows of the page carry the
     * marks.
     *
     * @return list<array{kind: string, icon: string, title: string, text: string, action: string}> text and action are HTML
     */
    public static function checks(): array
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $file = EnvironmentFile::LOCAL;
        self::prepare();
        $v = self::$view;
        $list = static fn(array $names): string => '<code>' . implode('</code>, <code>', array_map($e, $names)) . '</code>';
        $checks = [];
        if (!$v['project']) {
            $redundant = array_map(static fn(array $r): string => $r['package'] . '.' . $r['path'], Explain::redundantIn($v['layers'], 'local', $v['stage']));
            if ($redundant !== []) {
                $checks[] = [
                    'kind' => 'redundant',
                    'icon' => 'cmsIcon-copy',
                    'title' => count($redundant) . ' ' . (count($redundant) === 1 ? 'value repeats' : 'values repeat') . ' what applies anyway',
                    'text' => 'In <code>' . $e($file) . '</code>: ' . $list($redundant) . '. Without them the installation follows the package and config.json.',
                    'action' => '<button type="button" class="button" data-on_click="userConfirm" data-action="prune" data-confirm="Remove from ' . $e($file) . ': ' . $e(implode(', ', $redundant)) . '?" data-file="' . $e($file) . '"><i class="cmsIcon cmsIcon-broom"></i>Remove them</button>',
                ];
            }
        }
        $by = [];
        foreach ($v['findings'] as $name => $findings) {
            foreach ($findings as $finding) {
                $by[$finding['status']][$finding['file']][] = $name;
            }
        }
        foreach ($by[Reconcile::ORPHAN] ?? [] as $in => $names) {
            $checks[] = [
                'kind' => 'orphan',
                'icon' => 'cmsIcon-eraser',
                'title' => count($names) . ' defined nowhere',
                'text' => 'In <code>' . $e($in) . '</code>: ' . $list($names) . '. No package defines ' . (count($names) === 1 ? 'it' : 'them') . '.',
                'action' => $in === EnvironmentFile::LOCAL
                    ? '<button type="button" class="button" data-on_click="userConfirm" data-action="dropOrphans" data-confirm="Remove from ' . $e($in) . ': ' . $e(implode(', ', $names)) . '?" data-file="' . $e($in) . '"><i class="cmsIcon cmsIcon-eraser"></i>Remove them</button>'
                    : '<small>config.json counts for every installation: code elsewhere may still read them, so they stay.</small>',
            ];
        }
        foreach ($by[Reconcile::EXPOSED] ?? [] as $in => $names) {
            $checks[] = [
                'kind' => 'exposed',
                'icon' => 'cmsIcon-key-skeleton',
                'title' => 'Secrets in config.json',
                'text' => 'In <code>' . $e($in) . '</code>: ' . $list($names) . '. That file is in git. Enter them for each installation and take them out of config.json.',
                'action' => '',
            ];
        }
        foreach ($by[Reconcile::MISPLACED] ?? [] as $in => $names) {
            $checks[] = [
                'kind' => 'misplaced',
                'icon' => 'cmsIcon-arrows-left-right',
                'title' => count($names) . ' in the wrong file',
                'text' => 'In <code>' . $e($in) . '</code>: ' . $list($names) . '. The rows say where ' . (count($names) === 1 ? 'it belongs' : 'they belong') . '.',
                'action' => '',
            ];
        }
        foreach ($by[Reconcile::FOREIGN] ?? [] as $in => $names) {
            $checks[] = [
                'kind' => 'foreign',
                'icon' => 'cmsIcon-box',
                'title' => 'Packages not installed here',
                'text' => 'In <code>' . $e($in) . '</code>: ' . $list($names) . '. Their settings stay as they are.',
                'action' => '',
            ];
        }
        $required = $v['project'] ? [] : self::required();
        if ($required !== []) {
            $checks[] = [
                'kind' => 'required',
                'icon' => 'cmsIcon-triangle-exclamation',
                'title' => count($required) . ' required ' . (count($required) === 1 ? 'value is' : 'values are') . ' missing',
                'text' => $list($required) . '. Marked “required” in the rows.',
                'action' => '',
            ];
        }
        return $checks;
    }

    /**
     * The values of self::$view that have to be entered and are not.
     *
     * @return list<string> package.path
     */
    private static function required(): array
    {
        $missing = [];
        foreach (Reconcile::PACKAGES as $package) {
            self::eachValue($package, Explain::definition($package), [], static function (array $field, array $path) use ($package, &$missing): void {
                if (self::mandatory($package, $field, $path) === 'input') {
                    $missing[] = $package . '.' . implode('.', $path);
                }
            });
        }
        return $missing;
    }

    /**
     * Every value of a definition, the entries of a block with names of its
     * own (`connections`) as the page shows them.
     *
     * @param list<string>                                $path
     * @param callable(array<string, mixed>, list<string>): void $fn
     */
    private static function eachValue(string $package, array $node, array $path, callable $fn): void
    {
        foreach ($node['properties'] ?? [] as $key => $child) {
            $at = [...$path, (string) $key];
            if (Explain::isValue($child)) {
                $fn($child, $at);
            } elseif (is_array($child['additionalProperties'] ?? null)) {
                foreach (self::names($package, $child, $at) as $name) {
                    self::eachValue($package, $child['additionalProperties'], [...$at, $name], $fn);
                }
            } else {
                self::eachValue($package, $child, $at, $fn);
            }
        }
    }

    /**
     * The names of a block with names of its own: the required ones, then
     * what the files have.
     *
     * @param list<string> $path
     * @return list<string>
     */
    private static function names(string $package, array $node, array $path): array
    {
        $v = self::$view;
        $names = array_map('strval', (array) ($node['required'] ?? []));
        foreach (Explain::LAYERS as $layer) {
            foreach (array_keys((array) (is_array($v['layers'][$layer] ?? null) ? Tree::get($v['layers'][$layer], [$package, ...$path]) : [])) as $name) {
                if ($name !== '//' && !in_array((string) $name, $names, true)) {
                    $names[] = (string) $name;
                }
            }
        }
        return $names;
    }

    /**
     * Has a value to be there and is not: 'input' when someone has to enter
     * it, 'generate' when the code makes it when it is needed, else null.
     *
     * @param array<string, mixed> $field
     * @param list<string>         $path
     */
    private static function mandatory(string $package, array $field, array $path): ?string
    {
        $v = self::$view;
        $mandatory = (string) ($field['x-mandatory'] ?? '');
        $secret = !empty($field['writeOnly']);
        if ($mandatory === '' || $v['project']) {
            return null;
        }
        $active = Explain::active(Explain::definition($package), $v['layers'], $path, $package);
        if (!$active) {
            return null;
        }
        $value = Explain::value($field, $v['layers'], [$package, ...$path], $v['stage'], $active);
        $isSet = $secret ? (string) $value['value'] !== '' && $value['source'] !== 'none' : $value['source'] !== 'none';
        return $isSet ? null : ($mandatory === 'input' ? 'input' : 'generate');
    }

    /**
     * One package: its blocks in the order of the definition, each one opens
     * and closes. What stands beside the package (optional, Remove) and what
     * the files note about it come first, its tool last.
     */
    private static function package(string $package): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $definition = Explain::definition($package);
        $aside = self::aside($package, $definition, []);
        $notes = self::comments([$package]);
        if ($aside !== '' || $notes !== []) {
            echo '<div class="envPackageBar">' . $aside . ($notes === [] ? '' : '<p class="tagsHint">' . implode('</p><p class="tagsHint">', array_map($e, $notes)) . '</p>') . '</div>';
        }
        self::node($package, $definition, []);
        // what nothing defines: a block of its own after the others
        ob_start();
        self::$head = self::foldHead($package . '.orphans', self::blockHead('Defined nowhere', '', 'In the files, but no definition knows them.'));
        self::orphans($package);
        $found = self::$head === null;
        self::$head = null;
        self::endRows();
        $orphans = (string) ob_get_clean();
        if ($found) {
            echo '<div class="envBlock' . self::foldClass($package . '.orphans') . '" data-depth="1" data-fold="' . $e($package . '.orphans') . '">' . $orphans . '</div>';
        }
        self::tool($definition);
    }

    /** the head of a block that opens and closes: the chevron first */
    private static function foldHead(string $key, string $head): string
    {
        return preg_replace('~^<div class="envGroupHead">~', '<div class="envGroupHead">' . self::foldToggle($key), $head, 1) ?? $head;
    }

    /**
     * What a block brings beyond its values (`x-tool`: a class implementing
     * SettingsTool), below its rows.
     *
     * @param array<string, mixed> $node
     */
    private static function tool(array $node): void
    {
        $tool = self::toolOf($node);
        if ($tool === null) {
            return;
        }
        $v = self::$view;
        echo '<div class="envTool">';
        $tool::render(new ToolView($v['file'], $v['here'], $v['project'], $v['data']));
        echo '</div>';
    }

    /**
     * @param array<string, mixed> $node
     * @return class-string<SettingsTool>|null
     */
    private static function toolOf(array $node): ?string
    {
        $class = (string) ($node['x-tool'] ?? '');
        return $class !== '' && is_subclass_of($class, SettingsTool::class) ? $class : null;
    }

    /**
     * Every tool the definitions name: only these get calls (handle()).
     *
     * @return list<class-string<SettingsTool>>
     */
    private static function tools(): array
    {
        $found = [];
        $walk = static function (array $node) use (&$walk, &$found): void {
            if (($tool = self::toolOf($node)) !== null) {
                $found[$tool] = $tool;
            }
            foreach ($node['properties'] ?? [] as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
            if (is_array($node['additionalProperties'] ?? null)) {
                $walk($node['additionalProperties']);
            }
        };
        foreach (Reconcile::PACKAGES as $package) {
            $walk(Explain::definition($package));
        }
        return array_values($found);
    }

    /**
     * What stands beside the title of a block: optional or not, and Remove
     * where config.local.json has it. The same for a package, a block and a
     * named entry (`connections.main`): an entry the definition requires
     * stays.
     *
     * @param array<string, mixed> $node
     * @param list<string>         $path
     */
    private static function aside(string $package, array $node, array $path, bool $entry = false): string
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $v = self::$view;
        $optional = !empty($node['x-optional']);
        $out = '';
        if ($optional) {
            $active = Explain::active(Explain::definition($package), $v['layers'], $path, $package);
            $out .= '<span class="envNote">' . ($active ? 'optional, set up' : 'optional, not set up: its defaults apply once a value is entered') . '</span>';
        }
        $removable = ($optional || $entry && !EnvironmentFile::requiredEntry($package, $path))
            && self::writable() && (Explain::has((array) $v['layers']['local'], [$package, ...$path]) || Explain::has((array) $v['layers']['project'], [$package, ...$path]));
        if ($removable) {
            $out .= '<button type="button" class="button transparent" data-on_click="userConfirm" data-action="removeBlock" data-confirm="Remove ' . $e(self::keyPath($package, $path)) . '?" data-file="' . $e($v['file']) . '" data-package="' . $e($package) . '" data-path="' . $e(implode('.', $path)) . '"><i class="cmsIcon cmsIcon-trash"></i>Remove</button>';
        }
        return $out;
    }

    /** Is a table of rows open? Each block gets one of its own (rows(), endRows()). */
    private static bool $rowsOpen = false;

    /** the head of the block just opened, waiting for its table (openBlock()) */
    private static ?string $head = null;

    /**
     * Opens the table of the block, unless it is open. The head of the block
     * is its first row, a th over all columns: the table is as wide as what
     * it holds, the head with it. In tbody, not thead: a phone folds the rows
     * into cards and hides thead; tableWidgetTitle keeps the row out of that.
     */
    private static function rows(): void
    {
        if (!self::$rowsOpen) {
            echo '<div class="tableScroll"><table class="envRows"><tbody>';
            self::$rowsOpen = true;
        }
        if (self::$head !== null) {
            echo '<tr class="envHead tableWidgetTitle"><th colspan="4">' . self::$head . '</th></tr>';
            self::$head = null;
        }
    }

    /** a head that got no rows (a block of blocks): a table of its own, as wide as the box */
    private static function flushHead(): void
    {
        if (self::$head !== null) {
            self::endRows();
            echo '<div class="tableScroll"><table class="envRows is-head"><tbody><tr class="envHead tableWidgetTitle"><th colspan="4">' . self::$head . '</th></tr></tbody></table></div>';
            self::$head = null;
        }
    }

    /**
     * A block: a box, its head waits for the first row. With a fold key it
     * opens and closes (the blocks of the first level), as it was left.
     */
    private static function openBlock(int $depth, string $head, string $class = '', ?string $fold = null): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        self::flushHead();
        self::endRows();
        if ($fold !== null) {
            $class .= self::foldClass($fold);
            $head = self::foldHead($fold, $head);
        }
        echo '<div class="envBlock' . $class . '" data-depth="' . $depth . '"' . ($fold !== null ? ' data-fold="' . $e($fold) . '"' : '') . '>';
        self::$head = $head;
    }

    private static function closeBlock(): void
    {
        self::flushHead();
        self::endRows();
        echo '</div>';
    }

    /** closes the table of the block, if one is open: a heading or a tool follows */
    private static function endRows(): void
    {
        if (self::$rowsOpen) {
            echo '</tbody></table></div>';
            self::$rowsOpen = false;
        }
    }

    /**
     * The values of a block first, in one table, then its blocks, each with
     * a heading and a table of its own: a value never lands in the table of
     * the block before it.
     *
     * @param list<string> $path
     */
    private static function node(string $package, array $node, array $path): void
    {
        $values = [];
        $blocks = [];
        foreach ($node['properties'] ?? [] as $key => $child) {
            $at = [...$path, (string) $key];
            if (Explain::isValue($child)) {
                $values[] = [$child, $at];
            } else {
                $blocks[] = [$child, $at];
            }
        }
        $free = ($node['additionalProperties'] ?? false) === true;
        // a package has no loose values: they are its block General
        $general = $path === [] && ($values !== [] || $free);
        if ($general) {
            self::openBlock(1, self::blockHead('General'), '', $package . '.general');
        }
        foreach ($values as [$child, $at]) {
            self::row($package, $child, $at);
        }
        if ($free) {
            self::free($package, $node, $path);
        }
        if ($general) {
            self::closeBlock();
        }
        self::endRows();
        foreach ($blocks as [$child, $at]) {
            self::group($package, $child, $at);
        }
    }

    /**
     * The head of a block: title, then what stands beside it (marks, notes,
     * buttons) and the hints below. HTML, the title already escaped by the
     * caller where it carries marks.
     */
    private static function blockHead(string $title, string $aside = '', string ...$hints): string
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $out = '<div class="envGroupHead"><h3>' . $title . '</h3>' . $aside . '</div>';
        foreach ($hints as $hint) {
            $out .= '<small class="envHint">' . $e($hint) . '</small>';
        }
        return $out;
    }

    /**
     * A block: a box, its head the first row of its table. The same for a
     * block of values (`mail`), a block of named entries (`connections`) and
     * each entry (`connections.main`). The (i) carries the description and
     * the path, as at a value; what files note (`//`) shows below the title.
     *
     * @param array<string, mixed> $node
     * @param list<string>         $path
     */
    private static function block(string $package, array $node, array $path, string $title, string $aside, string $class = ''): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $head = self::blockHead($e($title) . self::info($node, self::definition($package, $path), self::keyPath($package, $path)), $aside, ...self::comments([$package, ...$path]));
        self::openBlock(count($path), $head, $class, count($path) === 1 ? self::keyPath($package, $path) : null);
        if (is_array($node['additionalProperties'] ?? null)) {
            self::entries($package, $node, $path);
        } else {
            self::node($package, $node, $path);
        }
        if (self::toolOf($node) !== null) {
            self::flushHead();
            self::endRows();
            self::tool($node);
        }
        self::closeBlock();
    }

    /** @param list<string> $path */
    private static function group(string $package, array $node, array $path): void
    {
        self::block($package, $node, $path, (string) ($node['title'] ?? self::label((string) end($path))), self::aside($package, $node, $path));
    }

    /**
     * The named entries of a block (`connections`): one block per name, the
     * required ones always, then a row for a new name where the definition
     * says what a name may be (`propertyNames`).
     *
     * @param array<string, mixed> $node
     * @param list<string>         $path
     */
    private static function entries(string $package, array $node, array $path): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $v = self::$view;
        $entry = $node['additionalProperties'];
        foreach (self::names($package, $node, $path) as $name) {
            $at = [...$path, $name];
            self::block($package, $entry, $at, ($entry['title'] ?? 'Entry') . ': ' . $name, self::aside($package, $entry, $at, true), ' is-entry');
        }
        $pattern = (string) ($node['propertyNames']['pattern'] ?? '');
        if ($pattern === '' || !self::writable()) {
            return;
        }
        // the new one below the entries, a table of its own
        self::flushHead();
        self::endRows();
        self::rows();
        $title = (string) ($entry['title'] ?? 'entry');
        $hint = (string) ($node['propertyNames']['description'] ?? '');
        echo '<tr class="envAdd"><td class="envLabel"><span>' . $e('New ' . strtolower($title)) . ($hint !== '' ? '<small>' . $e($hint) . '</small>' : '') . '</span></td>'
            . '<td class="envControl"><input name="name" aria-label="' . $e('Name of the new ' . strtolower($title)) . '" pattern="' . $e(trim($pattern, '^$')) . '" autocomplete="off"></td><td class="envOrigin"></td>'
            . '<td class="envActions"><button type="button" class="button" data-on_click="addEntry" data-file="' . $e($v['file']) . '" data-package="' . $e($package) . '" data-path="' . $e(implode('.', $path)) . '"><i class="cmsIcon cmsIcon-plus"></i>Add</button></td></tr>';
    }

    /**
     * A block that takes anything (`ini`): what the definition names comes
     * first, then what the files add.
     *
     * @param list<string> $path
     */
    private static function free(string $package, array $node, array $path): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $v = self::$view;
        $seen = [];
        foreach (Explain::LAYERS as $layer) {
            $tree = is_array($v['layers'][$layer] ?? null) ? Tree::get($v['layers'][$layer], [$package, ...$path]) : null;
            foreach (array_keys(is_array($tree) ? $tree : []) as $key) {
                if ($key !== '//' && !isset($node['properties'][$key])) {
                    $seen[(string) $key] = true;
                }
            }
        }
        foreach (array_keys($seen) as $key) {
            self::row($package, ['title' => $key, 'type' => 'string', 'x-overridable' => true, 'x-file' => 'project', 'x-free' => true], [...$path, $key]);
        }
        if (self::writable()) {
            $dotted = implode('.', $path);
            self::rows();
            echo '<tr class="envAdd"><td class="envLabel"><span>Another value<small>' . $e($dotted) . '.&lt;name&gt;, lower case and _</small></span></td><td class="envControl"><span class="envPair"><input name="key" aria-label="Name" pattern="[a-z][a-z0-9_]*" autocomplete="off" placeholder="name"><input name="value" aria-label="Value" autocomplete="off" placeholder="value"></span></td><td class="envOrigin">' . self::chip('local') . '</td><td class="envActions"><button type="button" class="button" data-on_click="addFree" data-file="' . $e($v['file']) . '" data-package="' . $e($package) . '" data-path="' . $e($dotted) . '"><i class="cmsIcon cmsIcon-plus"></i>Add</button></td></tr>';
        }
    }

    /**
     * One value: what it is, what applies, where from, and what can be done.
     *
     * @param array<string, mixed> $field
     * @param list<string>         $path
     */
    private static function row(string $package, array $field, array $path): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $v = self::$view;
        $dotted = implode('.', $path);
        $key = (string) end($path);
        $keys = [$package, ...$path];
        $secret = !empty($field['writeOnly']);
        $owner = (string) ($field['x-file'] ?? 'local');
        $active = Explain::active(Explain::definition($package), $v['layers'], $path, $package);
        $value = Explain::value($field, $v['layers'], $keys, $v['stage'], $active);
        $findings = $v['findings'][$package . '.' . $dotted] ?? [];
        $misplaced = array_values(array_filter($findings, static fn(array $f): bool => $f['status'] === Reconcile::MISPLACED));

        // a change goes to the file the value belongs to (EnvironmentFile::target())
        $target = EnvironmentFile::target($field, $owner, ['packages', ...$keys]);
        $layer = $target === EnvironmentFile::LOCAL ? 'local' : 'project';
        $own = is_array($v['layers'][$layer] ?? null) && Explain::has($v['layers'][$layer], $keys);
        $below = Explain::below($value['stack'], $layer);

        self::rows();
        echo '<tr' . (count($path) > 1 ? ' data-group="' . $e($path[count($path) - 2]) . '"' : '') . ' data-key="' . $e($key) . '"' . ($target === null ? ' class="is-readonly"' : '') . '>';
        echo '<td class="envLabel"><span>' . $e((string) ($field['title'] ?? self::label($key))) . self::info($field, self::definition($package, $path), self::keyPath($package, $path)) . self::places($package, $path, $secret) . self::backup($field);
        echo '</span></td><td class="envControl">';
        $isSet = false;
        if ($secret) {
            $isSet = (string) $value['value'] !== '' && $value['source'] !== 'none';
            if ($target !== null) {
                echo '<input type="password" data-on_blur="save" aria-label="' . $e((string) ($field['title'] ?? $dotted)) . '" data-file="' . $e($target) . '" data-package="' . $e($package) . '" data-path="' . $e($dotted) . '" name="value" autocomplete="new-password" placeholder="' . ($isSet ? '•••••••• set, empty keeps it' : 'not set') . '">';
            } else {
                echo '<span class="envValue">per installation</span>';
            }
        } elseif ($target === null) {
            echo '<span class="envValue' . ($value['source'] === 'none' ? ' is-empty' : '') . '">' . $e(self::show($value)) . '</span>';
        } else {
            self::control($target, $package, $dotted, $field, $own ? Tree::get((array) $v['layers'][$layer], $keys) : null, $own, $below, $value);
        }
        echo '</td><td class="envOrigin">';
        if ($secret) {
            echo self::chip($v['project'] ? 'local' : ($isSet ? $value['source'] : 'none'));
        } else {
            echo self::chip($value['source'], $value['source'] === 'stage' ? $v['stage'] : '', self::sourceLink($value['source'], $package, $path));
        }
        // notes
        if (!$secret && $own && Explain::redundant($value['stack'], $layer)) {
            echo '<small class="envNote">same as below</small>';
        }
        if (!$secret && $own && $owner === 'project' && $layer === 'local') {
            echo '<small class="envNote">overrides the project</small>';
        }
        if (!$active && $value['default'] !== null && $value['default']['source'] !== 'computed') {
            echo '<small class="envNote">default ' . $e(self::format($value['default']['value'])) . ' once set up</small>';
        }
        foreach ($misplaced as $finding) {
            echo '<small class="envNote is-warn">set in ' . $e($finding['file']) . ', belongs into ' . $e($finding['owner'] === 'project' ? 'config.json' : 'config.local.json') . '</small>';
        }
        if ($secret && Explain::has((array) $v['layers']['project'], $keys) && (string) Tree::get((array) $v['layers']['project'], $keys) !== '') {
            echo '<small class="envNote is-warn">lies in config.json, belongs into config.local.json</small>';
        }
        $mandatory = self::mandatory($package, $field, $path);
        if ($mandatory === 'input') {
            echo '<small class="envNote is-warn">required</small>';
        } elseif ($mandatory === 'generate') {
            echo '<small class="envNote">made when it is needed</small>';
        }
        echo '</td><td class="envActions">';
        // a required value with nothing below it cannot be emptied: the installation would lose its name
        if ($own && !$secret && !(($field['x-mandatory'] ?? '') === 'input' && $below['source'] === 'none')) {
            echo '<button type="button" class="button transparent" data-on_click="resetValue" data-file="' . $e($target) . '" data-package="' . $e($package) . '" data-path="' . $e($dotted) . '" title="' . $e('Remove from ' . $target . ': ' . self::show($below) . ' applies then') . '"><i class="cmsIcon cmsIcon-arrow-rotate-left"></i><span>' . $e(self::show($below)) . '</span></button>';
        }
        echo '</td></tr>';
    }

    /**
     * The field of a value that can be changed. Empty means: what lies below
     * applies, it shows as the placeholder.
     *
     * @param array{source: string, value: mixed} $below
     * @param array<string, mixed>                $value
     */
    private static function control(string $target, string $package, string $path, array $field, mixed $own, bool $has, array $below, array $value): void
    {
        $e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
        $key = substr((string) strrchr('.' . $path, '.'), 1);
        $parts = explode('.', $path);
        $group = count($parts) > 1 ? $parts[count($parts) - 2] : '';
        $attrs = ' aria-label="' . $e((string) ($field['title'] ?? self::label($key))) . '" data-file="' . $e($target) . '" data-group="' . $e($group) . '" data-key="' . $e($key) . '" data-package="' . $e($package) . '" data-path="' . $e($path) . '" name="value" autocomplete="off"';
        $types = (array) ($field['type'] ?? 'string');
        if (in_array('boolean', $types, true) && !empty($field['x-computed'])) {
            // the code decides unless a file does: a switch could not say so
            echo '<select data-on_change="save"' . $attrs . '>';
            echo '<option value=""' . (!$has ? ' selected' : '') . '>' . $e('as below: ' . self::show($below)) . '</option>';
            echo '<option value="1"' . ($has && $own ? ' selected' : '') . '>on</option><option value="0"' . ($has && !$own ? ' selected' : '') . '>off</option></select>';
            return;
        }
        if (in_array('boolean', $types, true)) {
            $on = $value['value'];
            echo '<label class="projectSwitch"><input type="checkbox" class="switch" data-on_change="save"' . $attrs . ($on === true || $on === 1 || $on === '1' ? ' checked' : '') . '><div><div></div></div></label>';
            return;
        }
        if (isset($field['enum'])) {
            echo '<select data-on_change="save"' . $attrs . '>';
            echo '<option value=""' . (!$has ? ' selected' : '') . '>' . $e('as below: ' . self::show($below)) . '</option>';
            foreach ($field['enum'] as $option) {
                if ($option === '') {
                    continue;
                }
                echo '<option value="' . $e($option) . '"' . ($has && (string) $own === (string) $option ? ' selected' : '') . '>' . $e($option) . '</option>';
            }
            echo '</select>';
            return;
        }
        if (($field['format'] ?? '') === 'email') {
            $attrs .= ' type="email"';
        } elseif (in_array('integer', $types, true)) {
            $attrs .= ' inputmode="numeric"';
        }
        if (in_array('array', $types, true) && !in_array('string', $types, true)) {
            echo '<span class="envValue">' . $e(self::format($value['value'])) . '</span>';
            return;
        }
        $attrs .= ' placeholder="' . $e($below['source'] === 'none' ? '' : self::show($below)) . '"';
        $shown = is_array($own) ? implode(', ', array_map('strval', $own)) : (is_scalar($own) ? (string) $own : '');
        echo '<input data-on_blur="save"' . $attrs . ' value="' . $e($has ? $shown : '') . '">';
    }

    /** What is under a package that nobody defines, at its end, struck through. */
    private static function orphans(string $package): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $v = self::$view;
        foreach ($v['findings'] as $name => $list) {
            if (!str_starts_with($name, $package . '.')) {
                continue;
            }
            foreach ($list as $finding) {
                if ($finding['status'] !== Reconcile::ORPHAN) {
                    continue;
                }
                $path = substr($name, strlen($package) + 1);
                $layer = $finding['file'] === EnvironmentFile::LOCAL ? 'local' : 'project';
                $raw = Tree::get((array) ($v['layers'][$layer] ?? []), [$package, ...explode('.', $path)]);
                $may = $layer === 'local';
                self::rows();
                echo '<tr class="envOrphan"><td class="envLabel"><span><s>' . $e($path) . '</s>' . self::place($layer, $finding['file'], self::valueIn($finding['file'], $package, explode('.', $path)), self::format($raw)) . '</span></td><td class="envControl"><code>' . $e(self::json($raw)) . '</code></td><td class="envOrigin">' . self::chip($layer, '', self::valueIn($finding['file'], $package, explode('.', $path))) . '</td><td class="envActions">';
                if ($may) {
                    echo '<button type="button" class="button transparent" data-on_click="userConfirm" data-action="dropOrphan" data-confirm="Remove ' . $e($name) . ' from ' . $e($finding['file']) . '?" data-file="' . $e($finding['file']) . '" data-name="' . $e($name) . '" title="Remove"><i class="cmsIcon cmsIcon-trash"></i></button>';
                } else {
                    echo '<small class="envNote">stays: another installation may read it</small>';
                }
                echo '</td></tr>';
            }
        }
    }

    /** Packages that are not installed here: shown as they are, they stay. */
    private static function foreign(): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $v = self::$view;
        foreach (Explain::LAYERS as $layer) {
            foreach ((array) ($v['layers'][$layer] ?? []) as $package => $tree) {
                if (Schema::installed((string) $package)) {
                    continue;
                }
                echo '<section class="widget envForeign' . self::foldClass('foreign.' . $package) . '" data-fold="foreign.' . $e($package) . '"><header>' . self::foldToggle('foreign.' . $package) . '<h2>' . $e($package) . self::info([], null, self::keyPath((string) $package, [])) . '</h2>' . self::chip($layer, '', ($in = self::layerFile($layer)) === null ? null : self::valueIn($in, (string) $package, [])) . '</header><main><p class="tagsHint">This package is not installed here. Its settings stay as they are.</p><pre>' . $e(self::json($tree, true)) . '</pre></main></section>';
            }
        }
    }

    /** The rest of a file: free constants, notes, what nothing knows. */
    private static function rest(): void
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $v = self::$view;
        $files = $v['project'] ? [EnvironmentFile::PROJECT => $v['data']] : [EnvironmentFile::PROJECT => EnvironmentFile::readProject(), EnvironmentFile::LOCAL => $v['data']];
        $rows = '';
        foreach ($files as $name => $data) {
            foreach ($data as $key => $value) {
                if ($key === 'packages') {
                    continue;
                }
                $label = match ($key) {
                    'constants' => 'Constants of the project',
                    '//' => 'Note',
                    default => (string) $key,
                };
                $rows .= '<tr class="is-readonly"><td class="envLabel"><span>' . $e($label) . self::info([], null, (string) $key) . self::place($name === EnvironmentFile::PROJECT ? 'project' : 'local', $name, self::valueIn($name, '', [(string) $key])) . '</span></td><td class="envControl"><code>' . $e(self::json($value, true)) . '</code></td><td class="envOrigin">' . self::chip($name === EnvironmentFile::PROJECT ? 'project' : 'local', '', self::valueIn($name, '', [(string) $key])) . '</td><td class="envActions"><small class="envNote">' . ($name === EnvironmentFile::PROJECT ? 'changes with the project' : 'outside of the definitions') . '</small></td></tr>';
            }
        }
        if ($rows !== '') {
            echo '<section class="widget envRest' . self::foldClass('rest') . '" data-fold="rest"><header>' . self::foldToggle('rest') . '<h2>More in the files</h2></header><div class="tableScroll"><table class="envRows"><tbody>' . $rows . '</tbody></table></div></section>';
        }
    }

    /** May this view write? This installation's, not the project's. */
    private static function writable(): bool
    {
        return !self::$view['project'] && EnvironmentFile::directory() !== null;
    }

    /**
     * `"//"` notes of a block, from every file that has the block.
     *
     * @param list<string> $keys
     * @return list<string>
     */
    private static function comments(array $keys): array
    {
        $notes = [];
        foreach (Explain::LAYERS as $layer) {
            $tree = self::$view['layers'][$layer] ?? null;
            $note = is_array($tree) ? Tree::get($tree, [...$keys, '//']) : null;
            if (is_string($note) && $note !== '') {
                $notes[] = $note;
            }
        }
        return array_values(array_unique($notes));
    }

    /**
     * Where a value comes from, as a chip. With $link (see definition(),
     * valueIn()) the chip opens that place in syscoder.
     *
     * @param array{href: string, where: string}|null $link
     */
    private static function chip(string $source, string $stage = '', ?array $link = null): string
    {
        $titles = [
            'package' => 'Default of the package',
            'stage' => 'Default of the package for this stage',
            'computed' => 'Decided by the code',
            'project' => 'config.json',
            'local' => 'config.local.json',
            'none' => 'Nothing sets it',
        ];
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $label = (self::SOURCE[$source] ?? $source) . ($stage !== '' ? ' · ' . $stage : '');
        if ($link !== null) {
            return '<a class="envChip is-' . $e($source) . '" target="syscoder" href="' . $e($link['href']) . '" title="' . $e(($titles[$source] ?? '') . ': ' . $link['where']) . '">' . $e($label) . '</a>';
        }
        return '<span class="envChip is-' . $e($source) . '" title="' . $e($titles[$source] ?? '') . '">' . $e($label) . '</span>';
    }

    /**
     * The description of a value or block from its definition, behind an
     * (i): a tooltip on hover, focus or tap (panel.js). With $link the (i)
     * opens the definition in syscoder. '' when there is neither.
     *
     * @param array<string, mixed>                    $field
     * @param array{href: string, where: string}|null $link
     */
    private static function info(array $field, ?array $link = null, string $key = ''): string
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $text = trim((string) ($field['description'] ?? ''));
        if ($text === '' && $link === null && $key === '') {
            return '';
        }
        $attrs = ' aria-label="' . $e($text !== '' ? $text : ($key !== '' ? $key : 'Definition')) . '" data-tip="' . $e($text) . '"' . ($key !== '' ? ' data-key="' . $e($key) . '"' : '');
        if ($link !== null) {
            return ' <a class="envInfo" target="syscoder" href="' . $e($link['href']) . '" data-src="' . $e($link['where']) . '"' . $attrs . '><i class="cmsIcon cmsIcon-circle-info"></i></a>';
        }
        return ' <span class="envInfo" tabindex="0" role="note"' . $attrs . '><i class="cmsIcon cmsIcon-circle-info"></i></span>';
    }

    /** where a value or block lies in a file: packages.<package>.<path>, for the tip of its (i) */
    private static function keyPath(string $package, array $path): string
    {
        return implode('.', ['packages', $package, ...$path]);
    }

    /** @var array<string, string> path => text of the files read for the links */
    private static array $texts = [];

    /** May the links into syscoder show? A superuser, where syscoder is. */
    private static function links(): bool
    {
        static $may = null;
        return $may ??= Syscoder::available() && Session::isSuperuser();
    }

    private static function text(string $path): string
    {
        return self::$texts[$path] ??= (string) \fs::file_get_contents($path);
    }

    /**
     * Where a block or value is defined: config.schema.json of its package,
     * at the line. Null without a definition or without syscoder.
     *
     * @param list<string> $path
     * @return array{href: string, where: string}|null
     */
    private static function definition(string $package, array $path): ?array
    {
        if (!self::links()) {
            return null;
        }
        $schema = Schema::file($package);
        $keys = self::schemaKeys(Schema::load($package), $path);
        if ($schema === null || $keys === null) {
            return null;
        }
        $line = $keys === [] ? 1 : JsonLines::line(self::text($schema), $keys);
        return $line === null ? null : ['href' => (string) Syscoder::href($schema, $line), 'where' => basename($schema) . ', line ' . $line];
    }

    /**
     * Where a config file writes a block or value, in the new layout or the
     * old one. Null when it does not, or without syscoder.
     *
     * @param string       $package '' for the top of the file (`constants`, `//`)
     * @param list<string> $path
     * @return array{href: string, where: string}|null
     */
    private static function valueIn(string $file, string $package, array $path): ?array
    {
        if (!self::links()) {
            return null;
        }
        $source = EnvironmentFile::source($file);
        if ($source === null || ($package === '' && $path === [])) {
            return null;
        }
        $candidates = $package === '' ? [$path] : [['packages', $package, ...$path], $package === 'system' && $path !== [] ? $path : [$package, ...$path]];
        $line = JsonLines::first(self::text($source), $candidates);
        return $line === null ? null : ['href' => (string) Syscoder::href($source, $line), 'where' => $file . ', line ' . $line];
    }

    /** the file a layer of self::$view stands for */
    private static function layerFile(string $layer): ?string
    {
        return match ($layer) {
            'project' => EnvironmentFile::PROJECT,
            'local' => EnvironmentFile::LOCAL,
            default => null,
        };
    }

    /**
     * The link of a source chip: a file opens at the value, a default of the
     * package at its definition.
     *
     * @param list<string> $path
     * @return array{href: string, where: string}|null
     */
    private static function sourceLink(string $source, string $package, array $path): ?array
    {
        if (in_array($source, ['package', 'stage'], true)) {
            return self::definition($package, $path);
        }
        $file = self::layerFile($source);
        return $file === null ? null : self::valueIn($file, $package, $path);
    }

    /**
     * Where a value is written: an (i) for each file that has it, coloured
     * by the file (config.json blue, config.local.json green). The tip tells what the file says; with syscoder it opens the
     * value there. A secret only tells that it is set.
     *
     * @param list<string> $path
     */
    private static function places(string $package, array $path, bool $secret = false): string
    {
        $out = '';
        $keys = [$package, ...$path];
        foreach (Explain::LAYERS as $layer) {
            $tree = self::$view['layers'][$layer] ?? null;
            $file = self::layerFile($layer);
            if (!is_array($tree) || $file === null || !Explain::has($tree, $keys)) {
                continue;
            }
            $value = Tree::get($tree, $keys);
            $out .= $secret
                ? self::place($layer, $file, null, (string) $value === '' ? '(empty)' : 'set')
                : self::place($layer, $file, self::valueIn($file, $package, $path), self::format($value));
        }
        return $out;
    }

    /**
     * One (i) of places(): the file and what it says there.
     *
     * @param array{href: string, where: string}|null $link
     */
    private static function place(string $layer, string $file, ?array $link, string $value = ''): string
    {
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
        $text = 'In ' . $file . ($value !== '' ? ': ' . $value : '');
        $attrs = ' class="envInfo envPlace is-' . $e($layer) . '" aria-label="' . $e($text) . '" data-tip="' . $e($text) . '"';
        if ($link !== null) {
            return ' <a target="syscoder" href="' . $e($link['href']) . '" data-src="' . $e($link['where']) . '"' . $attrs . '><i class="cmsIcon cmsIcon-circle-info"></i></a>';
        }
        return ' <span tabindex="0" role="note"' . $attrs . '><i class="cmsIcon cmsIcon-circle-info"></i></span>';
    }

    /**
     * The keys of a config path inside a definition: properties,
     * additionalProperties, the items of a list.
     * Null when the definition does not have it.
     *
     * @param list<string> $path
     * @return list<string>|null
     */
    private static function schemaKeys(array $node, array $path): ?array
    {
        $keys = [];
        foreach ($path as $key) {
            if (isset($node['properties'][$key]) && is_array($node['properties'][$key])) {
                $keys[] = 'properties';
                $keys[] = $key;
                $node = $node['properties'][$key];
            } elseif (isset($node['items']['properties'][$key]) && is_array($node['items']['properties'][$key])) {
                array_push($keys, 'items', 'properties', $key);
                $node = $node['items']['properties'][$key];
            } elseif (is_array($node['additionalProperties'] ?? null)) {
                $keys[] = 'additionalProperties';
                $node = $node['additionalProperties'];
            } else {
                return null;
            }
        }
        return $keys;
    }

    /** the backup mark of a value, from `x-backup` */
    private static function backup(array $field): string
    {
        return match ($field['x-backup'] ?? null) {
            'content' => ' <i class="cmsIcon cmsIcon-database envBackup" title="Travels with a backup"></i>',
            default => '',
        };
    }

    /** @param array{source: string, value: mixed} $entry */
    private static function show(array $entry): string
    {
        return match ($entry['source']) {
            'none' => 'not set',
            'computed' => 'automatic',
            default => self::format($entry['value']),
        };
    }

    private static function format(mixed $value): string
    {
        return match (true) {
            $value === null => 'empty',
            $value === true => 'on',
            $value === false => 'off',
            $value === '' => '(empty)',
            is_array($value) => $value === [] ? '(none)' : (array_is_list($value) && array_filter($value, 'is_scalar') === $value ? implode(', ', $value) : self::json($value)),
            default => (string) $value,
        };
    }

    private static function json(mixed $value, bool $pretty = false): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | ($pretty ? JSON_PRETTY_PRINT : 0));
    }

    /**
     * A call of the page. $action is the one asked for, when another panel
     * passes it on (the Check lightbox): it is not the action of $panel then.
     */
    public static function handle(PanelNode $panel, ?string $action = null): void
    {
        $action ??= (string) $panel->action;
        $file = (string) \http::dataset('file');
        if (!in_array($file, [EnvironmentFile::LOCAL, EnvironmentFile::PROJECT], true) || EnvironmentFile::directory() === null) {
            \message::error('Only config.json and config.local.json are written here.');
            return;
        }
        // a call of a tool goes to the tool (x-tool in the definitions)
        foreach (self::tools() as $tool) {
            if (in_array($action, $tool::actions(), true)) {
                $tool::handle($panel, $action, $file);
                return;
            }
        }
        $package = (string) \http::dataset('package');
        $path = (string) \http::dataset('path');
        $pruned = null;
        $result = match ($action) {
            'save' => EnvironmentFile::set($package, $path, self::posted()),
            'resetValue' => EnvironmentFile::set($package, $path, ''),
            'addFree' => trim((string) \http::dataset('key')) !== '' && EnvironmentFile::set($package, $path . '.' . trim((string) \http::dataset('key')), (string) \http::dataset('value')),
            'prune' => ($pruned = EnvironmentFile::prune()) !== false,
            'dropOrphans' => EnvironmentFile::dropOrphans(),
            'dropOrphan' => EnvironmentFile::dropOrphans((string) \http::dataset('name')),
            'addEntry' => EnvironmentFile::addEntry($package, $path, trim((string) \http::dataset('name'))),
            'removeBlock' => EnvironmentFile::removeBlock($package, $path),
            default => null,
        };
        if ($result === null || $action === '') {
            return;
        }
        if ($result === false) {
            \message::error('That change was not saved.');
            $panel->updateViews(['main', 'controls']);
            return;
        }
        \message::confirm(match ($action) {
            'resetValue' => 'Removed. What lies below applies again.',
            'prune' => $pruned === [] ? 'Nothing to remove.' : 'Removed: ' . implode(', ', (array) $pruned) . '.',
            'dropOrphans', 'dropOrphan', 'removeBlock' => 'Removed.',
            default => 'Saved.',
        });
        // a save changes where the value comes from: the row shows it again
        $panel->updateViews(['main', 'controls']);
    }

    private static function posted(): string
    {
        $values = \http::values();
        if (is_object($values)) {
            return (string) ($values->value ?? '');
        }
        if (is_array($values)) {
            return (string) ($values['value'] ?? '');
        }
        return (string) $values;
    }

    private static function label(string $key): string
    {
        return match ($key) {
            'pass' => 'Password',
            'serverName' => 'Server name',
            'serverIp' => 'Server IP',
            'rootDir' => 'Project folder',
            default => ucfirst($key),
        };
    }
}
