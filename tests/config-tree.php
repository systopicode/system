<?php

declare(strict_types=1);

/**
 * The package tree of Config: Layout lifts every file, Schema fills the
 * defaults. See .cursor/docs/config-struktur.md.
 *
 * Then every project next to the packages that names its installation in
 * config.local.json has to boot.
 *
 *   php tests/config-tree.php
 */

require __DIR__ . '/bootstrap.php';

use Systopic\System\Config\Config;
use Systopic\System\Config\Layout;
use Systopic\System\Config\Schema;

// ---- Layout
$lifted = Layout::packages([
	'db' => ['host' => 'h', 'name' => 'n', 'bindOnlyUsedParams' => false],
	'relay' => ['api' => 'a'],
	'debug' => ['debug' => true],
	'linkSites' => true,
	'constants' => ['PROJECT_NAMESPACE' => 'X', 'FREE' => 1],
	'matches' => [['path' => '/']],
]);
check('db becomes connections.main', $lifted['db']['connections']['main'] === ['host' => 'h', 'name' => 'n']);
check('bindOnlyUsedParams stays beside the connections', $lifted['db']['bindOnlyUsedParams'] === false);
check('relay moves', $lifted['relay'] === ['api' => 'a']);
check('debug and linkSites move to system', $lifted['system']['debug'] === ['debug' => true] && $lifted['system']['linkSites'] === true);
check('system constants move, free ones stay', $lifted['system']['constants'] === ['PROJECT_NAMESPACE' => 'X']);
check('matches stay out', !isset($lifted['system']['matches']));
check('the new place wins in one file', Layout::packages([
	'db' => ['host' => 'old'],
	'packages' => ['db' => ['connections' => ['main' => ['host' => 'new']]]],
])['db']['connections']['main']['host'] === 'new');
check('the old sign-in key still works', Layout::packages(['auth' => ['default_login_method' => 'code']])['system']['auth'] === ['defaultSignInMethod' => 'code']);
check('the new sign-in key beats the old', Layout::packages(['auth' => ['default_login_method' => 'code', 'defaultSignInMethod' => 'password']])['system']['auth'] === ['defaultSignInMethod' => 'password']);

// ---- Schema
$defaults = Schema::withDefaults('db', ['connections' => ['main' => ['name' => 'n'], 'revolver' => ['driver' => 'pgsql', 'name' => 'r']]], 'LIVE');
check('a connection gets its defaults', $defaults['connections']['main']['host'] === 'localhost' && $defaults['connections']['main']['driver'] === 'mysql');
check('a value beats its default', $defaults['connections']['revolver']['driver'] === 'pgsql');
check('a computed value gets no default', !array_key_exists('bindOnlyUsedParams', $defaults));
check('no connection is made up', Schema::withDefaults('db', [], 'LIVE') === ['connections' => []] || !isset(Schema::withDefaults('db', [], 'LIVE')['connections']['main']));
check('an optional package stays away', Schema::withDefaults('relay', [], 'LIVE') === []);
check('an optional package that is there is filled', Schema::withDefaults('relay', ['api' => 'a'], 'LIVE')['enabled'] === true);
$system = Schema::withDefaults('system', [], 'LIVE');
check('an optional block stays away', !isset($system['mail']));
check('defaults reach the tree', $system['auth']['deviceDays'] === 180 && $system['ini']['memory_limit'] === '1G');
check('a stage has its own default', Schema::withDefaults('system', [], 'DEV')['debug']['debug'] === true && $system['debug']['debug'] === false);

