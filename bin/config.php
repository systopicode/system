<?php

declare(strict_types=1);

/**
 * The config files of the project in the current folder: config.json, the
 * project, and config.local.json, this installation.
 *
 *   php vendor/systopic/system/bin/config.php --check
 *       what is unknown, repeated or in the wrong file. Writes nothing.
 *   php vendor/systopic/system/bin/config.php --adopt[=<instance>]
 *       takes an old instance file (DEV.ojhome.config.json …) over into
 *       config.local.json: its values, its name, and the site addresses its
 *       rules spell out. Without a name the one whose rules fit this machine.
 *       What config.local.json already says wins. The old file stays, the
 *       other machines adopt theirs; delete them all afterwards.
 *   php vendor/systopic/system/bin/config.php --prune-defaults
 *       takes out of config.local.json what only repeats the package
 *       defaults or config.json.
 *
 * Every file that changes is copied to var/config-backup/<time>/ first. See
 * .cursor/docs/config-struktur.md.
 */

use Systopic\System\Config\Config;
use Systopic\System\Config\Explain;
use Systopic\System\Config\Layout;
use Systopic\System\Config\MachineRules;
use Systopic\System\Config\Reconcile;
use Systopic\System\Config\Tree;
use Systopic\System\Setup\Setup;

$root = str_replace('\\', '/', (string) getcwd());
if (!is_file("$root/vendor/autoload.php") || !is_dir("$root/config")) {
	fwrite(STDERR, "run this in a project: no vendor/autoload.php or config/ here\n");
	exit(2);
}
require "$root/vendor/autoload.php";

$say = static fn(string $line) => print($line . "\n");
$read = static fn(string $file): ?array => is_file("$root/config/$file") ? Setup::readJson("$root/config/$file") : [];

$stamp = date('Ymd-His');
$backup = static function (string $file) use ($root, $stamp): void {
	if (!is_file("$root/config/$file")) {
		return;
	}
	$dir = "$root/var/config-backup/$stamp";
	@mkdir($dir, 0775, true);
	copy("$root/config/$file", "$dir/$file");
	@chmod("$dir/$file", 0600);
};

/** the layers as Explain sees them, each the `packages` of its file */
$layers = static function () use ($read): array {
	return [
		'project' => (array) (Layout::lift((array) $read('config.json'))['packages'] ?? []),
		'local' => (array) (Layout::lift((array) $read('config.local.json'))['packages'] ?? []),
	];
};

/** the stage config.local.json names, '' without one */
$stage = static function () use ($layers): string {
	$name = (string) ($layers()['local']['system']['instance'] ?? '');
	return $name === '' ? '' : strtoupper(explode('.', $name)[0]);
};

/** @return array<string, string> the old instance files: name => file */
$oldFiles = static function () use ($root): array {
	$found = [];
	foreach (glob("$root/config/*.config.json") ?: [] as $path) {
		$found[basename($path, '.config.json')] = basename($path);
	}
	ksort($found, SORT_STRING);
	return $found;
};

$option = $argv[1] ?? '';

if ($option === '--check') {
	$problems = 0;
	foreach (['config.json', 'config.local.json'] as $file) {
		$data = $read($file);
		if ($data === null) {
			$say("$file: no plain JSON, not checked");
			$problems++;
			continue;
		}
		if (array_intersect(array_keys($data), Layout::MOVED) !== []) {
			$say("$file: old layout");
		}
		foreach (Reconcile::findings($file, $data) as $finding) {
			$name = trim($finding['package'] . '.' . $finding['path'], '.');
			match ($finding['status']) {
				Reconcile::FOREIGN => $say("$file: $name is not installed here, it stays"),
				default => [$say("$file: $name: " . $finding['status']), $problems++],
			};
		}
	}
	if (($layers()['local']['system']['instance'] ?? '') === '') {
		$say('config.local.json: names no installation (packages.system.instance), --adopt takes an old instance file over');
		$problems++;
	}
	$redundant = Explain::redundantIn($layers(), 'local', $stage());
	if ($redundant !== []) {
		$say('config.local.json: ' . count($redundant) . ' values repeat what applies anyway, --prune-defaults takes them out');
	}
	if ($oldFiles() !== []) {
		$say('old instance files, no longer read: ' . implode(', ', $oldFiles()));
	}
	$say($problems === 0 ? 'fine' : "$problems to look at");
	exit($problems === 0 ? 0 : 1);
}

