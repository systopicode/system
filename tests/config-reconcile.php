<?php

declare(strict_types=1);

/** Config\Reconcile: one file against the definitions. See .cursor/docs/config-struktur.md, "Abgleich". */

require __DIR__ . '/bootstrap.php';

use Systopic\System\Config\Reconcile;

$of = static function (string $file, array $data, string $status): array {
	$found = array_filter(Reconcile::findings($file, $data), static fn(array $f): bool => $f['status'] === $status);
	return array_values(array_map(static fn(array $f): string => trim($f['package'] . '.' . $f['path'], '.'), $found));
};

$file = ['matches' => [['path' => '/x/']], 'packages' => [
	'db' => ['connections' => ['main' => ['name' => 'n', 'pass' => 'secret', 'gone' => 1]]],
	'system' => ['ini' => ['anything' => '1'], 'oldKey' => 'x', '//' => 'a note', 'linkSites' => true],
	'unknownpackage' => ['a' => 1],
]];

check('a value nobody defines is orphaned', $of('STAGING.config.json', $file, Reconcile::ORPHAN) === ['db.connections.main.gone', 'system.oldKey']);
check('a package that is not here is foreign', $of('STAGING.config.json', $file, Reconcile::FOREIGN) === ['unknownpackage']);
check('a secret outside of local is exposed', $of('STAGING.config.json', $file, Reconcile::EXPOSED) === ['db.connections.main.pass']);
check('in config.local.json it is where it belongs', $of('config.local.json', $file, Reconcile::EXPOSED) === []);
check('an empty secret exposes nothing', $of('STAGING.config.json', ['packages' => ['db' => ['connections' => ['main' => ['pass' => '']]]]], Reconcile::EXPOSED) === []);
check('open blocks and comments are nobody\'s orphans', !array_filter($of('STAGING.config.json', $file, Reconcile::ORPHAN), static fn(string $p): bool => str_contains($p, 'ini') || str_contains($p, '//')));
check('the old layout is read as well', $of('STAGING.config.json', ['db' => ['name' => 'n', 'pass' => 'p']], Reconcile::EXPOSED) === ['db.connections.main.pass']);

$missing = $of('STAGING.config.json', $file, Reconcile::MISSING);
check('a default the file lacks is missing', in_array('db.connections.main.host', $missing, true) && in_array('system.debug.debug', $missing, true));
check('no computed value', !in_array('system.assets', $missing, true) && !in_array('db.bindOnlyUsedParams', $missing, true));
check('no optional block', !array_filter($missing, static fn(string $p): bool => str_starts_with($p, 'system.mail') || str_starts_with($p, 'relay')));
check('a block that is there gets its defaults', in_array('system.mail.port', $of('STAGING.config.json', ['packages' => ['system' => ['mail' => ['host' => 'h']]]], Reconcile::MISSING), true));
check('config.local.json misses nothing', $of('config.local.json', $file, Reconcile::MISSING) === []);

check('what may not be overridden is misplaced in local', $of('config.local.json', ['packages' => ['system' => ['mail' => ['host' => 'h'], 'debug' => ['debug' => true]]]], Reconcile::MISPLACED) === ['system.mail.host']);
check('what belongs to config.json is misplaced in an instance', $of('STAGING.config.json', ['packages' => ['system' => ['constants' => ['PROJECT_NAMESPACE' => 'X']]]], Reconcile::MISPLACED) === ['system.constants.PROJECT_NAMESPACE']);
check('what may be overridden is not', $of('STAGING.config.json', ['packages' => ['system' => ['auth' => ['deviceDays' => 90]]]], Reconcile::MISPLACED) === []);

// saving
$saved = Reconcile::apply('STAGING.config.json', $file, true);
check('saving the own file takes the orphans out', !isset($saved['packages']['db']['connections']['main']['gone']) && !isset($saved['packages']['system']['oldKey']));
check('and fills in the defaults', $saved['packages']['db']['connections']['main']['host'] === 'localhost' && $saved['packages']['system']['debug']['debug'] === false);
check('a value stays', $saved['packages']['system']['linkSites'] === true && $saved['packages']['db']['connections']['main']['name'] === 'n');
check('the secret stays where it is, moving it is a click', $saved['packages']['db']['connections']['main']['pass'] === 'secret');
check('a foreign package stays', $saved['packages']['unknownpackage'] === ['a' => 1]);
check('open blocks and comments stay', $saved['packages']['system']['ini'] === ['anything' => '1'] && $saved['packages']['system']['//'] === 'a note');
check('the rules stay', $saved['matches'] === [['path' => '/x/']]);
check('what is new comes last in its block', array_key_first($saved['packages']['db']['connections']['main']) === 'name');

$other = Reconcile::apply('RC.config.json', $file, false);
check('another instance keeps its orphans', isset($other['packages']['system']['oldKey']));
check('and gets the defaults all the same', isset($other['packages']['system']['debug']));
$local = Reconcile::apply('config.local.json', $file, true);
check('config.local.json keeps its orphans', isset($local['packages']['system']['oldKey']));
check('and gets no defaults', !isset($local['packages']['system']['debug']));
check('the stage of the file decides a default', Reconcile::apply('DEV.x.config.json', [], true)['packages']['system']['debug']['debug'] === true);
check('saving twice changes nothing', Reconcile::apply('STAGING.config.json', $saved, true) === $saved);

// secrets
$data = ['packages' => ['db' => ['connections' => ['main' => ['name' => 'n', 'pass' => 'from-file']]], 'relay' => ['api' => 'a', 'secret' => 'hub']]];
$own = ['packages' => ['relay' => ['secret' => 'newer']]];
$moved = Reconcile::moveSecrets($data, $own);
check('secrets leave the file', $moved === ['db.connections.main.pass', 'relay.secret'] && !isset($data['packages']['db']['connections']['main']['pass']) && !isset($data['packages']['relay']['secret']));
check('they arrive in local', $own['packages']['db']['connections']['main']['pass'] === 'from-file');
check('what local has already wins', $own['packages']['relay']['secret'] === 'newer');
check('the rest stays', $data['packages']['db']['connections']['main']['name'] === 'n' && $data['packages']['relay']['api'] === 'a');
echo "all fine\n";
