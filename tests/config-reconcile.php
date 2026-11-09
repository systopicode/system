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

check('a default the file lacks is no finding: it lies at the bottom of the tree', !in_array('missing', array_column(Reconcile::findings('STAGING.config.json', $file), 'status'), true));

check('what may not be overridden is misplaced in local', $of('config.local.json', ['packages' => ['system' => ['mail' => ['host' => 'h'], 'debug' => ['debug' => true]]]], Reconcile::MISPLACED) === ['system.mail.host']);
check('what belongs to config.json is misplaced in an instance', $of('STAGING.config.json', ['packages' => ['system' => ['constants' => ['PROJECT_NAMESPACE' => 'X']]]], Reconcile::MISPLACED) === ['system.constants.PROJECT_NAMESPACE']);
check('what may be overridden is not', $of('STAGING.config.json', ['packages' => ['system' => ['auth' => ['deviceDays' => 90]]]], Reconcile::MISPLACED) === []);

// orphans go by a click
$clean = Reconcile::withoutOrphans('STAGING.config.json', $file);
check('every orphan goes', !isset($clean['packages']['db']['connections']['main']['gone']) && !isset($clean['packages']['system']['oldKey']));
check('a value stays', $clean['packages']['system']['linkSites'] === true && $clean['packages']['db']['connections']['main']['name'] === 'n');
check('no default comes in', !isset($clean['packages']['system']['debug']) && !isset($clean['packages']['db']['connections']['main']['host']));
check('the secret stays where it is, moving it is a click', $clean['packages']['db']['connections']['main']['pass'] === 'secret');
check('a foreign package stays', $clean['packages']['unknownpackage'] === ['a' => 1]);
check('open blocks and comments stay', $clean['packages']['system']['ini'] === ['anything' => '1'] && $clean['packages']['system']['//'] === 'a note');
check('the rules stay', $clean['matches'] === [['path' => '/x/']]);
$one = Reconcile::withoutOrphans('STAGING.config.json', $file, ['system.oldKey']);
check('one orphan goes alone', !isset($one['packages']['system']['oldKey']) && $one['packages']['db']['connections']['main']['gone'] === 1);

// secrets
$data = ['packages' => ['db' => ['connections' => ['main' => ['name' => 'n', 'pass' => 'from-file']]], 'relay' => ['api' => 'a', 'secret' => 'hub']]];
$own = ['packages' => ['relay' => ['secret' => 'newer']]];
$moved = Reconcile::moveSecrets($data, $own);
check('secrets leave the file', $moved === ['db.connections.main.pass', 'relay.secret'] && !isset($data['packages']['db']['connections']['main']['pass']) && !isset($data['packages']['relay']['secret']));
check('they arrive in local', $own['packages']['db']['connections']['main']['pass'] === 'from-file');
check('what local has already wins', $own['packages']['relay']['secret'] === 'newer');
check('the rest stays', $data['packages']['db']['connections']['main']['name'] === 'n' && $data['packages']['relay']['api'] === 'a');
echo "all fine\n";
