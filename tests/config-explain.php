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
check('the definitions of a package are one tree, in the order project, machine, local', array_slice(array_keys($system['properties']), 0, 4) === ['ini', 'constants', 'auth', 'debug']);
check('a block in two files is one block', array_keys($system['properties']['auth']['properties']) === ['defaultSignInMethod', 'deviceDays', 'screenDays', 'secret']);
check('every value knows its file', $system['properties']['auth']['properties']['secret']['x-file'] === 'local' && $system['properties']['debug']['properties']['debug']['x-file'] === 'machine');
check('a map takes the values of every file', isset(Explain::definition('db')['properties']['connections']['additionalProperties']['properties']['pass']));
check('an optional package stays optional', !empty(Explain::definition('relay')['x-optional']));

$field = $system['properties']['debug']['properties']['debug'];
$layers = ['project' => [], 'instance' => [], 'local' => []];
$v = Explain::value($field, $layers, ['system', 'debug', 'debug'], 'DEV');
check('nothing in the files: the stage default', $v['source'] === 'stage' && $v['value'] === true);
check('another stage: the package default', Explain::value($field, $layers, ['system', 'debug', 'debug'], 'LIVE')['source'] === 'package');
$layers['instance'] = ['system' => ['debug' => ['debug' => false]]];
$layers['local'] = ['system' => ['debug' => ['debug' => true]]];
$v = Explain::value($field, $layers, ['system', 'debug', 'debug'], 'DEV');
check('the later file wins', $v['source'] === 'local' && $v['value'] === true);
check('below local lies the instance', Explain::below($v['stack'], 'local') === ['source' => 'instance', 'value' => false]);
check('below the instance the stage', Explain::below($v['stack'], 'instance')['source'] === 'stage');
check('local repeating nothing below is not redundant', !Explain::redundant($v['stack'], 'local'));
$layers['instance'] = ['system' => ['debug' => ['debug' => true]]];
check('the instance repeating the stage is', Explain::redundant(Explain::value($field, $layers, ['system', 'debug', 'debug'], 'DEV')['stack'], 'instance'));
check('null is a value', Explain::value($system['properties']['auth']['properties']['screenDays'], ['project' => ['system' => ['auth' => ['screenDays' => null]]]], ['system', 'auth', 'screenDays'], 'DEV')['source'] === 'project');
check('a computed value says so', Explain::value(Explain::definition('db')['properties']['bindOnlyUsedParams'], [], ['db', 'bindOnlyUsedParams'], 'DEV')['source'] === 'computed');
check('a value without anything is not set', Explain::value($system['properties']['versionDate'], [], ['system', 'versionDate'], 'DEV')['source'] === 'none');

$relay = Explain::definition('relay');
check('an optional package that no file has gives no default', !Explain::active($relay, ['instance' => []], ['client'], 'relay') && Explain::value($relay['properties']['client'], [], ['relay', 'client'], 'DEV', false)['source'] === 'none');
check('once a file has it, it does', Explain::active($relay, ['instance' => ['relay' => ['api' => 'x']]], ['client'], 'relay'));
check('an optional block likewise', !Explain::active($system, ['instance' => ['system' => []]], ['mail', 'port'], 'system') && Explain::active($system, ['local' => ['system' => ['mail' => ['password' => 'x']]]], ['mail', 'port'], 'system'));

$found = Explain::redundantIn(['project' => [], 'instance' => ['relay' => ['client' => '/connection/', 'prefix' => '']], 'local' => null], 'instance', 'DEV');
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
    foreach (glob("$dir/*.config.json") ?: [] as $file) {
        $stage = strtoupper(explode('.', basename($file))[0]);
        $instance = (array) ($read($file)['packages'] ?? []);
        $layers = ['project' => $project, 'instance' => $instance, 'local' => null];
        foreach (['system', 'db', 'relay'] as $package) {
            $merged = Schema::withDefaults($package, Config::merge((array) ($project[$package] ?? []), (array) ($instance[$package] ?? [])), $stage);
            $definition = Explain::definition($package);
            foreach ($leaves($definition, $merged, []) as [$path, $field]) {
                $active = Explain::active($definition, $layers, $path, $package);
                $v = Explain::value($field, $layers, [$package, ...$path], $stage, $active);
                $expected = Tree::get($merged, $path);
                if ($v['source'] !== 'computed' && $v['value'] !== $expected) {
                    check(basename(dirname($dir)) . '/' . basename($file) . ": $package." . implode('.', $path) . ' is ' . json_encode($expected), false);
                }
                $compared++;
            }
        }
    }
}
check("Explain gives what Config::package() gives ($compared values)", $compared > 0);
echo "all fine\n";
