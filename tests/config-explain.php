<?php

declare(strict_types=1);

/** Config\Explain: where a value comes from. See .cursor/plans/settings-gui.plan.md. */

require __DIR__ . '/bootstrap.php';

use Systopic\System\Config\Config;
use Systopic\System\Config\Explain;
use Systopic\System\Config\Layout;
use Systopic\System\Config\Schema;
use Systopic\System\Config\Tree;

$system = Explain::definition('system');
check('one definition per package, in its own order', array_slice(array_keys($system['properties']), 0, 3) === ['instance', 'website', 'legal']);
check('the site addresses are the cms\'s', isset(Explain::definition('cms')['properties']['siteAddress']));
check('a block holds the project\'s values and the installation\'s', array_keys($system['properties']['auth']['properties']) === ['defaultSignInMethod', 'deviceDays', 'screenDays', 'secret']);
check('every value knows its file', $system['properties']['auth']['properties']['secret']['x-file'] === 'local'
    && $system['properties']['auth']['properties']['deviceDays']['x-file'] === 'project'
    && $system['properties']['debug']['properties']['debug']['x-file'] === 'local');
check('a block of the project passes it on', $system['properties']['ini']['properties']['memory_limit']['x-file'] === 'project' && !empty($system['properties']['ini']['properties']['memory_limit']['x-overridable']));
check('a map has every value of an entry', isset(Explain::definition('db')['properties']['connections']['additionalProperties']['properties']['pass']));
check('an optional package stays optional', !empty(Explain::definition('relay')['x-optional']));

$field = $system['properties']['ini']['properties']['memory_limit'];
$layers = ['project' => [], 'local' => []];
check('nothing in the files: the package default', Explain::value($field, $layers, ['system', 'ini', 'memory_limit'], 'DEV')['source'] === 'package');
$debug = $system['properties']['debug']['properties']['debug'];
$v = Explain::value($debug, $layers, ['system', 'debug', 'debug'], 'DEV');
check('a stage of its own', $v['source'] === 'stage' && $v['value'] === true);
check('another stage: the package default', Explain::value($debug, $layers, ['system', 'debug', 'debug'], 'LIVE')['source'] === 'package');
$layers['project'] = ['system' => ['ini' => ['memory_limit' => '2G']]];
$layers['local'] = ['system' => ['ini' => ['memory_limit' => '512M']]];
$v = Explain::value($field, $layers, ['system', 'ini', 'memory_limit'], 'DEV');
check('the installation wins', $v['source'] === 'local' && $v['value'] === '512M');
check('below it lies the project', Explain::below($v['stack'], 'local') === ['source' => 'project', 'value' => '2G']);
check('an override that differs is not redundant', !Explain::redundant($v['stack'], 'local'));
$layers['local'] = ['system' => ['ini' => ['memory_limit' => '2G']]];
check('one that repeats the project is', Explain::redundant(Explain::value($field, $layers, ['system', 'ini', 'memory_limit'], 'DEV')['stack'], 'local'));
check('null is a value', Explain::value($system['properties']['auth']['properties']['screenDays'], ['project' => ['system' => ['auth' => ['screenDays' => null]]]], ['system', 'auth', 'screenDays'], 'DEV')['source'] === 'project');
check('a computed value says so', Explain::value(Explain::definition('db')['properties']['bindOnlyUsedParams'], [], ['db', 'bindOnlyUsedParams'], 'DEV')['source'] === 'computed');
check('a value without anything is not set', Explain::value($system['properties']['versionDate'], [], ['system', 'versionDate'], 'DEV')['source'] === 'none');

$relay = Explain::definition('relay');
check('an optional package that no file has gives no default', !Explain::active($relay, ['local' => []], ['client'], 'relay') && Explain::value($relay['properties']['client'], [], ['relay', 'client'], 'DEV', false)['source'] === 'none');
check('once a file has it, it does', Explain::active($relay, ['local' => ['relay' => ['api' => 'x']]], ['client'], 'relay'));
check('an optional block likewise', !Explain::active($system, ['local' => ['system' => []]], ['mail', 'port'], 'system') && Explain::active($system, ['local' => ['system' => ['mail' => ['password' => 'x']]]], ['mail', 'port'], 'system'));

$found = Explain::redundantIn(['project' => [], 'local' => ['relay' => ['client' => '/connection/', 'prefix' => '']]], 'local', 'DEV');
check('a block that would run empty keeps a value', count($found) === 1);

// the same values as Config::package(), in every project here
$projects = glob(dirname(__DIR__, 3) . '/projects/*/config', GLOB_ONLYDIR) ?: [];
$compared = 0;
$leaves = static function (array $node, array $tree, array $path) use (&$leaves): array {
    $out = [];
    foreach ($node['properties'] ?? [] as $key => $child) {
        if (Explain::isValue($child)) {
            $out[] = [[...$path, (string) $key], $child];
        } elseif (is_array($child['additionalProperties'] ?? null)) {
            foreach (array_keys((array) Tree::get($tree, [...$path, (string) $key])) as $name) {
                $out = [...$out, ...$leaves($child['additionalProperties'], $tree, [...$path, (string) $key, (string) $name])];
            }
        } else {
            $out = [...$out, ...$leaves($child, $tree, [...$path, (string) $key])];
        }
    }
    return $out;
};
foreach ($projects as $dir) {
    $read = static fn(string $file): array => is_file($file) && is_array($data = json_decode((string) file_get_contents($file), true)) ? Layout::lift($data) : [];
    $project = (array) ($read("$dir/config.json")['packages'] ?? []);
    $local = (array) ($read("$dir/config.local.json")['packages'] ?? []);
    $name = (string) ($local['system']['instance'] ?? '');
    if ($name === '') {
        continue;
    }
    $stage = strtoupper(explode('.', $name)[0]);
    $layers = ['project' => $project, 'local' => $local];
    foreach (['system', 'db', 'relay'] as $package) {
        $merged = Schema::withDefaults($package, Config::merge((array) ($project[$package] ?? []), (array) ($local[$package] ?? [])), $stage);
        $definition = Explain::definition($package);
        foreach ($leaves($definition, $merged, []) as [$path, $field]) {
            $active = Explain::active($definition, $layers, $path, $package);
            $v = Explain::value($field, $layers, [$package, ...$path], $stage, $active);
            $expected = Tree::get($merged, $path);
            if ($v['source'] !== 'computed' && $v['value'] !== $expected) {
                check(basename(dirname($dir)) . ": $package." . implode('.', $path) . ' is ' . json_encode($expected), false);
            }
            $compared++;
        }
    }
}
check("Explain gives what Config::package() gives ($compared values)", $compared > 0);
echo "all fine\n";
