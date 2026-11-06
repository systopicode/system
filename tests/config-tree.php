<?php

declare(strict_types=1);

/**
 * The package tree of Config: Layout lifts every file, Schema fills the
 * defaults. See .cursor/docs/config-struktur.md.
 *
 * Read only. Then the proof of step 1: for every instance of every project in
 * projects/*, the tree says what the old accessors say. Values are compared,
 * never printed.
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

// ---- a project in the middle of the move
$dir = sys_get_temp_dir() . '/config-tree-' . bin2hex(random_bytes(4));
mkdir($dir);
$write = static fn(string $name, array $data) => file_put_contents("$dir/$name", json_encode($data));
$write('config.json', ['constants' => ['PROJECT_NAMESPACE' => 'Old', 'FREE' => 1]]);
$write('LIVE.config.json', ['default' => true, 'packages' => ['db' => ['connections' => [
	'main' => ['host' => 'h', 'name' => 'n', 'pass' => 'from-the-instance'],
	'revolver' => ['driver' => 'pgsql', 'name' => 'r'],
]], 'system' => ['linkSites' => true]]]);
$write('config.local.json', ['db' => ['pass' => 'from-local'], 'packages' => ['db' => ['connections' => ['revolver' => ['pass' => 'r-local']]]]]);
$config = Config::boot($dir, ['SERVER_NAME' => 'example.com', 'SERVER_ADDR' => '1.2.3.4'], $dir);
check('an old local beats a new instance', $config->connection()['pass'] === 'from-local');
check('the instance keeps the rest', $config->connection()['host'] === 'h');
check('a second connection', $config->connection('revolver')['driver'] === 'pgsql' && $config->connection('revolver')['pass'] === 'r-local');
check('an unknown connection is empty', $config->connection('nope') === []);
check('old and new files side by side', $config->package('system')['constants']['PROJECT_NAMESPACE'] === 'Old' && $config->package('system')['linkSites'] === true);
array_map('unlink', glob("$dir/*") ?: []);
rmdir($dir);

// ---- constants: the later file wins
$dir = sys_get_temp_dir() . '/config-tree-' . bin2hex(random_bytes(4));
mkdir($dir);
$write = static fn(string $name, array $data) => file_put_contents("$dir/$name", json_encode($data));
$write('config.json', ['constants' => ['TREE_A' => 'project', 'TREE_B' => 'project', 'TREE_C' => 'project'], 'ini' => ['precision' => 11]]);
$write('LIVE.config.json', ['default' => true, 'constants' => ['TREE_B' => 'instance', 'TREE_C' => 'instance'], 'ini' => ['precision' => 12, 'error_log' => 'nope.log', 'display_errors' => '1', 'error_reporting' => '0']]);
$write('config.local.json', ['constants' => ['TREE_C' => 'local']]);
$config = Config::boot($dir, ['SERVER_NAME' => 'example.com', 'SERVER_ADDR' => '1.2.3.4'], $dir);
$log = ini_get('error_log');
$reporting = error_reporting();
Config::applyIniDefaults();
check('the defaults come from the definitions', ini_get('memory_limit') === '1G' && ini_get('serialize_precision') === '14');
$config->defineConstants();
check('config.json alone', TREE_A === 'project');
check('the instance beats config.json', TREE_B === 'instance');
check('config.local.json beats the instance', TREE_C === 'local');
check('ini: the instance beats config.json', ini_get('precision') === '12');
check('a config cannot reach error_log', ini_get('error_log') === $log);
check('nor error_reporting', error_reporting() === -1);
check('nor display_errors, that is the debug switch', ini_get('display_errors') === '0');
check('defaults stay where no file says otherwise', ini_get('memory_limit') === '1G');
ini_set('display_errors', '1'); // the checks below have to be able to fail out loud
array_map('unlink', glob("$dir/*") ?: []);
rmdir($dir);

// ---- step 1: every instance says the same as before
$checked = 0;
$different = [];
foreach (glob(dirname(__DIR__, 3) . '/projects/*/config/config.json') ?: [] as $base) {
	$dir = dirname($base);
	$project = basename(dirname($dir));
	foreach (glob("$dir/*.config.json") ?: [] as $file) {
		$name = basename($file, '.config.json');
		if ($name === 'config') {
			continue;
		}
		// only a file in the old layout can be compared with the old accessors
		if (isset(json_decode((string) file_get_contents($file), true)['packages'])) {
			continue;
		}
		$config = Config::boot($dir, [], dirname($dir), $name);
		$old = $config->instance;
		$system = $config->package('system');
		$db = $old->db();
		$bind = $db['bindOnlyUsedParams'] ?? null;
		unset($db['bindOnlyUsedParams']);
		unset($db['pass']); // config.local.json of this machine lies over whichever instance is pinned here
		$main = $config->connection();
		$pairs = [
			'db' => [$db, array_intersect_key($main, $db)],
			'db host' => [$db['host'] ?? 'localhost', $main['host'] ?? 'localhost'],
			'bind' => [$bind, $config->package('db')['bindOnlyUsedParams'] ?? null],
			'debug' => [$old->debug()['debug'] ?? false, $system['debug']['debug'] ?? null],
			'render' => [$old->debug()['render'] ?? false, $system['debug']['render'] ?? null],
			'time' => [$old->debug()['time'] ?? false, $system['debug']['time'] ?? null],
			'displayErrors' => [$old->debug()['displayErrors'] ?? ($old->stage === 'DEV'), $system['debug']['displayErrors'] ?? null],
			'versionAppendix' => [$old->get('versionAppendix'), $system['versionAppendix'] ?? null],
			'versionDate' => [$old->get('versionDate'), $system['versionDate'] ?? null],
			'linkSites' => [!empty($old->get('linkSites')), !empty($system['linkSites'])],
			'assets' => [$old->get('assets'), $system['assets'] ?? null],
			'sysPath' => [$old->get('sysPath', $config->data['sysPath'] ?? 'vendor/systopic/system/'), $system['sysPath'] ?? null],
			'mail' => [(array) $old->get('mail', []), array_intersect_key($system['mail'] ?? [], (array) $old->get('mail', []))],
			'auth' => [(array) $old->get('auth', []), array_intersect_key($system['auth'] ?? [], (array) $old->get('auth', []))],
			'relay' => [(array) $old->get('relay', []), array_intersect_key($config->package('relay'), (array) $old->get('relay', []))],
		];
		foreach (['DEFAULT_TABLE_PREFIX', 'PROJECT_NAMESPACE', 'ACCOUNT_WHITELIST'] as $constant) {
			$pairs[$constant] = [$config->data['constants'][$constant] ?? null, $system['constants'][$constant] ?? null];
		}
		foreach ($pairs as $key => [$before, $after]) {
			if ($before != $after) {
				$different[] = "$project/$name: $key";
			}
		}
		$checked++;
	}
}
check("$checked instances in the old layout say the same as before", $different === []);
echo "all fine\n";
