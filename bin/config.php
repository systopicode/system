<?php

declare(strict_types=1);

/**
 * The config files of the project in the current folder.
 *
 *   php vendor/systopic/system/bin/config.php --check
 *       what is unknown, old or in the wrong place. Writes nothing.
 *   php vendor/systopic/system/bin/config.php --migrate
 *       the instance files and config.local.json to `packages.*`. config.json
 *       and RC.config.json stay: they reach RC, which runs the last tag.
 *       `--migrate=all` takes them too, for the day of the tag.
 *   php vendor/systopic/system/bin/config.php --move-secrets=<instance>
 *       the secrets of this machine's instance file to config.local.json. Runs
 *       on each machine for itself: first local, then out of the file, or a
 *       sync would take the password away.
 *   php vendor/systopic/system/bin/config.php --prune-defaults=<instance>
 *       takes out of an instance file what only repeats the package defaults
 *       or config.json. Runs on the machine of the instance: another one may
 *       run other code, with other defaults.
 *
 * Every file that changes is copied to var/config-backup/<time>/ first. See
 * .cursor/docs/config-struktur.md.
 */

use Systopic\System\Config\Explain;
use Systopic\System\Config\Layout;
use Systopic\System\Config\Tree;
use Systopic\System\Config\Reconcile;
use Systopic\System\Setup\Setup;

$root = str_replace('\\', '/', (string) getcwd());
if (!is_file("$root/vendor/autoload.php") || !is_dir("$root/config")) {
	fwrite(STDERR, "run this in a project: no vendor/autoload.php or config/ here\n");
	exit(2);
}
require "$root/vendor/autoload.php";

$say = static fn(string $line) => print($line . "\n");

/** @return list<string> file names: the instances, then config.local.json */
$instanceFiles = static function () use ($root): array {
	$files = array_map('basename', glob("$root/config/*.config.json") ?: []);
	sort($files, SORT_STRING);
	return is_file("$root/config/config.local.json") ? [...$files, 'config.local.json'] : $files;
};

/** config.json and an instance file, each its `packages` */
$layers = static function (string $file) use ($root): array {
	$project = is_file("$root/config/config.json") ? Setup::readJson("$root/config/config.json") : [];
	$instance = Setup::readJson("$root/config/$file");
	return [
		'project' => (array) (Layout::lift((array) $project)['packages'] ?? []),
		'instance' => (array) (Layout::lift((array) $instance)['packages'] ?? []),
		'local' => null,
	];
};

$stamp = date('Ymd-His');
$backup = static function (string $file) use ($root, $stamp): void {
	$dir = "$root/var/config-backup/$stamp";
	@mkdir($dir, 0775, true);
	copy("$root/config/$file", "$dir/$file");
	@chmod("$dir/$file", 0600);
};

$option = $argv[1] ?? '';

if ($option === '--check') {
	$problems = 0;
	foreach (['config.json', ...$instanceFiles()] as $file) {
		$data = Setup::readJson("$root/config/$file");
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
				default => [$say("$file: $name: " . $finding["status"]), $problems++],
			};
		}
		if ($file !== 'config.json' && $file !== 'config.local.json') {
			$redundant = Explain::redundantIn($layers($file), 'instance', strtoupper(explode('.', $file)[0]));
			if ($redundant !== []) {
				$say("$file: " . count($redundant) . " values repeat what applies anyway, --prune-defaults takes them out");
			}
		}
	}
	$say($problems === 0 ? 'fine' : "$problems to look at");
	exit($problems === 0 ? 0 : 1);
}

if ($option === '--migrate' || $option === '--migrate=all') {
	$files = $instanceFiles();
	if ($option === '--migrate=all') {
		$files[] = 'config.json';
	} else {
		$files = array_values(array_diff($files, ['RC.config.json']));
	}
	foreach ($files as $file) {
		$data = Setup::readJson("$root/config/$file");
		if ($data === null) {
			$say("$file: no plain JSON, left alone");
			continue;
		}
		$lifted = Layout::lift($data);
		if ($lifted === $data) {
			$say("$file: nothing to do");
			continue;
		}
		$backup($file);
		$say(Setup::writeJson("$root/config/$file", $lifted) ? "$file: moved" : "$file: cannot write");
	}
	exit(0);
}

if (str_starts_with($option, '--move-secrets=')) {
	$file = substr($option, strlen('--move-secrets=')) . '.config.json';
	$data = is_file("$root/config/$file") ? Setup::readJson("$root/config/$file") : null;
	$local = is_file("$root/config/config.local.json") ? Setup::readJson("$root/config/config.local.json") : [];
	if ($data === null || $local === null) {
		fwrite(STDERR, "$file or config.local.json cannot be read\n");
		exit(1);
	}
	$data = Layout::lift($data);
	$local = Layout::lift($local);
	$moved = Reconcile::moveSecrets($data, $local);
	if ($moved === []) {
		$say("$file: no secrets in it");
		exit(0);
	}
	if (is_file("$root/config/config.local.json")) {
		$backup('config.local.json');
	}
	$backup($file);
	// local first: a failure here leaves the secret where it was
	if (!Setup::writeJson("$root/config/config.local.json", $local) || !Setup::writeJson("$root/config/$file", $data)) {
		fwrite(STDERR, "cannot write, see var/config-backup/$stamp/\n");
		exit(1);
	}
	$say("$file: moved to config.local.json: " . implode(', ', $moved));
	exit(0);
}

if (str_starts_with($option, '--prune-defaults=')) {
	$file = substr($option, strlen('--prune-defaults=')) . '.config.json';
	$data = is_file("$root/config/$file") ? Setup::readJson("$root/config/$file") : null;
	if ($data === null) {
		fwrite(STDERR, "$file cannot be read\n");
		exit(1);
	}
	$found = Explain::redundantIn($layers($file), 'instance', strtoupper(explode('.', $file)[0]));
	if ($found === []) {
		$say("$file: nothing repeats");
		exit(0);
	}
	$data = Layout::lift($data);
	foreach ($found as $value) {
		Tree::drop($data, ['packages', $value['package'], ...explode('.', $value['path'])]);
	}
	$backup($file);
	if (!Setup::writeJson("$root/config/$file", $data)) {
		fwrite(STDERR, "$file: cannot write\n");
		exit(1);
	}
	$say("$file: took out " . implode(', ', array_map(static fn(array $v): string => $v['package'] . '.' . $v['path'], $found)));
	exit(0);
}

fwrite(STDERR, "usage: config.php --check | --migrate[=all] | --move-secrets=<instance> | --prune-defaults=<instance>\n");
exit(2);
