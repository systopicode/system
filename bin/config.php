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
 *
 * Every file that changes is copied to var/config-backup/<time>/ first. See
 * .cursor/docs/config-struktur.md.
 */

use Systopic\System\Config\Layout;
use Systopic\System\Config\Schema;
use Systopic\System\Config\Tree;
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

$stamp = date('Ymd-His');
$backup = static function (string $file) use ($root, $stamp): void {
	$dir = "$root/var/config-backup/$stamp";
	@mkdir($dir, 0775, true);
	copy("$root/config/$file", "$dir/$file");
	@chmod("$dir/$file", 0600);
};

/**
 * Every value below a package: dotted path => value. Lists are values, open
 * blocks (ini, the free constants) are the business of whoever fills them.
 */
$leaves = static function (array $tree, string $path = '') use (&$leaves): array {
	$out = [];
	foreach ($tree as $key => $value) {
		if ($key === '//') {
			continue;
		}
		$full = $path === '' ? (string) $key : "$path.$key";
		if (is_array($value) && !array_is_list($value) && $value !== []) {
			$out += $leaves($value, $full);
		} else {
			$out[$full] = $value;
		}
	}
	return $out;
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
		foreach (Layout::packages($data) as $package => $tree) {
			foreach ($leaves($tree) as $path => $value) {
				if (str_starts_with($path, 'ini.')) {
					continue;
				}
				$field = Schema::field($package, $path);
				if ($field === null) {
					$say("$file: $package.$path is defined nowhere");
					$problems++;
				} elseif (!empty($field[0]['writeOnly']) && $file !== 'config.local.json' && (string) $value !== '') {
					$say("$file: $package.$path is a secret outside of config.local.json");
					$problems++;
				}
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
	$moved = [];
	foreach ((array) ($data['packages'] ?? []) as $package => $tree) {
		foreach ($leaves((array) $tree) as $path => $value) {
			if (empty(Schema::field($package, $path)[0]['writeOnly'])) {
				continue;
			}
			$keys = ['packages', $package, ...explode('.', $path)];
			if ((string) $value !== '' && Tree::get($local, $keys) === null) {
				Tree::put($local, $keys, $value);
			}
			Tree::drop($data, $keys);
			$moved[] = "$package.$path";
		}
	}
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

fwrite(STDERR, "usage: config.php --check | --migrate[=all] | --move-secrets=<instance>\n");
exit(2);