if ($option === '--adopt' || str_starts_with($option, '--adopt=')) {
	$entries = [];
	foreach ($oldFiles() as $name => $file) {
		$data = $read($file);
		if (is_array($data)) {
			$entries[$name] = $data;
		}
	}
	$name = substr($option, strlen('--adopt='));
	if ($name === '' || $option === '--adopt') {
		$ip = \Systopic\System\Http::getSocketIp();
		$hit = MachineRules::select(array_filter($entries, static fn(array $e): bool => !empty($e['matches'])), ['SERVER_NAME' => $ip, 'HTTP_HOST' => $ip, 'SERVER_ADDR' => $ip], $root);
		$name = $hit[0] ?? '';
		if ($name === '') {
			fwrite(STDERR, "no instance file fits this machine ($ip): name it, --adopt=<instance> (" . implode(', ', array_keys($entries)) . ")\n");
			exit(1);
		}
	}
	if (!isset($entries[$name])) {
		fwrite(STDERR, "$name.config.json cannot be read\n");
		exit(1);
	}
	$local = $read('config.local.json');
	if ($local === null) {
		fwrite(STDERR, "config.local.json is no plain JSON: adopt by hand\n");
		exit(1);
	}
	$old = Layout::lift($entries[$name]);
	$local = Layout::lift($local);
	// what config.local.json says already wins, as it did over the instance file
	$packages = Config::merge((array) ($old['packages'] ?? []), (array) ($local['packages'] ?? []));
	$packages['system']['instance'] = $name;
	// the rules, as far as they spell out where the sites answer
	$addresses = [];
	foreach ((array) ($old['matches'] ?? []) as $rule) {
		$site = is_array($rule) ? (string) ($rule['site'] ?? '') : '';
		if ($site === '') {
			continue;
		}
		$label = substr($site, 0, (int) (strrpos($site, '.') ?: strlen($site)));
		$host = '';
		foreach ((array) ($rule['serverName'] ?? []) as $each) {
			if (!filter_var((string) $each, FILTER_VALIDATE_IP) && !str_contains((string) $each, '*') && $each !== 'dev.local') {
				$host = strtolower((string) $each);
				break;
			}
		}
		$path = strtolower(trim((string) (((array) ($rule['path'] ?? []))[0] ?? '')));
		if ($path !== '' && str_contains($path, "/$site/")) {
			// the folder of the site: in /projects/ or a folder next to it
			$pattern = str_starts_with($path, "/projects/$site/") ? '/projects(\.[a-z]+)?/{site}/' : str_replace("/$site/", '/{site}/', $path);
			$addresses[] = $host === '' ? $pattern : $host . $pattern;
		} elseif ($host !== '' && $path === '') {
			$addresses[] = str_starts_with($host, "$label.") ? '{name}' . substr($host, strlen($label)) : $host;
		}
	}
	$addresses = array_values(array_unique($addresses));
	// a literal host (rc.systopic.net) is the default site's: first
	usort($addresses, static fn(string $a, string $b): int => (int) str_contains($a, '{') <=> (int) str_contains($b, '{'));
	if ($addresses !== [] && !isset($packages['system']['siteAddress'])) {
		$packages['system']['siteAddress'] = count($addresses) === 1 ? $addresses[0] : $addresses;
	}
	foreach ($old as $key => $value) {
		if ($key === 'constants' && is_array($value)) {
			$local['constants'] = Config::merge($value, (array) ($local['constants'] ?? []));
		}
	}
	$local['packages'] = $packages;
	$backup('config.local.json');
	if (!Setup::writeJson("$root/config/config.local.json", $local)) {
		fwrite(STDERR, "config.local.json: cannot write\n");
		exit(1);
	}
	$say("config.local.json: is $name now" . ($addresses === [] ? '' : ', sites at ' . implode(', ', $addresses)));
	$say("$name.config.json is no longer read. Delete the old instance files once every machine has adopted its own.");
	exit(0);
}

if ($option === '--prune-defaults') {
	$data = $read('config.local.json');
	if (!is_array($data) || $data === []) {
		fwrite(STDERR, "config.local.json cannot be read\n");
		exit(1);
	}
	$found = Explain::redundantIn($layers(), 'local', $stage());
	if ($found === []) {
		$say('config.local.json: nothing repeats');
		exit(0);
	}
	$data = Layout::lift($data);
	foreach ($found as $value) {
		Tree::drop($data, ['packages', $value['package'], ...explode('.', $value['path'])]);
	}
	$backup('config.local.json');
	if (!Setup::writeJson("$root/config/config.local.json", $data)) {
		fwrite(STDERR, "config.local.json: cannot write\n");
		exit(1);
	}
	$say('config.local.json: took out ' . implode(', ', array_map(static fn(array $v): string => $v['package'] . '.' . $v['path'], $found)));
	exit(0);
}

fwrite(STDERR, "usage: config.php --check | --adopt[=<instance>] | --prune-defaults\n");
exit(2);
