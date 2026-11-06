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
		$config = Config::boot($dir, [], dirname($dir), $name);
		$old = $config->instance;
		$system = $config->package('system');
		$db = $old->db();
		$bind = $db['bindOnlyUsedParams'] ?? null;
		unset($db['bindOnlyUsedParams']);
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
if ($checked === 0) {
	echo "skipped: no projects next to the packages\n";
	exit(0);
}
check("$checked instances say the same as before", $different === []);
echo "all fine\n";
