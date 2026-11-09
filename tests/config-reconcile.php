<?php

declare(strict_types=1);

/** Config\Reconcile: one file against the definitions. See .cursor/docs/config-struktur.md, "Abgleich". */

require __DIR__ . '/bootstrap.php';

use Systopic\System\Config\Reconcile;

$of = static function (string $file, array $data, string $status): array {
	$found = array_filter(Reconcile::findings($file, $data), static fn(array $f): bool => $f['status'] === $status);
	return array_values(array_map(static fn(array $f): string => trim($f['package'] . '.' . $f['path'], '.'), $found));
};

$file = ['packages' => [
	'db' => ['connections' => ['main' => ['name' => 'n', 'pass' => 'secret', 'gone' => 1]]],
	'system' => ['ini' => ['anything' => '1'], 'oldKey' => 'x', '//' => 'a note', 'linkSites' => true],
	'unknownpackage' => ['a' => 1],
]];

check('a value nobody defines is orphaned', $of('config.local.json', $file, Reconcile::ORPHAN) === ['db.connections.main.gone', 'system.oldKey']);
check('a package that is not here is foreign', $of('config.local.json', $file, Reconcile::FOREIGN) === ['unknownpackage']);
check('a secret in config.json is exposed', $of('config.json', $file, Reconcile::EXPOSED) === ['db.connections.main.pass']);
check('in config.local.json it is where it belongs', $of('config.local.json', $file, Reconcile::EXPOSED) === []);
check('an empty secret exposes nothing', $of('config.json', ['packages' => ['db' => ['connections' => ['main' => ['pass' => '']]]]], Reconcile::EXPOSED) === []);
check('open blocks and comments are nobody\'s orphans', !array_filter($of('config.local.json', $file, Reconcile::ORPHAN), static fn(string $p): bool => str_contains($p, 'ini') || str_contains($p, '//')));
check('the old layout is read as well', $of('config.json', ['db' => ['name' => 'n', 'pass' => 'p']], Reconcile::EXPOSED) === ['db.connections.main.pass']);

check('a default the file lacks is no finding: it lies at the bottom of the tree', !in_array('missing', array_column(Reconcile::findings('config.local.json', $file), 'status'), true));

check('a value of the project that may not be overridden is misplaced in local', $of('config.local.json', ['packages' => ['system' => ['constants' => ['PROJECT_NAMESPACE' => 'X']]]], Reconcile::MISPLACED) === ['system.constants.PROJECT_NAMESPACE']);
check('a value of the installation is misplaced in config.json', $of('config.json', ['packages' => ['system' => ['mail' => ['host' => 'h']]]], Reconcile::MISPLACED) === ['system.mail.host']);
check('an override is not', $of('config.local.json', ['packages' => ['system' => ['auth' => ['deviceDays' => 90], 'ini' => ['memory_limit' => '2G'], 'debug' => ['debug' => true]]]], Reconcile::MISPLACED) === []);

// orphans go by a click
$clean = Reconcile::withoutOrphans('config.local.json', $file);
check('every orphan goes', !isset($clean['packages']['db']['connections']['main']['gone']) && !isset($clean['packages']['system']['oldKey']));
check('a value stays', $clean['packages']['system']['linkSites'] === true && $clean['packages']['db']['connections']['main']['name'] === 'n');
check('no default comes in', !isset($clean['packages']['system']['debug']) && !isset($clean['packages']['db']['connections']['main']['host']));
check('the secret stays', $clean['packages']['db']['connections']['main']['pass'] === 'secret');
check('a foreign package stays', $clean['packages']['unknownpackage'] === ['a' => 1]);
check('open blocks and comments stay', $clean['packages']['system']['ini'] === ['anything' => '1'] && $clean['packages']['system']['//'] === 'a note');
$one = Reconcile::withoutOrphans('config.local.json', $file, ['system.oldKey']);
check('one orphan goes alone', !isset($one['packages']['system']['oldKey']) && $one['packages']['db']['connections']['main']['gone'] === 1);
echo "all fine\n";