// ---- the two files
$dir = sys_get_temp_dir() . '/config-tree-' . bin2hex(random_bytes(4));
mkdir($dir);
$write = static fn(string $name, array $data) => file_put_contents("$dir/$name", json_encode($data));
$write('config.json', ['constants' => ['PROJECT_NAMESPACE' => 'Old', 'FREE' => 1]]);
rejects('without config.local.json there is no installation', static fn() => Config::boot($dir, [], $dir));
$write('DEV.old.config.json', ['packages' => ['system' => ['instance' => 'DEV.old']]]);
rejects('an old instance file names nothing', static fn() => Config::boot($dir, [], $dir));
$write('config.local.json', ['db' => ['pass' => 'from-local'], 'packages' => ['system' => ['instance' => 'STAGING', 'versionDate' => '2026-01-01'], 'db' => ['connections' => [
	'main' => ['host' => 'h', 'name' => 'n'],
	'revolver' => ['driver' => 'pgsql', 'name' => 'r', 'pass' => 'r-local'],
]]]]);
$config = Config::boot($dir, ['SERVER_NAME' => 'example.com', 'SERVER_ADDR' => '1.2.3.4'], $dir);
check('config.local.json names the installation', $config->instance->name === 'STAGING' && $config->instance->stage === 'STAGING');
check('the old layout and the new one in one file', $config->connection()['pass'] === 'from-local' && $config->connection()['host'] === 'h');
check('a second connection', $config->connection('revolver')['driver'] === 'pgsql' && $config->connection('revolver')['pass'] === 'r-local');
check('an unknown connection is empty', $config->connection('nope') === []);
check('config.json and config.local.json side by side', $config->package('system')['constants']['PROJECT_NAMESPACE'] === 'Old' && $config->package('system')['versionDate'] === '2026-01-01');
check('the stage decides the defaults', $config->package('system')['debug']['debug'] === false);
check('a pinned name runs as its stage', Config::boot($dir, [], $dir, 'DEV.cli')->package('system')['debug']['debug'] === true);
check('the files read', array_map('basename', $config->files) === ['config.json', 'config.local.json']);
array_map('unlink', glob("$dir/*") ?: []);
rmdir($dir);

// ---- constants and ini: config.local.json wins
$dir = sys_get_temp_dir() . '/config-tree-' . bin2hex(random_bytes(4));
mkdir($dir);
$write = static fn(string $name, array $data) => file_put_contents("$dir/$name", json_encode($data));
$write('config.json', ['constants' => ['TREE_A' => 'project', 'TREE_B' => 'project'], 'ini' => ['precision' => 11]]);
$write('config.local.json', ['constants' => ['TREE_B' => 'local'], 'ini' => ['precision' => 12, 'error_log' => 'nope.log', 'display_errors' => '1', 'error_reporting' => '0'], 'packages' => ['system' => ['instance' => 'LIVE']]]);
$config = Config::boot($dir, ['SERVER_NAME' => 'example.com', 'SERVER_ADDR' => '1.2.3.4'], $dir);
$log = ini_get('error_log');
Config::applyIniDefaults();
check('the defaults come from the definitions', ini_get('memory_limit') === '1G' && ini_get('serialize_precision') === '14');
$config->defineConstants();
check('config.json alone', TREE_A === 'project');
check('config.local.json beats config.json', TREE_B === 'local');
check('ini: config.local.json beats config.json', ini_get('precision') === '12');
check('a config cannot reach error_log', ini_get('error_log') === $log);
check('nor error_reporting', error_reporting() === -1);
check('nor display_errors, that is the debug switch', ini_get('display_errors') === '0');
check('defaults stay where no file says otherwise', ini_get('memory_limit') === '1G');
ini_set('display_errors', '1'); // the checks below have to be able to fail out loud
array_map('unlink', glob("$dir/*") ?: []);
rmdir($dir);

// ---- every project here boots
$booted = 0;
foreach (glob(dirname(__DIR__, 3) . '/projects/*/config/config.local.json') ?: [] as $local) {
	$dir = dirname($local);
	$data = json_decode((string) file_get_contents($local), true);
	if (($data['packages']['system']['instance'] ?? '') === '') {
		continue;
	}
	$config = Config::boot($dir, [], dirname($dir));
	check(basename(dirname($dir)) . ' boots as ' . $config->instance->name, ($config->connection()['name'] ?? '') !== '');
	$booted++;
}
echo $booted === 0 ? "skipped: no adopted project next to the packages\n" : '';
echo "all fine\n";
